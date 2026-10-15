@extends('layouts.main')
@section('title')
{{ $lesson['title'] }} — пробный урок. Школа Полтавского
@endsection
@section('description', '')
@push('meta')
    <meta property="og:type" content="video.other">
    <meta property="og:site_name" content="Школа Полтавского">
    {{-- В карточке превью — только название урока, без описания: без
         og:description Telegram подставил бы <meta name="description">,
         поэтому оно у этих страниц тоже пустое (см. section description). --}}
    <meta property="og:title" content="{{ $lesson['title'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if (! empty($lesson['cover']))
        <meta property="og:image" content="{{ url($lesson['cover']) }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
@endpush
@section('content')
    <div class="container mx-auto max-w-screen-lg md:mt-12 mt-8 md:mb-20 mb-12 px-3">
        <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 text-start tracking-wide text-zinc-900 sans">{{ $lesson['title'] }}</h1>

        <div class="border-4 border-blue-100 rounded-xl" style="position: relative; padding-top: 56.25%; width: 100%">
            <iframe src="https://kinescope.io/embed/{{ $lesson['video_id'] }}"
                    allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
                    frameborder="0" allowfullscreen
                    style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe>
        </div>

        <div class="text-center">
            <a class="bg-pink-500 px-6 py-4 text-white text-lg font-semibold mt-8 inline-block rounded-xl cursor-pointer" href="#"
               data-open-form="trial" data-label="Пробный урок «{{ $lesson['title'] }}»">Записаться на курс</a>
        </div>
    </div>
    @include('partials.lead-modal')
@endsection
