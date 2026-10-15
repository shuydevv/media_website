{{-- resources/views/thank-you.blade.php --}}
@extends('layouts.main')

@section('content')
  <div class="max-w-2xl mx-auto py-20 text-center mt-12 px-3">
    <h1 class="text-2xl font-semibold mb-3 sans">Спасибо! Заявка отправлена.</h1>
    <p class="text-gray-600">Обычно я отвечаю в течение 10-15 минут, если сейчас не поздний вечер или ночь</p>

    {{-- Только после формы «Записаться» (SiteLeadController): бот не может
         написать первым — запуск по этой ссылке открывает админу диалог с
         человеком через бота, даже если у него скрыт номер. --}}
    @if (session('lead_bot_url'))
      <div class="mt-8 bg-blue-50 border border-blue-100 rounded-2xl p-5">
        <p class="text-gray-800 font-medium">Чтобы я точно смог вам написать — нажмите и запустите бота в Telegram</p>
        <a class="bg-blue-500 hover:bg-blue-600 px-6 py-4 text-white font-semibold mt-4 inline-block rounded-xl" href="{{ session('lead_bot_url') }}" target="_blank" rel="noopener">Получить ответ в Telegram</a>
      </div>
    @endif

    <a class="bg-zinc-800 px-6 py-4 text-white mt-8 inline-block rounded-xl" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Вернуться на сайт</a>
  </div>
@endsection