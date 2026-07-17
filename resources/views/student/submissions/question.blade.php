@extends('layouts.main')

@section('content')
@php
  $isManual = $task->isAutoGradable() ? false : true;

  $answers = $submission->answers ?? [];
  $perTask = $submission->per_task_results ?? [];

  // Статус каждого вопроса для навигационной полоски
  $statusOf = function ($t) use ($answers, $perTask) {
    if (!array_key_exists($t->id, $answers)) return 'unanswered';
    if (!$t->isAutoGradable()) return 'saved';
    return $perTask[$t->id]['status'] ?? 'saved';
  };

  $pillClasses = [
    'unanswered' => 'bg-gray-100 text-gray-500 border-gray-200',
    'saved'      => 'bg-blue-50 text-blue-700 border-blue-200',
    'ok'         => 'bg-emerald-50 text-emerald-700 border-emerald-300',
    'partial'    => 'bg-amber-50 text-amber-700 border-amber-300',
    'fail'       => 'bg-rose-50 text-rose-700 border-rose-300',
  ];

  // Значение, которое показать в поле ввода прямо сейчас
  $prefill = $checkAnswer ?? $savedAnswer ?? '';
@endphp

<div class="max-w-3xl mx-auto px-3 sm:px-4 py-5 sm:py-6">

  <div class="flex items-center justify-between mb-4">
    <h1 class="text-lg sm:text-xl font-medium">{{ $homework->title ?? 'Домашнее задание' }}</h1>
    <a href="{{ route('student.submissions.finish', $submission) }}" class="text-sm text-blue-600 hover:underline">Обзор работы</a>
  </div>

  {{-- Навигация по вопросам --}}
  <div class="flex flex-wrap gap-2 mb-6">
    @foreach($tasks as $i => $t)
      @php $st = $statusOf($t); @endphp
      <a href="{{ route('student.submissions.question', [$submission, $i + 1]) }}"
         class="inline-flex items-center justify-center w-9 h-9 rounded-lg border text-sm font-medium {{ $pillClasses[$st] }} {{ ($i + 1) === $position ? 'ring-2 ring-blue-500' : '' }}">
        {{ $i + 1 }}
      </a>
    @endforeach
  </div>

  @if ($errors->any())
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc list-inside">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
    <div class="flex items-center justify-between gap-3 mb-4 sm:mb-5">
      <div class="flex items-center gap-3">
        <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-gray-100 border border-gray-200 text-gray-700">
          №{{ $task->task->number ?? '—' }} в ЕГЭ
        </span>
        <span class="text-base sm:text-lg font-semibold text-gray-900">Вопрос {{ $position }} из {{ $total }}</span>
      </div>

      @if(!empty($task->hint))
        <button type="button" id="hint-toggle" class="text-xs sm:text-sm text-blue-600 hover:underline whitespace-nowrap">
          Показать подсказку
        </button>
      @endif
    </div>

    @if(!empty($task->hint))
      <div id="hint-box" class="hidden mb-5 sm:mb-6 p-3 sm:p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-900 whitespace-pre-wrap">
        {{ $task->hint }}
      </div>
    @endif

    @include('student.submissions.partials.task-prompt', ['task' => $task])

    {{-- Результат проверки (после «check», ничего ещё не сохранено) --}}
    @if($checkResult)
      @php
        $bannerClasses = [
          'ok'      => 'bg-emerald-50 border-emerald-200 text-emerald-800',
          'partial' => 'bg-amber-50 border-amber-200 text-amber-800',
          'fail'    => 'bg-rose-50 border-rose-200 text-rose-800',
        ][$checkResult['status']];
        $bannerText = [
          'ok'      => 'Верно!',
          'partial' => 'Частично верно.',
          'fail'    => 'Неверно.',
        ][$checkResult['status']];
        $checkBorderColor = [
          'ok'      => '#10B981',
          'partial' => '#F59E0B',
          'fail'    => '#E19999',
        ][$checkResult['status']];
        $checkChars = preg_split('//u', (string) $checkAnswer, -1, PREG_SPLIT_NO_EMPTY) ?: [];
      @endphp
      <div class="mt-2 mb-4 p-4 rounded-xl border {{ $bannerClasses }}">
        <div class="font-medium">{{ $bannerText }} {{ $checkResult['score'] }} / {{ $checkResult['max'] }} баллов</div>
      </div>

      {{-- Ответ студента — тот же вид квадратиков, что и при вводе, только заполненный и подсвеченный --}}
      <div class="mb-5">
        <div class="text-xs text-gray-500 mb-2">Ваш ответ</div>
        <div class="pin-boxes">
          @forelse($checkChars as $ch)
            <div class="pin-box filled" style="border-color: {{ $checkBorderColor }};">{{ mb_strtoupper($ch) }}</div>
          @empty
            <div class="pin-box filled" style="border-color: {{ $checkBorderColor }};">—</div>
          @endforelse
        </div>
      </div>

      <div class="flex flex-wrap gap-3 mt-8">
        <form method="POST" action="{{ route('student.submissions.question.save', [$submission, $position]) }}">
          @csrf
          <input type="hidden" name="answer" value="{{ $checkAnswer }}">
          <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
            Сохранить этот ответ
          </button>
        </form>
        <a href="{{ route('student.submissions.question', [$submission, $position]) }}"
           class="px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50 text-sm sm:text-base">
          Ответить снова
        </a>
      </div>
    @else
      @if($savedResult)
        @php
          $sBanner = [
            'ok'      => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'partial' => 'bg-amber-50 border-amber-200 text-amber-800',
            'fail'    => 'bg-rose-50 border-rose-200 text-rose-800',
          ][$savedResult['status']] ?? 'bg-gray-50 border-gray-200 text-gray-700';
        @endphp
        <div class="mb-4 p-3 rounded-xl border text-sm {{ $sBanner }}">
          Сохранённый ответ: {{ $savedResult['score'] }} / {{ $savedResult['max'] }} баллов. Можно ответить ещё раз.
        </div>
      @elseif($savedAnswer !== null)
        <div class="mb-4 p-3 rounded-xl border border-blue-200 bg-blue-50 text-sm text-blue-800">
          Ответ сохранён и ждёт проверки куратором. Можно изменить его ниже.
        </div>
      @endif

      @if($isManual)
        <form method="POST" action="{{ route('student.submissions.question.save', [$submission, $position]) }}">
          @csrf
          <label class="block text-xs sm:text-sm text-gray-700 mb-2">Ваш ответ</label>
          <textarea name="answer" rows="5" class="w-full border rounded-xl px-3 py-2 sm:py-3 text-sm sm:text-base">{{ old('answer', $prefill) }}</textarea>
          <div class="text-[11px] sm:text-xs text-gray-500 mt-2 mb-4">Ответ проверит ваш наставник</div>
          <button type="submit" class="mt-8 px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
            Далее
          </button>
        </form>
      @else
        @php
          $type = $task->type;
          $orderMatters = (bool) ($task->order_matters ?? ($type === 'matching' || $type === 'table'));

          $tableForPin = null;
          if ($type === 'table') {
            $tableRaw = $task->table_content ?? null;
            if (is_string($tableRaw)) {
              $decoded = json_decode($tableRaw, true);
              $tableForPin = is_array($decoded) ? $decoded : null;
            } elseif (is_array($tableRaw)) {
              $tableForPin = $tableRaw;
            }
          }

          $pinAllowed = $type === 'image_auto' ? 'alnum' : 'digits';
          $pinMax = 17;
          if ($type === 'matching' && !empty($task->matches['left']) && is_array($task->matches['left'])) {
            $pinMax = count($task->matches['left']);
          }
          if ($type === 'table' && !empty($tableForPin['blanks']) && is_array($tableForPin['blanks'])) {
            $pinMax = count($tableForPin['blanks']);
          }
        @endphp
        <form method="POST" action="{{ route('student.submissions.question.check', [$submission, $position]) }}">
          @csrf
          <label class="block text-xs sm:text-sm text-gray-700">
            Ваш ответ
            @if(in_array($type, ['test','text_with_questions','matching','table']))
              <span class="text-gray-400">(строка цифр)</span>
            @else
              <span class="text-gray-400">(цифры или буквы)</span>
            @endif
          </label>

          <input
            type="text"
            name="answer"
            class="pin-hidden-input"
            autocomplete="off"
            value="{{ old('answer', $prefill) }}"
            @if(in_array($type, ['test','text_with_questions','matching','table'])) inputmode="numeric" pattern="[0-9\s]*" @endif
          >

          <div class="pin-field mt-2" tabindex="0" data-for="answer" data-allowed="{{ $pinAllowed }}" data-max="{{ $pinMax }}">
            <div class="pin-boxes"></div>
          </div>

          <div class="text-[11px] sm:text-xs text-gray-500 mt-2 sm:mt-3">
            @if($type==='text_with_questions')
              Порядок не важен (например, 135 = 531).
            @elseif($type==='test')
              {{ $orderMatters ? 'Порядок важен.' : 'Порядок не важен (234 = 432).' }}
            @elseif($type==='matching' || $type==='table')
              Порядок важен.
            @elseif($type==='image_auto')
              {{ $orderMatters ? 'Порядок важен.' : 'Для цифр — порядок не важен (234 = 432). Для слова — точное совпадение (без учёта регистра и «ё/е»).' }}
            @endif
          </div>

          <button type="submit" class="mt-8 px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
            Проверить ответ
          </button>
        </form>
      @endif
    @endif
  </div>
