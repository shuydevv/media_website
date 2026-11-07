{{-- resources/views/student/ege/written.blade.php
     Подход второй части станции «Задания» (WrittenPractice). Черновик —
     ответ пишется и сохраняется сам; две кнопки: «Сверить с образцом»
     (без корма, потом отправить нельзя) и «Отправить куратору» (корм —
     по баллу куратора). После любого из них — ответ рядом с образцом и
     чек-лист по критериям номера (config/task_selfcheck.php). --}}
@extends('layouts.main')

@section('title', 'Задание '.(int) $tasks->first()?->number.' — Школа Полтавского')

@section('back_url', route('student.ege.index', ['part' => 2]))

@php
  $t = $texts;
  $first = $tasks->first();
  $number = (int) $first?->number;
  $passage = $tasks->pluck('passage_text')->filter()->first();
  $answers = $review->answers ?? [];
  $marks = $review->self_marks ?? [];
  $isDraft = $review->status === \App\Models\TaskReview::DRAFT;
  $isSent = in_array($review->status, [\App\Models\TaskReview::SENT, \App\Models\TaskReview::CHECKED], true);
  $isChecked = $review->status === \App\Models\TaskReview::CHECKED;
  $foodIcon = auth()->user()->fish_food_icon_url;
@endphp

@section('content')
<div class="eg-page" id="egw" data-draft-url="{{ route('student.ege.written.draft', $review) }}" data-marks-url="{{ route('student.ege.written.marks', $review) }}">
  <div class="egw-head">
    <span class="eg-badge">№ {{ $number }}@if($tasks->count() > 1)–{{ (int) $tasks->last()->number }}@endif · {{ $numberTitle }}</span>
    <span class="eg-muted">{{ strtr($t['max_points'], [':n' => $review->max_score]) }}</span>
  </div>

  @if($isChecked)
    <div class="egw-banner is-checked">
      <b>{{ strtr($t['curator_score'], [':score' => $review->score, ':max' => $review->max_score]) }}</b>
      @if($review->corm_awarded > 0)
        <span class="egw-corm">{{ strtr($t['corm_review'], [':n' => $review->corm_awarded]) }} <img src="{{ $foodIcon }}" alt=""></span>
      @endif
      @if($review->comment)
        <div class="egw-comment"><span>{{ $t['curator_comment'] }}</span>{{ $review->comment }}</div>
      @endif
    </div>
  @elseif($review->status === \App\Models\TaskReview::SENT)
    <div class="egw-banner">
      <b>{{ $t['sent_title'] }} ✉️</b>
      <span>{{ $t['sent_body'] }}</span>
    </div>
  @endif

  @if($passage)
    <details class="eg-card egw-passage" @if($isDraft) open @endif>
      <summary>{{ $t['text_toggle'] }}</summary>
      <div class="eg-q">{{ $passage }}</div>
    </details>
  @endif

  <form method="POST" action="{{ route('student.ege.written.self', $review) }}" id="egw-form">
    @csrf
    @foreach($tasks as $task)
      @php
        $id = $task->id;
        $spec = $checklists[$id] ?? null;
        $m = $marks[(string) $id] ?? ['items' => [], 'penalty' => 0];
      @endphp
      <section class="eg-card egw-task">
        @if($tasks->count() > 1)<div class="egw-n">Задание {{ (int) $task->number }}</div>@endif
        <div class="eg-q">{{ $task->question_text }}</div>
        @if($task->image_path)
          <img class="eg-img" src="{{ asset('storage/'.$task->image_path) }}" alt="Иллюстрация к заданию" loading="lazy">
        @endif

        @if($isDraft)
          <label class="egw-label" for="egw-a-{{ $id }}">{{ $t['answer_area'] }}</label>
          <textarea class="egw-area" id="egw-a-{{ $id }}" name="answers[{{ $id }}]" rows="9" maxlength="{{ \App\Service\Bank\WrittenPractice::MAX_LENGTH }}"
                    placeholder="{{ $t['answer_area_hint'] }}">{{ old('answers.'.$id, $answers[(string) $id] ?? '') }}</textarea>
        @else
          {{-- Ответ и образец рядом; на телефоне — вкладки. --}}
          <div class="egw-tabs" role="tablist">
            <button type="button" class="egw-tab is-on" data-tab="mine-{{ $id }}">{{ $t['my_answer'] }}</button>
            <button type="button" class="egw-tab" data-tab="ex-{{ $id }}">{{ $t['exemplar'] }}</button>
          </div>
          <div class="egw-compare">
            <div class="egw-pane is-on" data-pane="mine-{{ $id }}">
              <div class="egw-pane-t">{{ $t['my_answer'] }}</div>
              <div class="egw-text">{{ ($answers[(string) $id] ?? '') !== '' ? $answers[(string) $id] : '—' }}</div>
            </div>
            <div class="egw-pane egw-pane-ex" data-pane="ex-{{ $id }}">
              <div class="egw-pane-t">{{ $t['exemplar'] }}</div>
              <div class="egw-text">{{ $task->answer }}</div>
            </div>
          </div>

          @if($spec)
            <div class="egw-check" data-task="{{ $id }}">
              <div class="egw-check-t">{{ $t['checklist'] }}</div>
              @foreach($spec['items'] as $i => $item)
                @php $max = (int) ($item['max'] ?? 1); $val = (int) ($m['items'][$i] ?? 0); @endphp
                <div class="egw-item">
                  <div class="egw-item-text">
                    <b>{{ $item['label'] }}</b>
                    <span>{{ $item['hint'] }}</span>
                  </div>
                  <div class="egw-seg" data-item="{{ $i }}">
                    @if($max === 1)
                      <button type="button" data-v="0" class="{{ $val === 0 ? 'is-on' : '' }}">Нет</button>
                      <button type="button" data-v="1" class="{{ $val === 1 ? 'is-on' : '' }}">Да</button>
                    @else
                      @for($v = 0; $v <= $max; $v++)
                        <button type="button" data-v="{{ $v }}" class="{{ $val === $v ? 'is-on' : '' }}">{{ $v }}</button>
                      @endfor
                    @endif
                  </div>
                </div>
              @endforeach
              @if(isset($spec['penalty']))
                @php $p = (int) ($m['penalty'] ?? 0); @endphp
                <div class="egw-item">
                  <div class="egw-item-text">
                    <b>{{ $spec['penalty']['label'] }}</b>
                    <span>{{ $spec['penalty']['hint'] }}</span>
                  </div>
                  <div class="egw-seg" data-penalty>
                    <button type="button" data-v="0" class="{{ $p === 0 ? 'is-on' : '' }}">Нет</button>
                    <button type="button" data-v="1" class="{{ $p === 1 ? 'is-on' : '' }}">Одна</button>
                    <button type="button" data-v="2" class="{{ $p === 2 ? 'is-on' : '' }}">Две+</button>
                  </div>
                </div>
              @endif
            </div>
          @endif
        @endif
      </section>
    @endforeach

    @if($isDraft)
      @error('answers')<div class="eg-error" style="margin-top:12px">{{ $message }}</div>@enderror
      <div class="egw-saved" id="egw-saved" aria-live="polite"></div>
      <div class="egw-actions">
        <div>
          <button type="submit" class="eg-btn eg-btn-ghost eg-btn-big" formaction="{{ route('student.ege.written.self', $review) }}">{{ $t['self_check'] }}</button>
          <p class="egw-note">{{ $t['self_check_note'] }}</p>
        </div>
        <div>
          <button type="submit" class="eg-btn eg-btn-big" id="egw-send" formaction="{{ route('student.ege.written.send', $review) }}"
                  @disabled($sendsLeft <= 0) data-confirm="{{ $t['send_confirm'] }}">{{ $t['send'] }}</button>
          <p class="egw-note">{{ $sendsLeft > 0 ? strtr($t['send_note'], [':n' => $sendsLeft]) : $t['send_limit'] }}</p>
        </div>
      </div>
    @endif
  </form>

  @unless($isDraft)
    @if(collect($checklists)->filter()->isNotEmpty())
      <div class="egw-total" id="egw-total" @if($review->self_score === null) hidden @endif>
        {{ strtr($t['self_total'], [':score' => $review->self_score ?? 0, ':max' => $review->max_score]) }}
      </div>
    @endif
    <div class="egw-actions">
      <form method="POST" action="{{ route('student.ege.written.start') }}">
        @csrf
        <input type="hidden" name="number" value="{{ $number }}">
        <button type="submit" class="eg-btn eg-btn-big">{{ $t['next_task'] }}</button>
      </form>
      <a class="eg-btn eg-btn-ghost eg-btn-big" href="{{ route('student.ege.index', ['part' => 2]) }}">К заданиям</a>
    </div>
  @endunless
