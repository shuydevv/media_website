/*
 * Чат трансляции (ТЗ «чат трансляций — этап 1»). Без сборки, vanilla JS.
 * Сервер — App\Http\Controllers\LiveChat\*, логика — App\Service\LiveChat.
 *
 * Обмен — опрос: «что изменилось после версии N» раз в 2 с (вкладка
 * свёрнута — раз в 15 с). Каждое изменённое сообщение приходит целиком
 * (с реакциями и отметками) — здесь его просто перерисовываем.
 *
 * Режимы (cfg.mode): live — чат эфира на странице урока; replay — чат в записи
 * (сообщения появляются по времени видео); console — пульт эфира для OBS.
 * Текст сообщений вставляется только через textContent — никакого HTML.
 */
(function () {
  'use strict';

  const roots = document.querySelectorAll('[data-live-chat]');
  roots.forEach((root) => {
    try {
      init(root, JSON.parse(root.getAttribute('data-live-chat')));
    } catch (e) {
      console.error('live-chat', e);
    }
  });

  function init(root, cfg) {
    const isReplay = cfg.mode === 'replay';
    const isConsole = cfg.mode === 'console';
    const touch = window.matchMedia('(hover: none)').matches;

    const S = {
      v: 0,
      me: cfg.me,
      state: cfg.state,
      msgs: new Map(),        // id → msg
      qOnly: new Map(),       // старые неотвеченные вопросы (только во вкладке)
      pinned: null,
      tab: 'chat',
      replyTo: null,
      question: false,
      slowUntil: 0,
      unseen: 0,
      unseenReplies: [],
      more: false,
      entered: !cfg.askName || isReplay || isConsole,
      replayAll: false,
      videoTime: 0,
      replayShift: 0,
      player: null,
    };
    const reactions = new Map(cfg.reactions.map((r) => [r.id, r]));

    // ── Разметка ───────────────────────────────────────────────────
    root.classList.add('lc', 'lc--' + cfg.mode);
    root.innerHTML = '';
    const head = el('div', 'lc-head');
    const title = el('div', 'lc-title', 'Чат');
    const pill = el('span', 'lc-pill');
    title.append(pill);
    head.append(title);

    const tabs = el('div', 'lc-tabs');
    const tabChat = btn('lc-tab is-active', 'Чат', () => setTab('chat'));
    const tabQ = btn('lc-tab', 'Вопросы', () => setTab('questions'));
    const qCount = el('span', 'lc-count');
    tabQ.append(qCount);
    tabs.append(tabChat, tabQ);
    if (S.me.team && !isReplay) head.append(tabs);

    let replayToggle = null;
    if (isReplay) {
      replayToggle = btn('lc-ghost', 'Весь чат', () => {
        S.replayAll = !S.replayAll;
        replayToggle.textContent = S.replayAll ? 'По видео' : 'Весь чат';
        renderReplay(true);
      });
      head.append(replayToggle);
    }

    const pinBar = el('div', 'lc-pin');
    const banner = el('div', 'lc-banner');

    const body = el('div', 'lc-body');
    const feed = el('div', 'lc-feed');
    const qList = el('div', 'lc-feed lc-qlist');
    qList.hidden = true;
    const empty = el('div', 'lc-empty');
    body.append(feed, qList, empty);

    const jumpNew = btn('lc-jump', '', () => scrollBottom(true));
    jumpNew.hidden = true;
    const jumpReply = btn('lc-jump lc-jump--reply', '', () => {
      const id = S.unseenReplies.shift();
      updateJumpReply();
      if (id) flash(id);
    });
    jumpReply.hidden = true;
    const jumps = el('div', 'lc-jumps');
    jumps.append(jumpReply, jumpNew);
    body.append(jumps);

    const composer = el('div', 'lc-composer');
    const replyBar = el('div', 'lc-replybar');
    replyBar.hidden = true;
    const notice = el('div', 'lc-notice');
    notice.hidden = true;
    const row = el('div', 'lc-row');
    const qBtn = btn('lc-qbtn', '?', () => {
      S.question = !S.question;
      qBtn.classList.toggle('is-on', S.question);
      input.placeholder = S.question ? 'Ваш вопрос преподавателю…' : 'Сообщение';
      input.focus();
    });
    qBtn.title = 'Вопрос преподавателю';
    const input = document.createElement('textarea');
    input.className = 'lc-input';
    input.rows = 1;
    input.maxLength = cfg.max;
    input.placeholder = 'Сообщение';
    const send = btn('lc-send', '', () => submit());
    send.setAttribute('aria-label', 'Отправить');
    send.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10.2 15 12 3.4 13.8z" fill="currentColor"/></svg>';
    if (!S.me.team) row.append(qBtn);
    row.append(input, send);
    const nameLine = el('div', 'lc-nameline');
    composer.append(replyBar, notice, row, nameLine);

    root.append(head, pinBar, banner, body);
    if (!isReplay) root.append(composer);

    // Вход в чат: имя показывается при каждом входе, уже подставленное.
    let gate = null;
    if (!S.entered) {
      gate = el('div', 'lc-gate');
      const box = el('div', 'lc-gate-box');
      const lbl = el('label', 'lc-gate-label', 'В чате вы будете:');
      const nameInput = document.createElement('input');
      nameInput.className = 'lc-gate-input';
      nameInput.maxLength = cfg.nameMax;
      nameInput.value = S.me.name;
      const enter = btn('lc-btn', 'Войти в чат', async () => {
        const name = nameInput.value.trim();
        if (!name) return nameInput.focus();
        if (name !== S.me.name) {
          const ok = await saveName(name);
          if (!ok) return;
        }
        S.entered = true;
        gate.remove();
        renderComposer();
        input.focus();
      });
      nameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') enter.click(); });
      box.append(lbl, nameInput, enter);
      gate.append(box);
      root.append(gate);
    }

    // ── Утилиты ────────────────────────────────────────────────────
    function el(tag, cls, text) {
      const n = document.createElement(tag);
      if (cls) n.className = cls;
      if (text !== undefined) n.textContent = text;
      return n;
    }
    function btn(cls, text, onClick) {
      const b = el('button', cls, text);
      b.type = 'button';
      if (onClick) b.addEventListener('click', onClick);
      return b;
    }
    function url(name, m) {
      const u = cfg.url[name];
      return m === undefined ? u : u.replace(/\/(messages|mutes)\/0(?=\/|$)/, '/$1/' + m);
    }
    async function api(method, u, data) {
      const res = await fetch(u, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: data ? JSON.stringify(data) : undefined,
      });
      let json = {};
      try { json = await res.json(); } catch (e) { /* пусто */ }
      if (res.status === 419) toast('Страница устарела — обновите её');
      return { ok: res.ok, status: res.status, json };
    }
    function hhmm(ts) {
      const d = new Date(ts * 1000);
      return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }
    function clock(sec) {
      sec = Math.max(0, Math.floor(sec));
      const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
      return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    }
    let toastTimer;
    function toast(text) {
      let t = root.querySelector('.lc-toast');
      if (!t) { t = el('div', 'lc-toast'); root.append(t); }
      t.textContent = text;
      t.classList.add('is-on');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('is-on'), 3000);
    }
    const HUES = [210, 340, 25, 145, 265, 190, 5, 300, 95, 45];
    function avatar(m) {
      const a = el('div', 'lc-av');
      if (m.av) {
        const img = el('img');
        img.src = m.av;
        img.alt = '';
        img.loading = 'lazy';
        a.append(img);
      } else {
        a.textContent = (m.n || '?').trim().charAt(0).toUpperCase();
        a.style.background = 'hsl(' + HUES[m.uid % HUES.length] + ' 70% 88%)';
        a.style.color = 'hsl(' + HUES[m.uid % HUES.length] + ' 55% 32%)';
      }
      return a;
    }

    // ── Отрисовка сообщения ────────────────────────────────────────
    function renderMsg(m, inQuestions) {
      const n = el('div', 'lc-msg');
      n.dataset.id = m.id;
      if (m.team) n.classList.add('is-team');
      if (m.uid === S.me.id) n.classList.add('is-mine');
      if (m.reply && m.reply.uid === S.me.id && m.uid !== S.me.id) n.classList.add('is-to-me');
      if (m.q) n.classList.add('is-question');

      n.append(avatar(m));
      const main = el('div', 'lc-main');
      const top = el('div', 'lc-top');
      const name = el('span', 'lc-name', m.n);
      if (m.real) {
        name.classList.add('has-real');
        name.dataset.real = m.real;
        name.addEventListener('click', (e) => { e.stopPropagation(); name.classList.toggle('show-real'); });
      }
      top.append(name);
      if (m.b) top.append(el('span', 'lc-badge', m.b));
      const time = el('span', 'lc-time', isReplay && m.t !== undefined ? clock(m.t + S.replayShift) : hhmm(m.ts));
      if (isReplay) {
        time.classList.add('is-link');
        time.title = 'Перемотать видео сюда';
        time.addEventListener('click', () => seekVideo(m.t + S.replayShift));
      }
      top.append(time);
      main.append(top);

      if (m.reply) {
        const q = el('div', 'lc-quote');
        q.append(el('b', '', m.reply.n), el('span', '', m.reply.text));
        q.addEventListener('click', () => flash(m.reply.id));
        main.append(q);
      }
      if (m.q) {
        const tags = el('div', 'lc-tags');
        tags.append(el('span', 'lc-qtag', '❓ Вопрос'));
        if (m.ans) {
          const a = el('span', 'lc-anstag', '✓ Ответ на эфире');
          if (isReplay && m.ansT !== undefined) {
            a.classList.add('is-link');
            a.title = 'Перемотать к ответу';
            a.addEventListener('click', () => seekVideo(m.ansT + S.replayShift));
          }
          tags.append(a);
        }
        main.append(tags);
      }
      main.append(el('div', 'lc-text', m.text));
      main.append(renderChips(m));
      n.append(main);

      if (!isReplay) attachActions(n, m, inQuestions);
      return n;
    }

    function renderChips(m) {
      const box = el('div', 'lc-chips');
      (m.rx || []).forEach(([rid, count, mine]) => {
        const r = reactions.get(rid);
        if (!r || count < 1) return;
        const c = btn('lc-chip' + (mine ? ' is-mine' : ''), '', () => react(m, rid, c));
        c.append(reactionImg(r, false), el('span', 'lc-chip-n', String(count)));
        c.disabled = !canReact();
        box.append(c);
      });
      return box;
    }

    function reactionImg(r, animated) {
      if (r.format === 'lottie' && (animated || !r.still)) {
        const holder = el('span', 'lc-lottie');
        loadLottie().then((lottie) => {
          if (!lottie) { holder.textContent = r.emoji || '★'; return; }
          lottie.loadAnimation({ container: holder, renderer: 'svg', loop: !!animated && animated !== 'once', autoplay: !!animated, path: r.anim });
        });
        return holder;
      }
      const img = el('img', 'lc-rimg');
      img.alt = r.emoji || r.name;
      img.draggable = false;
      img.src = animated ? r.anim : (r.still || r.anim);
      if (r.format === 'png') img.classList.add('is-png');
      return img;
    }

    let lottiePromise = null;
    function loadLottie() {
      if (window.lottie) return Promise.resolve(window.lottie);
      if (!lottiePromise) {
        lottiePromise = new Promise((resolve) => {
          const s = document.createElement('script');
          s.src = cfg.lottie;
          s.onload = () => resolve(window.lottie || null);
          s.onerror = () => resolve(null);
          document.head.append(s);
        });
      }
      return lottiePromise;
    }

    function canReact() {
      return !isReplay && !S.me.demo && (S.state.s === 'open' || S.state.s === 'break');
    }

    // ── Действия над сообщением ────────────────────────────────────
    function attachActions(n, m, inQuestions) {
      if (!touch) {
        const bar = el('div', 'lc-actions');
        if (canReact() && firstMain()) {
          const heart = btn('lc-act lc-heart', '', () => react(m, firstMain().id, heart));
          heart.append(reactionImg(firstMain(), false));
          hoverPicker(heart, m);
          bar.append(heart);
        }
        if (canWriteNow()) bar.append(btn('lc-act', '↩', () => startReply(m)));
        if (S.me.team && m.q) bar.append(btn('lc-act lc-act--ok' + (m.ans ? ' is-on' : ''), '✓', () => answer(m)));
        if (S.me.admin) bar.append(btn('lc-act', '⋯', (e) => adminMenu(m, e.currentTarget)));
        if (bar.childElementCount) n.append(bar);
      } else {
        longPress(n, () => sheet(m));
        swipeReply(n, m);
      }
      if (inQuestions) {
        n.addEventListener('dblclick', () => { setTab('chat'); flash(m.id); });
      }
    }

    function firstMain() {
      return cfg.reactions.find((r) => r.main && r.active) || cfg.reactions.find((r) => r.active);
    }

    // ПК: наведение на ❤️ раскрывает основной ряд, «›» — все реакции. Выбор
    // живёт поверх страницы (position: fixed), иначе его обрезает край ленты.
    let floatPicker = null, floatTimer = null;
    function closeFloat() {
      clearTimeout(floatTimer);
      if (floatPicker) { floatPicker.remove(); floatPicker = null; }
    }
    function hoverPicker(heart, m) {
      const leave = () => { clearTimeout(floatTimer); floatTimer = setTimeout(closeFloat, 250); };
      heart.addEventListener('mouseenter', () => {
        clearTimeout(floatTimer);
        if (floatPicker && floatPicker.dataset.id === String(m.id)) return;
        closeFloat();
        floatPicker = picker(m, closeFloat, false);
        floatPicker.dataset.id = m.id;
        floatPicker.classList.add('is-float');
        document.body.append(floatPicker);
        const r = heart.getBoundingClientRect();
        floatPicker.style.left = Math.max(8, Math.min(r.right - floatPicker.offsetWidth, window.innerWidth - floatPicker.offsetWidth - 8)) + 'px';
        const top = r.top - floatPicker.offsetHeight - 6;
        floatPicker.style.top = (top > 8 ? top : r.bottom + 6) + 'px';
        floatPicker.addEventListener('mouseenter', () => clearTimeout(floatTimer));
        floatPicker.addEventListener('mouseleave', leave);
      });
      heart.addEventListener('mouseleave', leave);
    }

    function picker(m, done, forSheet) {
      const p = el('div', 'lc-picker' + (forSheet ? ' is-sheet' : ''));
      const mainRow = el('div', 'lc-picker-row');
      const extraRow = el('div', 'lc-picker-row lc-picker-extra');
      cfg.reactions.filter((r) => r.active).forEach((r) => {
        const b = btn('lc-pick', '', () => {
          react(m, r.id, b);
          if (done) done();
        });
        b.title = r.name;
        const img = reactionImg(r, false);
        b.append(img);
        // Анимация грузится только при наведении — так выбор весит ~5 КБ на реакцию.
        if (!touch && r.format !== 'lottie') {
          b.addEventListener('mouseenter', () => { img.src = r.anim; });
          b.addEventListener('mouseleave', () => { img.src = r.still || r.anim; });
        }
        (r.main ? mainRow : extraRow).append(b);
      });
      p.append(mainRow);
      if (extraRow.childElementCount) {
        const more = btn('lc-pick lc-more', '›', () => {
          p.classList.toggle('is-all');
          // Раскрылся вверх — сдвигаем, чтобы нижний край остался у сердечка.
          if (p.classList.contains('is-float')) {
            const bottom = parseFloat(p.style.top) + p.dataset.h * 1;
            p.style.top = Math.max(8, bottom - p.offsetHeight) + 'px';
          }
        });
        more.title = 'Все реакции';
        mainRow.append(more);
        p.append(extraRow);
      }
      requestAnimationFrame(() => { p.dataset.h = p.offsetHeight; });
      return p;
    }

    async function react(m, rid, fromEl) {
      if (!canReact()) return;
      const r = reactions.get(rid);
      const mine = (m.rx || []).some(([id, , me]) => id === rid && me);
      // Сразу у себя (оптимистично), сервер подтвердит следующим опросом.
      const rx = (m.rx || []).map((x) => x.slice());
      const ex = rx.find((x) => x[0] === rid);
      if (ex) { ex[1] += mine ? -1 : 1; ex[2] = !mine; } else rx.push([rid, 1, true]);
      upsert(Object.assign({}, m, { rx: rx.filter((x) => x[1] > 0) }), true);
      if (!mine && r) burst(r, fromEl, m.id);
      if (navigator.vibrate && touch) navigator.vibrate(10);
      const res = await api('POST', url('react', m.id), { reaction: rid });
      if (!res.ok) toast(res.json.message || 'Не получилось');
      poll(true);
    }

    function burst(r, fromEl, id) {
      const node = feed.querySelector('.lc-msg[data-id="' + id + '"]');
      const chip = node && Array.from(node.querySelectorAll('.lc-chip')).find((c) => c.querySelector('img,.lc-lottie') && c.classList.contains('is-mine'));
      const anchor = (chip && chip.isConnected ? chip : fromEl) || node;
      if (!anchor || !anchor.getBoundingClientRect) return;
      const rect = anchor.getBoundingClientRect();
      const fx = el('div', 'lc-burst');
      fx.style.left = rect.left + rect.width / 2 + 'px';
      fx.style.top = rect.top + rect.height / 2 + 'px';
      // Сначала статичный кадр (уже в кэше) — анимация подменит его, как загрузится.
      const big = reactionImg(r, r.format === 'lottie' ? 'once' : false);
      fx.append(big);
      if (r.format !== 'lottie' && r.anim !== big.src) {
        const pre = new Image();
        pre.onload = () => { big.src = r.anim; };
        pre.src = r.anim;
      }
      for (let i = 0; i < 8; i++) {
        const p = el('i', 'lc-spark');
        const a = (Math.PI * 2 * i) / 8;
        p.style.setProperty('--dx', Math.cos(a) * 46 + 'px');
        p.style.setProperty('--dy', Math.sin(a) * 46 + 'px');
        p.style.background = 'hsl(' + (i * 45) + ' 85% 62%)';
        fx.append(p);
      }
      document.body.append(fx);
      setTimeout(() => fx.remove(), 1200);
      if (chip) {
        const n = chip.querySelector('.lc-chip-n');
        n && n.classList.add('is-bump');
      }
    }

    async function answer(m) {
      const res = await api('POST', url('answer', m.id), { answered: !m.ans });
      if (!res.ok) toast('Не получилось');
      poll(true);
    }

    function adminMenu(m, anchor) {
      closeMenus();
      const menu = el('div', 'lc-menu');
      const items = adminItems(m);
      items.forEach(([label, fn, danger]) => menu.append(btn('lc-menu-item' + (danger ? ' is-danger' : ''), label, () => { closeMenus(); fn(); })));
      document.body.append(menu);
      const r = anchor.getBoundingClientRect();
      menu.style.top = Math.min(r.bottom + 4, window.innerHeight - menu.offsetHeight - 8) + 'px';
      menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
      setTimeout(() => document.addEventListener('click', closeMenus, { once: true }), 0);
    }
    function closeMenus() {
      document.querySelectorAll('.lc-menu').forEach((x) => x.remove());
    }
    function adminItems(m) {
      const out = [];
      out.push([S.pinned && S.pinned.id === m.id ? 'Открепить' : 'Закрепить', () => pin(m)]);
      if (!m.team) {
        out.push(['Замьютить на 10 мин', () => mute(m, '10m')]);
        out.push(['Замьютить до конца эфира', () => mute(m, 'lesson')]);
        out.push(['Замьютить навсегда', () => mute(m, 'forever')]);
        out.push(['Снять мьют', () => unmute(m)]);
      }
      out.push(['Удалить сообщение', () => del(m), true]);
      return out;
    }
    async function pin(m) {
      const res = S.pinned && S.pinned.id === m.id ? await api('DELETE', url('unpin')) : await api('POST', url('pin', m.id));
      if (!res.ok) toast('Не получилось');
      poll(true);
    }
    async function mute(m, duration) {
      const res = await api('POST', url('mute', m.uid), { duration });
      toast(res.ok ? m.n + ' не может писать' : (res.json.message || 'Не получилось'));
      poll(true);
    }
    async function unmute(m) {
      const res = await api('DELETE', url('unmute', m.uid));
      toast(res.ok ? 'Мьют снят' : 'Не получилось');
      poll(true);
    }
    async function del(m) {
      if (!confirm('Удалить сообщение «' + m.text.slice(0, 60) + '»?')) return;
      const res = await api('DELETE', url('delete', m.id));
      if (!res.ok) toast('Не получилось');
      poll(true);
    }

    // Телефон: долгое нажатие → меню с реакциями (как в Telegram).
    function longPress(n, fn) {
      let t = null, x = 0, y = 0;
      n.addEventListener('touchstart', (e) => {
        if (e.target.closest('.lc-chip,.lc-quote,.lc-name')) return;
        x = e.touches[0].clientX; y = e.touches[0].clientY;
        t = setTimeout(() => { t = null; if (navigator.vibrate) navigator.vibrate(15); fn(); }, 400);
      }, { passive: true });
      n.addEventListener('touchmove', (e) => {
        if (t && (Math.abs(e.touches[0].clientX - x) > 8 || Math.abs(e.touches[0].clientY - y) > 8)) { clearTimeout(t); t = null; }
      }, { passive: true });
      n.addEventListener('touchend', () => { if (t) { clearTimeout(t); t = null; } });
      n.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    function swipeReply(n, m) {
      let x0 = null, y0 = 0, dx = 0;
      n.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; dx = 0; }, { passive: true });
      n.addEventListener('touchmove', (e) => {
        if (x0 === null) return;
        const ddx = e.touches[0].clientX - x0, ddy = e.touches[0].clientY - y0;
        if (Math.abs(ddy) > Math.abs(ddx)) { x0 = null; n.style.transform = ''; return; }
        dx = Math.min(0, Math.max(-70, ddx));
        n.style.transform = 'translateX(' + dx + 'px)';
      }, { passive: true });
      n.addEventListener('touchend', () => {
        n.style.transform = '';
        if (dx <= -55 && canWriteNow()) startReply(m);
        x0 = null;
      });
    }

    let sheetEl = null;
    function sheet(m) {
      closeSheet();
      const node = feed.querySelector('.lc-msg[data-id="' + m.id + '"]') || qList.querySelector('.lc-msg[data-id="' + m.id + '"]');
      sheetEl = el('div', 'lc-sheet');
      const back = el('div', 'lc-sheet-back');
      back.addEventListener('click', closeSheet);
      const wrap = el('div', 'lc-sheet-wrap');
      if (canReact()) wrap.append(picker(m, closeSheet, true));
      if (node) {
        const clone = node.cloneNode(true);
        clone.classList.add('is-lifted');
        clone.querySelectorAll('.lc-actions').forEach((x) => x.remove());
        wrap.append(clone);
      }
      const menu = el('div', 'lc-sheet-menu');
      const item = (label, fn, danger) => menu.append(btn('lc-menu-item' + (danger ? ' is-danger' : ''), label, () => { closeSheet(); fn(); }));
      if (canWriteNow()) item('Ответить', () => startReply(m));
      item('Копировать', () => { navigator.clipboard && navigator.clipboard.writeText(m.text); toast('Скопировано'); });
      if (S.me.team && m.q) item(m.ans ? 'Снять ✓' : '✓ Ответ на эфире', () => answer(m));
      if (S.me.admin) adminItems(m).forEach(([l, f, d]) => item(l, f, d));
      wrap.append(menu);
      sheetEl.append(back, wrap);
      document.body.append(sheetEl);
      requestAnimationFrame(() => sheetEl && sheetEl.classList.add('is-on'));
    }
    function closeSheet() {
      if (sheetEl) { sheetEl.remove(); sheetEl = null; }
    }

    function flash(id) {
      let node = feed.querySelector('.lc-msg[data-id="' + id + '"]');
      if (!node && S.tab === 'questions') node = qList.querySelector('.lc-msg[data-id="' + id + '"]');
      if (!node) { toast('Сообщение выше — прокрутите чат'); return; }
      node.scrollIntoView({ block: 'center', behavior: 'smooth' });
      node.classList.remove('is-flash');
      void node.offsetWidth;
      node.classList.add('is-flash');
    }

    // ── Ответ цитатой ──────────────────────────────────────────────
    function startReply(m) {
      if (!S.entered) return;
      S.replyTo = m;
      replyBar.innerHTML = '';
      const t = el('div', 'lc-replybar-text');
      t.append(el('b', '', 'Ответ ' + m.n), el('span', '', m.text.slice(0, 90)));
      replyBar.append(t, btn('lc-x', '✕', cancelReply));
      replyBar.hidden = false;
      input.focus();
    }
    function cancelReply() {
      S.replyTo = null;
      replyBar.hidden = true;
    }

    // ── Отправка ───────────────────────────────────────────────────
    function canWriteNow() {
      return !isReplay && S.entered && S.me.write;
    }

    let sending = false;
    async function submit() {
      const text = input.value.trim();
      if (!text || sending || !canWriteNow()) return;
      if (Date.now() < S.slowUntil) return;
      sending = true;
      send.disabled = true;
      const res = await api('POST', url('send'), { text, reply_to: S.replyTo ? S.replyTo.id : null, question: S.question });
      sending = false;
      send.disabled = false;
      if (res.ok) {
        input.value = '';
        autosize();
        cancelReply();
        if (S.question) qBtn.click();
        upsert(res.json.msg);
        scrollBottom(true);
        if (res.json.slow && res.json.slow.slow) startSlow(res.json.slow.wait);
        poll(true);
      } else if (res.json.error === 'slow') {
        startSlow(res.json.wait);
      } else if (res.json.error === 'muted' || res.json.error === 'closed') {
        poll(true, true);
        toast(res.json.message);
      } else {
        toast(res.json.message || 'Не отправилось, попробуйте ещё раз');
      }
    }

    let slowTimer = null;
    function startSlow(wait) {
      S.slowUntil = Date.now() + wait * 1000;
      clearInterval(slowTimer);
      const tick = () => {
        const left = Math.ceil((S.slowUntil - Date.now()) / 1000);
        if (left <= 0) { clearInterval(slowTimer); S.slowUntil = 0; renderComposer(); return; }
        notice.textContent = 'Не так быстро 🙂 Следующее сообщение через ' + left + ' с';
        notice.hidden = false;
        send.disabled = true;
      };
      tick();
      slowTimer = setInterval(tick, 1000);
    }

    async function saveName(name) {
      const res = await api('POST', url('name'), { name });
      if (!res.ok) { toast(res.json.message || 'Не получилось сохранить имя'); return false; }
      S.me.name = res.json.name;
      renderComposer();
      return true;
    }

    function autosize() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 120) + 'px';
      input.style.overflowY = input.scrollHeight > 120 ? 'auto' : 'hidden';
    }
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey && !touch) { e.preventDefault(); submit(); }
      if (e.key === 'Escape') cancelReply();
    });
    // Телефон: клавиатура не должна закрывать поле и последние сообщения.
    // Нижнее меню сайта (fixed) на это время прячем — иначе оно закрывает поле.
    input.addEventListener('focus', () => {
      if (!touch) return;
      document.body.classList.add('lc-typing');
      setTimeout(() => { composer.scrollIntoView({ block: 'end', behavior: 'smooth' }); scrollBottom(false); }, 350);
    });
    input.addEventListener('blur', () => document.body.classList.remove('lc-typing'));
    if (window.visualViewport && touch) {
      window.visualViewport.addEventListener('resize', () => {
        if (document.activeElement === input) composer.scrollIntoView({ block: 'end' });
      });
    }

    function renderComposer() {
      if (isReplay) return;
      nameLine.innerHTML = '';
      if (S.me.team) {
        nameLine.append(el('span', '', 'Вы пишете как '), el('b', '', S.me.name), el('span', 'lc-badge', S.me.badge));
      } else if (S.entered) {
        nameLine.append(el('span', '', 'Вы: '), el('b', '', S.me.name));
        if (!S.me.demo) nameLine.append(btn('lc-edit', '✎', editName));
      }
      const blocked = S.me.muted
        ? 'Вы не можете писать в этом чате' + (S.me.muted.until ? ' до ' + hhmm(S.me.muted.until) : (S.me.muted.lesson ? ' до конца эфира' : ''))
        : S.me.demo ? 'В демо-режиме писать в чат нельзя' : null;
      row.hidden = !!blocked || !S.me.write;
      if (blocked) {
        notice.textContent = blocked;
        notice.hidden = false;
        notice.classList.add('is-block');
      } else if (!S.slowUntil) {
        notice.hidden = true;
        notice.classList.remove('is-block');
      }
      composer.hidden = !blocked && !S.me.write && !S.me.team;
      send.disabled = !!S.slowUntil;
    }

    function editName() {
      const f = el('div', 'lc-name-edit');
      const i = document.createElement('input');
      i.className = 'lc-gate-input';
      i.maxLength = cfg.nameMax;
      i.value = S.me.name;
      const ok = btn('lc-btn lc-btn--sm', 'Сохранить', async () => {
        const v = i.value.trim();
        if (v && v !== S.me.name) await saveName(v); else renderComposer();
      });
      i.addEventListener('keydown', (e) => { if (e.key === 'Enter') ok.click(); if (e.key === 'Escape') renderComposer(); });
      f.append(i, ok);
      nameLine.innerHTML = '';
      nameLine.append(f);
      i.focus();
      i.select();
    }

    // ── Лента: слияние и прокрутка ─────────────────────────────────
    function nearBottom() {
      return body.scrollHeight - body.scrollTop - body.clientHeight < 90;
    }
    function scrollBottom(smooth) {
      body.scrollTo({ top: body.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
      S.unseen = 0;
      jumpNew.hidden = true;
    }
    body.addEventListener('scroll', () => {
      closeFloat();
      if (nearBottom()) { S.unseen = 0; jumpNew.hidden = true; }
      if (body.scrollTop < 40 && S.more && !loadingOlder && !isReplay && S.tab === 'chat') loadOlder();
    });

    function insertSorted(container, node, id) {
      const items = container.children;
      for (let i = items.length - 1; i >= 0; i--) {
        if (Number(items[i].dataset.id) < id) { items[i].after(node); return; }
      }
      container.prepend(node);
    }

    // silent — своё действие (реакция), без «новых сообщений»
    function upsert(m, silent) {
      if (m.del) {
        S.msgs.delete(m.id);
        S.qOnly.delete(m.id);
        feed.querySelectorAll('.lc-msg[data-id="' + m.id + '"]').forEach((x) => x.remove());
        renderQuestions();
        return;
      }
      const isNew = !S.msgs.has(m.id);
      S.msgs.set(m.id, m);
      if (isReplay) return;
      const node = renderMsg(m, false);
      const old = feed.querySelector('.lc-msg[data-id="' + m.id + '"]');
      if (old) old.replaceWith(node); else insertSorted(feed, node, m.id);
      if (S.pinned && S.pinned.id === m.id) renderPinned(m);
      if (isNew && !silent && m.uid !== S.me.id) {
        if (!nearBottom()) { S.unseen++; jumpNew.textContent = S.unseen + ' ' + plural(S.unseen, 'новое сообщение', 'новых сообщения', 'новых сообщений') + ' ↓'; jumpNew.hidden = false; }
        if (m.reply && m.reply.uid === S.me.id && (!nearBottom() || document.hidden)) { S.unseenReplies.push(m.id); updateJumpReply(); }
      }
    }
    function plural(n, one, few, many) {
      const m10 = n % 10, m100 = n % 100;
      if (m10 === 1 && m100 !== 11) return one;
      if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
      return many;
    }
    function updateJumpReply() {
      jumpReply.hidden = !S.unseenReplies.length;
      jumpReply.textContent = '↩ ' + S.unseenReplies.length;
      jumpReply.title = 'Вам ответили';
    }

    function renderPinned(m) {
      pinBar.innerHTML = '';
      pinBar.hidden = !m;
      if (!m) return;
      const t = el('div', 'lc-pin-text');
      t.append(el('b', '', '📌 ' + m.n), el('span', '', m.text));
      t.addEventListener('click', () => { if (isReplay) return; setTab('chat'); flash(m.id); });
      pinBar.append(t);
      if (S.me.admin && !isReplay) pinBar.append(btn('lc-x', '✕', () => pin(m)));
    }

    function renderQuestions() {
      if (!S.me.team || isReplay) return;
      const open = [];
      S.qOnly.forEach((m) => { if (!m.ans) open.push(m); });
      S.msgs.forEach((m) => { if (m.q && !m.ans && !S.qOnly.has(m.id)) open.push(m); });
      open.sort((a, b) => a.id - b.id);
      qCount.textContent = open.length ? ' ' + open.length : '';
      qList.innerHTML = '';
      open.forEach((m) => qList.append(renderMsg(m, true)));
      if (S.tab === 'questions') empty.textContent = open.length ? '' : 'Вопросов пока нет';
    }

    function setTab(t) {
      S.tab = t;
      tabChat.classList.toggle('is-active', t === 'chat');
      tabQ.classList.toggle('is-active', t === 'questions');
      feed.hidden = t !== 'chat';
      qList.hidden = t !== 'questions';
      jumps.hidden = t !== 'chat';
      renderQuestions();
      updateEmpty();
      if (t === 'chat') scrollBottom(false); else body.scrollTop = 0;
    }

    function updateEmpty() {
      if (S.tab === 'questions') return;
      empty.textContent = feed.childElementCount ? '' : (isReplay ? (S.replayAll ? 'В чате не было сообщений' : 'Сообщения появятся по ходу видео') : 'Сообщений пока нет');
    }

    function applyState() {
      const s = S.state.s;
      pill.className = 'lc-pill is-' + s;
      pill.textContent = isReplay ? 'запись' : ({ waiting: 'скоро', open: 'открыт', break: 'пауза', closed: 'завершён' })[s] || '';
      let text = '';
      if (!isReplay) {
        if (s === 'waiting') text = 'Чат откроется' + (S.state.opensLabel ? ' в ' + S.state.opensLabel : ' перед уроком');
        else if (s === 'break') text = 'Эфир прервался, ждём преподавателя';
        else if (s === 'closed') text = 'Эфир завершён';
      }
      banner.textContent = text;
      banner.hidden = !text;
      banner.className = 'lc-banner is-' + s;
      renderComposer();
    }

    // ── Опрос сервера ──────────────────────────────────────────────
    let pollTimer = null;
    let polling = false;
    let failures = 0;
    async function poll(now, full) {
      if (isReplay) return;
      clearTimeout(pollTimer);
      if (polling) { pollTimer = setTimeout(() => poll(), 300); return; }
      polling = true;
      try {
        const res = await fetch(cfg.url.feed + '&v=' + (full ? 0 : S.v), { credentials: 'omit', headers: { Accept: 'application/json' } });
        if (res.status === 401) { banner.textContent = 'Сессия чата устарела — обновите страницу'; banner.hidden = false; return; }
        if (!res.ok) throw new Error(res.status);
        apply(await res.json());
        failures = 0;
      } catch (e) {
        failures++;
      } finally {
        polling = false;
        const base = document.hidden ? cfg.poll[1] : cfg.poll[0];
        pollTimer = setTimeout(() => poll(), base * Math.min(8, 1 + failures));
      }
    }

    function apply(data) {
      const stateChanged = JSON.stringify(data.state) !== JSON.stringify(S.state);
      S.state = data.state;
      if (data.me) {
        const writeBefore = S.me.write;
        S.me = data.me;
        if (writeBefore !== S.me.write) rerenderAll();
      }
      if (data.reset) {
        S.msgs.clear();
        S.qOnly.clear();
        feed.innerHTML = '';
        S.more = !!data.more;
        (data.questions || []).forEach((m) => S.qOnly.set(m.id, m));
        (data.msgs || []).forEach((m) => upsert(m, true));
        scrollBottom(false);
      } else if (data.msgs) {
        const stick = nearBottom();
        data.msgs.forEach((m) => {
          if (S.qOnly.has(m.id)) { if (m.del) S.qOnly.delete(m.id); else S.qOnly.set(m.id, m); }
          if (!S.msgs.has(m.id) && S.qOnly.has(m.id)) return;
          // Изменение старого сообщения, которого нет в загруженной ленте, — пропускаем.
          if (!S.msgs.has(m.id) && !m.del && S.msgs.size && m.id < Math.min(...S.msgs.keys())) return;
          upsert(m);
        });
        if (stick) scrollBottom(false);
      }
      if ('pinned' in data) { S.pinned = data.pinned; renderPinned(data.pinned); }
      S.v = data.v;
      if (stateChanged) { applyState(); rerenderAll(); }
      renderQuestions();
      updateEmpty();
    }

    // Права на действия зависят от состояния (реакции — только пока чат открыт).
    function rerenderAll() {
      S.msgs.forEach((m) => {
        const old = feed.querySelector('.lc-msg[data-id="' + m.id + '"]');
        if (old) old.replaceWith(renderMsg(m, false));
      });
      renderComposer();
    }

    let loadingOlder = false;
    async function loadOlder() {
      const ids = Array.from(S.msgs.keys());
      if (!ids.length) return;
      loadingOlder = true;
      const before = Math.min(...ids);
      try {
        const res = await fetch(cfg.url.older + '&before=' + before, { credentials: 'omit', headers: { Accept: 'application/json' } });
        const data = await res.json();
        const h = body.scrollHeight;
        data.msgs.forEach((m) => upsert(m, true));
        S.more = data.more;
        body.scrollTop += body.scrollHeight - h;
      } catch (e) { /* следующая прокрутка попробует снова */ }
      loadingOlder = false;
    }

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) { poll(true); if (S.unseenReplies.length) updateJumpReply(); }
    });

    // ── Запись: сообщения по времени видео ─────────────────────────
    let replayMsgs = [];
    async function startReplay() {
      try {
        const res = await fetch(cfg.url.replay, { credentials: 'omit', headers: { Accept: 'application/json' } });
        const data = await res.json();
        S.replayShift = data.shift || 0;
        replayMsgs = data.msgs;
        replayMsgs.forEach((m) => S.msgs.set(m.id, m));
        renderReplay(true);
      } catch (e) {
        empty.textContent = 'Не удалось загрузить чат';
      }
    }
    let shown = 0;
    function renderReplay(rebuild) {
      const limit = S.replayAll ? Infinity : S.videoTime;
      const visible = replayMsgs.filter((m) => m.t + S.replayShift <= limit);
      if (rebuild || visible.length < shown) {
        feed.innerHTML = '';
        shown = 0;
      }
      const stick = rebuild ? !S.replayAll : nearBottom();
      for (let i = shown; i < visible.length; i++) feed.append(renderMsg(visible[i], false));
      shown = visible.length;
      updateEmpty();
      if (stick) scrollBottom(false);
    }
    function seekVideo(t) {
      if (S.player && S.player.seekTo) {
        S.player.seekTo(Math.max(0, t));
        if (S.player.play) S.player.play();
      }
    }
    if (isReplay) {
      window.addEventListener('kinescope-player', (e) => {
        if (e.detail.kind !== 'recording') return;
        S.player = e.detail.player;
        const E = S.player.Events;
        S.player.on(E.TimeUpdate, (ev) => {
          const t = Number((ev && ev.data && ev.data.currentTime) || 0);
          const back = t < S.videoTime - 2;
          S.videoTime = t;
          if (!S.replayAll) renderReplay(back);
        });
      });
    }

    // ── Старт ──────────────────────────────────────────────────────
    applyState();
    renderPinned(null);
    if (isReplay) {
      startReplay();
    } else {
      poll(true, true);
    }
  }
})();
