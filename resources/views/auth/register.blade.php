@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if ($errors->any())
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Регистрация</h1>

    <form method="POST" action="{{ route('auth.email.send') }}" class="space-y-4">
      @csrf
      <label class="block">
        <span class="text-sm text-gray-700">E-mail</span>
        <input type="email" name="email" placeholder="mail@mail.com" class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" required autofocus>
      </label>
      <div class="pt-2">
        <button class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">Продолжить</button>
      </div>
    </form>

    <p class="mt-6 pt-6 border-t border-gray-200 text-sm text-gray-600 text-center">
      Уже есть аккаунт? <a class="link-custom text-gray-900 font-medium" href="{{ route('login') }}">Войдите</a>
    </p>
  </div>
</div>
@endsection
