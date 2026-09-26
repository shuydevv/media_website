@extends('layouts.main')

@section('title', 'Восстановление доступа')

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
    <h1 class="text-2xl font-semibold sans mb-2">Восстановление доступа</h1>
    <p class="text-sm text-gray-600 mb-6">
      Укажите e-mail, и мы отправим ссылку для сброса пароля.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
      @csrf
      <label class="block">
        <span class="text-sm text-gray-700">E-mail</span>
        <input
          type="email"
          name="email"
          autocomplete="email"
          value="{{ old('email') }}"
          required
          autofocus
          class="mt-1 w-full border rounded-lg px-3 py-2 input-focus"
          placeholder="you@example.com">
      </label>

      <div class="pt-2">
        <button id="reset-submit" class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
          {{ session('status') ? 'Отправить ещё раз' : 'Отправить ссылку' }}
        </button>
      </div>
    </form>

    <script>
      (function () {
        var form = document.currentScript.previousElementSibling;
        var btn = document.getElementById('reset-submit');
        var label = btn.textContent.trim();
        var left = {{ (int) session('resend_in', 0) }};

        function tick() {
          if (left <= 0) { btn.disabled = false; btn.textContent = label; return; }
          btn.disabled = true;
          btn.textContent = 'Отправить ещё раз через ' + left + ' сек.';
          left--;
          setTimeout(tick, 1000);
        }
        tick();

        // письмо отправляется синхронно и может занять несколько секунд —
        // блокируем кнопку, чтобы не было двойных отправок
        form.addEventListener('submit', function () {
          btn.disabled = true;
          btn.textContent = 'Отправляем…';
        });
      })();
    </script>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
      <p class="text-sm text-gray-600">
        Вспомнили пароль? <a class="link-custom text-gray-900 font-medium" href="{{ route('login') }}">Войти</a>
      </p>
      <p class="mt-3 text-xs text-gray-500">
        Не приходит письмо? Проверьте «Спам» и правильность адреса. Если что — напишите в поддержку.
      </p>
    </div>
  </div>
</div>
@endsection
