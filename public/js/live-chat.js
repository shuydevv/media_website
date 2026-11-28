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
      room: { breakUntil: null, quietUntil: null },
      emo: [],                // эмоции над лентой
      poll: null,             // «Пон / Не пон»
      online: null,           // сколько в чате (видит команда)
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

    // Телефон: чат сворачивается в одну строку с последним сообщением и
    // счётчиком новых — для тех, кто хочет просто смотреть.
    const ticker = btn('lc-ticker', '', () => setFolded(false));
    const tickerText = el('span', 'lc-ticker-text', 'Чат свёрнут');
    const tickerCount = el('span', 'lc-ticker-count');
    tickerCount.hidden = true;
    ticker.append(tickerText, tickerCount);
    const fold = btn('lc-fold', '', () => setFolded(!root.classList.contains('is-folded')));
    fold.setAttribute('aria-label', 'Свернуть чат');
    fold.title = 'Свернуть чат';
    fold.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
    if (!isReplay) head.append(ticker, fold);

    let replayToggle = null;
    if (isReplay) {
      replayToggle = btn('lc-ghost', 'Весь чат', () => {
        S.replayAll = !S.replayAll;
        replayToggle.textContent = S.replayAll ? 'По видео' : 'Весь чат';
        renderReplay(true);
      });
      head.append(replayToggle);
    }

    // Пульт эфира — у команды прямо в чате (и на странице урока, и в OBS):
    // онлайн, «Пон / Не пон», перерыв, тишина, итоги урока.
    const consoleBar = el('div', 'lc-console');
    consoleBar.hidden = !S.me.team || isReplay;
    // Онлайн — в строке «Чат», чтобы кнопки пульта помещались в одну строку.
    const onlineEl = el('span', 'lc-online', '');
    onlineEl.title = 'Сейчас в чате';
    if (S.me.team && !isReplay) title.append(onlineEl);
    let pollBtn = null;
    if (S.me.admin && !isReplay) {
      pollBtn = btn('lc-cbtn', '👍 Пон / Не пон', () => togglePoll());
      consoleBar.append(
        pollBtn,
        btn('lc-cbtn', '☕ Перерыв', (e) => roomMenu(e.currentTarget, 'break')),
        btn('lc-cbtn', '🤫 Тишина', (e) => roomMenu(e.currentTarget, 'quiet')),
      );
    }
    const statsBtn = btn('lc-cbtn', '📊 Итоги', () => setView(S.view === 'stats' ? 'chat' : 'stats'));
    if (S.me.team && !isReplay) consoleBar.append(statsBtn);

    const pinBar = el('div', 'lc-pin');
    // Над лентой: опрос «Пон / Не пон» или эмоции учеников (опрос важнее).
    const strip = el('div', 'lc-strip');
    strip.hidden = true;
    const banner = el('div', 'lc-banner');

    const bodyWrap = el('div', 'lc-bodywrap');
    const body = el('div', 'lc-body');
    const feed = el('div', 'lc-feed');
    const qList = el('div', 'lc-feed lc-qlist');
    qList.hidden = true;
    const empty = el('div', 'lc-empty');
    const statsView = el('div', 'lc-stats');
    statsView.hidden = true;
    body.append(feed, qList, statsView, empty);

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

    root.append(head, consoleBar, pinBar, strip, banner, bodyWrap);
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
    let unreadFolded = 0;
    function setFolded(on) {
      root.classList.toggle('is-folded', on);
      const hostEl = root.closest('.lc-host');
      if (hostEl) hostEl.classList.toggle('is-folded', on);
      fold.title = on ? 'Развернуть чат' : 'Свернуть чат';
      try { localStorage.setItem('lc-folded', on ? '1' : ''); } catch (e) { /* приватный режим */ }
      if (!on) {
        unreadFolded = 0;
        tickerCount.hidden = true;
        scrollBottom(false);
      }
    }
    function tick(m) {
      tickerText.textContent = m.n + ': ' + m.text;
      if (root.classList.contains('is-folded') && m.uid !== S.me.id) {
        unreadFolded++;
        tickerCount.textContent = unreadFolded > 99 ? '99+' : String(unreadFolded);
        tickerCount.hidden = false;
      }
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
      return m === undefined ? u : u.replace(/\/(messages|mutes|emotions)\/0(?=\/|$)/, '/$1/' + m);
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
      n.dataset.uid = m.uid;
      n.dataset.ts = isReplay && m.t !== undefined ? m.t : m.ts;
      if (m.q || m.reply) n.dataset.solo = '1'; // вопрос и цитата начинают новую группу
      if (m.team) n.classList.add('is-team');
      if (m.uid === S.me.id) n.classList.add('is-mine');
      if (m.reply && m.reply.uid === S.me.id && m.uid !== S.me.id) n.classList.add('is-to-me');
      if (ctx === 'thread') n.classList.add('is-reply');
      if (ctx !== 'thread' && S.open.has(m.id)) n.classList.add('is-open-root');

      n.append(avatar(m.uid, m.n, m.av));
      n.append(el('span', 'lc-gtime', isReplay && m.t !== undefined ? clock(m.t + S.replayShift) : hhmm(m.ts)));
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
      // Одна метка у вопроса: до ответа — «❓ Вопрос», после — «✓ Вопрос · есть ответ».
      if (m.q) {
        const a = el('span', 'lc-qtag' + (m.ans ? ' is-done' : ''), m.ans ? '✓ Вопрос · есть ответ' : '❓ Вопрос');
        if (m.ans && isReplay && m.ansT !== undefined) {
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
      if (ctx !== 'thread' && m.tc && m.tc.c && !S.open.has(m.id)) main.append(threadLine(m, n));
      n.append(main);

      if (!isReplay) attachActions(n, m, ctx);
      return n;
    }

    // «💬 5 ответов» с аватарками ответивших — раскрывает ветку под сообщением.
    function threadLine(m, n) {
      const t = m.tc;
      const b = btn('lc-thline', '', () => toggleThread(n._m.id));
      const faces = el('span', 'lc-thfaces');
      (t.who || []).forEach((w) => faces.append(avatar(w.uid, w.n, w.av, 'lc-av lc-av--xs')));
      b.append(faces, el('span', '', t.c + ' ' + plural(t.c, 'ответ', 'ответа', 'ответов')));
      if (t.teach) b.append(el('span', 'lc-thteach', '· есть ответ преподавателя'));
      else if (t.last && !isReplay) b.append(el('span', 'lc-thlast', '· ' + hhmm(t.last)));
      return b;
    }

    function renderChips(m, n) {
      const box = el('div', 'lc-chips');
      // Реакции команды — как в Telegram: такая же плашка, но справа вместо
      // числа — аватарка преподавателя. В общий счётчик не входят.
      (m.tr || []).forEach(([rid, uid, name, av]) => {
        const r = reactions.get(rid);
        if (!r) return;
        const mine = uid === S.me.id;
        const t = mine ? btn('lc-chip lc-chip--tr is-mine', '', () => react(n._m, rid, t)) : el('span', 'lc-chip lc-chip--tr');
        t.dataset.rid = rid;
        t.title = name + ' — реакция';
        t.append(reactionImg(r, false), avatar(uid, name, av, 'lc-av lc-av--chip'));
        box.append(t);
      });
      (m.rx || []).forEach(([rid, count, mine]) => {
        const r = reactions.get(rid);
        if (!r || count < 1) return;
        const c = btn('lc-chip' + (mine ? ' is-mine' : ''), '', () => react(n._m, rid, c));
        c.dataset.rid = rid;
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

    // Подряд идущие сообщения одного человека (меньше 2 минут) — группой, как в
    // Telegram: аватарка и имя только у первого. Ветка, вопрос, цитата —
    // начинают новую группу.
    function regroup(list) {
      let prev = null;
      Array.from(list.children).forEach((x) => {
        if (!x.classList.contains('lc-msg')) { prev = null; return; }
        const cont = prev && !x.dataset.solo && prev.dataset.uid === x.dataset.uid
          && Math.abs(Number(x.dataset.ts) - Number(prev.dataset.ts)) < 120
          && !prev.classList.contains('is-open-root');
        x.classList.toggle('is-cont', !!cont);
        prev = x;
      });
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
      let mainIndex = 0;
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
        b.style.setProperty('--i', r.main ? mainIndex++ : extraIndex++);
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
      // Чуть позже: на телефоне сначала закрывается лист с реакциями.
      if (added && r) setTimeout(() => burst(r, fromEl, m.id, rid), 40);
      if (navigator.vibrate && touch) navigator.vibrate(10);
      const res = await api('POST', url('react', m.id), { reaction: rid });
      if (!res.ok) toast(res.json.message || 'Не получилось');
      poll(true);
    }

    // Появление реакции — один из четырёх вариантов, чтобы не приедалось.
    const BURSTS = ['rise', 'ring', 'confetti', 'pop'];
    let lastBurst = '';
    function burst(r, fromEl, id, rid) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      const node = Array.from(nodesOf(id)).find((x) => x.offsetParent);
      // Анимация — от той реакции, которую нажали, а не от первой своей.
      const chip = node && node.querySelector('.lc-chip.is-mine[data-rid="' + rid + '"], .lc-tr.is-mine[data-rid="' + rid + '"]');
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

    // Одно открытое меню; повторное нажатие на ту же кнопку его закрывает.
    // Раньше обработчик «нажали мимо» от прошлого открытия закрывал новое меню —
    // скрепка открывалась через раз.
    let menuAnchor = null;
    let menuOff = null;
    let hintTimer = null;
    function armMenu(menu, anchor) {
      menuAnchor = anchor;
      const off = (e) => {
        if (menu.contains(e.target) || (anchor && anchor.contains(e.target))) return;
        closeMenus();
      };
      menuOff = off;
      setTimeout(() => { if (menuOff === off) document.addEventListener('click', off); }, 0);
    }
    function toggleSame(anchor) {
      if (anchor && menuAnchor === anchor && document.querySelector('.lc-menu')) { closeMenus(); return true; }
      return false;
    }
    function popMenu(anchor, items, above) {
      if (toggleSame(anchor)) return;
      closeMenus();
      const menu = el('div', 'lc-menu');
      items.forEach(([label, fn, opts]) => {
        opts = opts || {};
        const b = btn('lc-menu-item' + (opts.danger ? ' is-danger' : ''), label, () => { closeMenus(); fn(); });
        if (opts.disabled) {
          b.disabled = true;
          if (opts.hint) {
            const h = el('span', 'lc-menu-hint', opts.hint);
            if (opts.until) h.dataset.until = opts.until; // живой отсчёт
            b.append(h);
          }
        }
        menu.append(b);
      });
      document.body.append(menu);
      const r = anchor.getBoundingClientRect();
      const top = above ? r.top - menu.offsetHeight - 6 : r.bottom + 4;
      menu.style.top = Math.max(8, Math.min(top, window.innerHeight - menu.offsetHeight - 8)) + 'px';
      menu.style.left = Math.max(8, Math.min(above ? r.left : r.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 8)) + 'px';
      if (menu.querySelector('[data-until]')) {
        hintTimer = setInterval(() => {
          menu.querySelectorAll('[data-until]').forEach((h) => {
            const l = Math.max(0, Number(h.dataset.until) - Math.floor(Date.now() / 1000));
            if (l > 0) { h.textContent = 'через ' + Math.floor(l / 60) + ':' + String(l % 60).padStart(2, '0'); return; }
            h.closest('button').disabled = false;
            h.remove();
          });
        }, 1000);
      }
      armMenu(menu, anchor);
    }
    function closeMenus() {
      document.querySelectorAll('.lc-menu').forEach((x) => x.remove());
      if (menuOff) document.removeEventListener('click', menuOff);
      menuOff = null;
      menuAnchor = null;
      clearInterval(hintTimer);
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
        emotionItem(),
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
      regroup(feed);
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
          blk.addEventListener('animationend', () => blk.classList.remove('is-new'), { once: true });
        }
        if (host.nextElementSibling !== blk) host.after(blk);
        const items = Array.from((S.threads.get(id) || new Map()).values()).sort((a, b) => a.id - b.id)
          .filter((m) => !isReplay || S.replayAll || m.t + S.replayShift <= S.videoTime);
        const keep = new Map(Array.from(blk.querySelectorAll(':scope > .lc-msg')).map((x) => [Number(x.dataset.id), x]));
        const nodes = items.map((m) => (keep.has(m.id) ? morph(keep.get(m.id), renderMsg(m, 'thread')) : renderMsg(m, 'thread')));
        const foot = el('div', 'lc-thread-foot');
        if (canWriteNow()) foot.append(btn('lc-link', 'Ответить', () => { const r = S.msgs.get(id) || S.qOnly.get(id); if (r) { setTarget(r, null); } }));
        foot.append(btn('lc-link lc-link--muted', 'Свернуть ответы', () => collapse(id)));
        if (!items.length) nodes.push(el('div', 'lc-thread-empty', S.threads.has(id) ? 'Ответов пока нет' : 'Загружаю…'));
        blk.replaceChildren(...nodes, foot);
        regroup(blk);
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
        if (touch) input.blur(); // телефон: после отправки клавиатура убирается
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
      if (res.json.name !== name.trim() && !S.me.emojiName) toast('Эмодзи в имени — с ' + cfg.emojiNameLevel + ' уровня акулы');
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
    // Телефон, открыта клавиатура: видео и чат прикрепляются к видимой части
    // экрана (visualViewport) — видео всегда сверху, чат сжимается до места над
    // клавиатурой, поле ввода — прямо над ней. Иначе браузер сам прокручивает
    // страницу к полю, и видео уезжает за верх экрана.
    // Нижнее меню сайта (fixed) на это время прячем — иначе оно закрывает поле.
    const layout = root.closest('.lc-layout');
    const host = root.closest('.lc-host');
    let pinned = false;
    function layoutPin() {
      if (!pinned) return;
      const vv = window.visualViewport;
      const stick = nearBottom();
      layout.style.top = vv.offsetTop + 'px';
      layout.style.height = vv.height + 'px';
      const video = layout.firstElementChild.getBoundingClientRect().height;
      host.style.minHeight = '0';
      host.style.height = Math.max(140, Math.floor(vv.height - video - 20)) + 'px';
      if (stick && !S.target) scrollBottom(false);
    }
    function pinLayout() {
      if (!touch || !phone || !layout || !host || !window.visualViewport || pinned || landscape.matches) return;
      pinned = true;
      layout.classList.add('is-pinned');
      layoutPin();
      setTimeout(layoutPin, 350); // клавиатура выезжает не сразу
    }
    function unpinLayout() {
      if (!pinned) return;
      pinned = false;
      layout.classList.remove('is-pinned');
      layout.style.top = layout.style.height = host.style.height = host.style.minHeight = '';
      layout.scrollIntoView({ block: 'start' });
    }
    input.addEventListener('focus', () => {
      if (!touch) return;
      document.body.classList.add('lc-typing');
      pinLayout();
    });
    input.addEventListener('blur', () => {
      // Нажатие на скрепку или меню тоже снимает фокус — ждём, вернётся ли он.
      setTimeout(() => {
        if (document.activeElement === input) return;
        document.body.classList.remove('lc-typing');
        unpinLayout();
      }, 200);
    });
    if (window.visualViewport && touch) {
      window.visualViewport.addEventListener('resize', layoutPin);
      window.visualViewport.addEventListener('scroll', layoutPin);
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
      regroup(feed);
      // строка свёрнутого чата — всегда последнее сообщение ленты
      if (isNew && m.id >= Number(tickerText.dataset.last || 0)) {
        tickerText.dataset.last = m.id;
        if (silent) tickerText.textContent = m.n + ': ' + m.text; else tick(m);
      }
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
      regroup(qList);
      open.forEach((m) => { if (S.open.has(m.id)) syncThread(m.id); });
      if (S.view === 'questions') empty.textContent = open.length ? '' : 'Вопросов пока нет';
    }

    function setView(v) {
      S.view = v;
      tabChat.classList.toggle('is-active', v === 'chat');
      tabQ.classList.toggle('is-active', v === 'questions');
      feed.hidden = v !== 'chat';
      qList.hidden = v !== 'questions';
      statsView.hidden = v !== 'stats';
      statsBtn.classList.toggle('is-on', v === 'stats');
      if (v === 'stats') loadStats(); else clearInterval(statsTimer);
      jumpNew.hidden = v !== 'chat' || !S.unseen;
      renderQuestions();
      updateEmpty();
      if (v === 'questions' || v === 'stats') body.scrollTop = 0; else scrollBottom(false);
    }

    function updateEmpty() {
      if (S.view === 'questions') return;
      if (S.view === 'stats') { empty.textContent = ''; return; }
      empty.textContent = feed.childElementCount ? '' : (isReplay ? (S.replayAll ? 'В чате не было сообщений' : 'Сообщения появятся по ходу видео') : 'Сообщений пока нет');
    }

    // Пока идёт эфир, экран телефона не гаснет (как в YouTube): ученик просто
    // смотрит и не трогает экран — без этого через минуту он погаснет.
    let wakeLock = null;
    async function keepAwake() {
      const live = S.state.s === 'open' || S.state.s === 'break';
      if (!touch || isReplay || !('wakeLock' in navigator)) return;
      if (!live || document.hidden) {
        if (wakeLock) { wakeLock.release().catch(() => {}); wakeLock = null; }
        return;
      }
      if (wakeLock) return;
      try {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => { wakeLock = null; });
      } catch (e) { /* не разрешили — не страшно */ }
    }

    function applyState() {
      keepAwake();
      const s = S.state.s;
      // Статус — кружком: зелёный — чат открыт, жёлтый — эфир прервался,
      // серый — закрыт, ещё не открылся или это запись. Словами — в подсказке.
      pill.className = 'lc-pill is-' + (isReplay ? 'replay' : s);
      pill.textContent = '';
      pill.title = isReplay ? 'Запись эфира' : ({ waiting: 'Чат скоро откроется', open: 'Чат открыт', break: 'Эфир прервался', closed: 'Эфир завершён' })[s] || '';
      let text = '';
      if (!isReplay) {
        if (s === 'waiting') text = 'Чат откроется' + (S.state.opensLabel ? ' в ' + S.state.opensLabel : ' перед уроком');
        else if (s === 'break') text = 'Эфир прервался, ждём преподавателя';
        else if (s === 'closed') text = 'Эфир завершён';
      }
      banner.textContent = text;
      banner.hidden = !text;
      banner.className = 'lc-banner is-' + s;
      applyRoom();
      renderComposer();
    }

    // ── Опрос сервера ──────────────────────────────────────────────
    let pollTimer = null;
    let lastPresence = 0;
    let polling = false;
    let failures = 0;
    async function poll(now, full) {
      if (isReplay) return;
      clearTimeout(pollTimer);
      if (polling) { pollTimer = setTimeout(() => poll(), 300); return; }
      polling = true;
      try {
        const presence = Date.now() - lastPresence > 20000;
        if (presence) lastPresence = Date.now();
        const res = await fetch(cfg.url.feed + '&v=' + (full ? 0 : S.v) + (presence ? '&p=1' : ''), { credentials: 'omit', headers: { Accept: 'application/json' } });
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
      if ('online' in data) { S.online = data.online; onlineEl.textContent = '👥 ' + data.online; }
      if (data.room) { S.room = data.room; applyRoom(); }
      if ('emo' in data) S.emo = data.emo || [];
      if ('poll' in data) S.poll = data.poll;
      if ('emo' in data || 'poll' in data) { renderStrip(); renderConsole(); }
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
      keepAwake();
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
      regroup(feed);
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

    // Телефон повёрнут: видео на весь экран, чат полупрозрачной колонкой
    // поверх (CSS — .lc-landscape). Страница под ним не прокручивается.
    const landscape = window.matchMedia('(hover: none) and (orientation: landscape) and (max-height: 520px)');
    // Высота — по видимой области экрана: «inset: 0» у fixed в мобильных
    // браузерах считается от другой высоты, и низ чата уезжал за экран.
    function landFit() {
      if (!layout || pinned) return;
      if (landscape.matches) {
        const vv = window.visualViewport;
        layout.style.top = (vv ? vv.offsetTop : 0) + 'px';
        layout.style.height = (vv ? vv.height : window.innerHeight) + 'px';
      } else {
        layout.style.top = layout.style.height = '';
      }
    }
    function onOrient() {
      if (!layout) return;
      document.documentElement.classList.toggle('lc-landscape', landscape.matches);
      landFit();
      if (!landscape.matches) layout.scrollIntoView({ block: 'start' });
    }
    if (landscape.addEventListener) landscape.addEventListener('change', onOrient);
    window.addEventListener('resize', landFit);
    if (window.visualViewport) window.visualViewport.addEventListener('resize', landFit);
    onOrient();

    // ── Пульт: перерыв, тишина, «Пон / Не пон» ─────────────────────
    const nowSec = () => Math.floor(Date.now() / 1000);
    const left = (ts) => Math.max(0, ts - nowSec());
    const mmss = (sec) => Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0');

    function roomMenu(anchor, kind) {
      const active = kind === 'break' ? S.room.breakUntil : S.room.quietUntil;
      const set = async (minutes) => {
        const res = await api('POST', url(kind), { minutes });
        if (!res.ok) toast(res.json.message || 'Не получилось');
        poll(true);
      };
      const items = [5, 10, 15].map((m) => [m + ' минут', () => set(m)]);
      if (active && left(active)) items.push([kind === 'break' ? 'Закончить перерыв' : 'Закончить тишину', () => set(0), { danger: true }]);
      popMenu(anchor, items);
    }
    async function togglePoll() {
      const active = S.poll && S.poll.active && left(S.poll.ends);
      const res = active ? await api('DELETE', url('pollClose')) : await api('POST', url('pollStart'));
      if (!res.ok) toast(res.json.message || 'Не получилось');
      poll(true);
    }
    function renderConsole() {
      if (!pollBtn) return;
      const active = S.poll && S.poll.active && left(S.poll.ends);
      pollBtn.textContent = active ? '⏹ Закрыть · ' + mmss(left(S.poll.ends)) : '👍 Пон / Не пон';
      pollBtn.classList.toggle('is-on', !!active);
    }

    // Перерыв — плашкой с отсчётом; тишина — ученикам можно только вопросы.
    function applyRoom() {
      if (isReplay) return;
      const s = S.state.s;
      const quiet = S.room.quietUntil && left(S.room.quietUntil);
      if (s === 'open' || s === 'break') {
        const br = S.room.breakUntil && left(S.room.breakUntil);
        const parts = [];
        if (br) parts.push('☕ Перерыв · вернёмся через ' + mmss(br));
        if (quiet) parts.push('🤫 Тишина до ' + hhmm(S.room.quietUntil) + (S.me.team ? '' : ' — можно только задать вопрос'));
        if (s === 'open') {
          banner.textContent = parts.join(' · ');
          banner.hidden = !parts.length;
          banner.className = 'lc-banner is-room';
        }
      }
      const forced = !!quiet && !S.me.team;
      if (forced !== !!S.quietForced) {
        S.quietForced = forced;
        if (forced) setQuestion(true); else setQuestion(false);
        qTag.querySelector('.lc-x').hidden = forced;
      }
    }

    // ── Над лентой: опрос или эмоции ───────────────────────────────
    function emotionOf(code) {
      return (cfg.emotions || []).find((e) => e.code === code) || { e: '❔', label: code, img: '' };
    }
    function emojiImg(e, cls) {
      if (!e.img) return el('span', cls || 'lc-emo-img', e.e);
      const i = el('img', cls || 'lc-emo-img');
      i.src = e.img;
      i.alt = e.e;
      return i;
    }
    function activeEmo() {
      return S.emo.filter((x) => left(x.ends) > 0);
    }
    function pollVisible() {
      return S.poll && (S.poll.active && left(S.poll.ends) > 0 || left(S.poll.hideAt) > 0);
    }

    function renderStrip() {
      if (isReplay) { strip.hidden = true; return; }
      if (pollVisible()) {
        renderPoll();
        return;
      }
      const list = activeEmo();
      strip.hidden = !list.length;
      strip.classList.remove('is-poll');
      if (!list.length) { strip.replaceChildren(); return; }
      // Эмоции по id — чтобы тающая полоска не начиналась заново при каждом опросе.
      const box = strip.querySelector(':scope > .lc-emos') || el('div', 'lc-emos');
      const keep = new Map(Array.from(box.children).map((x) => [Number(x.dataset.id), x]));
      const nodes = list.map((x) => {
        const info = emotionOf(x.code);
        let b = keep.get(x.id);
        if (!b) {
          b = btn('lc-emo is-new', '', () => tapEmotion(b));
          b.dataset.id = x.id;
          b.dataset.code = x.code; // свой цвет у каждой эмоции (CSS)
          b.addEventListener('animationend', (e) => { if (e.target === b) b.classList.remove('is-new', 'is-bump'); });
          longPress(b, () => { b._lp = Date.now(); emotionPop(b); });
          const bar = el('i', 'lc-emo-bar');
          bar.style.animationDuration = left(x.ends) + 's';
          const icon = el('span', 'lc-emo-icon');
          icon.append(emojiImg(info));
          b.append(icon, el('span', 'lc-emo-label', info.label), el('span', 'lc-emo-n'), bar);
          b._c = x.c;
        }
        // Кто-то поддержал — эмоция «вздрагивает», вылетает «+1».
        if (x.c > (b._c || 0) && b._e) {
          b.classList.remove('is-bump');
          void b.offsetWidth;
          b.classList.add('is-bump');
          floatText(b, '+' + (x.c - b._c));
        }
        b._c = x.c;
        b._e = x;
        b.classList.toggle('is-mine', !!x.mine);
        b.title = x.n + ' чувствует: ' + info.label + (x.c ? ' · поддержали: ' + x.names.join(', ') : '') + (x.mine || x.uid === S.me.id ? '' : ' · нажмите, чтобы поддержать');
        b.querySelector('.lc-emo-n').textContent = x.c ? String(x.c) : '';
        return b;
      });
      const ids = new Set(list.map((x) => x.id));
      Array.from(box.children).forEach((c) => { if (!ids.has(Number(c.dataset.id))) c.remove(); });
      nodes.forEach((b) => { if (b.parentNode !== box) box.append(b); });
      if (!box.isConnected) strip.replaceChildren(box);
    }

    // Нажатие на чужую эмоцию — сразу поддержка (как реакция); на свою или уже
    // поддержанную — подробности. Подробности ещё — долгим нажатием.
    async function tapEmotion(b) {
      if (b._lp && Date.now() - b._lp < 900) return; // это было долгое нажатие
      const x = b._e;
      if (x.mine || x.uid === S.me.id || !cfg.url.support || !canReact()) { emotionPop(b); return; }
      x.mine = true;
      x.c += 1;
      b._c = x.c;
      b._e = x;
      b.classList.add('is-mine');
      b.querySelector('.lc-emo-n').textContent = String(x.c);
      b.classList.remove('is-bump');
      void b.offsetWidth;
      b.classList.add('is-bump', 'is-pop');
      setTimeout(() => b.classList.remove('is-pop'), 650);
      floatText(b, '+1');
      if (navigator.vibrate && touch) navigator.vibrate(12);
      const res = await api('POST', url('support', x.id));
      if (!res.ok) toast(res.json.message || 'Не получилось');
      poll(true);
    }

    // «+1» и эмодзи, улетающие вверх от элемента.
    function floatText(anchor, text) {
      if (!anchor.isConnected || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      const r = anchor.getBoundingClientRect();
      const f = el('div', 'lc-float', text);
      f.style.left = r.left + r.width / 2 + 'px';
      f.style.top = r.top + 'px';
      document.body.append(f);
      setTimeout(() => f.remove(), 900);
    }

    function emotionPop(chip) {
      const x = chip._e;
      const info = emotionOf(x.code);
      const pop = el('div', 'lc-pop');
      const head = el('div', 'lc-pop-head');
      head.append(el('b', '', x.n), el('span', '', ' чувствует:'));
      const big = el('div', 'lc-pop-emo');
      big.append(emojiImg(info, 'lc-pop-img'), el('span', '', info.label));
      const sup = btn('lc-btn lc-btn--sm', x.uid === S.me.id ? 'Это ваша эмоция' : (x.mine ? 'Вы поддержали' : 'Поддержать'), async () => {
        closeMenus();
        const res = await api('POST', url('support', x.id));
        if (!res.ok) toast(res.json.message || 'Не получилось');
        else if (navigator.vibrate && touch) navigator.vibrate(10);
        poll(true);
      });
      sup.disabled = !!x.mine || !cfg.url.support || !canReact();
      pop.append(head, big, sup);
      if (x.c) pop.append(el('div', 'lc-pop-names', 'Поддержали (' + x.c + '): ' + x.names.join(', ') + (x.c > x.names.length ? '…' : '')));
      showPop(chip, pop);
    }

    function showPop(anchor, pop) {
      if (toggleSame(anchor)) return;
      closeMenus();
      pop.classList.add('lc-menu');
      document.body.append(pop);
      const r = anchor.getBoundingClientRect();
      pop.style.top = Math.max(8, Math.min(r.bottom + 6, window.innerHeight - pop.offsetHeight - 8)) + 'px';
      pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - pop.offsetWidth - 8)) + 'px';
      armMenu(pop, anchor);
    }

    // «Пон / Не пон» — как опрос в сторис: две большие кнопки, после ответа
    // (или конца опроса) — проценты. Кто как ответил — по наведению (ПК) или
    // долгому нажатию (телефон); у команды «не пон» видно сразу в пульте.
    function renderPoll() {
      const P = S.poll;
      strip.hidden = false;
      strip.classList.add('is-poll');
      const active = P.active && left(P.ends) > 0;
      const total = P.yes + P.no;
      const showRes = !active || P.mine !== null || S.me.team;
      let card = strip.querySelector(':scope > .lc-poll');
      if (!card || Number(card.dataset.id) !== P.id) card = buildPoll(P);
      card.classList.toggle('is-open', active && P.mine === null && !S.me.team);
      card.classList.toggle('is-done', !active);

      // кольцо-таймер
      const dur = Math.max(1, P.ends - P.at);
      card._ring.style.strokeDashoffset = String(RING * (1 - left(P.ends) / dur));
      card._ring.parentNode.parentNode.hidden = !active;
      card._time.textContent = active ? String(left(P.ends)) : 'итог · ' + total + ' ' + plural(total, 'ответ', 'ответа', 'ответов');
      card._count.textContent = active ? total + ' ' + plural(total, 'ответ', 'ответа', 'ответов') : '';

      card._opts.forEach((o) => {
        const n = o._choice ? P.yes : P.no;
        const pct = total ? Math.round(n / total * 100) : 0;
        o.classList.toggle('is-mine', P.mine === o._choice);
        o.classList.toggle('is-res', showRes);
        o.classList.toggle('is-lead', showRes && total > 0 && n * 2 > total);
        o.disabled = !active || P.mine !== null || S.me.team || S.me.demo;
        o._fill.style.width = (showRes ? pct : 0) + '%';
        countTo(o._pct, showRes ? pct : null);
        o._names = o._choice ? P.yesNames : P.noNames;
        o.title = o._names && o._names.length ? o._names.join(', ') : '';
      });
      card._no.hidden = !(S.me.team && P.noNames && P.noNames.length);
      card._no.textContent = 'Не пон: ' + (P.noNames || []).join(', ');
    }

    const RING = 2 * Math.PI * 9;
    function buildPoll(P) {
      const card = el('div', 'lc-poll');
      card.dataset.id = P.id;
      const head = el('div', 'lc-poll-head');
      const title = el('b', '', 'Пон или не пон?');
      const right = el('span', 'lc-poll-meta');
      card._count = el('span', 'lc-poll-count');
      const ringBox = el('span', 'lc-poll-ring');
      ringBox.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="9" class="lc-ring-bg"/><circle cx="12" cy="12" r="9" class="lc-ring-fg" stroke-dasharray="' + RING + '"/></svg>';
      card._ring = ringBox.querySelector('.lc-ring-fg');
      card._time = el('span', 'lc-poll-time');
      ringBox.append(card._time);
      right.append(card._count, ringBox);
      head.append(title, right);
      const opts = el('div', 'lc-poll-opts');
      card._opts = [[true, '👍', 'Пон'], [false, '🤔', 'Не пон']].map(([choice, emoji, label]) => {
        const o = btn('lc-poll-opt', '', () => vote(choice, o));
        o._choice = choice;
        o._fill = el('i', 'lc-poll-fill');
        o._pct = el('span', 'lc-poll-pct');
        const lab = el('span', 'lc-poll-label');
        lab.append(el('span', 'lc-poll-emoji', emoji), el('span', '', label));
        o.append(o._fill, lab, o._pct);
        longPress(o, () => {
          if (o._names && o._names.length) showPop(o, el('div', 'lc-pop-names', (choice ? 'Пон: ' : 'Не пон: ') + o._names.join(', ')));
        });
        opts.append(o);
        return o;
      });
      card._no = el('div', 'lc-poll-no');
      card.append(head, opts, card._no);
      strip.replaceChildren(card);
      return card;
    }

    // Проценты «досчитывают» до нового значения.
    function countTo(elm, target) {
      if (target === null) { elm.textContent = ''; elm._v = 0; return; }
      const from = elm._v || 0;
      if (from === target && elm.textContent) return;
      const t0 = performance.now();
      const step = (t) => {
        const k = Math.min(1, (t - t0) / 450);
        const v = Math.round(from + (target - from) * (1 - Math.pow(1 - k, 3)));
        elm.textContent = v + '%';
        if (k < 1) requestAnimationFrame(step);
      };
      elm._v = target;
      requestAnimationFrame(step);
    }

    async function vote(choice, btnEl) {
      if (!S.poll || S.poll.mine !== null) return;
      S.poll = Object.assign({}, S.poll, { mine: choice, yes: S.poll.yes + (choice ? 1 : 0), no: S.poll.no + (choice ? 0 : 1) });
      renderPoll();
      if (btnEl) {
        btnEl.classList.remove('is-voted');
        void btnEl.offsetWidth;
        btnEl.classList.add('is-voted');
        floatText(btnEl, choice ? '👍' : '🤔');
      }
      if (navigator.vibrate && touch) navigator.vibrate(12);
      const res = await api('POST', url('vote'), { choice });
      if (!res.ok) toast(res.json.message || 'Не получилось');
      poll(true);
    }

    // Пункт скрепки «Поделиться эмоцией» — с причиной, если сейчас нельзя.
    function emotionItem() {
      const label = '😊 Поделиться эмоцией';
      if (!cfg.url.emotion || S.me.guest) return [label, () => {}, { disabled: true, hint: 'для учеников' }];
      if (!S.me.emotion) return [label, () => {}, { disabled: true, hint: 'с ' + cfg.emotionLevel + ' уровня акулы' }];
      if (pollVisible() && S.poll.active) return [label, () => {}, { disabled: true, hint: 'идёт опрос' }];
      if (activeEmo().length >= cfg.emotionMax) return [label, () => {}, { disabled: true, hint: 'Максимум эмоций на экране' }];
      const open = () => setTimeout(() => emotionPicker(clip), 0);
      if (S.me.emotionAt && left(S.me.emotionAt)) return [label, open, { disabled: true, hint: 'через ' + mmss(left(S.me.emotionAt)), until: S.me.emotionAt }];
      return [label, open];
    }
    function emotionPicker(anchor) {
      const pop = el('div', 'lc-emo-grid');
      pop.append(el('div', 'lc-pop-title', 'Что вы чувствуете?'));
      const grid = el('div', 'lc-emo-cells');
      (cfg.emotions || []).forEach((e) => {
        const b = btn('lc-emo-cell', '', async () => {
          closeMenus();
          const res = await api('POST', url('emotion'), { code: e.code });
          if (!res.ok) toast(res.json.message || 'Не получилось');
          else if (navigator.vibrate && touch) navigator.vibrate(15);
          poll(true);
        });
        b.append(emojiImg(e, 'lc-emo-cell-img'), el('span', '', e.label));
        grid.append(b);
      });
      pop.append(grid);
      showPop(anchor, pop);
      const r = anchor.getBoundingClientRect();
      pop.style.top = Math.max(8, r.top - pop.offsetHeight - 8) + 'px';
    }

    // ── Итоги урока (команда) ──────────────────────────────────────
    let statsTimer = null;
    async function loadStats() {
      clearInterval(statsTimer);
      statsTimer = setInterval(() => { if (S.view === 'stats') refreshStats(); }, 10000);
      refreshStats();
    }
    async function refreshStats() {
      if (!cfg.url.stats) return;
      let d;
      try { d = await getJson(cfg.url.stats); } catch (e) { statsView.textContent = 'Не удалось загрузить итоги'; return; }
      const sec = (title, ...kids) => { const x = el('div', 'lc-stats-sec'); x.append(el('div', 'lc-stats-title', title), ...kids); return x; };
      const row = (a, b) => { const r = el('div', 'lc-stats-row'); r.append(el('span', '', a), el('b', '', b)); return r; };
      const nums = el('div', 'lc-stats-nums');
      [['в чате', d.online], ['сообщений', d.messages], ['писали', d.people], ['вопросов', d.questions], ['без ответа', d.unanswered]]
        .forEach(([l, n]) => { const c = el('div', 'lc-stats-num'); c.append(el('b', '', String(n)), el('span', '', l)); nums.append(c); });
      const parts = [nums];
      if (d.top.length) parts.push(sec('Самые активные', ...d.top.map((t, i) => row((i + 1) + '. ' + t.name, String(t.c)))));
      if (d.polls.length) {
        parts.push(sec('Пон / Не пон', ...d.polls.map((p) => {
          const tot = p.yes + p.no;
          const box = el('div', 'lc-stats-poll');
          const bar = el('div', 'lc-stats-bar');
          const y = el('i', 'is-yes');
          y.style.width = (tot ? Math.round(p.yes / tot * 100) : 0) + '%';
          bar.append(y);
          box.append(row(hhmm(p.at) + (p.active ? ' · идёт' : ''), tot ? Math.round(p.yes / tot * 100) + '% пон · ' + tot : 'нет ответов'), bar);
          if (p.noNames.length) box.append(el('div', 'lc-stats-names', 'Не пон: ' + p.noNames.join(', ')));
          return box;
        })));
      }
      if (d.emotions.length) parts.push(sec('Эмоции', ...d.emotions.map((e) => row(e.e + ' ' + e.label + ' · ' + e.n, '+' + e.c))));
      if (parts.length === 1) parts.push(el('div', 'lc-empty', 'Итоги появятся по ходу урока'));
      statsView.replaceChildren(...parts);
    }

    // Раз в секунду: отсчёты перерыва и опроса, исчезновение эмоций.
    if (!isReplay) {
      setInterval(() => {
        applyRoom();
        renderConsole();
        if (S.emo.length || S.poll) {
          const hadPoll = !strip.hidden && strip.classList.contains('is-poll');
          if (hadPoll && !pollVisible()) strip.classList.remove('is-poll');
          if (pollVisible()) renderPoll(); else renderStrip();
        }
      }, 1000);
    }

    // ── Старт ──────────────────────────────────────────────────────
    try { if (localStorage.getItem('lc-folded') && touch && !isReplay) setFolded(true); } catch (e) { /* приватный режим */ }
    applyState();
    renderPinned(null);
    if (isReplay) {
      startReplay();
    } else {
      poll(true, true);
    }
  }
})();
