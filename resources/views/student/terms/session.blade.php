{{-- resources/views/student/terms/session.blade.php
     Каркас сессии тренажёра. Всё содержимое — в partials/region, тот же
     партиал отдаётся htmx-запросам и подменяет #terms-app целиком.

     Скрипт ниже живёт вне #terms-app (как тосты в layouts/main), поэтому
     переживает подмену шага и может сравнить «было» и «стало»: по номеру
     карточки (data-key) решает, отбрасывать ли старую, по data-flash —
     какую анимацию проиграть. Вся анимация на GSAP; без него (не загрузился
     CDN) тренажёр работает так же, просто без движения. --}}
@extends('layouts.main')

@section('title', $kindLabel.' — Школа Полтавского')

{{-- Выход из сессии — стрелка «назад» в шапке, как на других страницах
     ученика (partials/student-back-button). Прогресс сохраняется по ходу
     сессии, так что уйти можно в любой момент. --}}
@section('back_url', route('student.terms.index'))

@section('content')
{{-- На телефоне и планшете место нижнего меню платформы занимает рыба с
     прогрессом (см. region.blade.php). Обычный <style>, а не классы на самом
     меню: оно общее для всех страниц ученика и рендерится в layouts/main.
     Граница та же, что у xl в Tailwind (1280px), — шире рыба стоит сбоку и
     меню не мешает. На итоге сессии меню тоже скрыто: это та же страница. --}}
<style>
  @media (max-width: 1279px) {
    /* Кнопка «Раскрыть меню» (та же схема, что при решении домашки, см.
       partials/student-bottom-nav) здесь тоже не нужна: на этих ширинах на
       месте меню полоска прогресса. На широком экране меню свёрнуто в эту
       кнопку, как в домашке. */
    #student-bottom-nav, #student-bottom-nav-reveal { display: none !important; }
    /* Вместе с меню убираем и место под него (pb-20 на body в layouts/main):
       иначе под итогом сессии оставалась пустая полоса. Место под рыбу во
       время заданий даёт отдельный отступ в region.blade.php. */
    body.student-portal { padding-bottom: 0 !important; }
  }
</style>
<div id="terms-app">
  @include('student.terms.partials.region')
</div>
@endsection

