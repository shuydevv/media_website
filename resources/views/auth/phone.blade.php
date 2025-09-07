@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto p-6 bg-white rounded-xl border mt-8">
  <h1 class="text-2xl font-medium mb-4">Вход по телефону</h1>
  <p class="text-gray-600 text-sm mb-4">Укажите номер в формате +7XXXXXXXXXX</p>

  @if ($errors->any())
    <div class="mb-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <form method="post" action="{{ route('auth.phone.send') }}" class="space-y-4">
    @csrf
    <input name="phone" type="tel" inputmode="tel" autocomplete="tel"
           class="w-full border rounded-lg px-3 py-2" placeholder="+7 999 123-45-67" required>
    <button class="w-full px-3 py-3 rounded-lg bg-zinc-900 text-white">Получить код</button>
  </form>
</div>
@endsection
