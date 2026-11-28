@extends('layouts.main')

@section('title', $lesson->title.' — Школа Полтавского')

@push('meta')
  <meta name="robots" content="noindex">
@endpush

@section('content')
{{-- Гостевая ссылка на эфир урока (LiveChat\GuestController): без входа на
     сайт, только этот урок — эфир или, когда он прошёл, запись с чатом. --}}
<div class="max-w-6xl mx-auto md:px-6 px-4 pt-4">
  <h1 class="sans-medium text-xl md:text-3xl mt-4 mb-2 text-zinc-900">{{ $lesson->title }}</h1>
  <p class="text-sm md:text-base text-zinc-600 mb-4">
    {{ $replay ? 'Запись трансляции' : 'Прямой эфир' }} · вы смотрите по гостевой ссылке
  </p>
</div>

@php $videoId = $replay ? $lesson->recording_link : $lesson->meet_link; @endphp
<div class="{{ $config ? 'lc-layout px-3 md:px-4' : 'max-w-6xl mx-auto md:px-6 px-4' }} mb-10">
  <div>
    <div class="lc-video border-4 rounded-2xl border-blue-200" style="position: relative; padding-top: 56.25%; width: 100%">
      <iframe id="kinescope-guest" data-kinescope-id="{{ $videoId }}"
              allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
              frameborder="0" allowfullscreen
              style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe>
    </div>
  </div>
  @if ($config)
    <div>@include('live-chat.panel', ['config' => $config])</div>
  @elseif (! config('live_chat.enabled') && ! $replay)
    {{-- Наш чат выключен (LIVE_CHAT_ENABLED=false) — чат Kinescope, как на странице урока. --}}
    <iframe src="https://kinescope.io/chat/{{ $lesson->meet_link }}" allow="fullscreen" frameborder="0" allowfullscreen
            class="w-full mt-4" style="min-height: 420px"></iframe>
  @endif
</div>

{{-- Плеер через IFrame API Kinescope — чату в записи нужна позиция видео
     (событие kinescope-player, как у watch-tracker на странице урока). --}}
<script>
(function () {
  const frame = document.getElementById('kinescope-guest');
  const kind = @json($replay ? 'recording' : 'live');
  const embed = () => { if (!frame.getAttribute('src')) frame.src = 'https://kinescope.io/embed/' + frame.dataset.kinescopeId; };
  const fallback = setTimeout(embed, 5000);
  window.onKinescopeIframeAPIReady = function (factory) {
    clearTimeout(fallback);
    factory.create(frame.id, { url: 'https://kinescope.io/' + frame.dataset.kinescopeId, size: { width: '100%', height: '100%' } })
      .then((player) => window.dispatchEvent(new CustomEvent('kinescope-player', { detail: { kind, player } })))
      .catch(embed);
  };
  const s = document.createElement('script');
  s.src = 'https://player.kinescope.io/latest/iframe.player.js';
  s.async = true;
  s.onerror = embed;
  document.head.appendChild(s);
})();
</script>
@endsection
