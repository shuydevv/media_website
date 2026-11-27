/*
 * Чат трансляции. Без сборки, vanilla JS.
 * Сервер — App\Http\Controllers\LiveChat\*, логика — App\Service\LiveChat.
 * Песочница (/live-chat/sandbox) подменяет сервер в браузере —
 * public/js/live-chat-sandbox.js: при изменении JSON ленты правь и её.
 *
 * Обмен — опрос: «что изменилось после версии N» раз в 2 с (вкладка
 * свёрнута — раз в 15 с). Каждое изменённое сообщение приходит целиком
 * (с реакциями и отметками) — здесь его просто перерисовываем на месте (morph).
 *
 * Ответы живут в ветках: сообщение с th (id исходного) в общую ленту не
 * попадает, у исходного — сводка tc (сколько ответов, кто). Ветка раскрывается
 * прямо под сообщением, как на форумах, с полосой слева. Реакции команды (tr) —
 * отдельно от общего счётчика (rx), аватаркой с эмодзи.
 *
 * Режимы (cfg.mode): live — чат эфира на странице урока; replay — чат в записи
 * (сообщения появляются по времени видео); console — пульт эфира для OBS.
 * Текст сообщений вставляется только через textContent — никакого HTML.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-live-chat]').forEach((root) => {
    try {
      init(root, JSON.parse(root.getAttribute('data-live-chat')));
    } catch (e) {
      console.error('live-chat', e);
    }
  });

  // Карандаш — resources/views/components/icon/edit-02.blade.php.
  const PENCIL = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.375 2.625a1.768 1.768 0 0 1 2.5 2.5L12 14l-4 1 1-4 9.375-8.375Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function init(root, cfg) {
    const isReplay = cfg.mode === 'replay';
    const isConsole = cfg.mode === 'console';
    const touch = window.matchMedia('(hover: none)').matches;
    const phone = window.matchMedia('(max-width: 767px)').matches;

    const S = {
      v: 0,
      me: cfg.me,
      state: cfg.state,
      msgs: new Map(),        // исходные сообщения в ленте: id → msg
      qOnly: new Map(),       // старые неотвеченные вопросы (только во вкладке)
      pinned: null,
      view: 'chat',           // chat | questions
      open: new Set(),        // раскрытые ветки (id исходных)
      threads: new Map(),     // загруженные ветки: id исходного → Map(id → ответ)
      target: null,           // куда пишем: {root, quote} — ответ в ветке
      question: false,
      slowUntil: 0,
      unseen: 0,
      unseenReplies: [],      // id веток, где ответили мне
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
    const tabChat = btn('lc-tab is-active', 'Чат', () => setView('chat'));
    const tabQ = btn('lc-tab', 'Вопросы', () => setView('questions'));
    const qCount = el('span', 'lc-count');
    tabQ.append(qCount);
    tabs.append(tabChat, tabQ);
    if (S.me.team && !isReplay) head.append(tabs);

    // «Ваше имя: Аня К. ✎» — у ученика справа в строке «Чат».
    const nameBox = el('div', 'lc-namebox');
    nameBox.hidden = true;
    head.append(nameBox);

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

    const bodyWrap = el('div', 'lc-bodywrap');
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
      if (id) expand(id, true);
    });
    jumpReply.hidden = true;
    const jumps = el('div', 'lc-jumps');
    jumps.append(jumpReply, jumpNew);
    bodyWrap.append(body, jumps);

    const composer = el('div', 'lc-composer');
    const replyBar = el('div', 'lc-replybar');
    replyBar.hidden = true;
    const qTag = el('div', 'lc-qmode');
    qTag.hidden = true;
    qTag.append(el('span', '', '❓ Вопрос преподавателю'), btn('lc-x', '✕', () => setQuestion(false)));
    const notice = el('div', 'lc-notice');
    notice.hidden = true;
    const row = el('div', 'lc-row');
    const clip = btn('lc-clip', '', (e) => clipMenu(e.currentTarget));
    clip.setAttribute('aria-label', 'Прикрепить');
    clip.title = 'Вопрос, эмоция, стикер';
    clip.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5 12.5 20a5.5 5.5 0 0 1-7.8-7.8l8.9-8.9a3.7 3.7 0 0 1 5.2 5.2l-8.9 8.9a1.8 1.8 0 0 1-2.6-2.6l8.2-8.2"/></svg>';
    const input = document.createElement('textarea');
    input.className = 'lc-input';
    input.rows = 1;
    input.maxLength = cfg.max;
    input.placeholder = 'Сообщение';
    const send = btn('lc-send', '', () => submit());
    send.setAttribute('aria-label', 'Отправить');
    send.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10.2 15 12 3.4 13.8z" fill="currentColor"/></svg>';
    if (!S.me.team) row.append(clip);
    row.append(input, send);
    composer.append(replyBar, qTag, notice, row);

    root.append(head, pinBar, banner, bodyWrap);
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
        // Телефон: видео + чат ровно на экран.
        if (phone) fitScreen();
        else input.focus();
      });
      nameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') enter.click(); });
      box.append(lbl, nameInput, enter);
      gate.append(box);
      root.append(gate);
    }
    function fitScreen() {
      const layout = root.closest('.lc-layout');
      if (layout) layout.scrollIntoView({ block: 'start', behavior: 'smooth' });
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
    async function getJson(u) {
      const res = await fetch(u, { credentials: 'omit', headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(res.status);
      return res.json();
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
    function plural(n, one, few, many) {
      const m10 = n % 10, m100 = n % 100;
      if (m10 === 1 && m100 !== 11) return one;
      if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
      return many;
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
    function avatar(uid, name, av, cls) {
      const a = el('div', cls || 'lc-av');
      if (av) {
        const img = el('img');
        img.src = av;
        img.alt = '';
        img.loading = 'lazy';
        a.append(img);
      } else {
        a.textContent = (name || '?').trim().charAt(0).toUpperCase();
        a.style.background = 'hsl(' + HUES[uid % HUES.length] + ' 70% 88%)';
        a.style.color = 'hsl(' + HUES[uid % HUES.length] + ' 55% 32%)';
      }
      return a;
    }
    function nodesOf(id) {
      return root.querySelectorAll('.lc-msg[data-id="' + id + '"]');
    }
    function findMsg(id) {
      if (S.msgs.has(id)) return S.msgs.get(id);
      if (S.qOnly.has(id)) return S.qOnly.get(id);
      for (const t of S.threads.values()) if (t.has(id)) return t.get(id);
      return null;
    }

    // ── Отрисовка сообщения ────────────────────────────────────────
    // ctx: feed — общая лента; questions — вкладка «Вопросы»; thread — ответ в ветке.
    function renderMsg(m, ctx) {
      const n = el('div', 'lc-msg');
      n._m = m; // обработчики читают актуальное сообщение отсюда (см. morph)
      n.dataset.id = m.id;
      if (m.team) n.classList.add('is-team');
      if (m.uid === S.me.id) n.classList.add('is-mine');
      if (m.reply && m.reply.uid === S.me.id && m.uid !== S.me.id) n.classList.add('is-to-me');
      if (ctx === 'thread') n.classList.add('is-reply');

      n.append(avatar(m.uid, m.n, m.av));
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
      if (m.q) top.append(el('span', 'lc-qtag', '❓ Вопрос'));
      if (m.q && m.ans) {
        const a = el('span', 'lc-anstag', '✓ Есть ответ');
        if (isReplay && m.ansT !== undefined) {
          a.classList.add('is-link');
          a.title = 'Перемотать к ответу';
          a.addEventListener('click', () => seekVideo(m.ansT + S.replayShift));
        }
        top.append(a);
      }
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
      main.append(el('div', 'lc-text', m.text));
      main.append(renderChips(m, n));
      if (ctx !== 'thread' && m.tc && m.tc.c) main.append(threadLine(m, n));
      n.append(main);

      if (!isReplay) attachActions(n, m, ctx);
      return n;
    }

    // «💬 5 ответов» с аватарками ответивших — раскрывает ветку под сообщением.
    function threadLine(m, n) {
      const t = m.tc;
      const isOpen = S.open.has(m.id);
      const b = btn('lc-thline' + (isOpen ? ' is-open' : ''), '', () => toggleThread(n._m.id));
      if (isOpen) {
        b.append(el('span', '', 'Свернуть ответы'));
        return b;
      }
      const faces = el('span', 'lc-thfaces');
      (t.who || []).forEach((w) => faces.append(avatar(w.uid, w.n, w.av, 'lc-av lc-av--xs')));
      b.append(faces, el('span', '', t.c + ' ' + plural(t.c, 'ответ', 'ответа', 'ответов')));
      if (t.teach) b.append(el('span', 'lc-thteach', '· есть ответ преподавателя'));
      return b;
    }

    function renderChips(m, n) {
      const box = el('div', 'lc-chips');
      // Реакции команды — аватаркой с эмодзи, как «сердечко от автора».
      (m.tr || []).forEach(([rid, uid, name, av]) => {
        const r = reactions.get(rid);
        if (!r) return;
        const mine = uid === S.me.id;
        const t = el(mine ? 'button' : 'span', 'lc-tr' + (mine ? ' is-mine' : ''));
        if (mine) { t.type = 'button'; t.addEventListener('click', () => react(n._m, rid, t)); }
        t.title = name + ' — реакция';
        t.append(avatar(uid, name, av, 'lc-av lc-av--tr'), reactionImg(r, false));
        box.append(t);
      });
      (m.rx || []).forEach(([rid, count, mine]) => {
        const r = reactions.get(rid);
        if (!r || count < 1) return;
        const c = btn('lc-chip' + (mine ? ' is-mine' : ''), '', () => react(n._m, rid, c));
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
    // «Ответить»: на исходное — ответ в его ветку; на ответ в ветке — с цитатой.
    function replyTo(m) {
      if (!canWriteNow()) return;
      const rootId = m.th || m.id;
      const rootMsg = m.th ? findMsg(m.th) : m;
      if (!rootMsg) return;
      expand(rootId, false);
      setTarget(rootMsg, m.th ? m : null);
    }

    function attachActions(n, m, ctx) {
      if (!touch) {
        const bar = el('div', 'lc-actions');
        if (canReact() && firstMain()) {
          const heart = btn('lc-act lc-heart', '', () => react(n._m, firstMain().id, heart));
          heart.append(reactionImg(firstMain(), false));
          hoverPicker(heart, n);
          bar.append(heart);
        }
        if (canWriteNow()) {
          const r = btn('lc-act', '↩', () => replyTo(n._m));
          r.title = 'Ответить';
          bar.append(r);
        }
        if (S.me.team && m.q) bar.append(btn('lc-act lc-act--ok' + (m.ans ? ' is-on' : ''), '✓', () => answer(n._m)));
        if (S.me.admin) bar.append(btn('lc-act', '⋯', (e) => adminMenu(n._m, e.currentTarget)));
        if (bar.childElementCount) n.append(bar);
      } else {
        longPress(n, () => sheet(n));
        swipeReply(n, () => replyTo(n._m));
      }
    }

    // Обновление показанного сообщения на месте: меняются только изменившиеся
    // части. Если пересоздавать сообщение целиком (а реакции приходят часто),
    // клик, начатый на старом элементе и отпущенный на новом, теряется.
    function morph(old, fresh) {
      old._m = fresh._m;
      old.className = fresh.className;
      const a = Array.from(old.children), b = Array.from(fresh.children);
      if (a.length !== b.length || a.some((x, i) => x.className !== b[i].className)) { old.replaceWith(fresh); return fresh; }
      b.forEach((y, i) => {
        const x = a[i];
        if (x.outerHTML === y.outerHTML) return;
        const xa = Array.from(x.children), ya = Array.from(y.children);
        if (x.classList.contains('lc-main') && xa.length === ya.length) {
          ya.forEach((yy, j) => { if (xa[j].outerHTML !== yy.outerHTML) xa[j].replaceWith(yy); });
        } else {
          x.replaceWith(y);
        }
      });
      return old;
    }

    function firstMain() {
      return cfg.reactions.find((r) => r.main && r.active) || cfg.reactions.find((r) => r.active);
    }

    // ПК: наведение на ❤️ раскрывает основной ряд, «›» или свайп влево — все
    // реакции (повторно «‹» — свернуть). Выбор живёт поверх страницы
    // (position: fixed), иначе его обрезает край ленты.
    let floatPicker = null, floatTimer = null;
    function closeFloat() {
      clearTimeout(floatTimer);
      if (floatPicker) { floatPicker.remove(); floatPicker = null; }
    }
    function hoverPicker(heart, n) {
      const leave = () => { clearTimeout(floatTimer); floatTimer = setTimeout(closeFloat, 250); };
      heart.addEventListener('mouseenter', () => {
        clearTimeout(floatTimer);
        if (floatPicker && floatPicker.dataset.id === String(n._m.id)) return;
        closeFloat();
        floatPicker = picker(n, closeFloat, false);
        floatPicker.dataset.id = n._m.id;
        floatPicker.classList.add('is-float');
        document.body.append(floatPicker);
        const r = heart.getBoundingClientRect();
        floatPicker.style.left = Math.max(8, Math.min(r.right - floatPicker.offsetWidth, window.innerWidth - floatPicker.offsetWidth - 8)) + 'px';
        const top = r.top - floatPicker.offsetHeight - 6;
        floatPicker.style.top = (top > 8 ? top : r.bottom + 6) + 'px';
        floatPicker.dataset.bottom = (top > 8 ? top : r.bottom + 6) + floatPicker.offsetHeight;
        floatPicker.dataset.up = top > 8 ? '1' : '';
        floatPicker.addEventListener('mouseenter', () => clearTimeout(floatTimer));
        floatPicker.addEventListener('mouseleave', leave);
      });
      heart.addEventListener('mouseleave', leave);
    }

    // holder — элемент сообщения (берём актуальное n._m) или само сообщение.
    function picker(holder, done, forSheet) {
      const cur = () => holder._m || holder;
      const p = el('div', 'lc-picker' + (forSheet ? ' is-sheet' : ''));
      const mainRow = el('div', 'lc-picker-row');
      const extraRow = el('div', 'lc-picker-row lc-picker-extra');
      let extraIndex = 0;
      cfg.reactions.filter((r) => r.active).forEach((r) => {
        const b = btn('lc-pick', '', () => {
          react(cur(), r.id, b);
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
        if (!r.main) b.style.setProperty('--i', extraIndex++);
        (r.main ? mainRow : extraRow).append(b);
      });
      p.append(mainRow);
      if (extraRow.childElementCount) {
        const more = btn('lc-pick lc-more', '', () => setAll(!p.classList.contains('is-all')));
        more.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>';
        more.title = 'Все реакции';
        mainRow.append(more);
        p.append(extraRow);
        const setAll = (on) => {
          p.classList.toggle('is-all', on);
          more.title = on ? 'Свернуть' : 'Все реакции';
          // Раскрылся вверх — нижний край остаётся у сердечка.
          if (p.classList.contains('is-float') && p.dataset.up) {
            p.style.top = Math.max(8, Number(p.dataset.bottom) - p.offsetHeight) + 'px';
          }
        };
        // Свайп влево по реакциям — раскрыть, вправо — свернуть.
        let x0 = null;
        p.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
        p.addEventListener('touchmove', (e) => {
          if (x0 === null) return;
          const dx = e.touches[0].clientX - x0;
          if (dx < -30) { x0 = null; setAll(true); } else if (dx > 30) { x0 = null; setAll(false); }
        }, { passive: true });
        p.addEventListener('wheel', (e) => { if (Math.abs(e.deltaX) > 20) setAll(e.deltaX > 0); }, { passive: true });
      }
      return p;
    }

    async function react(m, rid, fromEl) {
      if (!canReact()) return;
      const r = reactions.get(rid);
      let added;
      // Сразу у себя (оптимистично), сервер подтвердит следующим опросом
      // (и уберёт самую раннюю, если у ученика их стало больше трёх).
      if (S.me.team) {
        const tr = (m.tr || []).slice();
        const i = tr.findIndex((x) => x[0] === rid && x[1] === S.me.id);
        added = i < 0;
        if (added) tr.push([rid, S.me.id, S.me.name, null]); else tr.splice(i, 1);
        refresh(Object.assign({}, m, { tr }));
      } else {
        const mine = (m.rx || []).some(([id, , me]) => id === rid && me);
        added = !mine;
        const rx = (m.rx || []).map((x) => x.slice());
        const ex = rx.find((x) => x[0] === rid);
        if (ex) { ex[1] += mine ? -1 : 1; ex[2] = !mine; } else rx.push([rid, 1, true]);
        refresh(Object.assign({}, m, { rx: rx.filter((x) => x[1] > 0) }));
      }
      if (added && r) burst(r, fromEl, m.id);
      if (navigator.vibrate && touch) navigator.vibrate(10);
      const res = await api('POST', url('react', m.id), { reaction: rid });
      if (!res.ok) toast(res.json.message || 'Не получилось');
      poll(true);
    }

    // Появление реакции — один из четырёх вариантов, чтобы не приедалось.
    const BURSTS = ['rise', 'ring', 'confetti', 'pop'];
    let lastBurst = '';
    function burst(r, fromEl, id) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      const node = Array.from(nodesOf(id)).find((x) => x.offsetParent);
      const chip = node && node.querySelector('.lc-chip.is-mine, .lc-tr.is-mine');
      const anchor = (chip && chip.isConnected ? chip : (fromEl && fromEl.isConnected ? fromEl : null)) || node;
      if (!anchor || !anchor.getBoundingClientRect) return;
      const rect = anchor.getBoundingClientRect();
      const kind = BURSTS.filter((k) => k !== lastBurst)[Math.floor(Math.random() * (BURSTS.length - 1))];
      lastBurst = kind;
      const fx = el('div', 'lc-burst lc-burst--' + kind);
      fx.style.left = rect.left + rect.width / 2 + 'px';
      fx.style.top = rect.top + rect.height / 2 + 'px';
      const emoji = (size, cls) => {
        // Сначала статичный кадр (уже в кэше) — анимация подменит его, как загрузится.
        const big = reactionImg(r, r.format === 'lottie' ? 'once' : false);
        big.classList.add(cls || 'lc-fx-main');
        big.style.setProperty('--s', size + 'px');
        if (r.format !== 'lottie' && r.anim !== big.src && !cls) {
          const pre = new Image();
          pre.onload = () => { big.src = r.anim; };
          pre.src = r.anim;
        }
        return big;
      };
      fx.append(emoji(kind === 'confetti' ? 34 : 54));
      if (kind === 'ring') fx.append(el('i', 'lc-fx-ring'), el('i', 'lc-fx-ring lc-fx-ring--2'));
      if (kind === 'rise') {
        for (let i = 0; i < 3; i++) {
          const s = emoji(18 + i * 4, 'lc-fx-mini');
          s.style.setProperty('--dx', (i - 1) * 22 + 'px');
          s.style.animationDelay = 90 + i * 110 + 'ms';
          fx.append(s);
        }
      }
      if (kind === 'confetti') {
        for (let i = 0; i < 6; i++) {
          const s = emoji(16 + (i % 3) * 3, 'lc-fx-mini');
          const a = -Math.PI / 2 + (i - 2.5) * 0.45;
          s.style.setProperty('--dx', Math.cos(a) * (52 + (i % 2) * 18) + 'px');
          s.style.setProperty('--dy', Math.sin(a) * (52 + (i % 2) * 18) + 'px');
          s.style.setProperty('--rot', (i % 2 ? 1 : -1) * (40 + i * 15) + 'deg');
          fx.append(s);
        }
      }
      if (kind === 'pop') {
        for (let i = 0; i < 6; i++) {
          const p = el('i', 'lc-spark');
          const a = (Math.PI * 2 * i) / 6 + 0.3;
          p.style.setProperty('--dx', Math.cos(a) * 34 + 'px');
          p.style.setProperty('--dy', Math.sin(a) * 34 + 'px');
          p.style.background = 'hsl(' + (200 + i * 30) + ' 85% 70%)';
          fx.append(p);
        }
      }
      document.body.append(fx);
      setTimeout(() => fx.remove(), 1300);
      const n = chip && chip.querySelector('.lc-chip-n');
      if (n) n.classList.add('is-bump');
    }

    async function answer(m) {
      const res = await api('POST', url('answer', m.id), { answered: !m.ans });
      if (!res.ok) toast('Не получилось');
      poll(true);
    }

    function popMenu(anchor, items, above) {
      closeMenus();
      const menu = el('div', 'lc-menu');
      items.forEach(([label, fn, opts]) => {
        opts = opts || {};
        const b = btn('lc-menu-item' + (opts.danger ? ' is-danger' : ''), label, () => { closeMenus(); fn(); });
        if (opts.disabled) { b.disabled = true; if (opts.hint) b.append(el('span', 'lc-menu-hint', opts.hint)); }
        menu.append(b);
      });
      document.body.append(menu);
      const r = anchor.getBoundingClientRect();
      const top = above ? r.top - menu.offsetHeight - 6 : r.bottom + 4;
      menu.style.top = Math.max(8, Math.min(top, window.innerHeight - menu.offsetHeight - 8)) + 'px';
      menu.style.left = Math.max(8, Math.min(above ? r.left : r.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 8)) + 'px';
      setTimeout(() => document.addEventListener('click', closeMenus, { once: true }), 0);
    }
    function closeMenus() {
      document.querySelectorAll('.lc-menu').forEach((x) => x.remove());
    }
    function adminMenu(m, anchor) {
      popMenu(anchor, adminItems(m));
    }
    function adminItems(m) {
      const out = [];
      if (!m.th) out.push([S.pinned && S.pinned.id === m.id ? 'Открепить' : 'Закрепить', () => pin(m)]);
      if (!m.team) {
        out.push(['Замьютить на 10 мин', () => mute(m, '10m')]);
        out.push(['Замьютить до конца эфира', () => mute(m, 'lesson')]);
        out.push(['Замьютить навсегда', () => mute(m, 'forever')]);
        out.push(['Снять мьют', () => unmute(m)]);
      }
      out.push(['Удалить сообщение', () => del(m), { danger: true }]);
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

    // Скрепка: вопрос преподавателю, эмоция, стикеры.
    function clipMenu(anchor) {
      const inThread = !!S.target;
      popMenu(anchor, [
        ['❓ Вопрос преподавателю', () => setQuestion(true), { disabled: inThread, hint: inThread ? 'не в ответе' : '' }],
        ['😊 Поделиться эмоцией', () => {}, { disabled: true, hint: 'скоро' }],
        ['🎨 Стикеры', () => {}, { disabled: true, hint: 'скоро' }],
      ], true);
    }
    function setQuestion(on) {
      S.question = on && !S.target;
      qTag.hidden = !S.question;
      clip.classList.toggle('is-on', S.question);
      input.placeholder = S.question ? 'Ваш вопрос преподавателю…' : 'Сообщение';
      if (on) input.focus();
    }

    // Телефон: долгое нажатие → меню с реакциями (как в Telegram).
    function longPress(n, fn) {
      let t = null, x = 0, y = 0;
      n.addEventListener('touchstart', (e) => {
        if (e.target.closest('.lc-chip,.lc-quote,.lc-name,.lc-thline,.lc-tr,.lc-thread')) return;
        x = e.touches[0].clientX; y = e.touches[0].clientY;
        t = setTimeout(() => { t = null; if (navigator.vibrate) navigator.vibrate(15); fn(); }, 400);
      }, { passive: true });
      n.addEventListener('touchmove', (e) => {
        if (t && (Math.abs(e.touches[0].clientX - x) > 8 || Math.abs(e.touches[0].clientY - y) > 8)) { clearTimeout(t); t = null; }
      }, { passive: true });
      n.addEventListener('touchend', () => { if (t) { clearTimeout(t); t = null; } });
      n.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    function swipeReply(n, fn) {
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
        if (dx <= -55) fn();
        x0 = null;
      });
    }

    let sheetEl = null;
    function sheet(n) {
      const m = n._m;
      closeSheet();
      sheetEl = el('div', 'lc-sheet');
      const back = el('div', 'lc-sheet-back');
      back.addEventListener('click', closeSheet);
      const wrap = el('div', 'lc-sheet-wrap');
      if (canReact()) wrap.append(picker(n, closeSheet, true));
      const clone = n.cloneNode(true);
      clone.classList.add('is-lifted');
      clone.querySelectorAll('.lc-actions, .lc-thline').forEach((x) => x.remove());
      wrap.append(clone);
      const menu = el('div', 'lc-sheet-menu');
      const item = (label, fn, opts) => menu.append(btn('lc-menu-item' + (opts && opts.danger ? ' is-danger' : ''), label, () => { closeSheet(); fn(); }));
      if (canWriteNow()) item('Ответить', () => replyTo(m));
      if (!m.th && m.tc && m.tc.c) item(S.open.has(m.id) ? 'Свернуть ответы' : 'Показать ответы', () => toggleThread(m.id));
      item('Копировать', () => { navigator.clipboard && navigator.clipboard.writeText(m.text); toast('Скопировано'); });
      if (S.me.team && m.q) item(m.ans ? 'Снять «есть ответ»' : '✓ Есть ответ', () => answer(m));
      if (S.me.admin) adminItems(m).forEach(([l, f, o]) => item(l, f, o));
      wrap.append(menu);
      sheetEl.append(back, wrap);
      document.body.append(sheetEl);
      requestAnimationFrame(() => sheetEl && sheetEl.classList.add('is-on'));
    }
    function closeSheet() {
      if (sheetEl) { sheetEl.remove(); sheetEl = null; }
    }

    function flash(id) {
      const node = Array.from(nodesOf(id)).find((x) => x.offsetParent);
      if (!node) { toast('Сообщение выше — прокрутите чат'); return; }
      node.scrollIntoView({ block: 'center', behavior: 'smooth' });
      node.classList.remove('is-flash');
      void node.offsetWidth;
      node.classList.add('is-flash');
    }

    // ── Ветки: раскрываются под сообщением ─────────────────────────
    function toggleThread(id) {
      if (S.open.has(id)) collapse(id); else expand(id, false);
    }
    async function expand(id, scroll) {
      S.unseenReplies = S.unseenReplies.filter((x) => x !== id);
      updateJumpReply();
      const was = S.open.has(id);
      S.open.add(id);
      refreshRoot(id);
      if (!S.threads.has(id)) {
        S.threads.set(id, new Map());
        if (isReplay) {
          replayMsgs.filter((m) => m.th === id).forEach((m) => S.threads.get(id).set(m.id, m));
        } else {
          try {
            const data = await getJson(cfg.url.thread + '&root=' + id);
            (data.msgs || []).forEach((m) => S.threads.get(id).set(m.id, m));
          } catch (e) {
            S.threads.delete(id);
            toast('Не удалось загрузить ответы');
          }
        }
      }
      syncThread(id);
      if (scroll || !was) {
        const blk = feed.querySelector('.lc-thread[data-root="' + id + '"]');
        if (blk && scroll) blk.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    }
    function collapse(id) {
      S.open.delete(id);
      if (S.target && S.target.root.id === id) clearTarget();
      refreshRoot(id);
      syncThread(id);
    }
    // Исходное перерисовать (строка «N ответов» ↔ «Свернуть ответы»).
    function refreshRoot(id) {
      const m = S.msgs.get(id) || S.qOnly.get(id);
      if (m) refresh(m);
    }
    // Блок ответов под исходным сообщением — в ленте и во вкладке «Вопросы».
    function syncThread(id) {
      [feed, qList].forEach((list) => {
        const host = list.querySelector('.lc-msg[data-id="' + id + '"]');
        let blk = list.querySelector('.lc-thread[data-root="' + id + '"]');
        if (!host || !S.open.has(id)) { if (blk) blk.remove(); return; }
        if (!blk) {
          blk = el('div', 'lc-thread');
          blk.dataset.root = id;
          blk.dataset.id = id + '.5';
          blk.classList.add('is-new');
        }
        if (host.nextElementSibling !== blk) host.after(blk);
        const items = Array.from((S.threads.get(id) || new Map()).values()).sort((a, b) => a.id - b.id)
          .filter((m) => !isReplay || S.replayAll || m.t + S.replayShift <= S.videoTime);
        const keep = new Map(Array.from(blk.querySelectorAll(':scope > .lc-msg')).map((x) => [Number(x.dataset.id), x]));
        const nodes = items.map((m) => (keep.has(m.id) ? morph(keep.get(m.id), renderMsg(m, 'thread')) : renderMsg(m, 'thread')));
        const foot = el('div', 'lc-thread-foot');
        if (canWriteNow()) foot.append(btn('lc-link', 'Ответить', () => { const r = S.msgs.get(id) || S.qOnly.get(id); if (r) { setTarget(r, null); } }));
        foot.append(btn('lc-link lc-link--muted', 'Свернуть', () => collapse(id)));
        if (!items.length) nodes.push(el('div', 'lc-thread-empty', S.threads.has(id) ? 'Ответов пока нет' : 'Загружаю…'));
        blk.replaceChildren(...nodes, foot);
      });
    }

    // ── Ответ: в ветку, по желанию с цитатой ───────────────────────
    function setTarget(rootMsg, quote) {
      if (!S.entered) return;
      S.target = { root: rootMsg, quote };
      setQuestion(false);
      replyBar.innerHTML = '';
      const t = el('div', 'lc-replybar-text');
      const who = quote || rootMsg;
      t.append(el('b', '', 'Ответ ' + who.n), el('span', '', who.text.slice(0, 90)));
      replyBar.append(t, btn('lc-x', '✕', clearTarget));
      replyBar.hidden = false;
      input.focus();
    }
    function clearTarget() {
      S.target = null;
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
      const target = S.target;
      const res = await api('POST', url('send'), {
        text,
        thread: target ? target.root.id : null,
        reply_to: target && target.quote ? target.quote.id : null,
        question: S.question,
      });
      sending = false;
      send.disabled = false;
      if (res.ok) {
        input.value = '';
        autosize();
        clearTarget();
        if (S.question) setQuestion(false);
        put(res.json.msg);
        if (!target) scrollBottom(true);
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
      renderNameBox();
      return true;
    }

    // Высота поля — по тексту; после отправки — снова одна строка.
    function autosize() {
      input.style.height = '';
      if (!input.value) { input.style.overflowY = 'hidden'; return; }
      const frame = input.offsetHeight - input.clientHeight;
      const h = Math.min(input.scrollHeight + frame, 120);
      if (h > input.offsetHeight) input.style.height = h + 'px';
      input.style.overflowY = input.scrollHeight + frame > 120 ? 'auto' : 'hidden';
    }
    input.addEventListener('input', autosize);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey && !touch) { e.preventDefault(); submit(); }
      if (e.key === 'Escape') { clearTarget(); setQuestion(false); }
    });
    // Телефон: клавиатура не должна закрывать поле и последние сообщения.
    // Нижнее меню сайта (fixed) на это время прячем — иначе оно закрывает поле.
    input.addEventListener('focus', () => {
      if (!touch) return;
      document.body.classList.add('lc-typing');
      setTimeout(() => { composer.scrollIntoView({ block: 'end', behavior: 'smooth' }); if (!S.target) scrollBottom(false); }, 350);
    });
    input.addEventListener('blur', () => document.body.classList.remove('lc-typing'));
    if (window.visualViewport && touch) {
      window.visualViewport.addEventListener('resize', () => {
        if (document.activeElement === input) composer.scrollIntoView({ block: 'end' });
      });
    }

    function renderNameBox() {
      nameBox.innerHTML = '';
      nameBox.hidden = S.me.team || isReplay || !S.entered || S.me.demo;
      if (nameBox.hidden) return;
      const edit = btn('lc-pencil', '', editName);
      edit.innerHTML = PENCIL;
      edit.title = 'Изменить имя';
      edit.setAttribute('aria-label', 'Изменить имя');
      nameBox.append(el('span', 'lc-namebox-label', 'Ваше имя:'), el('b', '', S.me.name), edit);
    }

    function editName() {
      const i = document.createElement('input');
      i.className = 'lc-gate-input lc-name-input';
      i.maxLength = cfg.nameMax;
      i.value = S.me.name;
      const ok = btn('lc-btn lc-btn--sm', 'ОК', async () => {
        const v = i.value.trim();
        if (v && v !== S.me.name) await saveName(v); else renderNameBox();
      });
      i.addEventListener('keydown', (e) => { if (e.key === 'Enter') ok.click(); if (e.key === 'Escape') renderNameBox(); });
      nameBox.innerHTML = '';
      nameBox.append(i, ok);
      i.focus();
      i.select();
    }

    function renderComposer() {
      if (isReplay) return;
      renderNameBox();
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
      composer.hidden = !blocked && !S.me.write;
      send.disabled = !!S.slowUntil;
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
      if (body.scrollTop < 40 && S.more && !loadingOlder && !isReplay && S.view === 'chat') loadOlder();
    });

    function insertSorted(container, node, id) {
      const items = container.children;
      for (let i = items.length - 1; i >= 0; i--) {
        if (Number(items[i].dataset.id) < id) {
          // за исходным может стоять его раскрытая ветка
          const after = items[i].classList.contains('lc-msg') && items[i].nextElementSibling && items[i].nextElementSibling.classList.contains('lc-thread')
            ? items[i].nextElementSibling : items[i];
          after.after(node);
          return;
        }
      }
      container.prepend(node);
    }

    // Изменённое сообщение — туда, где оно показано (лента, ветка, вопросы).
    function refresh(m) {
      if (m.th) {
        const t = S.threads.get(m.th);
        if (t) { if (m.del) t.delete(m.id); else t.set(m.id, m); syncThread(m.th); }
        return;
      }
      if (S.msgs.has(m.id) || !S.qOnly.has(m.id)) upsertFeed(m, true);
      if (S.qOnly.has(m.id)) S.qOnly.set(m.id, m);
      renderQuestions();
    }

    // Новое/изменённое сообщение с сервера. silent — без «новых сообщений».
    function put(m, silent) {
      if (m.th) {
        const t = S.threads.get(m.th);
        const isNew = t ? !t.has(m.id) : true;
        if (t) { if (m.del) t.delete(m.id); else t.set(m.id, m); syncThread(m.th); }
        // Ответили в моей ветке, а она свёрнута — кнопка «↩ N ответов вам».
        const r = S.msgs.get(m.th) || S.qOnly.get(m.th);
        if (!silent && isNew && !m.del && r && r.uid === S.me.id && m.uid !== S.me.id && !S.open.has(m.th)
          && !S.unseenReplies.includes(m.th)) {
          S.unseenReplies.push(m.th);
          updateJumpReply();
        }
        return;
      }
      if (m.del) {
        S.open.delete(m.id);
        S.threads.delete(m.id);
        if (S.target && S.target.root.id === m.id) clearTarget();
      }
      upsertFeed(m, silent);
    }

    function upsertFeed(m, silent) {
      if (m.del) {
        S.msgs.delete(m.id);
        S.qOnly.delete(m.id);
        root.querySelectorAll('.lc-msg[data-id="' + m.id + '"], .lc-thread[data-root="' + m.id + '"]').forEach((x) => x.remove());
        renderQuestions();
        return;
      }
      const isNew = !S.msgs.has(m.id);
      S.msgs.set(m.id, m);
      if (isReplay) return;
      const node = renderMsg(m, 'feed');
      const old = feed.querySelector('.lc-msg[data-id="' + m.id + '"]');
      if (old) morph(old, node); else insertSorted(feed, node, m.id);
      if (S.open.has(m.id)) syncThread(m.id);
      if (S.pinned && S.pinned.id === m.id) renderPinned(m);
      if (isNew && !silent && m.uid !== S.me.id && (!nearBottom() || S.view !== 'chat')) {
        S.unseen++;
        jumpNew.textContent = S.unseen + ' ' + plural(S.unseen, 'новое сообщение', 'новых сообщения', 'новых сообщений') + ' ↓';
        jumpNew.hidden = S.view !== 'chat';
      }
    }
    function updateJumpReply() {
      jumpReply.hidden = !S.unseenReplies.length;
      jumpReply.textContent = '↩ ' + S.unseenReplies.length + ' ' + plural(S.unseenReplies.length, 'ответ вам', 'ответа вам', 'ответов вам');
    }

    function renderPinned(m) {
      pinBar.innerHTML = '';
      pinBar.hidden = !m;
      if (!m) return;
      const t = el('div', 'lc-pin-text');
      t.append(el('b', '', '📌 ' + m.n), el('span', '', m.text));
      t.addEventListener('click', () => { if (isReplay) return; setView('chat'); flash(m.id); });
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
      const keep = new Map(Array.from(qList.querySelectorAll(':scope > .lc-msg')).map((x) => [Number(x.dataset.id), x]));
      const nodes = open.map((m) => (keep.has(m.id) ? morph(keep.get(m.id), renderMsg(m, 'questions')) : renderMsg(m, 'questions')));
      qList.replaceChildren(...nodes);
      open.forEach((m) => { if (S.open.has(m.id)) syncThread(m.id); });
      if (S.view === 'questions') empty.textContent = open.length ? '' : 'Вопросов пока нет';
    }

    function setView(v) {
      S.view = v;
      tabChat.classList.toggle('is-active', v === 'chat');
      tabQ.classList.toggle('is-active', v === 'questions');
      feed.hidden = v !== 'chat';
      qList.hidden = v !== 'questions';
      jumpNew.hidden = v !== 'chat' || !S.unseen;
      renderQuestions();
      updateEmpty();
      if (v === 'questions') body.scrollTop = 0; else scrollBottom(false);
    }

    function updateEmpty() {
      if (S.view === 'questions') return;
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
        S.threads.clear();
        feed.innerHTML = '';
        S.more = !!data.more;
        (data.questions || []).forEach((m) => S.qOnly.set(m.id, m));
        (data.msgs || []).forEach((m) => put(m, true));
        Array.from(S.open).forEach((id) => { S.open.delete(id); if (S.msgs.has(id) || S.qOnly.has(id)) expand(id, false); });
        scrollBottom(false);
      } else if (data.msgs) {
        const stick = nearBottom();
        data.msgs.forEach((m) => {
          if (!m.th && S.qOnly.has(m.id)) {
            if (m.del) S.qOnly.delete(m.id); else S.qOnly.set(m.id, m);
            if (!S.msgs.has(m.id)) { renderQuestions(); return; }
          }
          // Изменение старого сообщения, которого нет в загруженной ленте, — пропускаем.
          if (!m.th && !S.msgs.has(m.id) && !m.del && S.msgs.size && m.id < Math.min(...S.msgs.keys())) return;
          put(m);
        });
        if (stick && S.view === 'chat') scrollBottom(false);
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
        if (old) morph(old, renderMsg(m, 'feed'));
      });
      S.open.forEach((id) => syncThread(id));
      renderComposer();
    }

    let loadingOlder = false;
    async function loadOlder() {
      const ids = Array.from(S.msgs.keys());
      if (!ids.length) return;
      loadingOlder = true;
      try {
        const data = await getJson(cfg.url.older + '&before=' + Math.min(...ids));
        const h = body.scrollHeight;
        data.msgs.forEach((m) => put(m, true));
        S.more = data.more;
        body.scrollTop += body.scrollHeight - h;
      } catch (e) { /* следующая прокрутка попробует снова */ }
      loadingOlder = false;
    }

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) poll(true);
    });

    // ── Запись: сообщения по времени видео ─────────────────────────
    let replayMsgs = [];
    async function startReplay() {
      try {
        const data = await getJson(cfg.url.replay);
        S.replayShift = data.shift || 0;
        replayMsgs = data.msgs;
        renderReplay(true);
      } catch (e) {
        empty.textContent = 'Не удалось загрузить чат';
      }
    }
    let shown = 0;
    function replayView(m, limit) {
      // Счётчик ветки — только из уже прозвучавших ответов.
      const answers = replayMsgs.filter((x) => x.th === m.id && (S.replayAll || x.t + S.replayShift <= limit));
      return m.tc ? Object.assign({}, m, { tc: answers.length ? Object.assign({}, m.tc, { c: answers.length }) : null }) : m;
    }
    function renderReplay(rebuild) {
      const limit = S.replayAll ? Infinity : S.videoTime;
      const visible = replayMsgs.filter((m) => !m.th && m.t + S.replayShift <= limit);
      if (rebuild || visible.length < shown) {
        feed.innerHTML = '';
        shown = 0;
      }
      const stick = rebuild ? !S.replayAll : nearBottom();
      for (let i = shown; i < visible.length; i++) {
        const m = replayView(visible[i], limit);
        S.msgs.set(m.id, m);
        feed.append(renderMsg(m, 'feed'));
      }
      shown = visible.length;
      S.open.forEach((id) => syncThread(id));
      updateEmpty();
      if (stick && S.view === 'chat') scrollBottom(false);
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
