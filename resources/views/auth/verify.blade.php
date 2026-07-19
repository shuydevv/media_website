@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if (session('resent'))
    <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
      {{ __('Письмо с новой ссылкой было отправлено на вашу почту') }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm text-center">
    <h1 class="text-2xl font-semibold sans mb-2">{{ __('Подтвердите вашу электронную почту') }}</h1>

    <div class="mt-4 space-y-2 text-sm text-gray-600">
      <p>
        {{ __('Перед тем, как продолжить, перейдите по ссылке в письме, отправленном на вашу почту:') }}
        <span class="font-medium text-gray-900">{{ Auth::user()->email }}</span>
      </p>
      <p>
        {{ __('Если вы не получили письмо, проверьте папку "спам" или отправьте письмо снова') }}
      </p>
    </div>

    <form method="POST" action="{{ route('verification.resend') }}" class="mt-6">
      @csrf
      <button type="submit" class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">
        {{ __('Отправить письмо еще раз') }}
      </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200">
      <p class="text-sm text-gray-500">{{ __('Нет доступа к почте? Вы можете выйти из аккаунта и зарегистрироваться на другую почту') }}</p>
      <form action="{{ route('logout') }}" method="post" class="mt-2">
        @csrf
        <button type="submit" class="link-custom text-sm text-gray-700">{{ __('Выйти из аккаунта') }}</button>
      </form>
    </div>
  </div>
</div>
@endsection
