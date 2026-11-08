{{-- Показ окна званий (partials/rank-modal). На экране результата ждёт,
     пока корм долетит до маскота (тайминги — animateResult() в
     student/terms/partials/session-script), на главной — сразу. Окно
     приходит и с подменой шага htmx, поэтому ловим и afterSwap. --}}
<script>
(function () {
  function gs() { return typeof window.gsap !== 'undefined' ? window.gsap : null; }
  var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function sparks(card) {
    var g = gs();
    var box = card.getBoundingClientRect();
    var colors = ['#E8B931', '#1F2A44', '#F5D76E', '#3B82F6'];
    for (var i = 0; i < 36; i++) {
      (function () {
        var el = document.createElement('div');
        var size = 5 + Math.random() * 5;
        el.style.cssText = 'position:fixed;pointer-events:none;z-index:91;border-radius:1px;width:' + size + 'px;height:' + (size * 0.45) + 'px;left:'
          + (box.left + box.width / 2) + 'px;top:' + (box.top + 70) + 'px;background:' + colors[i % colors.length] + ';';
        document.body.appendChild(el);
        var dx = (Math.random() - 0.5) * Math.min(window.innerWidth, 700);
        g.timeline({ onComplete: function () { el.remove(); } })
          .to(el, { x: dx * 0.4, y: -60 - Math.random() * 90, rotation: Math.random() * 360, duration: 0.4, ease: 'power2.out' })
          .to(el, { x: dx, y: window.innerHeight * 0.6, rotation: Math.random() * 720, opacity: 0, duration: 1.2 + Math.random() * 0.5, ease: 'power1.in' });
      })();
    }
  }

  function open(modal) {
    var g = calm ? null : gs();
    var card = modal.querySelector('.prs-rank-card');
    var ok = document.getElementById('prs-rank-ok');
    modal.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    if (ok) ok.focus({ preventScroll: true });

    if (g) {
      g.from(modal, { opacity: 0, duration: 0.3 });
      g.from(card, { scale: 0.6, y: 30, opacity: 0, duration: 0.7, ease: 'elastic.out(1, .6)', clearProps: 'transform,opacity' });
      g.from(modal.querySelector('.prs-rank-mascot'), { y: 20, opacity: 0, duration: 0.4, delay: 0.25, ease: 'back.out(2)', clearProps: 'transform,opacity' });
      var board = modal.querySelector('.prs-rank-board');
      if (board) {
        // Погон «прихлопывают» печатью — с размаху и с лёгким поворотом.
        g.from(board, { scale: 2.4, rotation: -25, opacity: 0, duration: 0.45, delay: 0.55, ease: 'power3.in', clearProps: 'transform,opacity',
          onComplete: function () {
            g.fromTo(card, { x: -4 }, { x: 0, duration: 0.3, ease: 'elastic.out(1.2, .3)', clearProps: 'transform' });
            if (navigator.vibrate) navigator.vibrate(15);
            sparks(card);
          } });
      }
      g.from(modal.querySelectorAll('.prs-rank-say, .prs-rank-label, .prs-rank-title, .prs-rank-note, #prs-rank-ok'),
        { y: 10, opacity: 0, duration: 0.35, delay: 0.7, stagger: 0.08, ease: 'power2.out', clearProps: 'transform,opacity' });
    }

    function close() {
      document.removeEventListener('keydown', onKey);
      var done = function () { modal.remove(); document.documentElement.style.overflow = ''; };
      g ? g.to(modal, { opacity: 0, duration: 0.25, onComplete: done }) : done();
    }
    function onKey(evt) { if (evt.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);
    if (ok) ok.addEventListener('click', close, { once: true });
  }

  function check() {
    var modal = document.getElementById('prs-rank');
    if (!modal || modal.dataset.armed) return;
    modal.dataset.armed = '1';
    // Окно — поверх всего, вне подменяемой области.
    document.body.appendChild(modal);

    var result = document.getElementById('term-result');
    var corm = result ? (parseInt(result.dataset.corm, 10) || 0) : 0;
    var delay = !result ? 400 : (corm > 0 ? (600 + corm * 280 + 550 + 1000) : 1300);
    setTimeout(function () { open(modal); }, calm ? 300 : delay);
  }

  document.addEventListener('DOMContentLoaded', check);
  document.body.addEventListener('htmx:afterSwap', function () { setTimeout(check, 0); });
})();
</script>
