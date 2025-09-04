@extends('layouts.main')

@section('content')
<div class="max-w-5xl mx-auto px-3 sm:px-4 py-5 sm:py-6">
  <h1 class="text-xl font-sans sm:text-2xl font-medium mb-5 sm:mb-6">
    Сдать домашку: {{ $homework->title ?? 'Домашнее задание' }}
  </h1>

  {{-- флеши/ошибки --}}
  @if(session('success'))
    <div class="mb-5 sm:mb-6 rounded-xl border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm sm:text-base">
      {{ session('success') }}
    </div>
  @endif
  @if ($errors->any())
    <div class="mb-5 sm:mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc list-inside">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('student.submissions.store', $homework) }}" method="post" enctype="multipart/form-data" class="space-y-5 sm:space-y-6">
    @csrf

    @php
      $tasksRaw = $homework->tasks ?? [];
      $tasks = collect($tasksRaw)->map(function($t) {
        $o = is_array($t) ? (object)$t : $t;
        if (!empty($o->type)) {
          if ($o->type === 'multiple_choice') $o->type = 'test';
          if ($o->type === 'text_based')      $o->type = 'text_with_questions';
          if ($o->type === 'image_written')   $o->type = 'image_manual';
        }
        return $o;
      });

      $storageUrl = function ($path) {
        if (!$path) return null;
        $isFull = \Illuminate\Support\Str::startsWith($path, ['http://','https://','/storage/','data:']);
        return $isFull ? $path : \Illuminate\Support\Facades\Storage::url($path);
      };
    @endphp

    @forelse($tasks as $idx => $task)
      @php
        $type          = $task->type ?? 'unknown';
        $title         = $task->title ?? ('Задание #'.(($task->id ?? $idx)));
        $questionText  = $task->question_text ?? null;
        $passageText   = $task->passage_text ?? null;

        $mediaPath     = $task->image_path ?? $task->image_path ?? null;
        $mediaUrl      = $storageUrl($mediaPath);

        $taskId        = $task->id ?? ('new_'.$idx);

        $rawOptions = $task->options ?? $task->image_auto_options ?? null;
        $options = [];
        if (is_array($rawOptions)) {
          $options = array_values(array_filter(array_map('trim', $rawOptions), fn($v) => $v !== '' && $v !== null));
        } elseif (is_string($rawOptions)) {
          $decoded = json_decode($rawOptions, true);
          if (is_array($decoded)) {
            $options = array_values(array_filter(array_map('trim', $decoded), fn($v) => $v !== '' && $v !== null));
          } else {
            $lines = preg_split("/\r\n|\r|\n/", $rawOptions);
            $options = array_values(array_filter(array_map('trim', $lines), fn($v) => $v !== '' && $v !== null));
          }
        }

        $orderMatters  = (bool)($task->order_matters ?? ($type==='matching' || $type==='table'));

        $table = $task->table_content ?? null;

        $pinAllowed = in_array($type, ['image_auto']) ? 'alnum' : 'digits';
        $pinMax     = 10;
        if ($type === 'matching' && !empty($task->matches['left']) && is_array($task->matches['left'])) {
            $pinMax = count($task->matches['left']);
        }
        if ($type === 'table' && !empty($table['blanks']) && is_array($table['blanks'])) {
            $pinMax = count($table['blanks']);
        }
      @endphp

      <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
        <div class="flex items-center gap-3 sm:gap-4 mb-4 sm:mb-5">
          <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-gray-100 border border-gray-200 text-gray-700">
            №{{ $task->task->number }} в ЕГЭ
          </span>
          <span class="text-base sm:text-lg font-semibold text-gray-900">
            Задание №{{ $idx+1 }}
          </span>
        </div>

        {{-- Текстовый фрагмент для text_with_questions — ПЕРВЫМ --}}
        @if($type === 'text_with_questions' && $passageText)
          @php
            $raw = (string)$passageText;
            $raw = preg_replace('/^\xEF\xBB\xBF/u', '', $raw);
            $raw = str_replace(["\r\n","\r"], "\n", $raw);
            $raw = str_replace("\xC2\xA0", ' ', $raw);
            $lines = preg_split('/\n/u', $raw);
            while ($lines && trim($lines[0]) === '') array_shift($lines);
            while ($lines && trim(end($lines)) === '') array_pop($lines);
            $i = 0; while ($i < count($lines) && trim($lines[$i]) === '') $i++; if ($i < count($lines)) $lines[$i] = ltrim($lines[$i]);
            $paragraphs = array_values(array_filter($lines, fn($s) => trim($s) !== ''));
          @endphp

          <div class="mt-1 mb-5 sm:mb-6 p-4 sm:p-5 rounded-xl bg-gray-50 border border-gray-200 leading-relaxed text-sm sm:text-base">
            @foreach($paragraphs as $p)
              <p class="mb-3 sm:mb-4 last:mb-0">{{ $p }}</p>
            @endforeach
          </div>
        @endif

        {{-- ВОПРОС / ТЕКСТ --}}
        @if($questionText)
          <div class="text-sm md:text-base text-gray-800 mt-1 whitespace-pre-wrap mb-5 sm:mb-6">{{ $questionText }}</div>
        @endif

        {{-- Пассаж для развёрнутого ответа --}}
        @if($task->type === 'written' && $task->passage_text)
          @php
            $raw2 = (string)$task->passage_text;
            $raw2 = preg_replace('/^\xEF\xBB\xBF/u', '', $raw2);
            $raw2 = str_replace(["\r\n","\r"], "\n", $raw2);
            $raw2 = str_replace("\xC2\xA0", ' ', $raw2);
            $l2 = preg_split('/\n/u', $raw2);
            while ($l2 && trim($l2[0]) === '') array_shift($l2);
            while ($l2 && trim(end($l2)) === '') array_pop($l2);
            $j = 0; while ($j < count($l2) && trim($l2[$j]) === '') $j++; if ($j < count($l2)) $l2[$j] = ltrim($l2[$j]);
            $paragraphs2 = array_values(array_filter($l2, fn($s) => trim($s) !== ''));
          @endphp

          <div class="mb-5 sm:mb-6 p-4 sm:p-5 bg-gray-50 border rounded-xl leading-relaxed text-sm sm:text-base">
            @foreach($paragraphs2 as $p)
              <p class="mb-3 sm:mb-4 last:mb-0">{{ $p }}</p>
            @endforeach
          </div>
        @endif

        {{-- table --}}
