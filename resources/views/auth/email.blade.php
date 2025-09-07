@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto p-6 bg-white rounded-xl border mt-8">
  <h1 class="text-2xl font-medium mb-4 sans">Регистрация по e-mail</h1>
  @if (session('status'))
    <div class="mb-3 text-sm text-emerald-700">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <form method="post" action="{{ route('auth.email.send') }}" class="mt-4">
    @csrf
    <label class="block">
      <span class="text-sm">E-mail</span>
      <input type="email" name="email" class="w-full border rounded-lg px-3 py-2" required autofocus>
    </label>
    <button class="w-full px-3 py-3 rounded-lg bg-zinc-900 text-white mt-7">Получить код</button>
  </form>
</div>
@endsection
