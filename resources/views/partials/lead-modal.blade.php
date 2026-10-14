{{--
    Модалка «Записаться»: имя + логин Telegram или телефон → SiteLeadController →
    тема «📝 Заявки с сайта» в админ-группе бота. Открывается любым
    элементом с data-open-form="<ключ кнопки>" и data-label="<что за кнопка>" —
    оба уходят в заявку, чтобы было видно, какая кнопка сработала.
    Подключается один раз на странице (сейчас — пробные уроки). На
    /repetitor свои формы и своя модалка (LeadController) — сюда не подключать,
    два обработчика data-open-form открывали бы обе.
--}}
<div id="lead-modal" class="fixed inset-0 z-[9998] hidden" role="dialog" aria-modal="true" aria-labelledby="lead-modal-title" aria-hidden="true">
  <div id="lead-overlay" class="fixed inset-0 z-[9998] bg-black opacity-0 transition-opacity duration-200 pointer-events-none"></div>

  <div class="relative z-[9999] w-full h-full flex items-center justify-center p-4">
    <div data-card class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 opacity-0 translate-y-4 transition duration-200">
      <button type="button" class="absolute -top-3 -right-3 bg-white shadow-md border rounded-full w-9 h-9 grid place-items-center hover:scale-105 transition"
              aria-label="Закрыть" data-close>✕</button>

      <form action="{{ route('site-lead.store') }}" method="POST" class="p-6 space-y-4" novalidate>
        @csrf
        <p id="lead-modal-title" class="text-gray-900 font-semibold text-lg">
          Оставь контакт — я напишу тебе в Telegram и расскажу про курс
        </p>

        <input type="hidden" name="cta" value="{{ old('cta') }}">
        <input type="hidden" name="cta_label" value="{{ old('cta_label') }}">
        <input type="hidden" name="page" value="{{ old('page') }}">
        {{-- Ловушка для спам-ботов: людям не видна (см. SiteLeadController). --}}
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true"
               style="position:absolute;left:-10000px;width:1px;height:1px;opacity:0">

        <div>
          <input name="name" type="text" placeholder="Имя" value="{{ old('name') }}" required maxlength="100" autocomplete="given-name"
                 class="w-full bg-purple-50 border {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }} rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
          @error('name')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <input name="telegram" type="text" placeholder="@username в Telegram или телефон" value="{{ old('telegram') }}" required maxlength="100"
                 autocomplete="off" autocapitalize="off" spellcheck="false"
                 class="w-full bg-purple-50 border {{ $errors->has('telegram') ? 'border-red-400' : 'border-gray-300' }} rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-purple-300">
          @error('telegram')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
          @enderror
          {{-- Показывается, когда в поле набирают номер (см. скрипт ниже). --}}
          <p data-phone-warning class="text-xs text-yellow-600 mt-1 hidden">Если у вас скрыт номер телефона, указывайте свой @username</p>
        </div>

        <button type="submit" class="w-full bg-pink-500 text-white font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.99]">
          Записаться
        </button>

        <p class="text-xs text-gray-500">
          Нажимая кнопку, вы принимаете положение об обработке персональных данных
        </p>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('lead-modal');
  if (!modal) return;
  var overlay = document.getElementById('lead-overlay');
  var card = modal.querySelector('[data-card]');
  var form = modal.querySelector('form');
  var inputCta = form.querySelector('input[name="cta"]');
  var inputLabel = form.querySelector('input[name="cta_label"]');
  var inputPage = form.querySelector('input[name="page"]');
  var lastTrigger = null;

  if (!inputPage.value) inputPage.value = location.href;

  // Набирают номер, а не логин — предупреждаем: по скрытому номеру
  // в Telegram не найти.
  var contactInput = form.querySelector('input[name="telegram"]');
  var phoneWarning = form.querySelector('[data-phone-warning]');
  function togglePhoneWarning() {
    var v = contactInput.value.trim();
    phoneWarning.classList.toggle('hidden', !/^\+?[\d\s()\-]{3,}$/.test(v));
  }
  contactInput.addEventListener('input', togglePhoneWarning);
  togglePhoneWarning();

  function open(cta, label, trigger) {
    lastTrigger = trigger || null;
    if (cta !== null) inputCta.value = cta || '';
    if (label !== null) inputLabel.value = (label || '').trim();

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    requestAnimationFrame(function () {
      overlay.classList.remove('opacity-0', 'pointer-events-none');
      overlay.classList.add('opacity-70');
      card.classList.remove('opacity-0', 'translate-y-4');
    });

    var first = form.querySelector('.border-red-400') || form.querySelector('input[name="name"]');
    if (first) first.focus();
  }

  function close() {
    overlay.classList.add('opacity-0');
    overlay.classList.remove('opacity-70');
    card.classList.add('opacity-0', 'translate-y-4');
    setTimeout(function () {
      overlay.classList.add('pointer-events-none');
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
      document.documentElement.style.overflow = '';
      if (lastTrigger) { try { lastTrigger.focus(); } catch (e) {} }
    }, 150);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-open-form]');
    if (!btn) return;
    e.preventDefault();
    open(btn.getAttribute('data-open-form'), btn.getAttribute('data-label') || btn.textContent, btn);
  });

  modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
  overlay.addEventListener('click', close);
  modal.addEventListener('click', function (e) { if (!card.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.getAttribute('aria-hidden') !== 'true') close();
  });

  // Сервер вернул ошибку (например, логин написан неверно) — сразу
  // открываем форму снова, с введёнными данными и текстом ошибки.
  @if ($errors->has('name') || $errors->has('telegram'))
    open(null, null, null);
  @endif
})();
</script>
