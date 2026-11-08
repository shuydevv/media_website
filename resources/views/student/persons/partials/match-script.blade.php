{{-- Скрипт задания «Соответствие» (partials/step-d). Живёт вне #terms-app,
     как общий скрипт сессии, и подхватывает задание после каждой подмены
     шага. Ответ — строка цифр по буквам, как в бланке ЕГЭ: вводится с
     клавиатуры в клеточки (на телефоне — цифровая клавиатура). Пустая буква уходит как «0» — сервер посчитает её ошибкой.
     Enter — «Проверить». --}}
<script>
(function () {
  function gs() { return typeof window.gsap !== 'undefined' ? window.gsap : null; }

  function init() {
    var root = document.getElementById('prs-match');
    var form = document.getElementById('prs-match-form');
    if (!root || !form || root.dataset.ready) return;
    root.dataset.ready = '1';

    var rows = parseInt(root.dataset.rows, 10) || 4;
    var max = parseInt(root.dataset.options, 10) || 6;
    var input = document.getElementById('prs-match-input');
    var hidden = document.getElementById('prs-match-answer');
    var boxes = form.querySelectorAll('[data-box]');
    var picks = root.querySelectorAll('.prs-pick');
    var check = form.querySelector('button[type="submit"]');
    var digits = [];
    for (var i = 0; i < rows; i++) digits.push('');

    function sync(changedBox) {
      var focused = document.activeElement === input;
      var cursor = digits.indexOf('');
      if (cursor < 0) cursor = rows - 1;
      boxes.forEach(function (box, i) {
        box.textContent = digits[i];
        box.classList.toggle('filled', digits[i] !== '');
        box.classList.toggle('active', focused && i === cursor);
      });
      picks.forEach(function (b) {
        b.classList.toggle('is-on', digits[+b.dataset.row] === b.dataset.digit);
      });
      hidden.value = digits.map(function (d) { return d || '0'; }).join('');
      check.disabled = digits.join('') === '';
    }

    // Клик по цифре у буквы: повторный клик снимает выбор.
    picks.forEach(function (b) {
      b.addEventListener('click', function () {
        var r = +b.dataset.row;
        digits[r] = digits[r] === b.dataset.digit ? '' : b.dataset.digit;
        input.value = digits.join('');
        sync(r);
      });
    });

    // Ввод в клеточки: цифры по порядку букв, только допустимые (1…max).
    input.addEventListener('input', function () {
      var typed = input.value.replace(new RegExp('[^1-' + max + ']', 'g'), '').slice(0, rows).split('');
      var before = digits.slice();
      for (var i = 0; i < rows; i++) digits[i] = typed[i] || '';
      input.value = typed.join('');
      var changed;
      for (var k = 0; k < rows; k++) if (digits[k] !== before[k]) changed = k;
      sync(changed);
    });
    input.addEventListener('focus', function () { input.value = digits.join(''); sync(); });
    input.addEventListener('blur', function () { sync(); });

    root.addEventListener('keydown', function (evt) {
      if (evt.key === 'Enter' && !check.disabled) {
        evt.preventDefault();
        check.click();
      }
    });

    sync();
    if (window.matchMedia('(hover: hover)').matches) input.focus({ preventScroll: true });

    var g = gs();
    if (g && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      g.from(root.querySelectorAll('.prs-lrow'), { y: 10, autoAlpha: 0, duration: 0.3, stagger: 0.06, ease: 'power2.out', clearProps: 'transform,opacity,visibility' });
      g.from(root.querySelectorAll('.prs-right li'), { x: 10, autoAlpha: 0, duration: 0.3, stagger: 0.04, delay: 0.1, ease: 'power2.out', clearProps: 'transform,opacity,visibility' });
    }
  }

  // Результат — те же движения, что у соответствия на станции «Задания»
  // (student/ege/round): всё верно — клеточки по очереди подпрыгивают;
  // частично — покачиваются; всё мимо — ряд «мотает головой».
  function animateResult() {
    var root = document.getElementById('prs-match');
    if (!root || root.dataset.answered !== '1' || root.dataset.ready) return;
    root.dataset.ready = '1';
    var g = gs();
    if (!g || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var boxes = root.querySelectorAll('.prs-answer .pin-box');
    var ok = root.querySelectorAll('.prs-answer .pin-box.is-ok').length;
    if (ok === boxes.length) {
      g.fromTo(boxes, { scale: 1, y: 0 }, { scale: 1.18, y: -6, duration: 0.16, yoyo: true, repeat: 1, stagger: 0.06, ease: 'power2.out', clearProps: 'transform' });
    } else if (ok > 0) {
      g.fromTo(boxes, { rotation: 0 }, { keyframes: [{ rotation: -8, duration: 0.09 }, { rotation: 6, duration: 0.09 }, { rotation: 0, duration: 0.12 }], stagger: 0.06, ease: 'power1.inOut', clearProps: 'transform' });
    } else {
      g.fromTo(root.querySelector('.prs-answer .pin-boxes'), { x: 0 }, { x: 7, duration: 0.06, yoyo: true, repeat: 5, ease: 'power1.inOut', clearProps: 'transform' });
    }
  }

  function run() { init(); animateResult(); }
  document.addEventListener('DOMContentLoaded', run);
  document.body.addEventListener('htmx:afterSwap', run);
})();
</script>
