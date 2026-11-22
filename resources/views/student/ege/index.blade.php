{{-- resources/views/student/ege/index.blade.php
     Главная станции «Задания»: вкладки «Часть 1» / «Часть 2», корм,
     продолжить/повторить, выбор номеров и блоков. Данные —
     TaskTrainer::overview() и WrittenPractice::overview(). --}}
@extends('layouts.main')

@section('title', 'Задания — Школа Полтавского')

@section('back_url', route('student.trainers.index', ['hall' => 'social']))

@php
  $t = $texts;
  $plural = fn (int $n, array $forms) => \App\Support\Text::plural($n, $forms);
  $foodIcon = auth()->user()->fish_food_icon_url;
  $corm = $overview['corm'];
  $numbers = $overview['numbers'];
  $unfinished = $overview['unfinished'];
  // С учётом «Плана на сегодня» — столько, сколько реально даст сессия.
  $size = app(\App\Service\Bank\TaskTrainer::class)->sessionSize(auth()->user());
  $hasBase = array_sum(array_column($numbers, 'total')) > 0;
  $dueNumbers = array_keys(array_filter($numbers, fn ($n) => $n['due'] > 0 && $n['n'] <= 16));
  $draft = $written['draft'];
  $unseenChecked = $written['reviews']->filter(fn ($r) => $r->status === 'checked' && !$r->seen_at)->count();
  $solvedLabel = fn (array $n) => $n['total'] > 0 && $n['solved'] >= $n['total'] ? 'Решено всё ✓' : 'Решено: '.$n['solved'];
@endphp

