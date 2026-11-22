{{-- Смена нормы — открывается кнопкой «изменить» в «Плане на сегодня». --}}
@if($norm)
  <dialog class="gym-norm-dialog" id="gym-norm-dialog">
    <div class="gym-norm-box">
    <button type="button" class="gym-norm-close" data-norm-close aria-label="Закрыть">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div class="gym-norm-q">Сколько минут в день на тренажёры?</div>
    <div class="gym-norm-sub">План на сегодня перестроим сразу, сделанное останется.</div>
    @include('student.trainers.partials.norm-form', ['current' => (int) $norm])
    </div>
  </dialog>
  <script>
    (function () {
      var dialog = document.getElementById('gym-norm-dialog');
      if (!dialog || !dialog.showModal) return;
      document.querySelectorAll('[data-norm-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.showModal(); });
      });
      dialog.querySelector('[data-norm-close]').addEventListener('click', function () { dialog.close(); });
      // Клик по затемнению вокруг окна — закрыть.
      dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    })();
  </script>
@endif
