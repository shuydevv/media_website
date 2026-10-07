{{-- Учёт просмотра урока: подключает плееры Kinescope через IFrame Player
     API и раз в 30 секунд, пока видео играет, шлёт отметку в
     Student\LessonWatchController::watch().
     Считаем реально просмотренное, а не позицию: для записей — прирост
     currentTime между событиями TimeUpdate (скачок от перемотки в сумму
     не попадает), для эфира — обычное время, пока плеер играет (у эфира
     нет осмысленной позиции/длительности). --}}
<script>
(function () {
  const frames = Array.from(document.querySelectorAll('iframe[data-kinescope-id]'));
  if (!frames.length) return;

  const WATCH_URL = @json(route('student.lessons.watch', $lesson));
  const CSRF = @json(csrf_token());
  const EXTERNAL_ID = @json((string) auth()->id());
  const BEAT_MS = 30000;
  // Больше этого прироста позиции между двумя TimeUpdate — уже перемотка,
  // а не воспроизведение (с запасом на скорость x2 и редкие события).
  const MAX_STEP_SECONDS = 5;

  const embedUrl = (frame) => 'https://kinescope.io/embed/' + frame.dataset.kinescopeId;

  // API не загрузился (блокировщик, сеть) — показываем видео как раньше,
  // просто без учёта просмотра. Важнее, чтобы урок открылся.
  let decided = false;
  function fallback() {
    if (decided) return;
    decided = true;
    frames.forEach((frame) => { if (!frame.getAttribute('src')) frame.src = embedUrl(frame); });
  }
  const fallbackTimer = setTimeout(fallback, 5000);

  function send(kind, seconds, position, duration, viaBeacon) {
    const body = new FormData();
    body.append('_token', CSRF);
    body.append('kind', kind);
    body.append('seconds', String(seconds));
    body.append('position', String(position));
    body.append('duration', String(duration));

    if (viaBeacon && navigator.sendBeacon) {
      navigator.sendBeacon(WATCH_URL, body);
      return;
    }
    fetch(WATCH_URL, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(() => {});
  }

  function track(player, kind) {
    const E = player.Events;
    const isLive = kind === 'live';
    const payload = (event) => (event && event.data) || {};

    let pending = 0;        // ещё не отправленные секунды
    let lastTime = null;    // позиция на прошлом TimeUpdate
    let position = 0;       // самая дальняя позиция
    let duration = 0;
    let playingSince = null; // для эфира: когда началось текущее воспроизведение

    function settleLive() {
      if (!isLive || playingSince === null) return;
      const now = Date.now();
      pending += (now - playingSince) / 1000;
      playingSince = now;
    }

    function flush(viaBeacon) {
      settleLive();
      const seconds = Math.floor(pending);
      if (seconds < 1) return;
      pending -= seconds;
      send(kind, seconds, Math.floor(position), isLive ? 0 : Math.floor(duration), viaBeacon);
    }

    const setDuration = (event) => {
      const d = Number(payload(event).duration);
      if (isFinite(d) && d > 0) duration = d;
    };
    player.on(E.Loaded, setDuration);
    player.on(E.DurationChange, setDuration);

    player.on(E.TimeUpdate, (event) => {
      const t = Number(payload(event).currentTime);
      if (!isFinite(t)) return;
      if (!isLive && lastTime !== null) {
        const step = t - lastTime;
        if (step > 0 && step <= MAX_STEP_SECONDS) pending += step;
      }
      lastTime = t;
      if (t > position) position = t;
    });

    player.on(E.Playing, () => { if (isLive && playingSince === null) playingSince = Date.now(); });

    const stop = () => {
      settleLive();
      playingSince = null;
      flush(false);
    };
    player.on(E.Pause, stop);
    player.on(E.Ended, stop);
    player.on(E.Waiting, () => { settleLive(); playingSince = null; });

    setInterval(() => flush(false), BEAT_MS);

    // Закрытие/сворачивание вкладки — обычный fetch может не успеть уйти.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') flush(true);
    });
    window.addEventListener('pagehide', () => flush(true));
  }

  window.onKinescopeIframeAPIReady = function (playerFactory) {
    if (decided) return;
    decided = true;
    clearTimeout(fallbackTimer);

    frames.forEach((frame) => {
      playerFactory
        .create(frame.id, {
          url: 'https://kinescope.io/' + frame.dataset.kinescopeId,
          size: { width: '100%', height: '100%' },
          settings: { externalId: EXTERNAL_ID },
        })
        .then((player) => track(player, frame.dataset.kind))
        .catch(() => {
          const el = document.getElementById(frame.id);
          if (el && !el.getAttribute('src')) el.src = embedUrl(frame);
        });
    });
  };

  const script = document.createElement('script');
  script.src = 'https://player.kinescope.io/latest/iframe.player.js';
  script.async = true;
  script.onerror = fallback;
  document.head.appendChild(script);
})();
</script>
