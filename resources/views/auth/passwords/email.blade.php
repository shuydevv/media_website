@extends('layouts.main')

@section('title', 'Восстановление доступа')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-8">
  <div class="w-full max-w-md bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Восстановление доступа</h1>
    <p class="text-sm text-gray-600 mb-6">
      Укажите e-mail, и мы отправим ссылку для сброса пароля.
    </p>

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

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
      @csrf
      <label class="block">
        <span class="text-sm">E-mail</span>
        <input
          type="email"
          name="email"
          autocomplete="email"
          value="{{ old('email') }}"
          required
          autofocus
          class="mt-1 w-full border rounded-lg px-3 py-2"
          placeholder="you@example.com">
      </label>

      <button class="w-full rounded-lg px-4 py-3 mt-4 bg-zinc-900 text-white">
        Отправить ссылку
      </button>
    </form>

    <div class="mt-6 flex items-center justify-between text-sm">
      <a class="underline text-gray-700" href="{{ route('login') }}">Войти</a>
      @if (Route::has('register'))
        <a class="underline text-gray-700" href="{{ route('register') }}">Создать аккаунт</a>
      @endif
    </div>

    <p class="mt-6 text-xs text-gray-500">
      Не приходит письмо? Проверьте «Спам» и правильность адреса. Если что — напишите в поддержку.
    </p>
  </div>
</div>
@endsection