@if($type === 'table')
  @php
    // Безопасно распакуем table_content (мог быть строкой)
    $tableRaw = $task->table_content ?? null;
    if (is_string($tableRaw)) {
      $decoded = json_decode($tableRaw, true);
      $table = is_array($decoded) ? $decoded : [];
    } elseif (is_array($tableRaw)) {
      $table = $tableRaw;
    } else {
      $table = [];
    }

    $cols   = is_array($table['cols'] ?? null) ? $table['cols'] : [];
    $rows   = is_array($table['rows'] ?? null) ? $table['rows'] : [];

    // Back-compat: если колонки не заданы, возьмём ширину по первой строке
    if (empty($cols) && !empty($rows) && is_array($rows[0] ?? null)) {
      $cols = array_map(fn($i) => 'Колонка '.($i+1), range(0, count($rows[0])-1));
    }

    // (Опционально, если когда-то были "blanks")
    $blanks = is_array($table['blanks'] ?? null) ? $table['blanks'] : [];
    $blankMap = [];
    foreach ($blanks as $b) {
      if (isset($b['r'], $b['c'])) $blankMap[$b['r'].'_'.$b['c']] = $b['key'] ?? '';
    }
  @endphp

  <div class="overflow-auto rounded-xl border border-gray-100 mt-1 mb-5 sm:mb-6">
    <table class="min-w-full border-collapse">
      @if(!empty($cols))
        <thead class="bg-gray-50">
          <tr>
            @foreach($cols as $c)
              <th class="border border-gray-200 px-3 py-2 sm:py-3 text-left text-xs sm:text-sm font-semibold text-gray-700">{{ $c }}</th>
            @endforeach
          </tr>
        </thead>
      @endif
      <tbody>
        @forelse($rows as $rIdx => $row)
          <tr class="odd:bg-white ">
            @foreach((array)$row as $cIdx => $cell)
              @php
                $k = $rIdx.'_'.$cIdx;
                $isBlank = array_key_exists($k, $blankMap);
                $badge = $isBlank ? ($blankMap[$k] ?: '') : '';
              @endphp
              <td class="px-3 py-2 sm:py-3 align-top border border-gray-200">
                @if($isBlank)
                  <div class="inline-flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-amber-50 border border-amber-200 text-amber-700 text-[10px] sm:text-xs font-semibold">{{ $badge }}</span>
                    <span class="text-gray-500 text-xs sm:text-sm">— заполнить</span>
                  </div>
                @else
                  <div class="text-sm sm:text-[15px] text-gray-800 whitespace-pre-wrap">{{ (string)$cell }}</div>
                @endif
              </td>
            @endforeach
          </tr>
        @empty
          <tr>
            <td class="px-3 py-3 text-xs sm:text-sm text-gray-500">Таблица не задана</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif


        @if(in_array($type, ['image_auto','image_manual']) && $mediaUrl)
          <div class="mt-1 mb-5 sm:mb-6">
            <img src="{{ $mediaUrl }}" alt="" class="w-full max-h-[360px] sm:max-h-[380px] object-contain rounded-xl border">
          </div>
        @endif

        @if(!empty($options))
          <div class="mt-1 mb-5 sm:mb-6 text-gray-900 text-sm sm:text-base flex flex-col flex-wrap gap-2 sm:gap-3 items-start">
            @foreach($options as $opt)
              <div class="px-2 sm:px-3 py-0.5 sm:py-1 rounded-lg border border-gray-200 bg-gray-50">{{ $opt }}</div>
            @endforeach
          </div>
        @endif

        {{-- matching --}}
        @if($type === 'matching')
          @php
            $left  = [];
            $right = [];
            if (!empty($task->matches['left'])) {
              $left = is_array($task->matches['left'])
                ? $task->matches['left']
                : preg_split("/\r\n|\r|\n/", (string)$task->matches['left']);
            }
            if (!empty($task->matches['right'])) {
              $right = is_array($task->matches['right'])
                ? $task->matches['right']
                : preg_split("/\r\n|\r|\n/", (string)$task->matches['right']);
            }
            $letters = ['А','Б','В','Г','Д','Е','Ж','З','И','К','Л','М'];
          @endphp
          <div class="grid md:grid-cols-2 gap-4 sm:gap-6 mt-1 mb-5 sm:mb-6">
            <div class="rounded-xl border bg-white">
              <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-gray-700">{{ $task->left_title ?? 'Левая колонка' }}</div>
              <div class="divide-y">
                @forelse($left as $iL => $val)
                  <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
                    <span class="text-gray-500 mr-2">{{ $letters[$iL] ?? ($iL+1) }}.</span> {{ $val }}
                  </div>
                @empty
                  <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-gray-500">Нет элементов</div>
                @endforelse
              </div>
            </div>
            <div class="rounded-xl border bg-white">
              <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-gray-700">{{ $task->right_title ?? 'Правая колонка' }}</div>
              <div class="divide-y">
                @forelse($right as $iR => $val)
                  <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
                    <span class="text-gray-500 mr-2">{{ $iR+1 }}.</span> {{ $val }}
                  </div>
                @empty
                  <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-gray-500">Нет элементов</div>
                @endforelse
              </div>
            </div>
          </div>
        @endif



        {{-- Поле ответа (PIN-style) --}}
        @if(in_array($type, ['test','text_with_questions','matching','image_auto','table']))
          <div class="mt-1">
            <label class="block text-xs sm:text-sm text-gray-700 ">
              Ваш ответ
              @if(in_array($type, ['test','text_with_questions','matching','table']))
                <span class="text-gray-400">(строка цифр)</span>
              @else
                <span class="text-gray-400">(цифры или буквы)</span>
              @endif
            </label>

            <input
              type="text"
              name="answers[{{ $taskId }}]"
              class="pin-hidden-input"
              autocomplete="off"
              @if(in_array($type, ['test','text_with_questions','matching','table'])) inputmode="numeric" pattern="[0-9\s]*" @endif
            >

            <div class="pin-field mt-2" tabindex="0"
                 data-for="answers[{{ $taskId }}]"
                 data-allowed="{{ in_array($type, ['image_auto']) ? 'alnum' : 'digits' }}"
                 data-max="{{ ( $type==='matching' && !empty($task->matches['left']) && is_array($task->matches['left']) ) ? count($task->matches['left']) : ( $type==='table' && !empty($table['blanks']) && is_array($table['blanks']) ? count($table['blanks']) : 17 ) }}">
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
          </div>
        @endif

        {{-- Ручная проверка --}}
        @if(in_array($type, ['written','image_manual']))
          <div class="mt-1">
            <label class="block text-xs sm:text-sm text-gray-700 mb-2">Ваш ответ</label>
            <textarea name="answers[{{ $taskId }}]" rows="4" class="w-full border rounded-xl px-3 py-2 sm:py-3 text-sm sm:text-base" placeholder="Введите развернутый ответ"></textarea>
            <div class="text-[11px] sm:text-xs text-gray-500 mt-2">Ответ проверит преподаватель или его помощник.</div>
          </div>
        @endif
      </div>
    @empty
      <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 text-sm text-gray-600">
        Заданий нет.
      </div>
    @endforelse

    <div class="pt-3 sm:pt-4">
      <button type="submit" class="inline-flex items-center px-4 sm:px-5 py-2.5 sm:py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
        Отправить
      </button>
    </div>
  </form>
