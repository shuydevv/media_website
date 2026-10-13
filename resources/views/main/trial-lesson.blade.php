@extends('layouts.main')
@section('title')
{{ $lesson['title'] }} — пробный урок. Школа Полтавского
@endsection
@section('description')
Пробный урок «{{ $lesson['title'] }}» — подготовка к ЕГЭ по обществознанию в Школе Полтавского
@endsection
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
            <a class="bg-pink-500 px-6 py-4 text-white text-lg font-semibold mt-8 inline-block rounded-xl" href="https://t.me/poltavskiy_ege?text={{ rawurlencode('Здравствуйте! Хочу записаться на курс') }}" target="_blank" rel="noopener">Записаться на курс</a>
        </div>
    </div>
@endsection
