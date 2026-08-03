{{-- resources/views/student/submissions/partials/question-region.blade.php
     Содержимое #wizard-app для одного вопроса. Рендерится и как часть полной
     страницы (question.blade.php), и как htmx-фрагмент (без layout). --}}
@php
  $isManual = $task->isAutoGradable() ? false : true;

  // Пробник: результат автопроверки скрыт до отправки всей работы (см.
  // SubmissionController::check() — там же основной guard). Здесь только
  // не даём этому просочиться через цвет пилюль/баннер сохранённого ответа.
  $isMock = ($homework->type ?? null) === 'mock';

  $answers = $submission->answers ?? [];
  $perTask = $submission->per_task_results ?? [];

  // Статус каждого вопроса для навигационной полоски
  $statusOf = function ($t) use ($answers, $perTask, $isMock) {
    if (!array_key_exists($t->id, $answers)) return 'unanswered';
    if (!$t->isAutoGradable() || $isMock) return 'saved';
    return $perTask[$t->id]['status'] ?? 'saved';
  };

  $pillClasses = [
    'unanswered' => 'bg-zinc-100 text-zinc-500 border-zinc-200',
    'saved'      => 'bg-blue-50 text-blue-700 border-blue-200',
    'ok'         => 'bg-emerald-50 text-emerald-700 border-emerald-300',
    'partial'    => 'bg-amber-50 text-amber-700 border-amber-300',
    'fail'       => 'bg-rose-50 text-rose-700 border-rose-300',
  ];

  // Значение, которое показать в поле ввода прямо сейчас
  $prefill = $checkAnswer ?? $savedAnswer ?? '';

  // Полностью верный ответ никогда не долетает сюда как $checkResult —
  // сервер сам сохраняет и переводит на следующий вопрос (см. контроллер).
  // В пробнике ответ можно менять сколько угодно раз до самой отправки,
  // даже если он уже был верным — поэтому здесь никогда не блокируем.
  $isLockedCorrect = !$isMock && $savedResult && ($savedResult['status'] ?? null) === 'ok';

  // Прогресс прохождения домашки — для полосы над навигацией по вопросам.
  $answeredCount = collect($tasks)->filter(fn ($t) => array_key_exists($t->id, $answers))->count();
  $progressPercent = $total > 0 ? round($answeredCount / $total * 100) : 0;

  // Нормализуем один раз: и для проверки "есть ли подсказка" (иначе строка
  // из одних пробелов/переносов считалась бы непустой и рисовала пустую
  // кнопку/плашку), и для самого рендера через white-space:pre-wrap.
  $hintText = \App\Support\Text::normalize($task->hint ?? null);
@endphp