</div>

{{-- Стили квадратиков --}}
<style>
.pin-field { position: relative; display:block; cursor:text; width:100%; }

/* Грид коробочек: desktop */
.pin-boxes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
  gap: 12px;
  width: 100%;
}

/* Квадраты: desktop */
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

/* Мобильные правки: уменьшаем размер и плотность */
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

/* реальный инпут прячем */
.pin-hidden-input { position:absolute; left:-99999px; width:1px; height:1px; opacity:0; }

textarea[name^="answers"] {
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
  outline: none;
}
textarea[name^="answers"]:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}
</style>

{{-- Логика квадратиков (адаптив: одна строка по умолчанию, авто-расширение при вводе) --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.pin-field').forEach((field) => {
    const name = field.getAttribute('data-for');
    const allowed = field.getAttribute('data-allowed') || 'digits';
    const minCountAttr = parseInt(field.getAttribute('data-max') || '10', 10); // верхняя граница по смыслу задачи
    const maxSafe = 100;

    const selector = 'input[name="'+name.replace(/([[\]])/g,'\\$1')+'"]';
    const realInput = document.querySelector(selector);
    if (!realInput) return;

    const boxesWrap = field.querySelector('.pin-boxes');

    // расчёт вместимости "в одну строку" с учётом мобильной/десктопной сетки
    function oneRowCapacity() {
      const isMobile = window.matchMedia('(max-width: 640px)').matches;
      const box = isMobile ? 32 : 42;  // px
      const gap = isMobile ? 10 : 12;  // px
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
      // не обрезаем под minCountAttr — пусть пользователь вводит сколько нужно (до maxSafe)
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

    // Инициализация
    buildBoxes(boxCount);
    renderBoxes(realInput.value || '');

    // Пересчитать вместимость при ресайзе — если строка не переполнена, держим одну строку
    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        const current = realInput.value || '';
        const cap = oneRowCapacity();
        // если текущая длина не превышает новую вместимость — перестроим под одну строку
        if (current.length <= cap) {
          boxCount = cap;
          buildBoxes(boxCount);
          renderBoxes(current);
        }
      }, 150);
    });

    // Поведение
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