@push('page-scripts')
<script>
(function () {
  var PIZZA_SRC = @json(asset('img/pizza.svg'));
  var app = document.getElementById('terms-app');
  if (!app) return;

  var before = null;        // снимок экрана перед подменой шага
  var lastOption = null;    // куда кликнули в квизе — оттуда «летит» вариант
  var mascotTimer = null;
  var autoTimer = null;     // автопереход после верного ответа
  var typingBefore = false; // перед подменой шага ученик печатал в квадратики
  // Сенсорный экран: клавиатура там экранная и закрывает пол-вопроса.
  var touchScreen = window.matchMedia('(hover: none) and (pointer: coarse)').matches;

  function gs() { return typeof window.gsap !== 'undefined' ? window.gsap : null; }
  function $(id) { return document.getElementById(id); }

  /* ---------- способ А: ввод в квадратики ---------- */

  // Квадратики не должны прятаться под экранной клавиатурой: после её
  // появления страница докручивается так, чтобы клетки (а если влезает — и
  // кнопка подсказки под ними) были над ней и над полосой прогресса внизу,
  // но не ушли под рыбу-подсказку сверху. visualViewport — видимая часть
  // страницы без клавиатуры; есть во всех современных мобильных браузерах.
  // Прокрутка мгновенная и до точного места: функция зовётся несколько раз,
  // пока клавиатура выезжает, и плавная прокрутка от устаревших координат
  // накапливалась — экран уезжал слишком низко.
  var KEYBOARD_GAP = 16;
  function keyboardSpacer() {
    var spacer = $('term-keyboard-spacer');
    if (!spacer) {
      spacer = document.createElement('div');
      spacer.id = 'term-keyboard-spacer';
      app.parentNode.insertBefore(spacer, app.nextSibling);
    }
    return spacer;
  }
  function dropKeyboardSpacer() {
    var spacer = $('term-keyboard-spacer');
    if (spacer) spacer.style.height = '0px';
  }
  function keepCellsAboveKeyboard() {
    var input = $('term-a-input');
    var cellsBox = $('term-a-cells');
    var form = $('term-a-form');
    var vv = window.visualViewport;
    if (!input || !cellsBox || !vv || document.activeElement !== input) return;

    // Клавиатура закрылась (на Android — кнопкой «назад», фокус остался).
    if (window.innerHeight - vv.height < 80) { dropKeyboardSpacer(); return; }

    var bottom = vv.offsetTop + vv.height;
    var top = vv.offsetTop;
    var strip = $('term-bottom-strip');
    if (strip && strip.offsetParent !== null) {
      var stripTop = strip.getBoundingClientRect().top;
      if (stripTop < bottom) bottom = stripTop;
    }
    var hud = $('term-hud-place');
    if (hud && getComputedStyle(hud).display !== 'none') {
      top = Math.max(top, hud.getBoundingClientRect().bottom);
    }

    var cells = cellsBox.getBoundingClientRect();
    var area = bottom - top - 2 * KEYBOARD_GAP;
    var formBox = form ? form.getBoundingClientRect() : cells;
    // Целимся в низ всей формы (клетки + «Открыть букву»), если она
    // помещается целиком, иначе — в низ клеток.
    var target = formBox.bottom - cells.top <= area ? formBox.bottom : cells.bottom;

    var shift = 0;
    if (target > bottom - KEYBOARD_GAP) shift = target - (bottom - KEYBOARD_GAP);
    if (cells.top - shift < top + KEYBOARD_GAP) shift = cells.top - (top + KEYBOARD_GAP);
    if (Math.abs(shift) < 2) return;

    // Прокрутить вниз некуда (клетки у самого низа страницы) — добавляем
    // под содержимым ровно недостающее место.
    var maxScroll = document.documentElement.scrollHeight - window.innerHeight;
    var lack = window.scrollY + shift - maxScroll;
    if (shift > 0 && lack > 0) {
      var spacer = keyboardSpacer();
      spacer.style.height = ((parseFloat(spacer.style.height) || 0) + Math.ceil(lack)) + 'px';
    }
    window.scrollTo({ top: window.scrollY + shift, behavior: 'instant' });
  }
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', keepCellsAboveKeyboard);
  }

  function initSquares() {
    var form = $('term-a-form');
    var cellsBox = $('term-a-cells');
    var input = $('term-a-input');
    var hidden = $('term-a-typed');
    if (!form || !cellsBox || !input) return;

    var cells = cellsBox.querySelectorAll('.term-cell');
    var locked = parseInt(cellsBox.dataset.locked, 10) || 0;
    var room = cells.length - locked;
    var sent = false;
    var shown = 0;
    var composing = false;

    // Поле — contenteditable (см. step-a.blade.php), текст — textContent.
    function caretToEnd() {
      if (document.activeElement !== input) return;
      var range = document.createRange();
      range.selectNodeContents(input);
      range.collapse(false);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    }

    function render() {
      // Только буквы и цифры: пробелы и дефисы в клетки не вводятся.
      var letters = Array.from(input.textContent).filter(function (ch) { return /[\p{L}\p{N}]/u.test(ch); }).slice(0, room);
      var text = letters.join('');
      // Пока клавиатура «собирает» слово (composition), DOM не трогаем —
      // иначе на Android буквы задваиваются. Лишнее срежем после.
      if (!composing && input.textContent !== text) {
        input.textContent = text;
        caretToEnd();
      }
      hidden.value = text;

      var focused = document.activeElement === input;
      var cursor = Math.min(letters.length, room - 1);
      for (var i = locked; i < cells.length; i++) {
        var here = focused && i - locked === cursor;
        cells[i].textContent = letters[i - locked] || '';
        cells[i].classList.toggle('ring-2', here);
        cells[i].classList.toggle('ring-blue-500', here);
      }

      // Новая буква «впрыгивает» в клетку.
      if (letters.length > shown && gs()) {
        gs().fromTo(cells[locked + letters.length - 1], { scale: 0.6 }, { scale: 1, duration: 0.25, ease: 'back.out(3)' });
      }
      shown = letters.length;

      return letters.length;
    }

    // Отправка: в hidden уже ровно столько букв, сколько клеток. Поле
    // закрываем и снимаем с него фокус — это обрывает «сборку» слова
    // клавиатурой, и всё, что ученик успел нажать сверх клеток, не
    // доходит никуда.
    function send() {
      if (sent || hidden.value === '') return;
      sent = true;
      input.setAttribute('contenteditable', 'false');
      input.textContent = hidden.value;
      input.blur();
      htmx.trigger(form, 'submit');
    }

    // Последняя клетка заполнена — отправляем сразу, без кнопки. Даже если
    // клавиатура ещё «собирает» слово (на Android это обычное состояние при
    // наборе): иначе отправка ждала бы конца сборки, а лишние нажатия
    // продолжали бы её — проверка могла не случиться вовсе.
    input.addEventListener('input', function () {
      if (render() >= room && room > 0) send();
    });
    input.addEventListener('compositionstart', function () { composing = true; });
    input.addEventListener('compositionend', function () {
      composing = false;
      if (render() >= room && room > 0) send();
    });
    // Перенос строки в редактируемом блоке — это отправка ответа (клавиша
    // «Готово» на телефоне шлёт его без keydown Enter).
    input.addEventListener('beforeinput', function (evt) {
      if (evt.inputType === 'insertParagraph' || evt.inputType === 'insertLineBreak') {
        evt.preventDefault();
        send();
        return;
      }
      // Клетки заполнены или ответ уже ушёл — новые символы не пускаем
      // (сборку слова отменить нельзя, её обрывает send()).
      if ((sent || hidden.value.length >= room) && evt.inputType.indexOf('insert') === 0 && evt.cancelable) {
        evt.preventDefault();
      }
    });
    // Вставка — только текстом, без разметки.
    input.addEventListener('paste', function (evt) {
      evt.preventDefault();
      var text = (evt.clipboardData || window.clipboardData).getData('text');
      document.execCommand('insertText', false, text);
    });
    input.addEventListener('focus', function () {
      render();
      caretToEnd();
      // Клавиатура выезжает не сразу — подстраховываемся, пока она едет
      // (основная подстройка — по resize у visualViewport выше).
      [150, 400, 800].forEach(function (ms) { setTimeout(keepCellsAboveKeyboard, ms); });
    });
    input.addEventListener('blur', function () {
      render();
      dropKeyboardSpacer();
    });
    input.addEventListener('keydown', function (evt) {
      // Enter — для коротких «других названий» (ВВП), которые клетки не
      // заполняют. send() не пускает пустой и повторный ответ.
      if (evt.key === 'Enter') {
        evt.preventDefault();
        send();
      }
    });
    // На компьютере сразу печатаем. На телефоне клавиатуру не открываем
    // сами — она закрыла бы половину вопроса; ученик тапает по клеткам,
    // когда готов. Исключение — клавиатура уже была открыта (взял
    // подсказку): тогда не закрываем её подменой шага.
    // preventScroll — иначе страница прыгает к полю при каждой подмене шага.
    if (!touchScreen || typingBefore) input.focus({ preventScroll: true });
    render();
  }

  /* ---------- карточки: старая улетает, новая поднимается из стопки ---------- */

  function snapshot() {
    var card = $('term-card');
    var ring = $('term-ring');
    var combo = document.querySelector('[data-combo]');
    var label = document.querySelector('[data-progress-label]');
    var hud = $('term-hud');
    var hudRect = hud ? hud.getBoundingClientRect() : null;
    var mascotImg = $('term-mascot');
    var shot = {
      mascotSrc: mascotImg ? mascotImg.getAttribute('src') : null,
      // На телефоне рыба есть на экране, только пока говорит; снимок нужен,
      // чтобы она ушла плавно, когда в новой разметке её уже не будет.
      hudGhost: null,
      key: card ? card.dataset.key : null,
      percent: ring ? parseFloat(ring.dataset.percent) : null,
      combo: combo ? parseInt(combo.dataset.combo, 10) || 0 : 0,
      label: label ? label.textContent : null,
      ghost: null
    };

    if (hud && hudRect.width > 0 && gs()) {
      var hudCopy = hud.cloneNode(true);
      hudCopy.removeAttribute('id');
      hudCopy.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
      hudCopy.style.cssText = 'position:fixed;margin:0;pointer-events:none;z-index:30;'
        + 'left:' + hudRect.left + 'px;top:' + hudRect.top + 'px;width:' + hudRect.width + 'px;';
      shot.hudGhost = hudCopy;
    }

    // Прокрутка и высота карточки — чтобы после подмены шага вернуть экран
    // ровно туда, где он был (см. keepPlace()).
    shot.scrollY = window.scrollY;
    var photo = card ? card.querySelector('img[id^="term-photo-"]') : null;
    shot.photoSrc = photo ? photo.getAttribute('src') : null;
    shot.cardHeight = card ? card.getBoundingClientRect().height : null;

    if (card && gs()) {
      var rect = card.getBoundingClientRect();
      var ghost = card.cloneNode(true);
      ghost.removeAttribute('id');
      ghost.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
      ghost.style.cssText = 'position:fixed;margin:0;pointer-events:none;z-index:40;'
        + 'left:' + rect.left + 'px;top:' + rect.top + 'px;width:' + rect.width + 'px;height:' + rect.height + 'px;';
      shot.ghost = ghost;
    }

    return shot;
  }

  function throwCard(ghost, direction, card) {
    var g = gs();
    document.body.appendChild(ghost);
    // Улетает непрозрачной и тает только в конце пути: если таять сразу,
    // пока она ещё на месте, сквозь неё просвечивает новая карточка и текст
    // ложится на текст.
    g.timeline({ onComplete: function () { ghost.remove(); } })
      .to(ghost, { x: direction * window.innerWidth * 0.9, y: -30, rotation: direction * 16, duration: 0.45, ease: 'power2.in' })
      .to(ghost, { opacity: 0, duration: 0.15, ease: 'power1.in' }, 0.3);

    if (card) {
      // Новая карточка была «под» старой и выглядывала сверху: опускается
      // на её место из положения тени.
      g.fromTo(card, { scale: 0.96, y: -8 }, { scale: 1, y: 0, duration: 0.4, ease: 'power2.out', clearProps: 'transform' });
      var layers = document.querySelectorAll('.term-stack-layer');
      if (layers.length) {
        g.from(layers, { y: 6, opacity: 0, duration: 0.4, ease: 'power2.out', clearProps: 'transform,opacity' });
      }
    }
  }

  /* ---------- отклик на ответ ---------- */

  function animateProgress(prev) {
    var g = gs();
    var ring = $('term-ring');
    if (!g || !ring) return;

    // Прогресс показан дважды: кольцом вокруг рыбы (широкий экран) и
    // полоской внизу (телефон) — двигаем оба, виден один.
    var to = parseFloat(ring.dataset.percent);
    var length = parseFloat(ring.dataset.length);
    if (prev && prev.percent !== null && prev.percent !== to) {
      g.fromTo(ring,
        { attr: { 'stroke-dashoffset': length * (1 - prev.percent / 100) } },
        { attr: { 'stroke-dashoffset': length * (1 - to / 100) }, duration: 0.5, ease: 'power2.out' });

      var bar = $('term-bar');
      if (bar) g.fromTo(bar, { width: prev.percent + '%' }, { width: to + '%', duration: 0.5, ease: 'power2.out' });
    }

    var labels = document.querySelectorAll('[data-progress-label]');
    if (prev && labels.length && prev.label !== null && prev.label !== labels[0].textContent) {
      g.fromTo(labels, { scale: 1.25 }, { scale: 1, duration: 0.4, ease: 'back.out(3)', clearProps: 'transform' });
    }
  }

  function animateCombo(prev) {
    var g = gs();
    var combos = document.querySelectorAll('[data-combo]');
    if (!g || !combos.length) return;

    var now = parseInt(combos[0].dataset.combo, 10) || 0;
    if (now >= 2 && prev && now > prev.combo) {
      g.fromTo(combos, { scale: 0.5, rotation: -8 }, { scale: 1, rotation: 0, duration: 0.45, ease: 'back.out(4)', clearProps: 'transform' });
    }
  }

  /* ---------- смена эмоции рыбы без мигания ----------
     Раньше рыба «мигала»: при каждом ответе блок с ней рендерится заново, и
     новая картинка эмоции пару кадров грузится/декодируется на пустом месте.
     Теперь: (1) все картинки эмоций загружаются заранее; (2) поверх новой
     картинки на время кладётся старая и растворяется, только когда новая
     готова к показу (img.decode()) — эмоции перетекают друг в друга. */

  var preloaded = {};
  function preloadMoods(img) {
    if (!img || !img.dataset.preload) return;
    try {
      JSON.parse(img.dataset.preload).forEach(function (src) {
        if (preloaded[src]) return;
        preloaded[src] = new Image();
        preloaded[src].src = src;
      });
    } catch (e) { /* без предзагрузки просто чуть дольше первая смена */ }
  }

  // Плавно перейти от картинки fromSrc к текущей картинке img. Слой со старой
  // картинкой лежит в том же круге (его родитель позиционирован), поэтому
  // прыжки и наклоны круга двигают обе картинки вместе.
  function crossfadeFrom(img, fromSrc) {
    if (!img || !fromSrc || fromSrc === img.getAttribute('src')) return;

    var layer = document.createElement('img');
    layer.src = fromSrc;
    layer.alt = '';
    layer.className = img.className;
    layer.style.cssText = 'position:absolute;inset:0;pointer-events:none;';
    img.parentElement.appendChild(layer);

    var ready = img.decode ? img.decode() : Promise.resolve();
    ready.catch(function () {}).then(function () {
      var g = gs();
      if (!g) { layer.remove(); return; }
      g.to(layer, { opacity: 0, duration: 0.3, ease: 'power1.out', onComplete: function () { layer.remove(); } });
    });
  }

  function setMood(img, src) {
    var from = img.getAttribute('src');
    if (!src || from === src) return;
    img.src = src;
    crossfadeFrom(img, from);
  }

  function animateMascot(flash, prev) {
    var g = gs();
    var img = $('term-mascot');
    if (!img) return;

    preloadMoods(img);
    // Блок с рыбой отрендерен заново — плавно переходим от той эмоции, что
    // была на экране до ответа.
    if (prev) crossfadeFrom(img, prev.mascotSrc);

    // Радость держится пару секунд. Задумчивость после ошибки (data-hold)
    // не сбрасываем: она уйдёт сама, когда придёт следующее задание.
    clearTimeout(mascotTimer);
    if (img.dataset.hold !== '1' && img.getAttribute('src') !== img.dataset.defaultSrc) {
      mascotTimer = setTimeout(function () { setMood(img, img.dataset.defaultSrc); }, 4000);
    }
    if (!g) return;

    // Двигаем круг, а не саму картинку: вместе с ней едет и слой перехода.
    var circle = img.parentElement;
    if (flash === 'correct' || flash === 'slot') {
      g.fromTo(circle, { y: 0, scale: 1 }, { y: -8, scale: 1.12, duration: 0.18, ease: 'power2.out', yoyo: true, repeat: 1, clearProps: 'transform' });
    } else if (flash === 'wrong') {
      g.fromTo(circle, { rotation: 0 }, { rotation: -10, duration: 0.25, ease: 'power1.inOut', yoyo: true, repeat: 1, clearProps: 'transform' });
    }
  }

  // Квиз: выбранный вариант перелетает в слот, слот «защёлкивается».
  function flyToSlot() {
    var g = gs();
    var slot = document.querySelector('#term-card [data-just-filled]');
    if (!g || !slot) return;

    var to = slot.getBoundingClientRect();

    // «Какой признак подходит?» не перескакивает на следующий слот, а
    // съезжает туда с только что заполненного.
    var active = document.querySelector('#term-card [data-active-slot]');
    if (active) {
      g.fromTo(active, { y: to.top - active.getBoundingClientRect().top }, { y: 0, duration: 0.4, ease: 'power2.inOut', clearProps: 'transform' });
    }

    if (lastOption) {
      // Цвета: вариант взлетает таким, каким был на кнопке (белый), в полёте
      // плавно зеленеет, а приземлившись, так же плавно остывает до обычного
      // вида заполненного слота. Раньше он становился зелёным в момент
      // клика и белым в момент посадки — два резких скачка.
      var WHITE = { backgroundColor: '#ffffff', borderColor: '#d4d4d8', color: '#18181b' };   // как у кнопки варианта
      var GREEN = { backgroundColor: '#ecfdf5', borderColor: '#6ee7b7', color: '#065f46' };   // emerald-50 / 300 / 800

      var chip = document.createElement('div');
      chip.textContent = lastOption.text;
      chip.className = 'rounded-xl border px-4 py-3 text-sm sm:text-base';
      chip.style.cssText = 'position:fixed;margin:0;pointer-events:none;z-index:45;overflow:hidden;box-sizing:border-box;'
        + 'left:' + lastOption.rect.left + 'px;top:' + lastOption.rect.top + 'px;width:' + lastOption.rect.width + 'px;height:' + lastOption.rect.height + 'px;';
      g.set(chip, WHITE);
      document.body.appendChild(chip);

      g.set(slot, { opacity: 0 });
      g.to(chip, {
        left: to.left, top: to.top, width: to.width, height: to.height,
        backgroundColor: GREEN.backgroundColor, borderColor: GREEN.borderColor, color: GREEN.color,
        duration: 0.45, ease: 'power2.inOut',
        onComplete: function () {
          chip.remove();
          // Слот принимает эстафету зелёным и возвращается к своим цветам из
          // классов (clearProps снимает инлайновые значения в конце).
          var own = window.getComputedStyle(slot);
          var rest = { backgroundColor: own.backgroundColor, borderColor: own.borderColor, color: own.color };
          g.set(slot, { opacity: 1 });
          g.fromTo(slot, GREEN, {
            backgroundColor: rest.backgroundColor, borderColor: rest.borderColor, color: rest.color,
            duration: 0.6, delay: 0.15, ease: 'power1.out', clearProps: 'backgroundColor,borderColor,color,opacity'
          });
          g.fromTo(slot, { scale: 1.03 }, { scale: 1, duration: 0.25, ease: 'power2.out', clearProps: 'transform' });
        }
      });
    } else {
      g.fromTo(slot, { scale: 1.03 }, { scale: 1, duration: 0.25, ease: 'power2.out', clearProps: 'transform' });
    }
  }

  function animateAnswer(flash) {
    var g = gs();
    if (!g) return;

    // Квадратики: верно — волна слева направо, мимо — короткое покачивание.
    var result = $('term-a-result');
    if (result && (flash === 'correct' || flash === 'wrong')) {
      var cells = result.querySelectorAll('.term-cell');
      if (result.dataset.correct === '1') {
        g.fromTo(cells, { y: 0, scale: 1 }, { y: -8, scale: 1.12, duration: 0.16, ease: 'power2.out', yoyo: true, repeat: 1, stagger: 0.035, clearProps: 'transform' });
        var check = $('term-a-check');
        if (check) {
          g.fromTo(check, { scale: 0, rotation: -45 }, { scale: 1, rotation: 0, duration: 0.4, delay: 0.1 + cells.length * 0.035, ease: 'back.out(3)', clearProps: 'transform' });
        }
      } else {
        flipToAnswer(result);
      }

      var chips = document.querySelectorAll('#term-card .term-chips > span');
      if (chips.length) {
        g.from(chips, { y: 6, opacity: 0, duration: 0.3, delay: 0.5, stagger: 0.06, ease: 'power2.out', clearProps: 'transform,opacity' });
      }
    }

    // Подсказка: открытая буква проявляется.
    if (flash === 'hint') {
      var box = $('term-a-cells');
      var locked = box ? parseInt(box.dataset.locked, 10) || 0 : 0;
      if (box && locked > 0) {
        g.fromTo(box.querySelectorAll('.term-cell')[locked - 1], { scale: 0.5, rotation: -12 }, { scale: 1, rotation: 0, duration: 0.35, ease: 'back.out(3)', clearProps: 'transform' });
      }
    }

    if (flash === 'slot' || (flash === 'correct' && document.querySelector('#term-card [data-just-filled]'))) {
      flyToSlot();
    }

    // Верный вариант после ответа (в квизе после ошибки, в «лишнем» — всегда).
    var pulse = document.querySelector('#term-card [data-pulse]');
    if (pulse && (flash === 'correct' || flash === 'wrong')) {
      g.fromTo(pulse, { scale: 1 }, { scale: 1.03, duration: 0.18, ease: 'power2.out', yoyo: true, repeat: 1, clearProps: 'transform' });
    }
  }

  /* ---------- рыба говорит ---------- */

  // На телефоне рыбы на экране обычно нет: она выезжает сверху, когда ей
  // есть что сказать, и так же уезжает со следующим заданием (есть ли она в
  // разметке, решает сервер — см. region.blade.php). На широком экране она
  // стоит всегда, там двигается только облачко с репликой.
  function animateHud(prev) {
    var g = gs();
    if (!g || !prev) return;

    var hud = $('term-hud');
    var visible = !!hud && hud.getBoundingClientRect().width > 0;

    if (visible && !prev.hudGhost) {
      g.fromTo(hud, { y: -160, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5, ease: 'back.out(1.4)', clearProps: 'transform,opacity' });
    } else if (!visible && prev.hudGhost) {
      document.body.appendChild(prev.hudGhost);
      g.to(prev.hudGhost, { y: -160, opacity: 0, duration: 0.35, ease: 'power2.in', onComplete: function () { prev.hudGhost.remove(); } });
    }

    var bubble = $('term-bubble');
    if (bubble && visible) {
      g.fromTo(bubble, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, delay: 0.15, ease: 'back.out(2.5)', clearProps: 'transform,opacity' });
    }
  }

  // Квадратики после неверного ответа: буквы ученика по одной
  // переворачиваются в правильные; где он ошибся — клетка на секунду жёлтая.
  function flipToAnswer(result) {
    var g = gs();
    var cells = Array.prototype.slice.call(result.querySelectorAll('.term-cell'));
    var revealed = result.dataset.revealed === '1';
    var amber = ['bg-amber-50', 'border-amber-300', 'text-amber-800'];
    var gray = ['bg-zinc-100', 'border-zinc-300', 'text-zinc-700'];

    cells.forEach(function (cell, i) {
      var answer = cell.textContent;
      var typed = cell.dataset.typed || '';
      var miss = !revealed && typed.toLowerCase().replace('ё', 'е') !== answer.toLowerCase().replace('ё', 'е');

      cell.textContent = typed;
      g.timeline({ delay: 0.25 + i * 0.07 })
        .to(cell, { rotationX: 90, duration: 0.13, ease: 'power1.in' })
        .add(function () {
          cell.textContent = answer;
          if (miss) {
            gray.forEach(function (c) { cell.classList.remove(c); });
            amber.forEach(function (c) { cell.classList.add(c); });
          }
        })
        .to(cell, { rotationX: 0, duration: 0.16, ease: 'power1.out', clearProps: 'transform' })
        .add(function () {
          if (!miss) return;
          amber.forEach(function (c) { cell.classList.remove(c); });
          gray.forEach(function (c) { cell.classList.add(c); });
        }, '+=1.1');
    });
  }

  // На телефоне рыба стоит прямо под шапкой сайта, а когда шапка уехала
  // при прокрутке — у самого верха экрана с небольшим отступом. Шапку она
  // не перекрывает никогда. Шапка не закреплена (прокручивается вместе со
  // страницей), поэтому место считаем от её нижнего края на экране. На
  // широком экране рыба стоит сбоку, там позицию задают классы xl:.
  var HUD_GAP = 8;
  function placeHud() {
    var place = $('term-hud-place');
    if (!place) return;
    if (window.innerWidth >= 1280) {
      place.style.top = '';
      return;
    }
    var row = document.getElementById('site-header-row');
    var header = row ? row.parentElement : null;
    var bottom = header ? header.getBoundingClientRect().bottom : 0;
    place.style.top = (Math.max(bottom, 0) + HUD_GAP) + 'px';
  }

  var hudFrame = null;
  function placeHudSoon() {
    if (hudFrame) return;
    hudFrame = requestAnimationFrame(function () { hudFrame = null; placeHud(); });
  }
  window.addEventListener('scroll', placeHudSoon, { passive: true });
  window.addEventListener('resize', placeHudSoon);

  // Верный ответ не требует «Далее»: экран сам уходит через data-delay.
  function scheduleAutoNext() {
    clearTimeout(autoTimer);
    var el = $('term-auto-next');
    if (!el) return;

    var delay = parseInt(el.dataset.delay, 10) || 1400;
    var go = function () {
      clearTimeout(autoTimer);
      if (document.body.contains(el)) htmx.trigger(el, 'autonext');
    };
    autoTimer = setTimeout(go, delay);

    // Полоска «Следующее задание» заполняется ровно за время ожидания,
    // «Дальше» переходит сразу (partials/next-bar).
    var fill = document.querySelector('#term-card .term-next-fill');
    if (fill) {
      if (gs()) {
        gs().fromTo(fill, { width: '0%' }, { width: '100%', duration: delay / 1000, ease: 'none' });
      } else {
        fill.style.width = '100%';
      }
    }
    var now = document.querySelector('#term-card [data-next-now]');
    if (now) {
      now.addEventListener('click', function () { now.disabled = true; go(); }, { once: true });
    }
  }

  /* ---------- итог: пицца летит к рыбе, за 3 корма — конфетти ---------- */

  function confetti() {
    var g = gs();
    var colors = ['#7C3AED', '#10B981', '#F59E0B', '#3B82F6', '#EC4899'];
    var layer = document.createElement('div');
    layer.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:70;overflow:hidden;';
    document.body.appendChild(layer);

    for (var i = 0; i < 60; i++) {
      (function () {
        var el = document.createElement('div');
        var size = 6 + Math.random() * 6;
        el.style.cssText = 'position:absolute;left:50%;top:22%;width:' + size + 'px;height:' + (size * 0.4) + 'px;border-radius:2px;background:'
          + colors[Math.floor(Math.random() * colors.length)] + ';';
        layer.appendChild(el);

        var dx = (Math.random() - 0.5) * window.innerWidth * 0.8;
        var spin = (Math.random() - 0.5) * 720;
        g.timeline({ onComplete: function () { el.remove(); } })
          .to(el, { x: dx * 0.4, y: -80 - Math.random() * 80, rotation: spin * 0.3, duration: 0.35 + Math.random() * 0.15, ease: 'power2.out' })
          .to(el, { x: dx, y: window.innerHeight * (0.5 + Math.random() * 0.5), rotation: spin, opacity: 0, duration: 1.1 + Math.random() * 0.6, ease: 'power1.in' });
      })();
    }
    setTimeout(function () { layer.remove(); }, 2600);
  }

  function animateResult() {
    var g = gs();
    var box = $('term-result');
    var mascot = $('term-result-mascot');
    if (!g || !box || !mascot) return;

    g.from(box, { y: 16, opacity: 0, duration: 0.4, ease: 'power2.out', clearProps: 'transform,opacity' });
    g.fromTo(mascot, { scale: 0.5, rotation: -8 }, { scale: 1, rotation: 0, duration: 0.6, ease: 'elastic.out(1, .5)', clearProps: 'transform' });

    // Кружки по терминам — по одному, как будто итог подсчитывается.
    var dots = box.querySelectorAll('[data-result-dot]');
    if (dots.length) {
      g.from(dots, { scale: 0, duration: 0.35, delay: 0.25, stagger: 0.08, ease: 'back.out(3)', clearProps: 'transform' });
    }
    var bubble = $('term-result-say');
    if (bubble) {
      g.from(bubble, { scale: 0.7, opacity: 0, duration: 0.35, delay: 0.2, ease: 'back.out(2.5)', transformOrigin: '0% 50%', clearProps: 'transform,opacity' });
    }

    var corm = parseInt(box.dataset.corm, 10) || 0;
    var badge = $('term-result-corm');
    if (corm <= 0 || !badge) return;

    var eaten = 0;
    for (var i = 0; i < corm; i++) {
      (function (n) {
        var pizza = document.createElement('img');
        pizza.src = PIZZA_SRC;
        pizza.alt = '';
        pizza.style.cssText = 'position:fixed;width:28px;height:28px;pointer-events:none;z-index:60;opacity:0;';
        document.body.appendChild(pizza);

        g.delayedCall(0.6 + n * 0.28, function () {
          // Координаты берём в момент старта: страница могла прокрутиться.
          var from = badge.getBoundingClientRect();
          var to = mascot.getBoundingClientRect();
          g.set(pizza, { left: from.left + 8, top: from.top + 6, opacity: 1 });
          g.to(pizza, {
            left: to.left + to.width / 2 - 14,
            top: to.top + to.height / 2 - 14,
            rotation: 360,
            scale: 0.6,
            duration: 0.55,
            ease: 'power2.in',
            onComplete: function () {
              pizza.remove();
              setMood(mascot, mascot.dataset.eatingSrc);
              g.fromTo(mascot, { scale: 1 }, { scale: 1.12, duration: 0.12, yoyo: true, repeat: 1, clearProps: 'transform' });
              if (navigator.vibrate) navigator.vibrate(10);

              if (++eaten === corm) {
                setTimeout(function () { setMood(mascot, mascot.dataset.happySrc); }, 700);
                if (corm >= 3) confetti();
              }
            }
          });
        });
      })(i);
    }
  }

  /* ---------- вход на экран ---------- */

  // prev === null — первая загрузка страницы: анимаций «после ответа» нет,
  // иначе обновление страницы проигрывало бы их заново.
  // Экран не должен прыгать, пока ученик остаётся на той же карточке.
  // Когда после ответа из карточки что-то пропадает (например, варианты в
  // конце квиза), страница становится короче, и браузер сам сбрасывает
  // прокрутку вверх — всё содержимое скачет вниз, а анимации, которые целятся
  // в старые координаты, промахиваются (вариант «летел наверх, а потом
  // оказывался внизу»). Поэтому на той же карточке держим её прежнюю высоту
  // до конца её жизни и возвращаем прокрутку — до первой отрисовки, так что
  // прыжка не видно. Новая карточка приходит новым элементом (стиль уходит
  // вместе со старой) и открывается с начала страницы.
  function keepPlace(prev, card) {
    if (!prev || prev.scrollY === undefined) return;

    if (!card || card.dataset.key !== prev.key) {
      if (card) window.scrollTo(0, 0);
      return;
    }

    // Своя высота карточки после ответа (без фиксации).
    card.style.minHeight = '';
    var natural = card.getBoundingClientRect().height;
    var pageAfter = document.documentElement.scrollHeight;

    if (prev.cardHeight && prev.cardHeight > natural + 4) {
      card.style.minHeight = prev.cardHeight + 'px';
      window.scrollTo(0, prev.scrollY);

      // Карточка стала короче (например, ответ в квадратиках короче формы
      // ввода). Если её можно поджать без сброса прокрутки — экран и так
      // помещается в укороченную страницу, — плавно поджимаем, чтобы внизу
      // не висело пустое место. Если поджатие сдвинуло бы прокрутку (ученик
      // внизу длинной страницы), оставляем прежнюю высоту: прыжок экрана
      // хуже пустого места, и через пару секунд карточка всё равно улетит.
      var fits = prev.scrollY + window.innerHeight <= pageAfter + 1;
      if (fits && gs()) {
        gs().to(card, {
          minHeight: natural, duration: 0.4, delay: 0.35, ease: 'power2.inOut',
          onComplete: function () { card.style.minHeight = ''; }
        });
      }
      return;
    }

    window.scrollTo(0, prev.scrollY);
  }

  // Фото термина в той же карточке сменилось (в квадратиках после ответа
  // размытое проявляется в чёткое): пока чёткое грузится, на его месте был
  // пустой серый прямоугольник. Теперь поверх лежит прежнее размытое и
  // растворяется, когда чёткое готово к показу.
  function crossfadePhoto(prev, card) {
    if (!prev || !prev.photoSrc || !card || card.dataset.key !== prev.key) return;
    var photo = card.querySelector('img[id^="term-photo-"]');
    if (!photo || photo.getAttribute('src') === prev.photoSrc) return;

    var box = photo.getBoundingClientRect();
    var frame = card.getBoundingClientRect();
    var layer = document.createElement('img');
    layer.src = prev.photoSrc;
    layer.alt = '';
    layer.className = photo.className;
    layer.style.cssText = 'position:absolute;margin:0;pointer-events:none;z-index:1;'
      + 'left:' + (box.left - frame.left) + 'px;top:' + (box.top - frame.top) + 'px;width:' + box.width + 'px;height:' + box.height + 'px;';
    card.appendChild(layer);

    var ready = photo.decode ? photo.decode() : Promise.resolve();
    ready.catch(function () {}).then(function () {
      var g = gs();
      if (!g) { layer.remove(); return; }
      g.to(layer, { opacity: 0, duration: 0.5, ease: 'power1.out', onComplete: function () { layer.remove(); } });
    });
  }

  function enter(prev) {
    keepPlace(prev, $('term-card'));
    placeHud();
    crossfadePhoto(prev, $('term-card'));
    initSquares();
    preloadMoods($('term-mascot'));
    var resultMascot = $('term-result-mascot');
    if (resultMascot) {
      [resultMascot.dataset.eatingSrc, resultMascot.dataset.happySrc].forEach(function (src) {
        if (src && !preloaded[src]) { preloaded[src] = new Image(); preloaded[src].src = src; }
      });
    }

    var card = $('term-card');
    var flash = card ? card.dataset.flash : null;

    if (prev) {
      var changed = !card || card.dataset.key !== prev.key;
      if (prev.ghost && changed && gs()) {
        // Верно — карточка улетает вправо, мимо или «ещё не запомнил» — влево.
        throwCard(prev.ghost, flash === 'again' ? -1 : 1, card);
      }

      // Квиз пройден: оставшиеся варианты не исчезают, а плавно гаснут.
      var faded = card && !changed ? card.querySelectorAll('.term-option-faded') : [];
      if (faded.length && gs()) {
        gs().from(faded, { color: '#18181b', borderColor: '#d4d4d8', duration: 0.6, ease: 'power1.out', clearProps: 'color,borderColor' });
      }

      animateProgress(prev);
      animateCombo(prev);
      animateMascot(flash, prev);
      animateHud(prev);
      animateAnswer(flash);
      if ((flash === 'correct' || flash === 'slot') && navigator.vibrate) navigator.vibrate(12);
      if (!card) animateResult();
    }

    // Срабатывает и при обычной загрузке страницы: обновление на решённом
    // задании не должно оставлять экран без кнопки и без перехода.
    scheduleAutoNext();

    // Enter ведёт дальше; поле квадратиков фокус забирает само.
    var focusTarget = document.querySelector('#term-card [data-autofocus]');
    if (focusTarget && !$('term-a-input')) focusTarget.focus({ preventScroll: true });
    lastOption = null;
  }

  // Захват клика до htmx: запоминаем, откуда полетит вариант.
  document.addEventListener('click', function (evt) {
    var option = evt.target.closest ? evt.target.closest('.term-option') : null;
    if (option) lastOption = { rect: option.getBoundingClientRect(), text: option.textContent.trim() };
  }, true);

  document.body.addEventListener('htmx:beforeSwap', function (evt) {
    if (evt.detail.target !== app) return;
    clearTimeout(autoTimer);
    typingBefore = document.activeElement && document.activeElement.id === 'term-a-input';
    // Поле квадратиков уходит вместе со шагом, blur может не прийти.
    var kbSpacer = $('term-keyboard-spacer');
    if (kbSpacer && !typingBefore) kbSpacer.style.height = '0px';
    before = snapshot();
  });
  document.body.addEventListener('htmx:afterSwap', function (evt) {
    if (evt.detail.target !== app) return;
    enter(before || { key: null, percent: null, combo: 0, label: null, ghost: null, hudGhost: null });
    before = null;
  });

  // GSAP и htmx подключены с defer — к DOMContentLoaded они уже выполнены.
  document.addEventListener('DOMContentLoaded', function () {
    // htmx после подмены «досаживает» (settle) атрибуты class/style/width/
    // height у элементов с тем же id: сначала ставит старые значения, через
    // 20 мс — значения из ответа сервера. Здесь это вредило: стирало
    // зафиксированную высоту карточки (экран прыгал вверх) и обрывало
    // анимации кольца, полоски прогресса и рыбы на полпути. Анимации этой
    // страницы делает GSAP, поэтому досадка не нужна. Настройка действует
    // только на страницах, где подключён этот скрипт.
    if (window.htmx) htmx.config.attributesToSettle = [];
    enter(null);
  });
})();
</script>
@endpush
