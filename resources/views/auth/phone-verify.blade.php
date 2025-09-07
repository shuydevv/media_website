@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto p-6 bg-white rounded-xl border mt-8">
  <h1 class="text-2xl font-medium mb-2">Введите код</h1>
  <p class="text-gray-600 text-sm mb-4">Мы отправили код на {{ session('masked_phone') }}</p>

  @if (session('status'))
    <div class="mb-3 text-sm text-emerald-700">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <form method="post" action="{{ route('auth.phone.verify') }}" class="space-y-4">
    @csrf
    <input name="code" type="text" inputmode="numeric" pattern="\d*" maxlength="6"
           class="w-full border rounded-lg px-3 py-2 tracking-widest text-center text-xl"
           placeholder="••••••" required>
    <button class="w-full px-3 py-3 rounded-lg bg-zinc-900 text-white">Продолжить</button>
  </form>

  <form method="post" action="{{ route('auth.phone.resend') }}" class="mt-3">
    @csrf
    <button class="text-sm text-blue-700 underline" type="submit">Отправить код снова</button>
  </form>
</div>
@endsection