</div>
@endsection

@push('page-scripts')
@include('student.ege.partials.styles')
<style>
  .egw-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
  .egw-banner { margin-bottom: 14px; padding: 14px 16px; border-radius: 18px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; font-size: 14px; line-height: 1.45; }
  .egw-banner b { display: block; font-size: 16px; margin-bottom: 2px; }
  .egw-banner.is-checked { background: #f0fdf4; border-color: #86efac; color: #14532d; }
  .egw-corm { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; padding: 3px 10px; border-radius: 99px; background: #fff7ed; color: #c2410c; font-weight: 700; }
  .egw-corm img { width: 18px; height: 18px; }
  .egw-comment { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: #fff; color: #18181b; white-space: pre-line; }
  .egw-comment span { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; margin-bottom: 2px; }
  .egw-passage { margin-bottom: 14px; }
  .egw-passage summary { cursor: pointer; font-weight: 600; color: #2563eb; margin-bottom: 4px; }
  .egw-passage .eg-q { margin-top: 8px; font-size: 15px; }
  .egw-task + .egw-task { margin-top: 14px; }
  .egw-n { font-size: 13px; font-weight: 700; color: #2563eb; margin-bottom: 6px; }
  .egw-label { display: block; margin-top: 16px; font-size: 13px; font-weight: 600; color: #52525b; }
  .egw-area { display: block; width: 100%; box-sizing: border-box; margin-top: 6px; padding: 12px 14px; border-radius: 14px; border: 1.5px solid #d4d4d8; font-size: 16px; line-height: 1.5; resize: vertical; min-height: 180px; font-family: inherit; }
  .egw-area:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
  .egw-saved { min-height: 18px; margin-top: 8px; font-size: 13px; color: #a1a1aa; text-align: right; }
  .egw-actions { display: grid; gap: 12px; margin-top: 12px; }
  @media (min-width: 640px) { .egw-actions { grid-template-columns: 1fr 1fr; } }
  .egw-actions form, .egw-actions a { display: block; }
  .egw-note { margin: 6px 2px 0; font-size: 13px; color: #71717a; line-height: 1.4; }

  .egw-tabs { display: none; gap: 4px; padding: 4px; margin-top: 14px; border-radius: 12px; background: #f4f4f5; }
  .egw-tab { flex: 1; padding: 8px 0; border: 0; border-radius: 9px; background: transparent; font-size: 14px; font-weight: 600; color: #52525b; cursor: pointer; }
  .egw-tab.is-on { background: #fff; color: #18181b; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
  .egw-compare { display: grid; gap: 10px; margin-top: 14px; }
  @media (min-width: 720px) { .egw-compare { grid-template-columns: 1fr 1fr; } }
  .egw-pane { padding: 12px 14px; border-radius: 14px; border: 1px solid #e4e4e7; background: #fafafa; }
  .egw-pane-ex { background: #f0fdf4; border-color: #bbf7d0; }
  .egw-pane-t { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; margin-bottom: 4px; }
  .egw-text { white-space: pre-line; font-size: 15px; line-height: 1.55; color: #18181b; }
  @media (max-width: 719px) {
    .egw-tabs { display: flex; }
    .egw-pane { display: none; }
    .egw-pane.is-on { display: block; }
    .egw-pane-t { display: none; }
  }

  .egw-check { margin-top: 16px; }
  .egw-check-t { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; margin-bottom: 4px; }
  .egw-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px solid #f4f4f5; }
  .egw-item-text { flex: 1; min-width: 0; line-height: 1.35; }
  .egw-item-text b { display: block; font-size: 15px; color: #18181b; }
  .egw-item-text span { display: block; font-size: 13px; color: #71717a; margin-top: 2px; }
  .egw-seg { flex: none; display: inline-flex; padding: 3px; border-radius: 11px; background: #f4f4f5; }
  .egw-seg button { min-width: 40px; height: 34px; padding: 0 10px; border: 0; border-radius: 8px; background: transparent; font-size: 14px; font-weight: 600; color: #52525b; cursor: pointer; }
  .egw-seg button.is-on { background: #fff; color: #18181b; box-shadow: 0 1px 3px rgba(0, 0, 0, .1); }
  @media (max-width: 560px) { .egw-item { flex-direction: column; align-items: stretch; } .egw-seg { align-self: flex-start; } }
  .egw-total { margin-top: 14px; padding: 12px 16px; border-radius: 14px; background: #fff; border: 1px solid #e4e4e7; font-size: 16px; font-weight: 700; text-align: center; color: #18181b; }
</style>
<script>
  (function () {
    var root = document.getElementById('egw');
    if (!root) return;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    function post(url, data) {
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(data),
        credentials: 'same-origin'
      }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    }

    // Черновик сохраняется сам — через полторы секунды после правки.
    var areas = Array.prototype.slice.call(root.querySelectorAll('.egw-area'));
    var saved = document.getElementById('egw-saved');
    var timer = null;
    function collect() {
      var answers = {};
      areas.forEach(function (a) { answers[a.id.replace('egw-a-', '')] = a.value; });
      return answers;
    }
    function save() {
      post(root.getAttribute('data-draft-url'), { answers: collect() })
        .then(function () { if (saved) saved.textContent = @json($t['saved']) + ' ✓'; })
        .catch(function () { if (saved) saved.textContent = ''; });
    }
    areas.forEach(function (a) {
      a.addEventListener('input', function () {
        if (saved) saved.textContent = '';
        clearTimeout(timer);
        timer = setTimeout(save, 1500);
      });
    });
    var send = document.getElementById('egw-send');
    if (send) send.addEventListener('click', function (e) {
      if (!window.confirm(send.getAttribute('data-confirm'))) e.preventDefault();
    });

    // Вкладки «Мой ответ / Образец» на телефоне.
    root.querySelectorAll('.egw-tabs').forEach(function (tabs) {
      tabs.addEventListener('click', function (e) {
        var b = e.target.closest('.egw-tab');
        if (!b) return;
        var section = tabs.parentElement;
        tabs.querySelectorAll('.egw-tab').forEach(function (x) { x.classList.toggle('is-on', x === b); });
        section.querySelectorAll('.egw-pane').forEach(function (p) { p.classList.toggle('is-on', p.getAttribute('data-pane') === b.getAttribute('data-tab')); });
      });
    });

    // Чек-лист: каждая отметка уходит на сервер, балл считает он.
    var total = document.getElementById('egw-total');
    var totalText = @json($t['self_total']);
    function marks() {
      var out = {};
      root.querySelectorAll('.egw-check').forEach(function (box) {
        var items = [];
        box.querySelectorAll('.egw-seg[data-item]').forEach(function (seg) {
          var on = seg.querySelector('button.is-on');
          items[parseInt(seg.getAttribute('data-item'), 10)] = on ? parseInt(on.getAttribute('data-v'), 10) : 0;
        });
        var pen = box.querySelector('.egw-seg[data-penalty] button.is-on');
        out[box.getAttribute('data-task')] = { items: items, penalty: pen ? parseInt(pen.getAttribute('data-v'), 10) : 0 };
      });
      return out;
    }
    root.querySelectorAll('.egw-seg').forEach(function (seg) {
      seg.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;
        seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
        post(root.getAttribute('data-marks-url'), { marks: marks() }).then(function (res) {
          if (!total) return;
          total.hidden = false;
          total.textContent = totalText.replace(':score', res.self_score).replace(':max', res.max);
          if (window.gsap && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            window.gsap.fromTo(total, { scale: 0.96 }, { scale: 1, duration: 0.35, ease: 'back.out(3)', clearProps: 'transform' });
          }
        });
      });
    });
  })();
</script>
@endpush
