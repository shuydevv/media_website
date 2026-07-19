@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if ($errors->any())
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Вход по телефону</h1>
    <p class="text-sm text-gray-600 mb-6">Укажите номер в формате +7XXXXXXXXXX</p>

    <form method="post" action="{{ route('auth.phone.send') }}" class="space-y-4">
      @csrf
      <label class="block">
        <span class="text-sm text-gray-700">Номер телефона</span>
        <input name="phone" type="tel" inputmode="tel" autocomplete="tel"
               class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" placeholder="+7 999 123-45-67" required>
      </label>
      <div class="pt-2">
        <button class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">Получить код</button>
      </div>
    </form>
  </div>
</div>
@endsection
