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

        <div class="border-4 border-blue-100 rounded-xl overflow-hidden" style="position: relative; padding-top: 56.25%; width: 100%">
            @if ($feedback)
                {{-- Плеер подключается через IFrame API Kinescope (скрипт ниже) —
                     чтобы считать просмотр; не загрузился API — обычный embed. --}}
                <div style="position: absolute; inset: 0;"><div id="trial-player" style="width: 100%; height: 100%;"></div></div>
            @else
                <iframe src="https://kinescope.io/embed/{{ $lesson['video_id'] }}"
                        allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;"
                        frameborder="0" allowfullscreen
                        style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe>
            @endif
        </div>

        @if ($feedback)
            {{-- Оценка урока — только для пришедших из бота. Про Telegram здесь
                 ни слова: человек не должен знать, что ему напишут. Можно
                 переголосовать — считается последняя оценка. --}}
            <div id="trial-rating" class="mt-6 text-center" data-url="{{ $feedback['rate_url'] }}">
                <p class="text-lg font-medium text-zinc-900 mb-3">Как вам урок?</p>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach ($feedback['buttons'] as $value => $label)
                        <button type="button" data-rating="{{ $value }}" aria-pressed="{{ $feedback['rating'] === $value ? 'true' : 'false' }}"
                                class="trial-rate-btn px-5 py-3 rounded-xl border-2 text-base font-medium transition
                                       {{ $feedback['rating'] === $value ? 'border-blue-500 bg-blue-500 text-white' : 'border-zinc-200 bg-white text-zinc-800 hover:border-blue-300' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <p data-rating-thanks class="mt-3 text-sm text-zinc-600 {{ $feedback['rating'] ? '' : 'hidden' }}">Спасибо за оценку!</p>
            </div>
        @endif

        <div class="text-center">
            <a class="bg-pink-500 px-6 py-4 text-white text-lg font-semibold mt-8 inline-block rounded-xl cursor-pointer" href="#"
               data-open-form="trial" data-label="Пробный урок «{{ $lesson['title'] }}»">Записаться на курс</a>
        </div>
    </div>
    @include('partials.lead-modal')

    @if ($feedback)
        <script>
        (function () {
          var CSRF = @json(csrf_token());

          // ---- Оценка под видео
          var box = document.getElementById('trial-rating');
          if (box) {
            var thanks = box.querySelector('[data-rating-thanks]');
            var on = ['border-blue-500', 'bg-blue-500', 'text-white'];
            var off = ['border-zinc-200', 'bg-white', 'text-zinc-800', 'hover:border-blue-300'];
            box.querySelectorAll('[data-rating]').forEach(function (btn) {
              btn.addEventListener('click', function () {
                // Сразу подсвечиваем — запрос в фоне; оценка не должна «думать».
                box.querySelectorAll('[data-rating]').forEach(function (b) {
                  var active = b === btn;
                  b.setAttribute('aria-pressed', active ? 'true' : 'false');
                  on.forEach(function (c) { b.classList.toggle(c, active); });
                  off.forEach(function (c) { b.classList.toggle(c, !active); });
                });
                thanks.classList.remove('hidden');

                var body = new FormData();
                body.append('_token', CSRF);
                body.append('rating', btn.dataset.rating);
                fetch(box.dataset.url, { method: 'POST', body: body, credentials: 'same-origin',
                  headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
              });
            });
          }

          // ---- Учёт просмотра (как student/lessons/partials/watch-tracker):
          // считаем реально просмотренное — прирост currentTime между
          // событиями, без перемотки; position — где остановился (для
          // «продолжить с того же места»).
          var PROGRESS_URL = @json($feedback['progress_url']);
          var START_AT = @json($feedback['start_at']);
          var VIDEO_ID = @json($lesson['video_id']);
          var MAX_STEP = 5;
          var holder = document.getElementById('trial-player');

          var decided = false;
          function fallback() {
            if (decided) return;
            decided = true;
            holder.outerHTML = '<iframe src="https://kinescope.io/embed/' + VIDEO_ID + '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" frameborder="0" allowfullscreen style="width:100%;height:100%"></iframe>';
          }
          var fallbackTimer = setTimeout(fallback, 5000);

          function track(player) {
            var E = player.Events;
            var pending = 0, lastTime = null, position = 0, duration = 0;
            var data = function (ev) { return (ev && ev.data) || {}; };

            function flush(viaBeacon) {
              var seconds = Math.floor(pending);
              if (seconds < 1) return;
              pending -= seconds;
              var body = new FormData();
              body.append('_token', CSRF);
              body.append('seconds', String(seconds));
              body.append('position', String(Math.floor(position)));
              body.append('duration', String(Math.floor(duration)));
              if (viaBeacon && navigator.sendBeacon) { navigator.sendBeacon(PROGRESS_URL, body); return; }
              fetch(PROGRESS_URL, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
            }

            var setDuration = function (ev) { var d = Number(data(ev).duration); if (isFinite(d) && d > 0) duration = d; };
            player.on(E.Loaded, function (ev) {
              setDuration(ev);
              if (START_AT > 0 && player.seekTo) { try { player.seekTo(START_AT); } catch (e) {} }
            });
            player.on(E.DurationChange, setDuration);
            player.on(E.TimeUpdate, function (ev) {
              var t = Number(data(ev).currentTime);
              if (!isFinite(t)) return;
              if (lastTime !== null) {
                var step = t - lastTime;
                if (step > 0 && step <= MAX_STEP) pending += step;
              }
              lastTime = t;
              position = t;
            });
            player.on(E.Pause, function () { flush(false); });
            player.on(E.Ended, function () { flush(false); });
            setInterval(function () { flush(false); }, 30000);
            document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') flush(true); });
            window.addEventListener('pagehide', function () { flush(true); });
          }

          window.onKinescopeIframeAPIReady = function (factory) {
            if (decided) return;
            decided = true;
            clearTimeout(fallbackTimer);
            factory.create('trial-player', { url: 'https://kinescope.io/' + VIDEO_ID, size: { width: '100%', height: '100%' } })
              .then(track)
              .catch(function () { decided = false; fallback(); });
          };

          var script = document.createElement('script');
          script.src = 'https://player.kinescope.io/latest/iframe.player.js';
          script.async = true;
          script.onerror = fallback;
          document.head.appendChild(script);
        })();
        </script>
    @endif
@endsection
