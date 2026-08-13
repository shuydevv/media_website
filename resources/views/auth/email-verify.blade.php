@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if (session('status'))
    <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
      {{ session('status') }}
    </div>
  @endif
  @if ($errors->any())
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Введите код из Email</h1>
    <p class="text-sm text-gray-600 mb-6">Код отправлен на {{ $masked }}</p>

    {{-- Пока письмо ещё в очереди (см. /auth/email/status), сюда JS подставит
         предупреждение, если доставка окончательно провалилась. --}}
    <div id="email-delivery-warning" class="hidden mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
      Код не отправлен, сообщите в поддержку, что есть проблема.
    </div>

    <form method="post" action="{{ route('auth.email.verify') }}" class="space-y-4" id="email-verify-form">
      @csrf
      <input name="code" type="text" inputmode="numeric" pattern="\d*" maxlength="6"
             class="w-full border rounded-lg px-3 py-2 tracking-widest text-center text-xl input-focus"
             placeholder="••••••" required>
      <div class="pt-2">
        <button type="submit" class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">Продолжить</button>
      </div>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
      <form method="post" action="{{ route('auth.email.resend') }}" id="email-resend-form">
        @csrf
        <button class="link-custom text-sm text-blue-700" type="submit">Код не пришел? Отправить снова</button>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  // Двойной тап — та же защита, что на форме email (см. auth/email.blade.php).
  document.querySelectorAll('#email-verify-form, #email-resend-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const btn = form.querySelector('button[type="submit"]');
      if (btn.disabled) { e.preventDefault(); return; }
      btn.disabled = true;
      btn.textContent = form.id === 'email-resend-form' ? 'Отправка…' : 'Проверяем…';
    });
  });

  // Письмо уходит через очередь — в момент показа этой страницы ещё не известно,
  // дошло ли оно. Опрашиваем статус, пока не увидим 'sent'/'failed' или не истечёт
  // разумное время ожидания — тогда тоже считаем это проблемой с доставкой, а не
  // молчим (см. VerifyEmailWithCode::failed() и слушатель NotificationSent).
  const warning = document.getElementById('email-delivery-warning');
  const POLL_MS = 2000;
  const MAX_POLLS = 10; // ~20 сек
  let polls = 0;

  function poll() {
    fetch('{{ route('auth.email.status') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (!data) return;
        if (data.status === 'sent') return; // тихо останавливаем опрос
        if (data.status === 'failed' || ++polls >= MAX_POLLS) {
          warning.classList.remove('hidden');
          return;
        }
        setTimeout(poll, POLL_MS);
      })
      .catch(() => {}); // сетевая ошибка самого опроса — не повод пугать пользователя
  }
  poll();
})();
</script>
@endsection
