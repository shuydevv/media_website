{{-- resources/views/student/statements/session.blade.php
     Сессия «Верных суждений». Карточки приходят в $boot (без ответов),
     ответ уходит POST'ом на answer и возвращает разбор; итог сессии — в
     ответе на последнюю карточку. Вся отрисовка — скрипт ниже, без сборки.

     Управление: телефон — свайп карточки (вправо — «Верно», влево —
     «Неверно») или кнопки; компьютер — кнопки или ← / →, ↓ — «Не знаю»,
     Enter — «Дальше». Стили — свои (<style>), не Tailwind: экран рисуется
     скриптом, и новые классы не должны зависеть от пересборки CSS. --}}
@extends('layouts.main')

@section('title', 'Верные суждения — Школа Полтавского')

@section('back_url', route('student.statements.index'))

@section('content')
{{-- {{ }} экранирует JSON для атрибута: в суждениях бывают кавычки и апострофы. --}}
<div id="st-app" class="st" data-boot="{{ json_encode($boot, JSON_UNESCAPED_UNICODE) }}">
  <div class="st-top">
    <div class="st-progress"><div class="st-progress-bar" id="st-bar"></div></div>
    <div class="st-count" id="st-count"></div>
  </div>
  <div id="st-stage"></div>
  <noscript><div class="st-note">Для тренажёра нужен JavaScript.</div></noscript>
</div>
@endsection

