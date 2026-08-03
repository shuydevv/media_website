{{-- resources/views/components/mock-timer.blade.php
     Обратный отсчёт до конца пробника (фиксированные 3ч30м, см.
     Homework::MOCK_TIME_LIMIT_MINUTES). Подключается через @include в
     question-region.blade.php и finish-region.blade.php — оба переживают
     htmx hx-swap="innerHTML" на #wizard-app, поэтому интервал каждый раз
     пересоздаётся и обязательно чистит предыдущий (см. скрипт ниже), иначе
     при переходах между вопросами таймеры копились бы один поверх другого.

     По достижении нуля — просто перезагружаем текущую страницу: серверная
     autoFinishIfExpired() (вызывается из ensureInProgress() при любом
     обращении к visard'у) сама завершит попытку и отдаст редирект на
     результаты. Никакой логики начисления баллов на клиенте. --}}
<div id="mock-timer" class="mock-timer" data-expires-at="{{ $expiresAt }}">
  <x-icon name="clock" class="w-4 h-4 shrink-0" />
  <span id="mock-timer-text">--:--:--</span>
</div>

<style>
  .mock-timer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 10px;
    border: 1px solid #e4e4e7;
    background: #fafafa;
    color: #3f3f46;
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    white-space: nowrap;
    transition: background-color .3s ease, border-color .3s ease, color .3s ease;
  }
  .mock-timer.is-warning {
    border-color: #fcd34d;
    background: #fffbeb;
    color: #b45309;
  }
  .mock-timer.is-danger {
    border-color: #fda4af;
    background: #fff1f2;
    color: #be123c;
    animation: mock-timer-pulse 1s ease-in-out infinite;
  }
  @keyframes mock-timer-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
  }
</style>

<script>
(function () {
  var el = document.getElementById('mock-timer');
  if (!el) return;

  var textEl = document.getElementById('mock-timer-text');
  var expiresAt = new Date(el.dataset.expiresAt).getTime();

  // Переживает несколько htmx-подмен #wizard-app за сессию — не даём
  // интервалам копиться один поверх другого на отвязанных от DOM узлах.
  if (window.__mockTimerInterval) {
    clearInterval(window.__mockTimerInterval);
  }

  function pad(n) { return String(n).padStart(2, '0'); }

  function render() {
    var remainingMs = expiresAt - Date.now();

    if (remainingMs <= 0) {
      textEl.textContent = '00:00:00';
      clearInterval(window.__mockTimerInterval);
      window.location.reload();
      return;
    }

    var totalSec = Math.floor(remainingMs / 1000);
    var h = Math.floor(totalSec / 3600);
    var m = Math.floor((totalSec % 3600) / 60);
    var s = totalSec % 60;
    textEl.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);

    var minutesLeft = remainingMs / 60000;
    el.classList.toggle('is-warning', minutesLeft <= 30 && minutesLeft > 10);
    el.classList.toggle('is-danger', minutesLeft <= 10);
  }

  render();
  window.__mockTimerInterval = setInterval(render, 1000);
})();
</script>