<div class="max-w-3xl mx-auto px-3 sm:px-4 py-5 sm:py-6">

  <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
    <h1 class="sans-medium text-lg sm:text-xl text-zinc-900">{{ $homework->title ?? 'Домашнее задание' }}</h1>
    <div class="flex items-center gap-2 sm:gap-3">
      @if(!empty($expiresAt))
        @include('components.mock-timer', ['expiresAt' => $expiresAt])
      @endif
      <a href="{{ route('student.submissions.finish', $submission) }}"
         hx-get="{{ route('student.submissions.finish', $submission) }}"
         hx-target="#wizard-app"
         hx-swap="innerHTML"
         hx-push-url="true"
         hx-confirm="Перейти к отправке работы? Прогресс сохранится, неотвеченные вопросы можно будет решить позже."
         class="relative inline-flex items-center px-3 py-1.5 rounded-lg border border-zinc-300 text-xs sm:text-sm text-zinc-600 hover:bg-zinc-50 whitespace-nowrap">
        <span class="btn-label">Перейти к отправке</span>
        <span class="btn-spinner">
          <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
        </span>
      </a>
    </div>
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
        <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-zinc-100 border border-zinc-200 text-zinc-700">
          №{{ $task->number ?? '—' }} в ЕГЭ
        </span>
        <span class="sans-medium text-lg text-zinc-900">Вопрос {{ $position }} из {{ $total }}</span>
      </div>

      @if($hintText)
        <button type="button" id="hint-toggle" class="text-xs sm:text-sm text-blue-600 hover:underline whitespace-nowrap">
          Показать подсказку
        </button>
      @endif
    </div>

    @if($hintText)
      {{-- Отступ после плашки с подсказкой — отдельный пустой спейсер ВНУТРИ
           этого же схлопывающегося контейнера (а не margin/padding на самой
           плашке): padding внутри плашки просто увеличивает синий фон, а не
           создаёт зазор ПОСЛЕ неё; margin на внешнем блоке не обрезается
           overflow:hidden и остаётся даже когда блок закрыт (height:0) — из-за
           этого отступ над текстом вопроса раньше был то с подсказкой, то без.
           Спейсер же — часть содержимого, которое подрезается вместе со всем
           остальным при сворачивании. Высоту при открытии JS теперь считает
           по box.scrollHeight (весь контент контейнера), а не по одному
           дочернему элементу — иначе спейсер не попадал бы в расчёт. --}}
      <div id="hint-box" class="overflow-hidden" style="height:0;">
        <div id="hint-box-inner" class="p-4 sm:p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-900 whitespace-pre-wrap">{{$hintText}}</div>
        <div class="h-5 sm:h-6" aria-hidden="true"></div>
      </div>
    @endif

    @include('student.submissions.partials.task-prompt', ['task' => $task])

    @if($savedResult && $isMock)
      {{-- Пробник: ответ сохранён, но результат проверки не показываем до отправки работы. --}}
      <div class="mb-4 p-3 rounded-xl border border-blue-200 bg-blue-50 text-sm text-blue-800 inline-block">
        Ответ сохранён. Можно изменить его ниже.
      </div>
    @elseif($savedResult)
      @php
        $sBanner = [
          'ok'      => 'bg-emerald-50 border-emerald-200 text-emerald-800',
          'partial' => 'bg-amber-50 border-amber-200 text-amber-800',
          'fail'    => 'bg-rose-50 border-rose-200 text-rose-800',
        ][$savedResult['status']] ?? 'bg-zinc-50 border-zinc-200 text-zinc-700';
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
        <label class="block text-xs sm:text-sm text-zinc-700 mb-2">Ваш ответ</label>
        <textarea name="answer" rows="5" class="w-full border rounded-xl px-3 py-2 sm:py-3 text-sm sm:text-base">{{ old('answer', $prefill) }}</textarea>
        <div class="text-[11px] sm:text-xs text-zinc-500 mt-2 mb-4">Ответ проверит ваш наставник</div>
        <x-ui.button type="submit" variant="accent" class="relative mt-8 text-sm sm:text-base">
          <span class="btn-label">Далее</span>
          <span class="btn-spinner">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </span>
        </x-ui.button>
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
        <label class="block text-xs sm:text-sm text-zinc-700">
          Ваш ответ
          @if(in_array($type, ['test','text_with_questions','matching','table']))
            <span class="text-zinc-400">(строка цифр)</span>
          @else
            <span class="text-zinc-400">(цифры или буквы)</span>
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

        <div class="text-[11px] sm:text-xs text-zinc-500 mt-2 sm:mt-3">
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

        <x-ui.button type="submit" variant="accent" class="relative mt-8 text-sm sm:text-base">
          <span class="btn-label">{{ $isMock ? 'Следующий вопрос' : 'Проверить ответ' }}</span>
          <span class="btn-spinner">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </span>
        </x-ui.button>
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
      // Маскот текущего уровня ученика с эмоцией под статус ответа.
      $mascotStateByStatus = [
        'partial' => 'partly_correct',
        'fail'    => 'wrong',
      ];
      $mascotState = $mascotStateByStatus[$checkResult['status']] ?? 'wrong';
      $mascotSrc = app(\App\Service\FishFoodService::class)->mascotImageUrl($fishLevel, $mascotState);
    @endphp
    <div id="check-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" data-status="{{ $checkResult['status'] }}">
      <div class="check-modal-panel bg-white rounded-2xl p-5 sm:p-6 max-w-sm w-full shadow-xl">
        <div class="check-modal-mascot flex justify-center mb-3">
          <div class="w-36 h-36 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden">
            <img src="{{ $mascotSrc }}" alt="" class="w-full h-full object-contain">
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
      const gsapOk  = typeof window.gsap !== 'undefined';

      // Серия верных ответов подряд прервалась — сбрасываем счётчик (см. layouts/main.blade.php).
      if (typeof window.__resetAnswerStreak === 'function') {
        window.__resetAnswerStreak();
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
  @include('components.pin-field-script')
@endunless

@if($hintText)
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
      // scrollHeight всего контейнера, а не offsetHeight одной только плашки —
      // иначе спейсер после неё не попадал бы в расчёт высоты открытия.
      gsap.to(box, { height: box.scrollHeight, duration: .35, ease: 'power2.out' });
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