@push('page-scripts')
<style>
  .st { max-width: 640px; margin: 0 auto; padding: 16px 16px 40px; color: #18181b; }
  .st-top { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  .st-progress { flex: 1; height: 8px; border-radius: 99px; background: #e4e4e7; overflow: hidden; }
  .st-progress-bar { height: 100%; width: 0; background: #2563eb; border-radius: 99px; }
  .st-count { display: inline-block; font-size: 14px; color: #71717a; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .st-note { padding: 16px; text-align: center; color: #71717a; }

  .st-speaker { display: flex; align-items: flex-end; gap: 10px; margin-bottom: 10px; }
  .st-avatar { width: 64px; height: 64px; flex: none; object-fit: contain; }
  .st-avatar-emoji { width: 64px; height: 64px; flex: none; font-size: 44px; line-height: 64px; text-align: center; }
  .st-bubble { position: relative; background: #fff; border: 1px solid #e4e4e7; border-radius: 16px 16px 16px 4px; padding: 8px 12px; font-size: 14px; color: #3f3f46; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
  .st-bubble b { display: block; font-size: 12px; font-weight: 600; margin-bottom: 2px; }

  .st-card { position: relative; background: #fff; border: 1px solid #e4e4e7; border-radius: 20px; padding: 20px; box-shadow: 0 6px 24px rgba(37, 99, 235, .08); touch-action: pan-y; user-select: none; overflow: hidden; transition: border-color .3s, background-color .3s; }
  /* transform и высоту карточки двигает GSAP — CSS-переходов на них нет, иначе дёргается. */
  .st-card.dragging { transition: none; cursor: grabbing; }
  .st-card.is-right { border-color: #86efac; background: #f0fdf4; }
  .st-card.is-wrong { border-color: #fcd34d; background: #fffbeb; }
  .st-about { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; margin-bottom: 8px; }
  .st-text { font-size: 19px; line-height: 1.45; }
  .st-hint { position: absolute; top: 14px; padding: 2px 10px; border-radius: 99px; font-size: 13px; font-weight: 600; opacity: 0; }
  .st-hint.yes { right: 14px; background: #dcfce7; color: #15803d; }
  .st-hint.no { left: 14px; background: #fee2e2; color: #b91c1c; }

  .st-answers { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; }
  /* Вид кнопок — «игровой», общий для тренажёров: partials/game-buttons. Здесь — только размер. */
  .st-btn { min-height: 52px; padding: 10px 16px; }
  .st-btn-main { width: 100%; }
  .st-btn-ghost { width: 100%; }
  .st-skip { display: block; margin: 10px auto 0; background: none; border: 0; color: #71717a; font-size: 15px; padding: 8px 12px; cursor: pointer; }
  .st-skip:hover { color: #18181b; }
  /* Выбранный ответ — заливка цветом кнопки (вместо наклона карточки). */
  .st-btn-yes.is-chosen { background: #16a34a; border-color: #16a34a; color: #fff; }
  .st-btn-no.is-chosen { background: #dc2626; border-color: #dc2626; color: #fff; }
  .st-skip.is-chosen { color: #18181b; font-weight: 600; }
  .st-btn.is-chosen:disabled, .st-skip.is-chosen:disabled { opacity: 1; }
  .st-keys { margin-top: 10px; text-align: center; font-size: 12px; color: #a1a1aa; }
  .st-keys kbd { display: inline-block; min-width: 22px; padding: 1px 6px; border: 1px solid #d4d4d8; border-bottom-width: 2px; border-radius: 6px; background: #fff; font-family: inherit; color: #52525b; }
  @media (hover: none) { .st-keys { display: none; } }

  .st-verdict { display: flex; align-items: center; gap: 8px; margin-top: 14px; font-weight: 600; }
  .st-verdict-icon { display: inline-block; }
  .st-verdict.ok { color: #15803d; }
  .st-verdict.bad { color: #b45309; }
  .st-explain { margin-top: 10px; font-size: 15px; line-height: 1.5; color: #3f3f46; white-space: pre-wrap; }
  .st-correction { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: #eff6ff; color: #1e3a8a; font-size: 15px; }
  .st-correction b { display: block; font-size: 12px; margin-bottom: 2px; }
  .st-shark { display: flex; align-items: flex-end; gap: 10px; margin-top: 14px; }
  .st-shark img { width: 56px; height: 56px; object-fit: contain; flex: none; }
  /* Серия верных ответов — оранжево-красная плашка с бликом. */
  .st-combo { position: relative; overflow: hidden; display: table; margin-top: 6px; padding: 3px 11px; border-radius: 99px; background: linear-gradient(90deg, #f97316, #ef4444); color: #fff; font-size: 13px; font-weight: 600; }
  .st-combo span { position: relative; }
  .st-combo-shine { position: absolute; top: 0; left: 0; width: 40%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent); transform: translateX(-120%) skewX(-20deg); pointer-events: none; }
  /* Маскот с репликой слева, «Дальше» — справа в той же строке. */
  .st-after { display: flex; align-items: flex-end; gap: 12px; margin-top: 14px; }
  .st-after .st-shark { flex: 1; min-width: 0; margin-top: 0; }
  .st-next { flex: none; width: auto; min-width: 150px; padding: 0 28px; }
  .st-report { display: block; margin: 12px auto 0; background: none; border: 0; color: #a1a1aa; font-size: 13px; cursor: pointer; text-decoration: underline; }

  /* Итог — «стекло»: полупрозрачная заливка, лёгкое размытие узора под ним,
     светлая обводка и блик сверху (автор: сплошной белый хуже, без заливки — пусто). */
  .st-result-head { text-align: center; margin: 0 0 12px; padding: 18px 16px 20px; border-radius: 20px;
    background: linear-gradient(160deg, rgba(255, 255, 255, .3), rgba(255, 255, 255, .12));
    border: 1px solid rgba(255, 255, 255, .7);
    box-shadow: 0 8px 28px rgba(37, 99, 235, .08), inset 0 1px 0 rgba(255, 255, 255, .9), inset 0 0 0 1px rgba(228, 228, 231, .6);
    -webkit-backdrop-filter: blur(2px) saturate(1.15); backdrop-filter: blur(2px) saturate(1.15); }
  /* Без поддержки размытия — просто светлее, чтобы текст читался. */
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) { .st-result-head { background: rgba(255, 255, 255, .85); } }
  /* Картинки в проекте блочные (Tailwind preflight), поэтому акула с
     репликой — во флекс-ряду, иначе акула уезжала влево отдельной строкой. */
  .st-result-who { display: flex; align-items: flex-end; justify-content: center; gap: 10px; }
  .st-result-who img { width: 88px; height: 88px; object-fit: contain; flex: none; }
  .st-result-who .st-bubble { max-width: 320px; text-align: left; margin-bottom: 14px; }
  .st-corm-plus, .st-corm { justify-content: center; }
  .st-result-tag { display: inline-block; margin-bottom: 10px; padding: 3px 12px; border-radius: 99px; background: #f4f4f5; color: #52525b; font-size: 13px; font-weight: 600; }
  .st-ring { position: relative; width: 168px; height: 168px; margin: 10px auto 4px; }
  .st-ring svg { width: 100%; height: 100%; display: block; }
  .st-ring-track { fill: none; stroke: #e4e4e7; stroke-width: 8; }
  .st-ring-fill { fill: none; stroke-width: 8; stroke-linecap: round; }
  .st-ring-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  /* Счёт: верные — крупно, «/ 15» — мельче и серым, иначе «14 / 15» не влезало в кольцо. */
  .st-score { display: flex; align-items: baseline; gap: 3px; line-height: 1; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .st-score-n { font-size: 40px; letter-spacing: -0.02em; }
  .st-score-of { font-size: 18px; color: #a1a1aa; font-weight: 600; }
  .st-ring-label { font-size: 12px; color: #71717a; margin-top: 2px; }
  .st-corm { margin-top: 4px; color: #52525b; }
  .st-corm-note { font-size: 14px; color: #71717a; }
  .st-corm-plus { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 99px; background: #fff7ed; color: #c2410c; font-weight: 600; }
  .st-corm-plus img { width: 20px; height: 20px; }
  .st-section { background: #fff; border: 1px solid #e4e4e7; border-radius: 16px; padding: 14px 16px; margin-top: 12px; }
  .st-section h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #a1a1aa; margin-bottom: 8px; font-weight: 600; }
  .st-section-note { margin: -4px 0 4px; font-size: 13px; color: #71717a; }
  /* Строка раздела: эмодзи, название, полоска на всю свободную ширину, счёт, статус. */
  .st-row { display: flex; align-items: center; gap: 10px; font-size: 14px; margin: 8px 0; }
  .st-row-emoji { flex: none; width: 22px; text-align: center; font-size: 17px; }
  .st-row-title { flex: none; min-width: 0; max-width: 45%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .st-meter { flex: 1; min-width: 40px; height: 8px; border-radius: 99px; background: #e4e4e7; overflow: hidden; }
  .st-meter i { display: block; height: 100%; border-radius: 99px; background: #2563eb; }
  .st-row-count { flex: none; width: 34px; text-align: right; color: #52525b; font-variant-numeric: tabular-nums; }
  .st-row-status { flex: none; width: 20px; text-align: center; }
  .st-mistake { padding: 10px 0; border-top: 1px solid #f4f4f5; }
  .st-mistake:first-of-type { border-top: 0; }
  .st-mistake .t { font-size: 15px; }
  .st-mistake .v { font-size: 13px; font-weight: 600; margin-top: 4px; }
  .st-mistake .e { font-size: 14px; color: #52525b; margin-top: 2px; white-space: pre-wrap; }
  .st-actions { display: grid; gap: 10px; margin-top: 16px; }
  /* ПК — кнопки итога в один ряд, телефон — столбиком. */
  @media (min-width: 640px) { .st-actions { grid-auto-flow: column; grid-auto-columns: 1fr; } }
  .st-actions .st-btn { width: 100%; }
  @media (prefers-reduced-motion: reduce) { .st-card { transition: none; } }
</style>
<script>
/*
 * Экран сессии «Верных суждений». Анимация — GSAP, как в тренажёре терминов
 * (student/terms/partials/session-script): GSAP подключён в layouts/main с
 * defer, поэтому всё стартует на DOMContentLoaded. Без GSAP (CDN не
 * ответил) или при «уменьшить движение» в системе экран работает так же,
 * просто без движения: каждая анимация начинается с `var g = gs(); if (!g)`.
 */
(function () {
  var root = document.getElementById('st-app');
  if (!root) return;
  var boot = JSON.parse(root.getAttribute('data-boot'));
  var stage = document.getElementById('st-stage');
  var bar = document.getElementById('st-bar');
  var counter = document.getElementById('st-count');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

  var position = boot.position;
  var total = boot.total;
  var busy = false;
  var shownAt = 0;
  var onKey = null;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function gs() { return !reduced && typeof window.gsap !== 'undefined' ? window.gsap : null; }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') node.textContent = attrs[k];
      else if (k === 'class') node.className = attrs[k];
      else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2), attrs[k]);
      else node.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }

  function vibrate(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } }

  /* ---------- прогресс ---------- */

  var shownDone = null;
  function progress(done) {
    var to = Math.round(100 * done / total);
    counter.textContent = Math.min(done + 1, total) + ' / ' + total;
    var g = gs();
    if (!g || shownDone === null) {
      bar.style.width = to + '%';
    } else if (done !== shownDone) {
      g.to(bar, { width: to + '%', duration: 0.5, ease: 'power2.out' });
      g.fromTo(counter, { scale: 1.25 }, { scale: 1, duration: 0.4, ease: 'back.out(3)', clearProps: 'transform' });
    }
    shownDone = done;
  }

  /* ---------- персонаж ---------- */

  function avatar(who, mood) {
    var c = boot.characters[who];
    if (c && c.avatar) return el('img', { class: 'st-avatar', src: c.avatar + '-' + mood + '.webp', alt: c.name });
    return el('div', { class: 'st-avatar-emoji', text: c ? c.emoji : '🐟' });
  }

  // Эмоции персонажа заранее — чтобы смена лица после ответа не мигала.
  function preloadMoods(who) {
    var c = boot.characters[who];
    if (!c || !c.avatar) return;
    ['happy', 'sad'].forEach(function (m) { var i = new Image(); i.src = c.avatar + '-' + m + '.webp'; });
  }

  function speaker(card, mood, line) {
    var c = boot.characters[card.who] || { name: '', color: '#71717a' };
    var name = el('b', { text: c.name });
    name.style.color = c.color;
    var bubble = el('div', { class: 'st-bubble' }, [name, el('span', { class: 'st-line', text: line })]);
    return el('div', { class: 'st-speaker' }, [avatar(card.who, mood), bubble]);
  }

  /* ---------- сеть, клавиши ---------- */

  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(data),
      credentials: 'same-origin'
    }).then(function (r) {
      if (r.status === 409) { window.location.reload(); throw new Error('stale'); }
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function setKeys(handler) { onKey = handler; }
  document.addEventListener('keydown', function (e) {
    if (!onKey || e.altKey || e.ctrlKey || e.metaKey || e.repeat) return;
    if (e.target && /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
    if (onKey(e.key)) e.preventDefault();
  });

  /* ---------- переход между карточками ---------- */

  // Старая карточка улетает влево копией поверх страницы, а новая уже
  // въезжает на её место — как смена карточки в тренажёре терминов.
  function throwAway(node) {
    var g = gs();
    if (!g || !node || !node.isConnected) return;
    var rect = node.getBoundingClientRect();
    var ghost = node.cloneNode(true);
    ghost.style.cssText = 'position:fixed;margin:0;z-index:60;pointer-events:none;left:' + rect.left + 'px;top:' + rect.top
      + 'px;width:' + rect.width + 'px;height:' + rect.height + 'px;';
    document.body.appendChild(ghost);
    g.timeline({ onComplete: function () { ghost.remove(); } })
      .to(ghost, { x: -window.innerWidth * 0.9, y: -30, rotation: -14, duration: 0.45, ease: 'power2.in' })
      .to(ghost, { opacity: 0, duration: 0.15, ease: 'power1.in' }, 0.3);
  }

  function enterCard(parts) {
    var g = gs();
    if (!g) return;
    var tl = g.timeline();
    tl.fromTo(parts.avatar, { scale: 0.4, y: 12, opacity: 0 }, { scale: 1, y: 0, opacity: 1, duration: 0.5, ease: 'back.out(2.2)', clearProps: 'transform,opacity' }, 0.05)
      .fromTo(parts.bubble, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)', transformOrigin: '0% 100%', clearProps: 'transform,opacity' }, 0.18)
      .fromTo(parts.card, { y: 26, scale: 0.96, opacity: 0 }, { y: 0, scale: 1, opacity: 1, duration: 0.45, ease: 'power3.out', clearProps: 'transform,opacity' }, 0)
      .fromTo(parts.controls, { y: 12, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.05, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.2);
  }

  /* ---------- карточка ---------- */

  function showCard() {
    var card = boot.cards[position];
    progress(position);
    preloadMoods(card.who);
    stage.innerHTML = '';

    var cardNode = el('div', { class: 'st-card' }, [
      el('div', { class: 'st-hint no', text: 'Неверно' }),
      el('div', { class: 'st-hint yes', text: 'Верно' }),
      el('div', { class: 'st-about', text: card.about ? 'Суждение ' + card.about : (boot.blockTitles[card.block] || 'Суждение') }),
      el('div', { class: 'st-text', text: card.text })
    ]);
    var no = el('button', { class: 'st-btn st-btn-no', type: 'button', text: 'Неверно', onclick: function () { send('false', no); } });
    var yes = el('button', { class: 'st-btn st-btn-yes', type: 'button', text: 'Верно', onclick: function () { send('true', yes); } });
    var skip = el('button', { class: 'st-skip', type: 'button', text: '🤷 Не знаю', onclick: function () { send('unknown', skip); } });
    var answers = el('div', { class: 'st-answers' }, [no, yes]);
    var keys = el('div', { class: 'st-keys' });
    keys.innerHTML = '<kbd>←</kbd> неверно · <kbd>→</kbd> верно · <kbd>↓</kbd> не знаю';
    var who = speaker(card, 'idle', card.intro);

    stage.appendChild(who);
    stage.appendChild(cardNode);
    stage.appendChild(answers);
    stage.appendChild(skip);
    stage.appendChild(keys);

    enterCard({ avatar: who.firstChild, bubble: who.lastChild, card: cardNode, controls: [no, yes, skip, keys] });

    var drag = swipe(cardNode);
    shownAt = Date.now();
    busy = false;
    var keyHandler = function (key) {
      if (key === 'ArrowLeft') { send('false', no); return true; }
      if (key === 'ArrowRight') { send('true', yes); return true; }
      if (key === 'ArrowDown') { send('unknown', skip); return true; }
      return false;
    };
    setKeys(keyHandler);

    function send(answer, button) {
      if (busy) return;
      busy = true;
      setKeys(null);
      [no, yes, skip].forEach(function (b) { b.disabled = true; });

      // Отклик сразу, не дожидаясь сервера: выбранная кнопка заливается своим
      // цветом и проминается, остальные бледнеют. Карточку не наклоняем
      // (автор: наклон при клике не нравится); при свайпе она и так у пальца.
      var others = [no, yes, skip].filter(function (b) { return b !== button; });
      button.classList.add('is-chosen');
      var g = gs();
      if (g) {
        g.fromTo(button, { scale: 1 }, { scale: 0.95, duration: 0.09, yoyo: true, repeat: 1, ease: 'power1.inOut', clearProps: 'transform' });
        g.to(others, { opacity: 0.35, duration: 0.15, ease: 'power1.out' });
      } else {
        others.forEach(function (b) { b.style.opacity = 0.35; });
      }

      post(boot.answerUrl, { position: position, answer: answer, ms: Date.now() - shownAt })
        .then(function (res) { feedback(card, { who: who, card: cardNode, answers: answers, skip: skip, keys: keys }, res); })
        .catch(function (err) {
          if (err.message === 'stale') return;
          busy = false;
          [no, yes, skip].forEach(function (b) { b.disabled = false; b.classList.remove('is-chosen'); b.style.opacity = ''; });
          setKeys(keyHandler);
          drag.reset();
          alert('Не получилось отправить ответ. Проверь интернет и попробуй ещё раз.');
        });
    }

    // Свайп: тянем карточку, дальше порога — ответ; не дотянул — пружиной назад.
    function swipe(node) {
      var startX = null, dx = 0, hintYes = node.querySelector('.st-hint.yes'), hintNo = node.querySelector('.st-hint.no');
      function place(x) {
        var g = gs();
        if (g) g.set(node, { x: x, rotation: x / 25 });
        else node.style.transform = 'translateX(' + x + 'px) rotate(' + (x / 25) + 'deg)';
      }
      function reset() {
        var g = gs();
        if (g) g.to(node, { x: 0, y: 0, rotation: 0, duration: 0.6, ease: 'elastic.out(1, 0.55)' });
        else node.style.transform = '';
      }
      node.addEventListener('pointerdown', function (e) {
        if (busy || (e.pointerType === 'mouse' && e.button !== 0)) return;
        startX = e.clientX; dx = 0;
        var g = gs();
        if (g) g.killTweensOf(node);
        node.classList.add('dragging');
        node.setPointerCapture(e.pointerId);
      });
      node.addEventListener('pointermove', function (e) {
        if (startX === null) return;
        dx = e.clientX - startX;
        place(dx);
        hintYes.style.opacity = Math.max(0, Math.min(1, dx / 90));
        hintNo.style.opacity = Math.max(0, Math.min(1, -dx / 90));
      });
      function end() {
        if (startX === null) return;
        startX = null;
        node.classList.remove('dragging');
        hintYes.style.opacity = 0; hintNo.style.opacity = 0;
        if (Math.abs(dx) > 90) {
          var side = dx > 0 ? 1 : -1;
          var g = gs();
          if (g) g.to(node, { x: side * 40, rotation: side * 4, duration: 0.2, ease: 'power2.out' });
          send(side > 0 ? 'true' : 'false', side > 0 ? yes : no);
        } else {
          reset();
        }
      }
      node.addEventListener('pointerup', end);
      node.addEventListener('pointercancel', end);
      return { reset: reset };
    }
  }

  /* ---------- разбор ---------- */

  function feedback(card, parts, res) {
    var r = res.result;
    var g = gs();
    var cardNode = parts.card;
    progress(position + 1);
    vibrate(r.correct ? 8 : [20, 40, 20]);

    // Убираем кнопки ответа, ниже появятся разбор и «Дальше».
    var leaving = [parts.answers, parts.skip, parts.keys];
    if (g) {
      g.to(leaving, { y: 8, opacity: 0, duration: 0.18, ease: 'power1.in', onComplete: function () { leaving.forEach(function (n) { n.remove(); }); } });
    } else {
      leaving.forEach(function (n) { n.remove(); });
    }

    // Персонаж: прав, если суждение верное — радуется и подпрыгивает;
    // ошибся — грустит и качает головой. Реплика сменяется «пузырём».
    var face = parts.who.firstChild;
    var line = parts.who.querySelector('.st-line');
    var mood = r.is_true ? 'happy' : 'sad';
    if (face.tagName === 'IMG') face.src = boot.characters[card.who].avatar + '-' + mood + '.webp';
    line.textContent = r.reaction;
    if (g) {
      if (r.is_true) g.fromTo(face, { y: 0, scale: 1 }, { y: -10, scale: 1.12, duration: 0.18, ease: 'power2.out', yoyo: true, repeat: 1, clearProps: 'transform' });
      else g.fromTo(face, { rotation: 0 }, { rotation: -10, duration: 0.22, ease: 'power1.inOut', yoyo: true, repeat: 1, clearProps: 'transform' });
      g.fromTo(parts.who.lastChild, { scale: 0.85 }, { scale: 1, duration: 0.35, ease: 'back.out(3)', transformOrigin: '0% 100%', clearProps: 'transform' });
    }

    // Разбор дописывается в карточку; высота карточки растёт плавно.
    var before = cardNode.offsetHeight;
    var verdict = el('div', { class: 'st-verdict ' + (r.correct ? 'ok' : 'bad') }, [
      el('span', { class: 'st-verdict-icon', text: r.is_true ? '✅' : '❌' }),
      document.createTextNode(r.is_true ? 'Суждение верное' : 'Суждение неверное')
    ]);
    var added = [verdict];
    if (r.explanation) added.push(el('div', { class: 'st-explain', text: r.explanation }));
    if (r.correction) added.push(el('div', { class: 'st-correction' }, [el('b', { text: boot.correctionLabel }), document.createTextNode(r.correction)]));
    added.forEach(function (n) { cardNode.appendChild(n); });
    cardNode.classList.add(r.correct ? 'is-right' : 'is-wrong');

    var sharkImg = el('img', { src: boot.shark + '-' + (r.correct ? 'happy' : 'think') + '.webp', alt: '' });
    var sharkLine = el('div', { class: 'st-bubble' }, [document.createTextNode(r.say)]);
    var combo = r.combo ? el('div', { class: 'st-combo' }, [el('span', { text: r.combo }), el('i', { class: 'st-combo-shine' })]) : null;
    if (combo) sharkLine.appendChild(combo);
    var shark = el('div', { class: 'st-shark' }, [sharkImg, sharkLine]);
    var next = el('button', { class: 'st-btn st-btn-main st-next', type: 'button', text: res.finished ? 'К итогу' : 'Дальше', onclick: go });
    var report = reportButton(r.statement_id);
    stage.appendChild(el('div', { class: 'st-after' }, [shark, next]));
    stage.appendChild(report);

    if (g) {
      var after = cardNode.offsetHeight;
      var tl = g.timeline();
      // Карточка возвращается на место пружиной (после кивка или свайпа).
      // Верно — пружиной на место и «вдох»; ошибка — быстро на место и встряска.
      if (r.correct) {
        tl.to(cardNode, { x: 0, y: 0, rotation: 0, duration: 0.55, ease: 'elastic.out(1, 0.6)' }, 0)
          .fromTo(cardNode, { scale: 1 }, { scale: 1.03, duration: 0.16, ease: 'power2.out', yoyo: true, repeat: 1 }, 0.05);
      } else {
        tl.to(cardNode, { x: 0, y: 0, rotation: 0, duration: 0.15, ease: 'power2.out' }, 0)
          .to(cardNode, { keyframes: { x: [0, -10, 9, -7, 5, -2, 0] }, duration: 0.45, ease: 'power1.out' }, 0.15);
      }
      tl.fromTo(cardNode, { height: before }, { height: after, duration: 0.35, ease: 'power2.inOut', clearProps: 'height' }, 0.1)
        .fromTo(verdict.firstChild, { scale: 0, rotation: -45 }, { scale: 1, rotation: 0, duration: 0.45, ease: 'back.out(3)', clearProps: 'transform' }, 0.2)
        .fromTo(added, { y: 8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.08, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.18)
        .fromTo(sharkImg, { scale: 0.5, y: 10, opacity: 0 }, { scale: 1, y: 0, opacity: 1, duration: 0.5, ease: 'back.out(2.2)', clearProps: 'transform,opacity' }, 0.3)
        .fromTo(sharkLine, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)', transformOrigin: '0% 100%', clearProps: 'transform,opacity' }, 0.4)
        .fromTo([next, report], { y: 12, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.06, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.45);
      if (combo) {
        // Серия — «огненная» плашка: влетает пружиной, по ней пробегает
        // блик, вверх вылетают огоньки.
        tl.fromTo(combo, { scale: 0, rotation: -8 }, { scale: 1, rotation: 0, duration: 0.7, ease: 'elastic.out(1, 0.5)', transformOrigin: '0% 50%', clearProps: 'transform' }, 0.6)
          .fromTo(combo.querySelector('.st-combo-shine'), { xPercent: -120 }, { xPercent: 320, duration: 0.6, ease: 'power2.inOut' }, 0.95)
          .add(function () { sparks(combo); }, 0.85);
      }
    }
    next.focus({ preventScroll: true });

    setKeys(function (key) {
      if (key === 'Enter' || key === ' ') { go(); return true; }
      return false;
    });

    function go() {
      setKeys(null);
      next.disabled = true;
      throwAway(cardNode);
      position += 1;
      window.scrollTo({ top: 0 });
      if (res.finished) showSummary(res.summary);
      else showCard();
    }
  }

  // Огоньки над плашкой серии: несколько 🔥 разлетаются вверх и гаснут.
  function sparks(anchor) {
    var g = gs();
    if (!g || !anchor.isConnected) return;
    var rect = anchor.getBoundingClientRect();
    for (var i = 0; i < 5; i++) {
      (function (n) {
        var spark = el('span', { text: '🔥' });
        spark.style.cssText = 'position:fixed;z-index:65;pointer-events:none;font-size:' + (14 + Math.random() * 8) + 'px;left:'
          + (rect.left + rect.width * (0.15 + n * 0.17)) + 'px;top:' + (rect.top - 4) + 'px;';
        document.body.appendChild(spark);
        g.fromTo(spark, { y: 0, x: 0, scale: 0.4, opacity: 1 }, {
          y: -40 - Math.random() * 35, x: (Math.random() - 0.5) * 30, scale: 1, opacity: 0, rotation: (Math.random() - 0.5) * 40,
          duration: 0.8 + Math.random() * 0.3, delay: n * 0.05, ease: 'power2.out',
          onComplete: function () { spark.remove(); }
        });
      })(i);
    }
  }

  function reportButton(statementId) {
    var btn = el('button', { class: 'st-report', type: 'button', text: 'Ошибка в карточке?' });
    btn.addEventListener('click', function () {
      var message = window.prompt('Что не так с карточкой? Напиши пару слов — проверим.');
      if (message === null) return;
      btn.disabled = true;
      post(boot.reportUrl, { statement_id: statementId, message: message })
        .then(function () { btn.textContent = 'Спасибо, проверим 🙏'; })
        .catch(function () { btn.disabled = false; alert('Не получилось отправить. Попробуй ещё раз.'); });
    });
    return btn;
  }

  /* ---------- итог ---------- */

  function form(action, fields, label, cls) {
    var f = el('form', { method: 'POST', action: action });
    f.appendChild(el('input', { type: 'hidden', name: '_token', value: csrf }));
    (fields || []).forEach(function (pair) { f.appendChild(el('input', { type: 'hidden', name: pair[0], value: pair[1] })); });
    f.appendChild(el('button', { class: 'st-btn ' + cls, type: 'submit', text: label }));
    return f;
  }

  function confetti() {
    var g = gs();
    if (!g) return;
    var colors = ['#2563EB', '#10B981', '#F59E0B', '#EC4899', '#7C3AED'];
    var layer = el('div');
    layer.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:70;overflow:hidden;';
    document.body.appendChild(layer);
    for (var i = 0; i < 60; i++) {
      (function () {
        var bit = el('div');
        var size = 6 + Math.random() * 6;
        bit.style.cssText = 'position:absolute;left:50%;top:22%;width:' + size + 'px;height:' + (size * 0.4) + 'px;border-radius:2px;background:'
          + colors[Math.floor(Math.random() * colors.length)] + ';';
        layer.appendChild(bit);
        var dx = (Math.random() - 0.5) * window.innerWidth * 0.8;
        var spin = (Math.random() - 0.5) * 720;
        g.timeline({ onComplete: function () { bit.remove(); } })
          .to(bit, { x: dx * 0.4, y: -80 - Math.random() * 80, rotation: spin * 0.3, duration: 0.35 + Math.random() * 0.15, ease: 'power2.out' })
          .to(bit, { x: dx, y: window.innerHeight * (0.5 + Math.random() * 0.5), rotation: spin, opacity: 0, duration: 1.1 + Math.random() * 0.6, ease: 'power1.in' });
      })();
    }
    setTimeout(function () { layer.remove(); }, 2600);
  }

  // Минимум карточек раздела в раунде, чтобы показывать его строку в итоге:
  // «1/1» и полная полоска ничего не говорят.
  var MIN_BLOCK_CARDS = 3;

  function showSummary(s) {
    // Сверху полоска «3 / 3» на итоге не нужна — счёт крупно в кольце.
    var top = root.querySelector('.st-top');
    if (top) top.style.display = 'none';
    stage.innerHTML = '';

    var perfect = s.correct === s.total;
    var good = s.correct * 3 >= s.total * 2;
    // Цвет результата: всё верно — зелёный, хорошо — синий, есть над чем поработать — оранжевый.
    var tone = perfect ? '#16a34a' : (good ? '#2563eb' : '#f97316');

    var head = el('div', { class: 'st-result-head' });
    var tag = s.kind === 'mistakes' ? el('div', { class: 'st-result-tag', text: 'Повтор ошибок' }) : null;
    if (tag) head.appendChild(tag);

    var sharkImg = el('img', { src: boot.shark + '-' + (good ? 'happy' : 'think') + '.webp', alt: '' });
    var say = el('div', { class: 'st-bubble', text: s.say });
    // Акула и её реплика — одной парой, как персонаж на карточке.
    head.appendChild(el('div', { class: 'st-result-who' }, [sharkImg, say]));

    // Кольцо со счётом внутри — как в тренажёре терминов.
    var R = 62, C = 2 * Math.PI * R;
    var ring = el('div', { class: 'st-ring' });
    ring.innerHTML = '<svg viewBox="0 0 140 140" aria-hidden="true"><circle cx="70" cy="70" r="' + R + '" class="st-ring-track"/>'
      + '<circle cx="70" cy="70" r="' + R + '" class="st-ring-fill" stroke="' + tone + '" stroke-dasharray="' + C.toFixed(2)
      + '" stroke-dashoffset="' + (C * (1 - s.correct / s.total)).toFixed(2) + '" transform="rotate(-90 70 70)"/></svg>';
    var scoreN = el('span', { class: 'st-score-n', text: String(s.correct) });
    scoreN.style.color = tone;
    var score = el('div', { class: 'st-score' }, [scoreN, el('span', { class: 'st-score-of', text: '/ ' + s.total })]);
    ring.appendChild(el('div', { class: 'st-ring-text' }, [score, el('div', { class: 'st-ring-label', text: 'верных ответов' })]));
    head.appendChild(ring);

    var plus = null;
    // Корм — той же иконкой, что везде на платформе (не эмодзи).
    if (s.corm > 0) {
      plus = el('div', { class: 'st-corm st-corm-plus', text: '+' + s.corm + ' ' });
      for (var f = 0; f < s.corm; f++) plus.appendChild(el('img', { src: boot.foodIcon, alt: f === 0 ? 'корма' : '' }));
      head.appendChild(plus);
    }
    // Сколько ещё раундов с кормом сегодня или почему корма не было.
    var note = s.corm_note ? el('div', { class: 'st-corm st-corm-note', text: s.corm_note }) : null;
    if (note) head.appendChild(note);
    var streak = s.best_streak >= 5 ? el('div', { class: 'st-corm', text: '🔥 Лучшая серия: ' + s.best_streak }) : null;
    if (streak) head.appendChild(streak);
    stage.appendChild(head);

    var sections = [];
    var rows = [];
    var meters = [];
    var shownBlocks = s.blocks.filter(function (b) { return b.total >= MIN_BLOCK_CARDS; });
    if (shownBlocks.length) {
      var byBlock = el('div', { class: 'st-section' }, [el('h3', { text: 'По разделам' })]);
      shownBlocks.forEach(function (b) {
        var all = b.correct === b.total;
        var weak = b.correct * 3 < b.total * 2;
        var fill = el('i');
        fill.style.width = Math.round(100 * b.correct / b.total) + '%';
        fill.style.background = all ? '#16a34a' : (weak ? '#f97316' : '#2563eb');
        meters.push(fill);
        var status = el('span', { class: 'st-row-status', text: all ? '✅' : (weak ? '🎯' : '') });
        if (all) status.title = 'Без ошибок';
        if (weak) status.title = 'Точка роста';
        var row = el('div', { class: 'st-row' }, [
          el('span', { class: 'st-row-emoji', text: b.emoji || '' }),
          el('span', { class: 'st-row-title', text: b.title }),
          el('span', { class: 'st-meter' }, [fill]),
          el('span', { class: 'st-row-count', text: b.correct + '/' + b.total }),
          status
        ]);
        rows.push(row);
        byBlock.appendChild(row);
      });
      stage.appendChild(byBlock);
      sections.push(byBlock);
    }

    var mistakeNodes = [];
    if (s.mistakes.length) {
      var list = el('div', { class: 'st-section' }, [
        el('h3', { text: 'Ошибки — разберём ещё раз' }),
        el('div', { class: 'st-section-note', text: boot.mistakesReturn })
      ]);
      s.mistakes.forEach(function (m) {
        var item = el('div', { class: 'st-mistake' }, [
          el('div', { class: 't', text: m.text }),
          el('div', { class: 'v', text: m.is_true ? '✅ Верное' : '❌ Неверное' })
        ]);
        item.querySelector('.v').style.color = m.is_true ? '#15803d' : '#b45309';
        if (m.explanation) item.appendChild(el('div', { class: 'e', text: m.explanation }));
        if (m.correction) item.appendChild(el('div', { class: 'e', text: boot.correctionLabel + ' ' + m.correction }));
        mistakeNodes.push(item);
        list.appendChild(item);
      });
      stage.appendChild(list);
      sections.push(list);
    }

    var actions = el('div', { class: 'st-actions' });
    if (s.kind === 'regular' && s.mistakes.length) {
      actions.appendChild(form(boot.mistakesUrl, [], 'Повторить ошибки', 'st-btn-main'));
    }
    actions.appendChild(form(boot.startUrl, boot.blocks.map(function (b) { return ['blocks[]', b]; }), 'Новый раунд',
      s.kind === 'regular' && s.mistakes.length ? 'st-btn-ghost' : 'st-btn-main'));
    actions.appendChild(el('a', { class: 'st-btn st-btn-ghost', href: boot.hallUrl, text: 'Вернуться в зал' }));
    stage.appendChild(actions);
    setKeys(null);

    var g = gs();
    if (!g) return;
    var tl = g.timeline();
    if (tag) tl.fromTo(tag, { y: -8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, ease: 'power2.out', clearProps: 'transform,opacity' }, 0);
    tl.fromTo(sharkImg, { scale: 0.4, rotation: -10, opacity: 0 }, { scale: 1, rotation: 0, opacity: 1, duration: 0.7, ease: 'elastic.out(1, 0.5)', clearProps: 'transform,opacity' }, 0.1)
      .fromTo(say, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)', transformOrigin: '0% 100%', clearProps: 'transform,opacity' }, 0.3)
      .fromTo(ring, { scale: 0.85, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.4, ease: 'back.out(2)', clearProps: 'transform,opacity' }, 0.3);

    // Кольцо заполняется, а счёт внутри набегает от нуля вместе с ним.
    var fillDur = Math.min(1.2, 0.08 * s.correct + 0.4);
    tl.fromTo(ring.querySelector('.st-ring-fill'), { attr: { 'stroke-dashoffset': C } },
      { attr: { 'stroke-dashoffset': C * (1 - s.correct / s.total) }, duration: fillDur, ease: 'power2.out' }, 0.45);
    var counterObj = { n: 0 };
    tl.to(counterObj, { n: s.correct, duration: fillDur, ease: 'power2.out', onUpdate: function () {
      scoreN.textContent = String(Math.round(counterObj.n));
    } }, 0.45);
    var scoreEnd = 0.45 + fillDur;
    tl.fromTo(ring, { scale: 1 }, { scale: 1.06, duration: 0.15, yoyo: true, repeat: 1, ease: 'power2.out', clearProps: 'transform' }, scoreEnd);

    if (plus) {
      tl.fromTo(plus, { scale: 0.3, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.45, ease: 'back.out(3)', clearProps: 'transform,opacity' }, scoreEnd + 0.1)
        .fromTo(plus.querySelectorAll('img'), { y: -18, opacity: 0, rotation: -30 }, { y: 0, opacity: 1, rotation: 0, duration: 0.4, stagger: 0.12, ease: 'bounce.out', clearProps: 'transform,opacity' }, scoreEnd + 0.25);
    }
    if (perfect || s.corm >= 3) tl.add(confetti, scoreEnd + 0.2);
    var extras = [note, streak].filter(Boolean);
    if (extras.length) tl.fromTo(extras, { y: 6, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.08, ease: 'power2.out', clearProps: 'transform,opacity' }, scoreEnd + 0.3);

    if (sections.length) tl.fromTo(sections, { y: 16, opacity: 0 }, { y: 0, opacity: 1, duration: 0.4, stagger: 0.1, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.6);
    if (rows.length) tl.fromTo(rows, { x: -8, opacity: 0 }, { x: 0, opacity: 1, duration: 0.3, stagger: 0.07, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.75);
    if (meters.length) tl.from(meters, { width: 0, duration: 0.6, stagger: 0.07, ease: 'power2.out' }, 0.8);
    if (mistakeNodes.length) tl.fromTo(mistakeNodes, { y: 8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.05, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.85);
    tl.fromTo(actions.children, { y: 12, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.06, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.6);
  }

  /* ---------- старт ---------- */

  function start() {
    if (boot.summary) showSummary(boot.summary);
    else if (position < total) showCard();
    else window.location.href = boot.indexUrl;
  }

  // GSAP подключён с defer — к DOMContentLoaded он уже выполнен.
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
</script>
@endpush
