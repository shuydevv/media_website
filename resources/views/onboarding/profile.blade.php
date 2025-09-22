@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto p-6 bg-white rounded-xl border mt-8">
  <h1 class="text-2xl font-medium sans md:mb-6 mb-4">Введите свои данные</h1>

  @if ($errors->any())
    <div class="mb-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <form method="post" action="{{ route('onboarding.profile.save') }}" class="">
    @csrf
    <div class="mb-4">
      <label class="block text-sm mb-1">Имя</label>
      <input required name="first_name" value="{{ old('first_name', $user->first_name) }}"
             class="w-full border rounded-lg px-3 py-2" required>
    </div>
    <div class="mb-4">
      <label class="block text-sm mb-1">Фамилия</label>
      <input required name="last_name" value="{{ old('last_name', $user->last_name) }}"
             class="w-full border rounded-lg px-3 py-2">
    </div>
    
    <div class="mb-4">
      <label class="block text-sm mb-1">Логин в телеграм</label>
      <input required name="name" value=""
             class="w-full border rounded-lg px-3 py-2">
    </div>

    <div class="mb-4">
        <label class="block text-sm mb-1">Пароль</label>
        <input type="password" name="password" required minlength="8"
        class="w-full border rounded-lg px-3 py-2"/>
        @error('password') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
    </div>

    <div class="mb-4">
        <label class="block text-sm mb-1">Подтверждение пароля</label>
        <input type="password" name="password_confirmation" required minlength="8"
        class="w-full border rounded-lg px-3 py-2"/>
        @error('password_confirmation') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
    </div>

    {{-- <div class="mb-7">
      <label class="block text-sm mb-1">
        <span class="text-sm">Часовой пояс (только РФ)</span>
  <input
      list="timezone-list"
      name="timezone"
      class="input"
      required
      value="{{ old('timezone', $currentTz ?? ($user->timezone ?? 'Europe/Moscow')) }}"
      placeholder="Europe/Moscow" />

  <datalist id="timezone-list">
    @foreach(($timezones ?? []) as $tz)
      <option value="{{ $tz }}"></option>
    @endforeach
  </datalist>

  @error('timezone') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
      </label>

    </div> --}}
    <button class="w-full px-3 py-3 rounded-lg bg-zinc-900 text-white">Сохранить</button>
  </form>
</div>
@endsection
