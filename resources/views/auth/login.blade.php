@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if (request('expired'))
    <div class="mb-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
      {{ __('Превышен лимит устройств, с которых совершен вход в аккаунт. Войдите снова.') }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">{{ __('Вход в аккаунт') }}</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
      @csrf

      <label class="block">
        <span class="text-sm text-gray-700">{{ __('Логин (Email)') }}</span>
        <input id="email" type="email" placeholder="mail@mail.com" class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        @error('email')
          <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
      </label>

      <label class="block">
        <span class="text-sm text-gray-700">{{ __('Пароль') }}</span>
        <input id="password" type="password" placeholder="Введите пароль" class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" name="password" required autocomplete="current-password">
        @error('password')
          <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
        @enderror
      </label>

      <div class="flex items-center gap-2">
        <input class="checkbox-custom" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
        <label class="text-sm text-gray-700 cursor-pointer" for="remember">
          {{ __('Запомнить меня') }}
        </label>
      </div>

      <div class="pt-2">
        <button type="submit" class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">
          {{ __('Войти') }}
        </button>
      </div>

      @if (Route::has('password.request'))
        <div class="text-center text-sm">
          <a class="link-custom text-gray-700" href="{{ route('password.request') }}">{{ __('Забыли пароль?') }}</a>
        </div>
      @endif
    </form>

    <p class="mt-6 pt-6 border-t border-gray-200 text-sm text-gray-600 text-center">
      {{ __('У вас еще нет аккаунта?') }} <a class="link-custom text-gray-900 font-medium" href="{{ route('register') }}">{{ __('Зарегистрируйтесь!') }}</a>
    </p>
  </div>
</div>
@endsection
