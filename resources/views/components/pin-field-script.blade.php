{{--
    Логика квадратиков-инпута для ответа на автопроверяемое задание (стили
    — resources/css/app.css, .pin-field/.pin-boxes/.pin-box/.pin-hidden-input).
    Универсальный по CSS-классам/data-атрибутам, ничего не завязано на
    студенческий флоу — переиспользуется и в форме создания/редактирования
    задания (тот же формат ответа, что вводит студент: набор цифр/букв).
    Изначально жил только в student/submissions/partials/question-region.blade.php —
    вынесен сюда, чтобы не плодить вторую копию для админки.
--}}
<script>
(function () {
  document.querySelectorAll('.pin-field').forEach((field) => {
    const name = field.getAttribute('data-for');
    const allowed = field.getAttribute('data-allowed') || 'digits';
    const maxSafe = 100;

    const selector = 'input[name="'+name.replace(/([[\]])/g,'\\$1')+'"]';
    const realInput = document.querySelector(selector);
    if (!realInput) return;

    const boxesWrap = field.querySelector('.pin-boxes');

    function oneRowCapacity() {
      const isMobile = window.matchMedia('(max-width: 640px)').matches;
      const box = isMobile ? 32 : 42;
      const gap = isMobile ? 10 : 12;
      const w = field.clientWidth || boxesWrap.clientWidth || 320;
      const fit = Math.max(4, Math.floor((w + gap) / (box + gap)));
      return Math.min(fit, maxSafe);
    }

    let boxCount = Math.max(boxesWrap.children.length, (realInput.value || '').length, oneRowCapacity());

    function buildBoxes(count) {
      const c = Math.min(count, maxSafe);
      boxesWrap.innerHTML = '';
      for (let i = 0; i < c; i++) {
        const d = document.createElement('div');
        d.className = 'pin-box';
        boxesWrap.appendChild(d);
      }
    }

    // Дополняет недостающие квадратики, не трогая уже существующие (в т.ч. отрисованные
    // сервером) — без этого при каждой пересборке был бы виден «прыжок» пустое/заполнено.
    function growBoxesTo(count) {
      const c = Math.min(count, maxSafe);
      for (let i = boxesWrap.children.length; i < c; i++) {
        const d = document.createElement('div');
        d.className = 'pin-box';
        boxesWrap.appendChild(d);
      }
    }

    function ensureBoxesFor(len) {
      if (len > boxCount) {
        boxCount = Math.min(len, maxSafe);
        growBoxesTo(boxCount);
      }
    }

    function sanitizeRaw(v) {
      v = (v || '').toString();
      if (allowed === 'digits') v = v.replace(/\D+/g, '');
      else v = v.replace(/[^0-9A-Za-zА-Яа-яЁё]+/g, '');
      return v.slice(0, maxSafe);
    }

    let hasFocus = false;
    function getCaretIndex() {
      try {
        const pos = realInput.selectionStart ?? 0;
        const len = (realInput.value || '').length;
        return Math.max(0, Math.min(pos, Math.max(len, 0), boxCount - 1));
      } catch {
        const len = (realInput.value || '').length;
        return Math.min(len, boxCount - 1);
      }
    }

    function renderBoxes(v) {
      const boxes = boxesWrap.querySelectorAll('.pin-box');
      boxes.forEach(b => b.classList.remove('active'));
      for (let i = 0; i < boxes.length; i++) {
        const ch = v[i] || '';
        boxes[i].textContent = (ch || '').toUpperCase();
        boxes[i].classList.toggle('filled', !!ch);
      }
      if (hasFocus) {
        const activeIdx = getCaretIndex();
        if (boxes[activeIdx]) boxes[activeIdx].classList.add('active');
      }
    }

    growBoxesTo(boxCount);
    renderBoxes(realInput.value || '');

    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        const current = realInput.value || '';
        const cap = oneRowCapacity();
        if (current.length <= cap) {
          boxCount = cap;
          buildBoxes(boxCount);
          renderBoxes(current);
        }
      }, 150);
    });

    function moveCaretToEnd() {
      const len = (realInput.value || '').length;
      try { realInput.setSelectionRange(len, len); } catch (e) {}
    }

    field.addEventListener('click', () => {
      realInput.focus();
      moveCaretToEnd();
    });
    realInput.addEventListener('focus', () => {
      hasFocus = true;
      moveCaretToEnd();
      renderBoxes(realInput.value || '');
    });
    realInput.addEventListener('blur',  () => { hasFocus = false; renderBoxes(realInput.value || ''); });

    realInput.addEventListener('input', () => {
      const cleaned = sanitizeRaw(realInput.value);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
    });

    // Enter — отправить форму (как клик по «Проверить ответ»), если поле не пустое.
    realInput.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      const form = realInput.closest('form');
      if (!form) return;
      const btn = form.querySelector('button[type="submit"]');
      if (btn && btn.disabled) return;
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });

    field.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      const cleaned = sanitizeRaw(text);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
      realInput.focus();
      realInput.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // Публичный хук — досчитать/дорастить квадратики под изменившееся
    // содержание (например, число пунктов слева в "Соотнесении" или число
    // помеченных пропусков в таблице), не переинициализируя виджет и не
    // трогая уже введённое значение. Используется формой создания задания.
    window.growPinField = window.growPinField || {};
    window.growPinField[name] = function (minCount) {
      if (minCount > boxCount) {
        boxCount = Math.min(minCount, maxSafe);
        growBoxesTo(boxCount);
        renderBoxes(realInput.value || '');
      }
    };

    // Публичный хук — задать значение целиком (форма создания задания
    // выводит ответ автоматически из отметок "верный вариант"/пар в
    // "Соотнесении"/значений пропусков в таблице, а не ждёт, пока админ
    // впишет его вручную в квадратики).
    window.setPinFieldValue = window.setPinFieldValue || {};
    window.setPinFieldValue[name] = function (value) {
      const cleaned = sanitizeRaw(value);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
      realInput.dispatchEvent(new Event('input', { bubbles: true }));
    };
  });
})();
</script>