</div>

@unless($isManual)
{{-- Стили и логика квадратиков-инпута для авто-проверяемых заданий --}}
<style>
.pin-field { position: relative; display:block; cursor:text; width:100%; }

.pin-boxes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
  gap: 12px;
  width: 100%;
}

.pin-box {
  aspect-ratio: 1 / 1;
  min-width: 42px;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  display:flex; align-items:center; justify-content:center;
  font-size:20px; font-weight:600; background:#fff;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.pin-box.filled { border-color:#2563eb; }
.pin-box.active { border-color:#2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25); }

@media (max-width: 640px) {
  .pin-boxes {
    grid-template-columns: repeat(auto-fill, minmax(32px, 1fr));
    gap: 10px;
  }
  .pin-box {
    min-width: 32px;
    font-size: 18px;
    border-radius: 9px;
  }
}

.pin-hidden-input { position:absolute; left:-99999px; width:1px; height:1px; opacity:0; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.pin-field').forEach((field) => {
    const name = field.getAttribute('data-for');
    const allowed = field.getAttribute('data-allowed') || 'digits';
    const maxSafe = 100;

    const selector = 'input[name="'+name.replace(/([[\]])/g,'\\$1')+'"]';
    const realInput = document.querySelector(selector);
    if (!realInput) return;

    const boxesWrap = field.querySelector('.pin-boxes');

    function oneRowCapacity() {
      const isMobile = window.matchMedia('(max-width: 640px)').matches;
      const box = isMobile ? 32 : 42;
      const gap = isMobile ? 10 : 12;
      const w = field.clientWidth || boxesWrap.clientWidth || 320;
      const fit = Math.max(4, Math.floor((w + gap) / (box + gap)));
      return Math.min(fit, maxSafe);
    }

    let boxCount = Math.max((realInput.value || '').length, oneRowCapacity());

    function buildBoxes(count) {
      const c = Math.min(count, maxSafe);
      boxesWrap.innerHTML = '';
      for (let i = 0; i < c; i++) {
        const d = document.createElement('div');
        d.className = 'pin-box';
        boxesWrap.appendChild(d);
      }
    }

    function ensureBoxesFor(len) {
      if (len > boxCount) {
        boxCount = Math.min(len, maxSafe);
        buildBoxes(boxCount);
      }
    }

    function sanitizeRaw(v) {
      v = (v || '').toString();
      if (allowed === 'digits') v = v.replace(/\D+/g, '');
      else v = v.replace(/[^0-9A-Za-zА-Яа-яЁё]+/g, '');
      return v.slice(0, maxSafe);
    }

    let hasFocus = false;
    function getCaretIndex() {
      try {
        const pos = realInput.selectionStart ?? 0;
        const len = (realInput.value || '').length;
        return Math.max(0, Math.min(pos, Math.max(len, 0), boxCount - 1));
      } catch {
        const len = (realInput.value || '').length;
        return Math.min(len, boxCount - 1);
      }
    }

    function renderBoxes(v) {
      const boxes = boxesWrap.querySelectorAll('.pin-box');
      boxes.forEach(b => b.classList.remove('active'));
      for (let i = 0; i < boxes.length; i++) {
        const ch = v[i] || '';
        boxes[i].textContent = (ch || '').toUpperCase();
        boxes[i].classList.toggle('filled', !!ch);
      }
      if (hasFocus) {
        const activeIdx = getCaretIndex();
        if (boxes[activeIdx]) boxes[activeIdx].classList.add('active');
      }
    }

    buildBoxes(boxCount);
    renderBoxes(realInput.value || '');

    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        const current = realInput.value || '';
        const cap = oneRowCapacity();
        if (current.length <= cap) {
          boxCount = cap;
          buildBoxes(boxCount);
          renderBoxes(current);
        }
      }, 150);
    });

    field.addEventListener('click', () => realInput.focus());
    realInput.addEventListener('focus', () => { hasFocus = true; renderBoxes(realInput.value || ''); });
    realInput.addEventListener('blur',  () => { hasFocus = false; renderBoxes(realInput.value || ''); });

    realInput.addEventListener('input', () => {
      const cleaned = sanitizeRaw(realInput.value);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
    });

    field.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = sanitizeRaw(text);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
      realInput.focus();
    });
  });
});
</script>
@endunless

@if(!empty($task->hint))
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('hint-toggle');
  const box = document.getElementById('hint-box');
  if (!btn || !box) return;
  btn.addEventListener('click', () => {
    box.classList.toggle('hidden');
    btn.textContent = box.classList.contains('hidden') ? 'Показать подсказку' : 'Скрыть подсказку';
  });
});
</script>
@endif
@endsection
