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
        <button class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">
          Отправить ссылку
        </button>
      </div>
    </form>

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
