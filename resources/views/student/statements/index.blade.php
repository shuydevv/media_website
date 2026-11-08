{{-- resources/views/student/statements/index.blade.php
     Главная «Верных суждений»: корм на сегодня, повтор ошибок, выбор
     блоков и старт раунда. Данные — StatementTrainer::overview().
     Тон — как у рыбы (config/fish_speech.php): «мы», без рода. Ученику
     говорим «раунд» (в коде — сессия). Стили — свои (.st*), не Tailwind:
     не зависят от пересборки CSS. --}}
@extends('layouts.main')

@section('title', 'Верные суждения — Школа Полтавского')

@section('back_url', route('student.trainers.index', ['hall' => 'social']))

@php
  $plural = fn (int $n, array $forms) => \App\Support\Text::plural($n, $forms);
  $word = ['суждение', 'суждения', 'суждений'];
  $unfinished = $overview['unfinished'];
  $phrases = config('statements.phrases');
  $corm = $overview['corm'];
  $foodIcon = auth()->user()->fish_food_icon_url;
  $size = (int) config('statements.session_size');
  $tiers = collect(config('statements.corm'))->sortKeysDesc()
      ->map(fn ($food, $percent) => ['from' => (int) ceil($size * $percent / 100), 'food' => (int) $food])
      ->values();
  // Примерно 12 секунд на карточку с разбором.
  $minutes = max(1, (int) round($size * 12 / 60));
  $hasBase = array_sum(array_column($overview['blocks'], 'total')) > 0;
  $dueBlocks = array_keys(array_filter($overview['blocks'], fn ($b) => $b['due'] > 0));

  // «Пройдено: 7»; «из скольки» — только когда пройдено больше половины,
  // иначе «7 из 607» скорее пугает, чем радует.
  $passed = function (array $b): string {
      if ($b['total'] > 0 && $b['seen'] >= $b['total']) {
          return 'Пройдено всё ✓';
      }

      return 'Пройдено: '.$b['seen'].($b['seen'] * 2 > $b['total'] ? ' из '.$b['total'] : '');
  };
@endphp