{{-- Тихий автосейв черновика ответов в localStorage (твой рабочий вариант — без изменений) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form[action]') || document.querySelector('form');
  if (!form) return;

  const cssEscape = (window.CSS && CSS.escape) ? CSS.escape : function (s) {
    return String(s).replace(/[^a-zA-Z0-9_\-\u00A0-\uFFFF]/g, '\\$&');
  };

  const DRAFT_KEY = (() => {
    try {
      const hw  = @json($homework->id ?? 'unknown');
      const uid = @json(auth()->id() ?? 'guest');
      return `egestore:draft:homework:${hw}:user:${uid}`;
    } catch (e) {
      return 'egestore:draft:homework:unknown:user:guest';
    }
  })();

  function getAnswerFields() {
    return Array.from(form.querySelectorAll('[name^="answers["]'));
  }

  function collectAnswers() {
    const data = {};
    getAnswerFields().forEach(el => {
      const m = el.name.match(/^answers\[(.+?)\]$/);
      if (!m) return;
      data[m[1]] = el.value || '';
    });
    return data;
  }

  let saveTimer = null;
  function debounce(fn, ms) {
    return (...args) => {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(() => fn(...args), ms);
    };
  }

  function saveDraft() {
    try {
      const payload = {
        version: 1,
        homework_id: @json($homework->id ?? null),
        updated_at: new Date().toISOString(),
        answers: collectAnswers(),
      };
      localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
    } catch (e) { /* тихо */ }
  }
  const scheduleSave = debounce(saveDraft, 600);

  function loadDraft() {
    try {
      const raw = localStorage.getItem(DRAFT_KEY);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed.answers !== 'object') return;

      const hasAnyValue = getAnswerFields().some(el => (el.value || '').length > 0);
      if (hasAnyValue) return;

      Object.entries(parsed.answers).forEach(([key, val]) => {
        const el = form.querySelector(`[name="answers[${cssEscape(key)}]"]`);
        if (!el) return;
        el.value = val || '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
      });
    } catch (e) { /* тихо */ }
  }

  setTimeout(loadDraft, 0);

  form.addEventListener('input', (e) => {
    const t = e.target;
    if (t && t.name && t.name.startsWith('answers[')) scheduleSave();
  });
  form.addEventListener('change', (e) => {
    const t = e.target;
    if (t && t.name && t.name.startsWith('answers[')) scheduleSave();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') saveDraft();
  });
  window.addEventListener('beforeunload', saveDraft);

  form.addEventListener('submit', () => {
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
  });
});
</script>
@endsection
