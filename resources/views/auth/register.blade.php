@extends('layouts.main')

@section('content')
<div class="container">
  <div class="flex justify-center mt-10">
    <div class="card-body border rounded p-8 w-96">
      <div class="mb-6 text-2xl text-center">Регистрация</div>

      @if ($errors->any())
        <div class="mb-3 text-sm text-red-600">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('auth.email.send') }}" class="space-y-4">
        @csrf
        <label class="block">
          <span class="text-sm">E-mail</span>
          <input type="email" name="email" class="w-full border rounded-lg px-3 py-2" required autofocus>
        </label>
        <button class="w-full px-3 py-3 rounded-lg bg-zinc-900 text-white">Продолжить</button>
      </form>

      <p class="mt-4 text-sm text-gray-600">
        Уже есть аккаунт? <a class="underline" href="{{ route('login') }}">Войдите</a>
      </p>
    </div>
  </div>
</div>
@endsection