@section('content')
<div class="eg-page">
  <h1 class="eg-h1">{{ $t['title'] }}</h1>

  <nav class="eg-tabs" aria-label="Часть экзамена">
    <a href="{{ route('student.ege.index') }}" class="eg-tab {{ $part === 1 ? 'is-on' : '' }}">{{ $t['part1'] }} <small>1–16</small></a>
    <a href="{{ route('student.ege.index', ['part' => 2]) }}" class="eg-tab {{ $part === 2 ? 'is-on' : '' }}">{{ $t['part2'] }} <small>17–25</small>@if($unseenChecked) <small>· новое</small>@endif</a>
  </nav>

  @if(session('ege_empty'))
    <div class="eg-notice">{{ session('ege_empty') }}</div>
  @endif
  @if(!$hasBase)
    <div class="eg-notice">
      Задания ещё не загружены — скоро появятся.
      @if(auth()->user()->isAdmin())
        <span style="display:block;margin-top:4px">Для админа: <code>php artisan bank:import</code>.</span>
      @endif
    </div>
  @endif

  @if($part === 1)
    {{-- Корм: первые N раундов — полная шкала, дальше — сверхурочные. --}}
    <div class="egc">
      <div class="egc-slots" aria-hidden="true">
        @for($i = 0; $i < $corm['full']; $i++)
          @if($i < min($corm['paid'], $corm['full']))
            <span class="egc-slot is-used">✓</span>
          @else
            <span class="egc-slot is-free"><img src="{{ $foodIcon }}" alt=""></span>
          @endif
        @endfor
      </div>
      <div class="egc-text">
        @if($corm['full_left'] > 0)
          <b>Сегодня {{ $corm['full_left'] === 1 ? 'доступен' : 'доступно' }} ещё {{ $plural($corm['full_left'], ['раунд', 'раунда', 'раундов']) }}</b>
        @else
          <b>Сверхурочные: 1 корм за раунд от {{ config('task_trainer.overtime_min') }}%</b>
        @endif
        @if($corm['earned'] > 0)<span class="egc-earned"><b>+{{ $corm['earned'] }}</b><img src="{{ $foodIcon }}" alt="корма"> за сегодня</span>@endif
      </div>
      <span class="egc-wallet"><img src="{{ $foodIcon }}" alt="">Корм <b>{{ $cormBalance }}</b></span>
      <button type="button" class="egc-help" id="egc-help" aria-expanded="false" aria-controls="egc-tiers" aria-label="Сколько корма за раунд">?</button>
      <div class="egc-tiers" id="egc-tiers" hidden>
        @foreach(collect(config('task_trainer.corm'))->sortKeysDesc() as $percent => $food)
          <div><span>{{ $percent == 100 ? 'Все баллы' : 'от '.$percent.'% баллов' }}</span><span>{{ $food }} корма</span></div>
        @endforeach
        <p>Так — первые {{ $plural($corm['full'], ['раунд', 'раунда', 'раундов']) }} за день. Дальше — 1 корм за раунд от {{ config('task_trainer.overtime_min') }}%. Повтор ошибок — без корма.</p>
      </div>
    </div>

    @if($unfinished)
      <div class="eg-action">
        <div class="eg-action-text">
          <b>{{ $t['continue_title'] }}</b>
          <span>Осталось {{ $plural(max(1, $unfinished->total - $unfinished->position), ['задание', 'задания', 'заданий']) }}</span>
        </div>
        <a class="eg-btn" href="{{ route('student.ege.round', $unfinished) }}">{{ $t['continue'] }}</a>
      </div>
    @elseif($dueNumbers)
      <form method="POST" action="{{ route('student.ege.start') }}" class="eg-action">
        @csrf
        @foreach($dueNumbers as $n)<input type="hidden" name="numbers[]" value="{{ $n }}">@endforeach
        <div class="eg-action-text">
          <b>{{ strtr($t['review_due'], [':n' => $plural(array_sum(array_map(fn ($n) => $numbers[$n]['due'], $dueNumbers)), ['задание', 'задания', 'заданий'])]) }}</b>
        </div>
        <button type="submit" class="eg-btn">{{ $t['review'] }}</button>
      </form>
    @endif

    <form method="POST" action="{{ route('student.ege.start') }}" class="eg-card" id="eg-pick">
      @csrf
      <div class="eg-card-title">{{ $t['pick_numbers'] }}</div>
      @error('numbers')<div class="eg-error">{{ $message }}</div>@enderror

      <div class="eg-nums" id="eg-nums">
        <button type="button" class="eg-num eg-num-all" id="eg-all" aria-pressed="false">
          <span class="eg-num-n">1–16</span>
          <span class="eg-num-t">{{ $t['all_numbers'] }}</span>
          <span class="eg-num-s" id="eg-all-sub">Выбрать всё</span>
        </button>
        @foreach(range(1, 16) as $n)
          @php $num = $numbers[$n]; @endphp
          <label class="eg-num {{ $num['total'] === 0 ? 'is-off' : '' }}">
            <input type="checkbox" name="numbers[]" value="{{ $n }}" class="eg-num-input" @disabled($num['total'] === 0)>
            <span class="eg-num-n"><span class="eg-num-star" aria-hidden="true">⭐</span>{{ $n }}</span>
            <span class="eg-num-t">{{ $num['title'] }}</span>
            @php $nb = config('task_trainer.number_blocks.'.$n); @endphp
            <span class="eg-num-b" title="{{ $nb ? ($blocks[$nb] ?? '') : $t['number_all_blocks'] }}">{{ $nb ? ($blockEmoji[$nb] ?? '').' '.config('task_trainer.block_short.'.$nb) : $t['number_all_blocks'] }}</span>
            <span class="eg-num-s">{{ $solvedLabel($num) }}@if($num['due'])<span class="due"> · повтор {{ $num['due'] }}</span>@endif</span>
          </label>
        @endforeach
      </div>

      @include('student.ege.partials.blocks', ['blocksInstead' => true])

      <button type="submit" class="eg-btn eg-btn-big" id="eg-go" style="margin-top:16px" disabled>
        <span class="eg-go-on">{{ strtr($t['start'], [':n' => $size]) }}</span>
        <span class="eg-go-off">{{ $t['start_empty'] }}</span>
      </button>
    </form>
  @else
    {{-- Часть 2 --}}
    @php $sendsPerDay = (int) config('task_trainer.reviews_per_day'); @endphp
    <div class="egc">
      <div class="egc-slots" aria-hidden="true">
        @for($i = 0; $i < $sendsPerDay; $i++)
          @if($i < $sendsPerDay - $written['sends_left'])
            <span class="egc-slot is-used">✓</span>
          @else
            <span class="egc-slot is-free"><img src="{{ $foodIcon }}" alt=""></span>
          @endif
        @endfor
      </div>
      <div class="egc-text">
        @if($written['sends_left'] > 0)
          <b>Куратору сегодня можно отправить ещё {{ $plural($written['sends_left'], ['задание', 'задания', 'заданий']) }}</b>
        @else
          <b>{{ $t['send_limit'] }}</b>
        @endif
        <span>Корм начисляется по баллам куратора</span>
      </div>
      <span class="egc-wallet"><img src="{{ $foodIcon }}" alt="">Корм <b>{{ $cormBalance }}</b></span>
    </div>

    @if($draft)
      <div class="eg-action">
        <div class="eg-action-text">
          <b>Черновик: задание {{ (int) $draft->task->number }}</b>
        </div>
        <a class="eg-btn" href="{{ route('student.ege.written', $draft) }}">{{ $t['continue'] }}</a>
      </div>
    @endif

    @if($written['reviews']->isNotEmpty())
      <div class="eg-card" style="margin-bottom:14px">
        <div class="eg-card-title">{{ $t['on_review'] }}</div>
        <div style="margin-top:8px">
          @foreach($written['reviews'] as $r)
            <a class="eg-rev" href="{{ route('student.ege.written', $r) }}">
              <span class="eg-rev-n">{{ (int) $r->task->number }}</span>
              <span class="eg-rev-text">{{ \Illuminate\Support\Str::limit($r->task->question_text, 90) }}</span>
              @if($r->status === 'checked' && !$r->seen_at)<span class="eg-rev-new" aria-label="новое"></span>@endif
              <span class="eg-rev-status {{ $r->status === 'checked' ? 'is-checked' : '' }}">
                {{ $r->status === 'checked' ? strtr($t['status_checked'], [':score' => $r->score, ':max' => $r->max_score]) : $t['status_sent'] }}
              </span>
            </a>
          @endforeach
        </div>
      </div>
    @endif

    <form method="POST" action="{{ route('student.ege.written.start') }}" class="eg-card" id="eg-pick">
      @csrf
      <div class="eg-card-title">{{ $t['pick_number'] }}</div>
      @error('number')<div class="eg-error">{{ $message }}</div>@enderror

      <div class="eg-nums">
        @foreach(range(17, 25) as $n)
          @php $num = $numbers[$n]; $done = $written['solved'][$n] ?? 0; @endphp
          <label class="eg-num {{ $num['total'] === 0 ? 'is-off' : '' }}">
            <input type="radio" name="number" value="{{ $n }}" class="eg-num-input" @disabled($num['total'] === 0)>
            <span class="eg-num-n"><span class="eg-num-star" aria-hidden="true">⭐</span>{{ $n }}</span>
            <span class="eg-num-t">{{ $num['title'] }}</span>
            @php $nb = config('task_trainer.number_blocks.'.$n); @endphp
            <span class="eg-num-b" title="{{ $nb ? ($blocks[$nb] ?? '') : $t['number_all_blocks'] }}">{{ $nb ? ($blockEmoji[$nb] ?? '').' '.config('task_trainer.block_short.'.$nb) : $t['number_all_blocks'] }}</span>
            <span class="eg-num-s">Решено: {{ $done }}</span>
          </label>
        @endforeach
      </div>

      @include('student.ege.partials.blocks')

      <button type="submit" class="eg-btn eg-btn-big" id="eg-go" style="margin-top:16px" disabled>
        <span class="eg-go-on" id="eg-go-label">{{ $t['start_part2_empty'] }}</span>
        <span class="eg-go-off">{{ $t['start_part2_empty'] }}</span>
      </button>
    </form>
  @endif
