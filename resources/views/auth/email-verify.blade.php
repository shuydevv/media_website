@extends('layouts.main')

@section('content')
<div class="max-w-md mx-auto px-4 pt-16 pb-8">
  @if (session('status'))
    <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
      {{ session('status') }}
    </div>
  @endif
  @if ($errors->any())
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="bg-white border rounded-2xl p-6 shadow-sm">
    <h1 class="text-2xl font-semibold sans mb-2">Введите код из Email</h1>
    <p class="text-sm text-gray-600 mb-6">Код отправлен на {{ $masked }}</p>

    <form method="post" action="{{ route('auth.email.verify') }}" class="space-y-4">
      @csrf
      <input name="code" type="text" inputmode="numeric" pattern="\d*" maxlength="6"
             class="w-full border rounded-lg px-3 py-2 tracking-widest text-center text-xl input-focus"
             placeholder="••••••" required>
      <div class="pt-2">
        <button class="w-full rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition">Продолжить</button>
      </div>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-200 text-center">
      <form method="post" action="{{ route('auth.email.resend') }}">
        @csrf
        <button class="link-custom text-sm text-blue-700" type="submit">Код не пришел? Отправить снова</button>
      </form>
    </div>
  </div>
</div>
@endsection
