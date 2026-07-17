{{-- resources/views/student/submissions/partials/question-region.blade.php
     Содержимое #wizard-app для одного вопроса. Рендерится и как часть полной
     страницы (question.blade.php), и как htmx-фрагмент (без layout). --}}
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

  // Полностью верный ответ никогда не долетает сюда как $checkResult —
  // сервер сам сохраняет и переводит на следующий вопрос (см. контроллер).
  $isLockedCorrect = $savedResult && ($savedResult['status'] ?? null) === 'ok';

  // Прогресс прохождения домашки — для полосы над навигацией по вопросам.
  $answeredCount = collect($tasks)->filter(fn ($t) => array_key_exists($t->id, $answers))->count();
  $progressPercent = $total > 0 ? round($answeredCount / $total * 100) : 0;
@endphp

<div class="max-w-3xl mx-auto px-3 sm:px-4 py-5 sm:py-6">

  <div class="flex items-center justify-between gap-3 mb-4">
    <h1 class="text-lg sm:text-xl font-medium"><span class="sans">{{ $homework->title ?? 'Домашнее задание' }}</span></h1>
    <a href="{{ route('student.submissions.finish', $submission) }}"
       hx-get="{{ route('student.submissions.finish', $submission) }}"
       hx-target="#wizard-app"
       hx-swap="innerHTML"
       hx-push-url="true"
       hx-confirm="Перейти к отправке работы? Прогресс сохранится, неотвеченные вопросы можно будет решить позже."
       class="relative inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 text-xs sm:text-sm text-gray-600 hover:bg-gray-50 whitespace-nowrap">
      <span class="btn-label">Перейти к отправке</span>
      <span class="btn-spinner">
        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
      </span>
    </a>
  </div>

  {{-- Прогресс прохождения домашки --}}
  <div class="mb-4">
    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
      <div id="progress-bar-fill" class="h-full rounded-full bg-blue-500" style="width:0%;" data-percent="{{ $progressPercent }}"></div>
    </div>
  </div>

  {{-- Навигация по вопросам --}}
  <div class="flex flex-wrap gap-2 mb-6">
    @foreach($tasks as $i => $t)
      @php $st = $statusOf($t); @endphp
      <a href="{{ route('student.submissions.question', [$submission, $i + 1]) }}"
         hx-get="{{ route('student.submissions.question', [$submission, $i + 1]) }}"
         hx-target="#wizard-app"
         hx-swap="innerHTML"
         hx-push-url="true"
         class="pill-nav-item inline-flex items-center justify-center w-9 h-9 rounded-lg border text-sm font-medium {{ $pillClasses[$st] }} {{ ($i + 1) === $position ? 'ring-2 ring-blue-500' : '' }}">
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
      <div id="hint-box" class="overflow-hidden mb-5 sm:mb-6" style="height:0;">
        <div id="hint-box-inner" class="p-3 sm:p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-900 whitespace-pre-wrap">
          {{ $task->hint }}
        </div>
      </div>
    @endif

    @include('student.submissions.partials.task-prompt', ['task' => $task])

    @if($savedResult)
      @php
        $sBanner = [
          'ok'      => 'bg-emerald-50 border-emerald-200 text-emerald-800',
          'partial' => 'bg-amber-50 border-amber-200 text-amber-800',
          'fail'    => 'bg-rose-50 border-rose-200 text-rose-800',
        ][$savedResult['status']] ?? 'bg-gray-50 border-gray-200 text-gray-700';
      @endphp
      <div class="mb-4 p-3 rounded-xl border text-sm inline-block {{ $sBanner }}">
        Сохранённый ответ: {{ $savedResult['score'] }} / {{ $savedResult['max'] }} баллов.
        {{ $isLockedCorrect ? '' : 'Можно ответить ещё раз.' }}
      </div>
    @elseif($savedAnswer !== null)
      <div class="mb-4 p-3 rounded-xl border border-blue-200 bg-blue-50 text-sm text-blue-800">
        Ответ сохранён и ждёт проверки куратором. Можно изменить его ниже.
      </div>
    @endif

    @if($isManual)
      <form method="POST" action="{{ route('student.submissions.question.save', [$submission, $position]) }}"
            hx-post="{{ route('student.submissions.question.save', [$submission, $position]) }}"
            hx-target="#wizard-app"
            hx-swap="innerHTML">
        @csrf
        <label class="block text-xs sm:text-sm text-gray-700 mb-2">Ваш ответ</label>
        <textarea name="answer" rows="5" class="w-full border rounded-xl px-3 py-2 sm:py-3 text-sm sm:text-base">{{ old('answer', $prefill) }}</textarea>
        <div class="text-[11px] sm:text-xs text-gray-500 mt-2 mb-4">Ответ проверит ваш наставник</div>
        <button type="submit" class="relative mt-8 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
          <span class="btn-label">Далее</span>
          <span class="btn-spinner">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </span>
        </button>
      </form>
    @elseif(!$isLockedCorrect)
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
      <form method="POST" action="{{ route('student.submissions.question.check', [$submission, $position]) }}"
            hx-post="{{ route('student.submissions.question.check', [$submission, $position]) }}"
            hx-target="#wizard-app"
            hx-swap="innerHTML">
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

        @php
          $pinValue = (string) old('answer', $prefill);
          $pinChars = preg_split('//u', $pinValue, -1, PREG_SPLIT_NO_EMPTY) ?: [];
          $pinInitialCount = max(count($pinChars), 6);
        @endphp
        <div class="pin-field mt-2" tabindex="0"
             data-for="answer" data-allowed="{{ $pinAllowed }}" data-max="{{ $pinMax }}">
          {{-- Квадратики сразу отрисованы сервером (не только через JS), чтобы при
               htmx-подмене #wizard-app не было вспышки «пусто -> заполнилось». --}}
          <div class="pin-boxes">
            @for ($i = 0; $i < $pinInitialCount; $i++)
              <div class="pin-box {{ isset($pinChars[$i]) ? 'filled' : '' }}">{{ isset($pinChars[$i]) ? mb_strtoupper($pinChars[$i]) : '' }}</div>
            @endfor
          </div>
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

        <button type="submit" class="relative mt-8 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
          <span class="btn-label">Проверить ответ</span>
          <span class="btn-spinner">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </span>
        </button>
      </form>
    @endif
  </div>

  {{-- Модалка с результатом неверной/частично верной проверки. Полностью верный
       ответ сервер сохраняет и сразу переводит на следующий вопрос — $checkResult
       со статусом ok сюда никогда не долетает. --}}
  @if($checkResult)
    @php
      $modalClasses = [
        'partial' => 'bg-amber-50 border-amber-200 text-amber-800',
        'fail'    => 'bg-rose-50 border-rose-200 text-rose-800',
      ][$checkResult['status']] ?? 'bg-rose-50 border-rose-200 text-rose-800';
      $modalText = [
        'partial' => 'Частично верно.',
        'fail'    => 'Неверно.',
      ][$checkResult['status']] ?? 'Неверно.';
      // Заглушки маскота по статусу — переиспользуем существующие иконки проекта,
      // реальный персонаж придёт позже (просто подменить src).
      $mascotByStatus = [
        'partial' => asset('img/person.svg'),
        'fail'    => asset('img/crying.svg'),
      ];
      $mascotSrc = $mascotByStatus[$checkResult['status']] ?? asset('img/person.svg');
    @endphp
    <div id="check-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" data-status="{{ $checkResult['status'] }}">
      <div class="check-modal-panel bg-white rounded-2xl p-5 sm:p-6 max-w-sm w-full shadow-xl">
        {{-- Заглушка под маскота — позже здесь будет персонаж, реагирующий на ответ --}}
        <div class="check-modal-mascot flex justify-center mb-3">
          <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
            <img src="{{ $mascotSrc }}" alt="" class="w-10 h-10 opacity-70">
          </div>
        </div>

        <div class="check-modal-body">
          <div class="rounded-xl border p-3 mb-5 text-center {{ $modalClasses }}">
            <div class="font-medium">{{ $modalText }} {{ $checkResult['score'] }} / {{ $checkResult['max'] }} баллов</div>
          </div>
        </div>

        <div class="check-modal-actions flex gap-3">
          <button type="button" id="check-modal-retry" class="flex-1 px-4 py-2 rounded-xl border border-gray-300 hover:bg-gray-50 text-sm sm:text-base">
            Ответить ещё раз
          </button>
          <form class="flex-1" method="POST" action="{{ route('student.submissions.question.save', [$submission, $position]) }}"
                hx-post="{{ route('student.submissions.question.save', [$submission, $position]) }}"
                hx-target="#wizard-app"
                hx-swap="innerHTML">
            @csrf
            <input type="hidden" name="answer" value="{{ $checkAnswer }}">
            <button type="submit" class="relative w-full inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
              <span class="btn-label">Следующий вопрос</span>
              <span class="btn-spinner">
                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
              </span>
            </button>
          </form>
        </div>
      </div>
    </div>

    <script>
    (function () {
      const modal = document.getElementById('check-modal');
      if (!modal) return;

      const panel   = modal.querySelector('.check-modal-panel');
      const mascot  = modal.querySelector('.check-modal-mascot');
      const body    = modal.querySelector('.check-modal-body');
      const actions = modal.querySelector('.check-modal-actions');
      const status  = modal.getAttribute('data-status');
      const gsapOk  = typeof window.gsap !== 'undefined';

      // Серия верных ответов подряд прервалась — сбрасываем счётчик (см. layouts/main.blade.php).
      if (typeof window.__resetAnswerStreak === 'function') {
        window.__resetAnswerStreak();
      }
      if (typeof window.__playSound === 'function') {
        window.__playSound('fail');
      }

      if (gsapOk) {
        gsap.set(modal, { autoAlpha: 0 });
        gsap.set(panel, { autoAlpha: 0, scale: .85, y: 20 });
        gsap.set(mascot, { autoAlpha: 0, y: -24, scale: .5, rotate: -8 });
        gsap.set([body, actions], { autoAlpha: 0, y: 10 });

        const tl = gsap.timeline();
        tl.to(modal, { autoAlpha: 1, duration: .15 })
          .to(panel, { autoAlpha: 1, scale: 1, y: 0, duration: .4, ease: 'back.out(1.8)' }, '-=0.05')
          .to(mascot, { autoAlpha: 1, y: 0, scale: 1, rotate: 0, duration: .55, ease: 'elastic.out(1, .5)' }, '-=0.25')
          .to(body, { autoAlpha: 1, y: 0, duration: .25, ease: 'power2.out' }, '-=0.2')
          .to(actions, { autoAlpha: 1, y: 0, duration: .25, ease: 'power2.out' }, '-=0.1');

        // Лёгкая тряска — только когда совсем мимо. «Частично верно» всё же похвала за часть баллов.
        if (status === 'fail') {
          tl.to(panel, { keyframes: { x: [0, -8, 8, -6, 6, -3, 3, 0] }, duration: .45, ease: 'power1.inOut' });
        }
      } else {
        // GSAP не загрузился — просто показываем модалку без анимации, а не прячем навсегда.
        modal.style.visibility = 'visible';
      }

      function close() {
        if (gsapOk) {
          gsap.to(panel, { scale: .92, y: 8, duration: .16, ease: 'power2.in' });
          gsap.to(modal, { autoAlpha: 0, duration: .18, ease: 'power2.in', onComplete: () => modal.remove() });
        } else {
          modal.remove();
        }
      }

      const retryBtn = document.getElementById('check-modal-retry');
      if (retryBtn) retryBtn.addEventListener('click', close);

      modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

      document.addEventListener('keydown', function onKey(e) {
        if (e.key === 'Escape') {
          close();
          document.removeEventListener('keydown', onKey);
        }
      });
    })();
    </script>
  @endif
</div>

@unless($isManual)
{{-- Логика квадратиков-инпута для авто-проверяемых заданий (стили — в resources/css/app.css) --}}
<script>
(function () {
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

    let boxCount = Math.max(boxesWrap.children.length, (realInput.value || '').length, oneRowCapacity());

    function buildBoxes(count) {
      const c = Math.min(count, maxSafe);
      boxesWrap.innerHTML = '';
      for (let i = 0; i < c; i++) {
        const d = document.createElement('div');
        d.className = 'pin-box';
        boxesWrap.appendChild(d);
      }
    }

    // Дополняет недостающие квадратики, не трогая уже существующие (в т.ч. отрисованные
    // сервером) — без этого при каждой пересборке был бы виден «прыжок» пустое/заполнено.
    function growBoxesTo(count) {
      const c = Math.min(count, maxSafe);
      for (let i = boxesWrap.children.length; i < c; i++) {
        const d = document.createElement('div');
        d.className = 'pin-box';
        boxesWrap.appendChild(d);
      }
    }

    function ensureBoxesFor(len) {
      if (len > boxCount) {
        boxCount = Math.min(len, maxSafe);
        growBoxesTo(boxCount);
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

    growBoxesTo(boxCount);
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

    function moveCaretToEnd() {
      const len = (realInput.value || '').length;
      try { realInput.setSelectionRange(len, len); } catch (e) {}
    }

    field.addEventListener('click', () => {
      realInput.focus();
      moveCaretToEnd();
    });
    realInput.addEventListener('focus', () => {
      hasFocus = true;
      moveCaretToEnd();
      renderBoxes(realInput.value || '');
    });
    realInput.addEventListener('blur',  () => { hasFocus = false; renderBoxes(realInput.value || ''); });

    realInput.addEventListener('input', () => {
      const cleaned = sanitizeRaw(realInput.value);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
    });

    // Enter — отправить форму (как клик по «Проверить ответ»), если поле не пустое.
    realInput.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      const form = realInput.closest('form');
      if (!form) return;
      const btn = form.querySelector('button[type="submit"]');
      if (btn && btn.disabled) return;
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });

    field.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = sanitizeRaw(text);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
      realInput.focus();
      realInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
})();
</script>
@endunless

@if(!empty($task->hint))
<script>
(function () {
  const btn = document.getElementById('hint-toggle');
  const box = document.getElementById('hint-box');
  const inner = document.getElementById('hint-box-inner');
  if (!btn || !box || !inner) return;

  const gsapOk = typeof window.gsap !== 'undefined';
  let open = false;

  if (gsapOk) gsap.set(inner, { autoAlpha: 0, y: -6 });

  btn.addEventListener('click', () => {
    open = !open;
    btn.textContent = open ? 'Скрыть подсказку' : 'Показать подсказку';

    if (!gsapOk) {
      box.style.height = open ? 'auto' : '0';
      return;
    }

    if (open) {
      gsap.to(box, { height: inner.offsetHeight, duration: .35, ease: 'power2.out' });
      gsap.to(inner, { autoAlpha: 1, y: 0, duration: .3, delay: .08, ease: 'power2.out' });
    } else {
      gsap.to(inner, { autoAlpha: 0, y: -6, duration: .15 });
      gsap.to(box, { height: 0, duration: .25, ease: 'power2.in', delay: .05 });
    }
  });
})();
</script>
@endif

<script>
(function () {
  // Кнопка отправки неактивна, пока поле ответа пустое.
  document.querySelectorAll('form').forEach((form) => {
    const field = form.querySelector('[name="answer"]');
    const btn = form.querySelector('button[type="submit"]');
    if (!field || !btn || field.type === 'hidden') return;

    function sync() {
      const empty = !field.value || !field.value.trim().length;
      btn.disabled = empty;
      btn.classList.toggle('opacity-50', empty);
      btn.classList.toggle('cursor-not-allowed', empty);
    }

    sync();
    field.addEventListener('input', sync);
  });
})();
</script>

<script>
(function () {
  const bar = document.getElementById('progress-bar-fill');
  if (!bar) return;
  const pct = parseFloat(bar.getAttribute('data-percent')) || 0;

  if (typeof window.gsap !== 'undefined') {
    gsap.to(bar, { width: pct + '%', duration: .6, ease: 'power2.out' });
  } else {
    bar.style.width = pct + '%';
  }
})();
</script>
