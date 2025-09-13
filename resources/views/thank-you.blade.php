{{-- resources/views/thank-you.blade.php --}}
@extends('layouts.main')

@section('content')
  <div class="max-w-2xl mx-auto py-20 text-center mt-12 px-3">
    <h1 class="text-2xl font-semibold mb-3 sans">Спасибо! Заявка отправлена.</h1>
    <p class="text-gray-600">Обычно я отвечаю в течение 10-15 минут, если сейчас не поздний вечер или ночь</p>
    <a class="bg-zinc-800 px-6 py-4 text-white mt-8 inline-block rounded-xl" href="/repetitor">Вернуться на сайт</a>
  </div>
@endsection