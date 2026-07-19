@extends('layouts.main')

@section('title', 'Новый пароль')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if ($errors->any())
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Установите новый пароль</h1>
    <p class="text-sm text-gray-600 mb-6">Придумайте надёжный пароль (не короче 8 символов).</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="token" value="{{ $token ?? request('token') }}">
      <input type="hidden" name="email" value="{{ $email ?? old('email', request('email')) }}">

      <label class="block">
        <span class="text-sm text-gray-700">Новый пароль</span>
        <input type="password" name="password" placeholder="Не короче 8 символов" required minlength="8" autocomplete="new-password"
               class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" />
      </label>

      <label class="block">
        <span class="text-sm text-gray-700">Повторите пароль</span>
        <input type="password" name="password_confirmation" placeholder="Повторите пароль" required minlength="8" autocomplete="new-password"
               class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" />
      </label>

      <div class="pt-2">
        <button class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">
          Сохранить пароль
        </button>
      </div>
    </form>

    <p class="mt-6 pt-6 border-t border-gray-200 text-sm text-gray-600 text-center">
      <a class="link-custom text-gray-900 font-medium" href="{{ route('login') }}">Вернуться ко входу</a>
    </p>
  </div>
</div>
@endsection
