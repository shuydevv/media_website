{{--
    Логика квадратиков-инпута для ответа на автопроверяемое задание (стили
    — resources/css/app.css, .pin-field/.pin-boxes/.pin-box/.pin-hidden-input).
    Универсальный по CSS-классам/data-атрибутам, ничего не завязано на
    студенческий флоу — переиспользуется и в форме создания/редактирования
    задания (тот же формат ответа, что вводит студент: набор цифр/букв).
    Изначально жил только в student/submissions/partials/question-region.blade.php —
    вынесен сюда, чтобы не плодить вторую копию для админки.

    Пара .pin-field ↔ .pin-hidden-input ищется ЧЕРЕЗ DOM (соседний элемент
    в общем родителе), а не по имени поля через data-for/document.querySelector —
    в конструкторе домашки карточки задания клонируются (см. "Добавить
    задание"), и после переименования полей (tasks[0][answer] →
    tasks[1][answer]) реальный <input> получает новое имя, а data-for на
    клонированном .pin-field — нет (клон копирует его как есть). Поиск по
    имени в этом случае у ВТОРОЙ карточки находил бы поле ПЕРВОЙ — любое
    growPinField/setPinFieldValue из второй карточки тихо портило бы ответ
    первой. Поиск по соседству переживает клонирование сам по себе, без
    отдельной синхронизации data-for.
--}}
<script>
(function () {
  // Идемпотентность держим на WeakSet конкретных DOM-узлов, а не на
  // data-атрибуте: cloneNode(true) (карточка "Добавить задание") копирует
  // ВСЕ атрибуты as-is, включая data-pin-ready — клон выглядел бы уже
  // готовым и setupPinField() тихо ничего не делал бы для него (ни
  // перерисовки квадратиков под текущее — уже очищенное — значение, ни
  // навешивания обработчиков). WeakSet различает клон и оригинал по
  // идентичности объекта, а не по унаследованным атрибутам.
  const readyFields = new WeakSet();

  function setupPinField(field) {
    if (readyFields.has(field)) return;
    readyFields.add(field);

    const allowed = field.getAttribute('data-allowed') || 'digits';
    const maxSafe = 100;

    const realInput = field.parentElement?.querySelector('.pin-hidden-input');
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
    // Клон карточки копирует и старые квадратики (текст внутри), а не
    // только их количество — renderBoxes() ниже перерисовывает содержимое
    // ВСЕХ существующих квадратиков по актуальному значению realInput
    // (уже очищенному к этому моменту), так что клон "первым делом" сам
    // стирает унаследованные от оригинала цифры.
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

    // Публичные хуки — привязаны к КОНКРЕТНОМУ узлу .pin-field, не к имени
    // поля: growPinField/setPinFieldValue из другой карточки физически не
    // могут задеть это поле, даже если что-то у них с именами разъедется.
    field._growPinField = function (minCount) {
      if (minCount > boxCount) {
        boxCount = Math.min(minCount, maxSafe);
        growBoxesTo(boxCount);
        renderBoxes(realInput.value || '');
      }
    };
    field._setPinFieldValue = function (value) {
      const cleaned = sanitizeRaw(value);
      realInput.value = cleaned;
      ensureBoxesFor(cleaned.length);
      renderBoxes(cleaned);
      realInput.dispatchEvent(new Event('input', { bubbles: true }));
    };
  }

  document.querySelectorAll('.pin-field').forEach(setupPinField);

  // Публичная точка входа — конструктор домашки вызывает это для только
  // что клонированной карточки задания (см. initRoot() в
  // task-editor-script.blade.php), где .pin-field ещё не был инициализирован.
  window.setupPinField = setupPinField;
})();
</script>