@section('content')
<div class="st-page">
  <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900 mb-4">Верные суждения</h1>

  {{-- Корм сегодня: слоты — раунды с кормом (с иконкой — свободен, с
       галочкой — использован), ниже — сколько заработано и баланс. --}}
  <div class="stc {{ $corm['left'] > 0 ? '' : 'stc-done' }}">
    <div class="stc-slots" aria-hidden="true">
      @for($i = 0; $i < $corm['limit']; $i++)
        @if($i < $corm['paid'])
          <span class="stc-slot is-used">✓</span>
        @else
          <span class="stc-slot is-free"><img src="{{ $foodIcon }}" alt=""></span>
        @endif
      @endfor
    </div>
    <div class="stc-text">
      <b>{{ $corm['left'] > 0
          ? strtr($phrases['corm_left'], [':rounds' => \App\Service\Statements\StatementTrainer::rounds($corm['left'])])
          : $phrases['corm_done'] }}</b>
      <span class="stc-sub">
        @if($corm['left'] === 0)
          <span>{{ $phrases['corm_done_hint'] }}</span>
        @elseif($corm['earned'] > 0)
          <span class="stc-piece"><span class="stc-plus">+{{ $corm['earned'] }}</span> сегодня</span>
        @endif
      </span>
    </div>
    {{-- Баланс — тем же чипом, что в Качалке (.gym-chip в
         student/trainers/index): ПК — слева от «?», телефон — под «?». --}}
    <span class="stc-wallet"><img src="{{ $foodIcon }}" alt="">Корм <b>{{ $cormBalance }}</b></span>
    <button type="button" class="stc-help" id="stc-help" aria-expanded="false" aria-controls="stc-tiers" aria-label="Сколько корма за раунд">?</button>
    <div class="stc-tiers" id="stc-tiers" hidden>
      <div class="stc-tiers-title">Корм за раунд</div>
      @foreach($tiers as $tier)
        <div class="stc-tier">
          <span>{{ $tier['from'] === $size ? $size.' из '.$size : 'от '.$tier['from'].' верных' }}</span>
          <span class="stc-tier-food">@for($f = 0; $f < $tier['food']; $f++)<img src="{{ $foodIcon }}" alt="">@endfor</span>
        </div>
      @endforeach
      <div class="stc-tier-note">{{ $phrases['corm_rule'] }}</div>
    </div>
  </div>

  @if(session('statements_empty'))
    <div class="st-notice">{{ session('statements_empty') }}</div>
  @endif

  @if(!$hasBase)
    {{-- База не залита (не запущен statements:import) — иначе все блоки
         просто серые, и это выглядит как «закончились попытки». --}}
    <div class="st-notice">
      Карточки ещё не загружены — скоро появятся.
      @if(auth()->user()->isAdmin())
        <span class="block mt-1">Для админа: запустите <code>php artisan statements:import</code>.</span>
      @endif
    </div>
  @endif

  @if($unfinished)
    <div class="st-action">
      <div class="st-action-text">
        <b>Мы уже на полпути!</b>
        <span>Осталось {{ $plural(max(1, $unfinished->total - $unfinished->position), $word) }}</span>
      </div>
      <a class="st-action-btn" href="{{ route('student.statements.session', $unfinished) }}">Продолжить</a>
    </div>
  @elseif($overview['due'] > 0)
    {{-- Ошибки и так идут в раунд первыми; кнопка — короткий путь к ним:
         раунд по блокам, где есть что повторить. --}}
    <form method="POST" action="{{ route('student.statements.start') }}" class="st-action">
      @csrf
      @foreach($dueBlocks as $key)
        <input type="hidden" name="blocks[]" value="{{ $key }}">
      @endforeach
      <div class="st-action-text">
        <b>На повтор: {{ $plural($overview['due'], $word) }}</b>
        <span>Ошибки прошлых раундов — пора закрепить</span>
      </div>
      <button type="submit" class="st-action-btn">Повторить</button>
    </form>
  @endif

  <form method="POST" action="{{ route('student.statements.start') }}" class="st-card" id="st-pick">
    @csrf
    <div class="st-card-title">{{ $phrases['pick_title'] }}</div>

    @error('blocks')
      <div class="st-error">{{ $message }}</div>
    @enderror

    {{-- «Все разделы» — первой плиткой. По умолчанию ничего не выбрано
         (решение автора от 06.11.2026): ученик сам решает, что качать. --}}
    <div class="st-grid">
      <button type="button" class="st-tile st-tile-all" id="st-all" aria-pressed="false">
        <span class="st-tile-check" aria-hidden="true"></span>
        <span class="st-tile-emoji" aria-hidden="true">📚</span>
        <span class="st-tile-title">Все разделы</span>
        <span class="st-tile-sub" id="st-all-sub">Выбрать всё</span>
      </button>
      @foreach($overview['blocks'] as $key => $block)
        <label class="st-tile {{ $block['total'] === 0 ? 'is-off' : '' }}">
          <input type="checkbox" name="blocks[]" value="{{ $key }}" class="st-tile-input"
                 @checked(in_array($key, old('blocks', []), true)) @disabled($block['total'] === 0)>
          <span class="st-tile-check" aria-hidden="true"></span>
          <span class="st-tile-emoji" aria-hidden="true">{{ config('statements.block_emoji.'.$key) }}</span>
          <span class="st-tile-title">{{ $block['title'] }}</span>
          <span class="st-tile-sub">
            {{ $passed($block) }}@if($block['due'] > 0)<span class="st-tile-due"> · на повтор {{ $block['due'] }}</span>@endif
          </span>
        </label>
      @endforeach
    </div>

    <button type="submit" class="st-go" id="st-go" @disabled(!$hasBase)>
      {{-- Пока блоки не выбраны — кнопка говорит, что сделать. --}}
      <span class="st-go-on">Погнали <span class="st-go-meta">· {{ $size }} карточек, ~{{ $minutes }} мин</span></span>
      <span class="st-go-off">Выберите блоки</span>
    </button>
  </form>
</div>
@endsection

