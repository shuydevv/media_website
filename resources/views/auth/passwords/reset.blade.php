@extends('layouts.main')

@section('title', 'Новый пароль')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-10">
  <div class="w-full max-w-md bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Установите новый пароль</h1>
    <p class="text-sm text-gray-600 mb-6">Придумайте надёжный пароль (не короче 8 символов).</p>

    @if ($errors->any())
      <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="token" value="{{ $token ?? request('token') }}">

      <label class="block">
        <span class="text-sm">E-mail</span>
        <input type="email" name="email"
               value="{{ $email ?? old('email', request('email')) }}"
               required autocomplete="email"
               class="mt-1 w-full border rounded-lg px-3 py-2" />
      </label>

      <label class="block">
        <span class="text-sm">Новый пароль</span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password"
               class="mt-1 w-full border rounded-lg px-3 py-2" />
      </label>

      <label class="block">
        <span class="text-sm">Повторите пароль</span>
        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
               class="mt-1 w-full border rounded-lg px-3 py-2" />
      </label>

      <button class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white">
        Сохранить пароль
      </button>
    </form>

    <div class="mt-6 text-sm">
      <a class="underline text-gray-700" href="{{ route('login') }}">Вернуться ко входу</a>
    </div>
  </div>
</div>
@endsection