</div>
@endsection

@push('page-scripts')
@include('student.ege.partials.styles')
<style>
  .eg-go-off { display: none; }
  .eg-btn:disabled .eg-go-on { display: none; }
  .eg-btn:disabled .eg-go-off { display: inline; }
</style>
<script>
  (function () {
    var inputs = Array.prototype.slice.call(document.querySelectorAll('.eg-num-input:not(:disabled)'));
    // Часть 1: номера ИЛИ блоки. Выбран блок — номера снимаются (сервер
    // возьмёт все 1–16), выбран номер — снимаются блоки.
    var exclusive = !!document.getElementById('eg-all');
    var chips = Array.prototype.slice.call(document.querySelectorAll('.eg-chip input'));
    var nums = document.getElementById('eg-nums');
    var go = document.getElementById('eg-go');
    var all = document.getElementById('eg-all');
    var allSub = document.getElementById('eg-all-sub');
    var label = document.getElementById('eg-go-label');
    var part2Text = @json($t['start_part2']);

    function sync() {
      var checked = inputs.filter(function (i) { return i.checked; });
      if (all) {
        var full = inputs.length > 0 && checked.length === inputs.length;
        all.setAttribute('aria-pressed', full ? 'true' : 'false');
        allSub.textContent = full ? 'Снять всё' : 'Выбрать всё';
      }
      if (label && checked[0]) label.textContent = part2Text.replace(':n', checked[0].value);
      var byBlock = exclusive && chips.some(function (c) { return c.checked; });
      if (nums) nums.classList.toggle('is-auto', byBlock);
      if (go) go.disabled = checked.length === 0 && !byBlock;
    }
    inputs.forEach(function (i) { i.addEventListener('change', function () {
      if (exclusive && i.checked) chips.forEach(function (c) { c.checked = false; });
      sync();
    }); });
    if (exclusive) chips.forEach(function (c) { c.addEventListener('change', function () {
      if (c.checked) inputs.forEach(function (i) { i.checked = false; });
      sync();
    }); });
    if (all) all.addEventListener('click', function () {
      var full = all.getAttribute('aria-pressed') === 'true';
      inputs.forEach(function (i) { i.checked = !full; });
      if (!full) chips.forEach(function (c) { c.checked = false; });
      sync();
    });
    sync();

    var help = document.getElementById('egc-help');
    var tiers = document.getElementById('egc-tiers');
    if (help && tiers) {
      var toggle = function (open) { tiers.hidden = !open; help.setAttribute('aria-expanded', open ? 'true' : 'false'); };
      help.addEventListener('click', function (e) { e.stopPropagation(); toggle(tiers.hidden); });
      document.addEventListener('click', function (e) { if (!tiers.contains(e.target)) toggle(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
    }
  })();
</script>
@endpush