@push('page-scripts')
<style>
  .st-page { max-width: 760px; margin: 0 auto; padding: 24px 16px 32px; }

  /* Полоска «корм сегодня» */
  .stc { position: relative; display: flex; align-items: center; gap: 14px; margin-bottom: 14px; padding: 12px 14px 12px 12px; border-radius: 18px; border: 1px solid #e4e4e7; background: #fff; box-shadow: 0 6px 24px rgba(37, 99, 235, .06); }
  .stc-slots { display: flex; gap: 6px; flex: none; }
  .stc-slot { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; }
  .stc-slot.is-free { background: #eff6ff; box-shadow: inset 0 0 0 1.5px #bfdbfe; }
  .stc-slot.is-free img { width: 26px; height: 26px; }
  .stc-slot.is-used { background: #f4f4f5; color: #a1a1aa; font-size: 18px; font-weight: 600; }
  .stc-text { min-width: 0; flex: 1; line-height: 1.3; }
  .stc-text b { display: block; font-size: 16px; font-weight: 600; color: #18181b; }
  .stc-sub { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-top: 2px; font-size: 13px; color: #71717a; }
  .stc-sub img { width: 15px; height: 15px; }
  .stc-sub:empty { display: none; }
  .stc-piece { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
  /* Как «?» рядом: белый, тонкая обводка, без тени (чип внутри карточки). */
  .stc-wallet { flex: none; display: inline-flex; align-items: center; gap: 6px; background: #fff; border: 1px solid #e4e4e7; border-radius: 999px; padding: 3px 12px 3px 6px; font-size: 14px; color: #18181b; white-space: nowrap; }
  .stc-wallet { height: 28px; box-sizing: border-box; }
  .stc-wallet img { width: 18px; height: 18px; }
  .stc-wallet b { font-weight: 600; }
  .stc-plus { font-weight: 600; color: #15803d; }
  .stc-help { flex: none; width: 28px; height: 28px; border-radius: 99px; border: 1px solid #e4e4e7; background: #fff; color: #71717a; font-size: 13px; font-weight: 600; cursor: pointer; }
  .stc-help:hover, .stc-help[aria-expanded="true"] { color: #18181b; border-color: #a1a1aa; }
  .stc-tiers { position: absolute; z-index: 20; top: calc(100% + 6px); right: 0; width: 220px; padding: 12px 14px; border-radius: 14px; border: 1px solid #e4e4e7; background: #fff; box-shadow: 0 10px 30px rgba(15, 23, 42, .12); }
  .stc-tiers-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; margin-bottom: 4px; }
  .stc-tier { display: flex; align-items: center; justify-content: space-between; font-size: 14px; padding: 4px 0; color: #3f3f46; }
  .stc-tier-food { display: inline-flex; gap: 2px; }
  .stc-tier-food img { width: 17px; height: 17px; }
  .stc-tier-note { margin-top: 6px; padding-top: 8px; border-top: 1px solid #f4f4f5; font-size: 12px; color: #71717a; }

  .st-notice { margin-bottom: 14px; padding: 12px 14px; border-radius: 14px; border: 1px solid #fde68a; background: #fffbeb; font-size: 14px; color: #78350f; }

  /* Продолжить / Повторить ошибки */
  .st-action { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; padding: 12px 12px 12px 16px; border-radius: 18px; background: #fff; border: 1px solid #bfdbfe; box-shadow: 0 4px 16px rgba(37, 99, 235, .08); }
  .st-action-text { min-width: 0; flex: 1; line-height: 1.3; }
  .st-action-text b { display: block; font-size: 15px; font-weight: 600; color: #18181b; }
  .st-action-text span { display: block; font-size: 13px; color: #71717a; }
  .st-action-btn { flex: none; min-height: 40px; font-size: 15px; }

  /* Выбор блоков */
  .st-card { padding: 18px; border-radius: 20px; background: #fff; border: 1px solid #e4e4e7; box-shadow: 0 6px 24px rgba(37, 99, 235, .06); }
  .st-card-title { font-size: 19px; font-weight: 600; color: #18181b; }
  .st-error { margin-top: 8px; font-size: 14px; color: #e11d48; }
  .st-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
  @media (min-width: 640px) { .st-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  .st-tile { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; min-height: 72px; padding: 12px 34px 12px 12px; border-radius: 14px; border: 1.5px solid #e4e4e7; background: #fff; text-align: left; cursor: pointer; user-select: none; transition: border-color .15s, box-shadow .15s; }
  @media (hover: hover) { .st-tile:not(.is-off):hover { border-color: #a1a1aa; } }
  .st-tile-input { position: absolute; opacity: 0; pointer-events: none; }
  .st-tile-title { font-size: 14px; font-weight: 600; color: #18181b; line-height: 1.25; }
  /* Значок раздела — крупно, над названием. */
  .st-tile-emoji { display: block; font-size: 26px; line-height: 1; margin-bottom: 6px; }
  .st-tile-sub { font-size: 12px; color: #71717a; }
  .st-tile-due { color: #c2410c; }
  .st-tile-check { position: absolute; top: 10px; right: 10px; width: 18px; height: 18px; border-radius: 99px; border: 1.5px solid #d4d4d8; background: #fff; }
  .st-tile:has(.st-tile-input:checked), .st-tile-all[aria-pressed="true"] { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .1); }
  .st-tile:has(.st-tile-input:checked) .st-tile-check, .st-tile-all[aria-pressed="true"] .st-tile-check { border-color: #2563eb; background: #2563eb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M4 8.5l2.5 2.5L12 5.5' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / 14px no-repeat; }
  .st-tile:has(.st-tile-input:focus-visible) { outline: 2px solid #2563eb; outline-offset: 2px; }
  .st-tile.is-off { opacity: .45; cursor: default; }
  /* «Все разделы» — действие, а не заглушка: голубая, сплошная рамка. */
  .st-tile-all { background: #eff6ff; border-color: #bfdbfe; }
  .st-tile-all .st-tile-title { color: #1d4ed8; }
  .st-tile-all .st-tile-sub { color: #3b82f6; }
  .st-tile-all .st-tile-check { border-color: #93c5fd; }

  .st-go { display: flex; width: 100%; min-height: 54px; margin-top: 16px; font-size: 17px; }
  .st-go-meta { font-weight: 400; opacity: .85; font-size: 15px; }
  .st-go-off { display: none; }
  .st-go:disabled .st-go-on { display: none; }
  .st-go:disabled .st-go-off { display: inline; }

  /* Наведение — только мышью: на сенсорном экране рамка «залипала» после
     касания и выглядела как полувыбранный блок. */
  @media (hover: hover) {
  }

  /* Телефон: полоска корма в две строки — сверху фраза и «?», снизу слоты,
     «+3 сегодня · всего 12». В одну строку фраза рвалась посередине. */
  @media (max-width: 560px) {
    .stc { display: grid; grid-template-columns: auto 1fr auto; grid-template-areas: "title title help" "slots sub wallet"; column-gap: 10px; row-gap: 8px; }
    .stc-wallet { grid-area: wallet; justify-self: end; }
    .stc-help { justify-self: end; }
    .stc-text { display: contents; }
    .stc-text b { grid-area: title; align-self: center; }
    .stc-sub { grid-area: sub; margin-top: 0; }
    .stc-slots { grid-area: slots; }
    .stc-help { grid-area: help; }
    .stc-slot { width: 32px; height: 32px; border-radius: 11px; }
    .stc-slot.is-free img { width: 21px; height: 21px; }
    .st-go-meta { display: none; }
  }
</style>
<script>
  (function () {
    var inputs = Array.prototype.slice.call(document.querySelectorAll('.st-tile-input:not(:disabled)'));
    var go = document.getElementById('st-go');
    var all = document.getElementById('st-all');
    var allSub = document.getElementById('st-all-sub');

    function sync() {
      var checked = inputs.filter(function (i) { return i.checked; }).length;
      var full = inputs.length > 0 && checked === inputs.length;
      all.setAttribute('aria-pressed', full ? 'true' : 'false');
      allSub.textContent = full ? 'Снять всё' : 'Выбрать всё';
      go.disabled = checked === 0;
    }
    inputs.forEach(function (i) { i.addEventListener('change', sync); });
    // «Все разделы» — переключатель: всё выбрано → снять всё.
    all.addEventListener('click', function () {
      var full = all.getAttribute('aria-pressed') === 'true';
      inputs.forEach(function (i) { i.checked = !full; });
      sync();
    });
    sync();

    // Пороги корма — по «?», закрываются кликом мимо или Esc.
    var help = document.getElementById('stc-help');
    var tiers = document.getElementById('stc-tiers');
    function toggle(open) { tiers.hidden = !open; help.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    help.addEventListener('click', function (e) { e.stopPropagation(); toggle(tiers.hidden); });
    document.addEventListener('click', function (e) { if (!tiers.contains(e.target)) toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
  })();
</script>
@endpush
