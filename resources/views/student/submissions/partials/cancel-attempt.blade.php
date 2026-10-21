{{-- Кнопка «Отменить попытку» + модалка подтверждения (см. SubmissionController::cancel()).
     Подтверждение обязательно — кнопка стоит рядом с навигацией визарда, и
     случайное нажатие стёрло бы все ответы попытки. Форма внутри модалки —
     обычная, без htmx: после отмены уходим со страницы визарда целиком.
     Подключается и в визарде (htmx-фрагмент), и на странице результата.
     $buttonClass — внешний вид кнопки, если рядом с ней другие кнопки
     (на странице результата); по умолчанию — маленькая кнопка визарда. --}}
@php
  $cancelModalId = 'cancel-attempt-modal-' . $submission->id;
  $buttonClass = $buttonClass ?? 'inline-flex items-center px-3 py-1.5 rounded-lg border border-zinc-300 text-xs sm:text-sm text-rose-600 hover:bg-rose-50 whitespace-nowrap';
  $cancelText = $isMock
      ? 'Ответы этой попытки удалятся, и она не будет засчитана. Пробник можно будет начать заново.'
      : 'Ответы этой попытки удалятся. В зачёте останется прошлая попытка.';
  $fish = app(\App\Service\FishFoodService::class);
  $cancelMascot = $fish->mascotImageUrl($fish->levelFor((int) (auth()->user()->fish_total_fed ?? 0)), 'partly_correct');
@endphp

<button type="button"
        data-cancel-attempt-open="{{ $cancelModalId }}"
        class="{{ $buttonClass }}">
  Отменить попытку
</button>

<div id="{{ $cancelModalId }}" class="cancel-attempt-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4"
     role="dialog" aria-modal="true" aria-labelledby="{{ $cancelModalId }}-title">
  <div class="cancel-attempt-panel bg-white rounded-2xl p-5 sm:p-6 max-w-sm w-full shadow-xl text-left whitespace-normal">
    <div class="flex justify-center mb-3">
      <div class="w-28 h-28 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden">
        <img src="{{ $cancelMascot }}" alt="" class="w-full h-full object-contain">
      </div>
    </div>

    <div id="{{ $cancelModalId }}-title" class="sans-medium text-lg text-zinc-900 text-center mb-2">
      Вы уверены, что хотите отменить попытку?
    </div>
    <p class="text-sm text-zinc-600 text-center mb-5">{{ $cancelText }}</p>

    <div class="flex flex-col-reverse sm:flex-row gap-3">
      <button type="button" data-cancel-attempt-close
              class="flex-1 px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
        Нет, продолжить
      </button>
      <form method="POST" action="{{ route('student.submissions.cancel', $submission) }}" class="flex-1" data-cancel-attempt-form>
        @csrf
        <button type="submit"
                class="relative w-full inline-flex items-center justify-center px-4 py-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-sm sm:text-base">
          <span class="btn-label">Да, отменить</span>
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
  // Делегирование на document и один раз на страницу: визард перерисовывает
  // #wizard-app через htmx, и обработчики на самих кнопках терялись бы.
  if (window.__cancelAttemptModalBound) return;
  window.__cancelAttemptModalBound = true;

  const gsapOk = () => typeof window.gsap !== 'undefined';
  let current = null;

  function open(modal) {
    current = modal;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    const panel = modal.querySelector('.cancel-attempt-panel');
    if (gsapOk()) {
      gsap.fromTo(modal, { autoAlpha: 0 }, { autoAlpha: 1, duration: .15 });
      gsap.fromTo(panel, { autoAlpha: 0, scale: .9, y: 16 }, { autoAlpha: 1, scale: 1, y: 0, duration: .35, ease: 'back.out(1.8)' });
    }
    const safe = modal.querySelector('[data-cancel-attempt-close]');
    if (safe) safe.focus();
  }

  function close() {
    const modal = current;
    if (!modal) return;
    current = null;
    const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    if (gsapOk()) {
      gsap.to(modal.querySelector('.cancel-attempt-panel'), { scale: .94, y: 8, duration: .15, ease: 'power2.in' });
      gsap.to(modal, { autoAlpha: 0, duration: .18, ease: 'power2.in', onComplete: hide });
    } else {
      hide();
    }
  }

  document.addEventListener('click', function (e) {
    const opener = e.target.closest('[data-cancel-attempt-open]');
    if (opener) {
      const modal = document.getElementById(opener.getAttribute('data-cancel-attempt-open'));
      if (modal) open(modal);
      return;
    }
    if (!current) return;
    if (e.target.closest('[data-cancel-attempt-close]') || e.target === current) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && current) close();
  });

  // Защита от двойного нажатия «Да, отменить».
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('[data-cancel-attempt-form]');
    if (!form) return;
    const btn = form.querySelector('button[type=submit]');
    if (btn) { btn.disabled = true; btn.classList.add('htmx-request'); }
  });
})();
</script>
