/*
 * Песочница чата трансляций (/live-chat/sandbox). Подменяет сервер чата прямо
 * в браузере: live-chat.js ходит на /__sandbox/…, здесь эти запросы отвечают
 * тем же JSON, что и App\Service\LiveChat\LiveChatService. В базу ничего не
 * пишется. «Ученики»-боты пишут по сценарию урока и отвечают на действия
 * преподавателя (реакции, «спасибо» на цитату, поток ответов на «пишем ответ»).
 */
(function () {
  'use strict';
  const C = window.LC_SANDBOX;
  if (!C) return;

  const speed = () => window.LC_SANDBOX_SPEED || 1;
  const now = () => Math.floor(Date.now() / 1000);
  const rnd = (a, b) => a + Math.random() * (b - a);
  const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];
  const later = (sec, fn) => setTimeout(fn, (sec * 1000) / speed());

  // ── Участники ────────────────────────────────────────────────────
  const TEACHER_BOT = 1000;
  const bots = [
    [101, 'Аня', 'Котова Анна'], [102, 'Миша', 'Орлов Михаил'], [103, 'Катя Л.', 'Лебедева Екатерина'],
    [104, 'Дима', 'Смирнов Дмитрий'], [105, 'Соня', 'Белова София'], [106, 'Тимур', 'Галиев Тимур'],
    [107, 'Вика', 'Петрова Виктория'], [108, 'Петя 🐸', 'Иванов Пётр'], [109, 'Лиза', 'Морозова Елизавета'],
    [110, 'Артём', 'Кузнецов Артём'], [111, 'Даша', 'Новикова Дарья'], [112, 'Егор', 'Волков Егор'],
  ];
  const users = new Map();
  bots.forEach(([id, n, real]) => users.set(id, { n, real, team: false }));
  users.set(TEACHER_BOT, { n: 'Александр Полтавский', b: 'Преподаватель', team: true });
  const V = C.viewer;
  users.set(V.id, { n: V.n, b: V.b, team: V.team, av: V.av, real: V.real });
  const viewerIsTeacher = V.team;
  const studentIds = bots.map((b) => b[0]);

  // ── «Сервер» ─────────────────────────────────────────────────────
  let ver = 0;
  let nextId = 1;
  const msgs = new Map();
  const rx = new Map();           // id сообщения → Map(id реакции → Set(uid))
  let pinned = null;
  const mutes = new Map();        // uid → {until: ts|null, lesson: bool}
  const sent = new Map();         // uid → [[ts, текст]] — для медленного режима
  const STATE = { s: 'open', opensAt: null, opensLabel: null, breakUntil: null };

  function mutedNow(uid) {
    const m = mutes.get(uid);
    if (!m) return null;
    if (m.until && m.until <= now()) { mutes.delete(uid); return null; }
    return m;
  }
  function touch(m) { m.v = ++ver; }

  function add(uid, text, opts) {
    opts = opts || {};
    if (mutedNow(uid)) return null;
    const u = users.get(uid);
    const m = { id: nextId++, uid, n: opts.name || u.n, b: u.b || null, text, q: !!opts.q, ans: false, ansTs: null, ts: now(), reply: opts.reply || null, del: false };
    touch(m);
    msgs.set(m.id, m);
    setTimeout(() => react(m), 0);
    return m;
  }

  function present(m) {
    if (m.del) return { id: m.id, v: m.v, del: true };
    const u = users.get(m.uid) || {};
    const list = [];
    const r = rx.get(m.id);
    if (r) Array.from(r.entries()).sort((a, b) => a[0] - b[0]).forEach(([tid, set]) => { if (set.size) list.push([tid, set.size, set.has(V.id)]); });
    let reply = null;
    if (m.reply) {
      const o = msgs.get(m.reply);
      if (o) reply = { id: o.id, uid: o.uid, n: o.n, text: o.del ? 'Сообщение удалено' : o.text.slice(0, 90) };
    }
    const out = { id: m.id, v: m.v, uid: m.uid, n: m.n, b: m.b, team: !!u.team, av: u.av || null, text: m.text, q: m.q, ans: m.ans, ansTs: m.ansTs, ts: m.ts, reply, rx: list };
    if (V.admin && !u.team && u.real) out.real = u.real;
    return out;
  }

  function me() {
    const mu = mutedNow(V.id);
    return { id: V.id, name: users.get(V.id).n, badge: V.b, team: V.team, admin: V.admin, demo: false,
      muted: mu ? { until: mu.until, lesson: mu.lesson } : null, write: !mu };
  }

  function feed(v) {
    const base = { v: ver, state: STATE };
    if (v === ver) return base;
    const live = Array.from(msgs.values()).filter((m) => !m.del).sort((a, b) => a.id - b.id);
    if (v <= 0 || v > ver) {
      const last = live.slice(-100);
      const ids = new Set(last.map((m) => m.id));
      return Object.assign(base, {
        reset: true, more: live.length > 100, msgs: last.map(present),
        questions: V.team ? live.filter((m) => m.q && !m.ans && !ids.has(m.id)).map(present) : [],
        pinned: pinned && msgs.get(pinned) && !msgs.get(pinned).del ? present(msgs.get(pinned)) : null, me: me(),
      });
    }
    const changed = Array.from(msgs.values()).filter((m) => m.v > v).sort((a, b) => a.v - b.v);
    return Object.assign(base, {
      msgs: changed.map(present),
      pinned: pinned && msgs.get(pinned) && !msgs.get(pinned).del ? present(msgs.get(pinned)) : null, me: me(),
    });
  }

  function json(body, status) {
    return new Response(JSON.stringify(body), { status: status || 200, headers: { 'Content-Type': 'application/json' } });
  }

  function handle(method, path, q, data) {
    let m;
    if (path === 'feed') return json(feed(Number(q.get('v') || 0)));
    if (path === 'older') {
      const before = Number(q.get('before'));
      const list = Array.from(msgs.values()).filter((x) => !x.del && x.id < before).sort((a, b) => a.id - b.id);
      return json({ more: list.length > 50, msgs: list.slice(-50).map(present) });
    }
    if (path === 'send') {
      const mu = mutedNow(V.id);
      if (mu) return json({ error: 'muted', message: 'Вы не можете писать в этом чате' }, 422);
      const text = String(data.text || '').trim();
      if (!text) return json({ error: 'empty', message: 'Пустое сообщение' }, 422);
      let slow = { slow: false, wait: 0 };
      if (!V.team) {
        const hist = (sent.get(V.id) || []).filter((h) => h[0] > now() - 150);
        const last = hist[hist.length - 1];
        const flooded = hist.length >= 4 && now() - hist[hist.length - 4][0] <= 10;
        if (last && last.slow && now() - last[0] < 30) return json({ error: 'slow', message: 'Не так быстро', wait: 30 - (now() - last[0]) }, 422);
        const entry = [now(), text];
        if (flooded) { entry.slow = true; slow = { slow: true, wait: 30 }; }
        hist.push(entry);
        sent.set(V.id, hist);
      }
      m = add(V.id, text, { reply: data.reply_to || null, q: !V.team && !!data.question });
      return json({ msg: present(m), slow });
    }
    if (path === 'name') {
      const name = String(data.name || '').trim().slice(0, 32);
      if (!name) return json({ error: 'empty', message: 'Введите имя' }, 422);
      users.get(V.id).n = name;
      return json({ name });
    }
    if (path === 'pin' && method === 'DELETE') { pinned = null; ver++; return json({ ok: true }); }

    let mm = path.match(/^messages\/(\d+)(?:\/(reactions|answer|pin))?$/);
    if (mm) {
      m = msgs.get(Number(mm[1]));
      if (!m) return json({ error: 'missing' }, 404);
      const act = mm[2];
      if (act === 'reactions') { toggleReaction(m, V.id, Number(data.reaction)); return json({ ok: true }); }
      if (act === 'answer') { setAnswered(m, data.answered !== false); return json({ ok: true }); }
      if (act === 'pin') { pinned = m.id; ver++; return json({ ok: true }); }
      if (method === 'DELETE') { m.del = true; if (pinned === m.id) pinned = null; touch(m); return json({ ok: true }); }
    }
    mm = path.match(/^mutes\/(\d+)$/);
    if (mm) {
      const uid = Number(mm[1]);
      if (method === 'DELETE') mutes.delete(uid);
      else if (users.get(uid) && users.get(uid).team) return json({ error: 'team', message: 'Команду замьютить нельзя' }, 422);
      else mutes.set(uid, { until: data.duration === '10m' ? now() + 600 : null, lesson: data.duration !== 'forever' });
      ver++;
      return json({ ok: true });
    }
    return json({ error: 'unknown' }, 404);
  }

  function toggleReaction(m, uid, tid) {
    if (!rx.has(m.id)) rx.set(m.id, new Map());
    const r = rx.get(m.id);
    if (!r.has(tid)) r.set(tid, new Set());
    const set = r.get(tid);
    if (set.has(uid)) set.delete(uid); else set.add(uid);
    touch(m);
  }
  function setAnswered(m, yes) {
    m.ans = yes;
    m.ansTs = yes ? now() : null;
    touch(m);
    if (yes && m.uid !== V.id && Math.random() < 0.6) {
      later(rnd(3, 8), () => add(m.uid, pick(['спасибо!', 'поняла, спасибо 🙏', 'аа, всё ясно', 'спасибо, теперь понятно']), {}));
    }
  }

  const realFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    const url = String(typeof input === 'string' ? input : input.url);
    const i = url.indexOf('/__sandbox/');
    if (i === -1) return realFetch(input, init);
    const u = new URL(url, location.href);
    const path = u.pathname.slice(u.pathname.indexOf('/__sandbox/') + 11);
    const method = (init && init.method) || 'GET';
    let data = {};
    try { data = init && init.body ? JSON.parse(init.body) : {}; } catch (e) { /* пусто */ }
    // Небольшая задержка — как у настоящего сервера.
    return new Promise((resolve) => setTimeout(() => resolve(handle(method, path, u.searchParams, data)), 120 + Math.random() * 150));
  };

  // ── Поведение «учеников» ─────────────────────────────────────────
  const MAIN = C.mainReactions || [];
  const HEART = C.heart;
  const free = () => studentIds.filter((id) => !mutedNow(id));
  const ANSWERS = ['134', '134', '13', '134', '143', '1 3 4', '+', '134!', '234', 'не знаю(', '14', '134 вроде'];
  const THANKS = ['спасибо!', 'понял', 'поняла 👍', 'ага, ясно', 'спасибо, понятно', 'ооо понятно'];

  function react(m) {
    const author = users.get(m.uid) || {};
    if (author.team) {
      // Сообщение преподавателя: реакции, а на «пишем ответ» — поток ответов.
      const n = Math.floor(rnd(1, 5));
      for (let i = 0; i < n; i++) {
        const who = pick(free());
        if (who) later(rnd(1.5, 7), () => { if (!m.del && MAIN.length) toggleReaction(m, who, i === 0 && HEART ? HEART : pick(MAIN)); });
      }
      if (/ответ|пишите|напишите|пишем|вариант|кто готов|плюс/i.test(m.text)) {
        const crowd = free().sort(() => Math.random() - 0.5).slice(0, Math.floor(rnd(6, 11)));
        crowd.forEach((who) => later(rnd(2, 14), () => add(who, pick(ANSWERS))));
      }
      if (m.reply) {
        const orig = msgs.get(m.reply);
        if (orig && !users.get(orig.uid).team && orig.uid !== V.id && Math.random() < 0.7) {
          later(rnd(4, 9), () => add(orig.uid, pick(THANKS), { reply: m.id }));
        }
      }
      return;
    }
    if (m.uid === V.id && !V.team) {
      // Ученик (вы) спросил — «преподаватель» отвечает через 15 с.
      if (m.q) later(15, () => { setAnswered(m, true); add(TEACHER_BOT, 'Хороший вопрос, ответил голосом 🙂', { reply: m.id }); });
      if (Math.random() < 0.4) later(rnd(2, 6), () => { const w = pick(free()); if (w && MAIN.length) toggleReaction(m, w, pick(MAIN)); });
      return;
    }
    if (Math.random() < 0.25 && MAIN.length) {
      const w = pick(free());
      if (w && w !== m.uid) later(rnd(2, 8), () => { if (!m.del) toggleReaction(m, w, pick(MAIN)); });
    }
  }

  // ── Сценарий урока: [секунда, кто, текст, вопрос?] ─────────────
  const T = TEACHER_BOT;
  const script = [
    [0, 101, 'Здравствуйте! 👋'], [3, 102, 'всем привет'], [6, 103, 'добрый вечер'], [9, 104, 'а запись будет?'],
    [12, 105, 'Здрасьте)'], [16, 106, '+'], [18, T, 'Всем привет! Начинаем через минуту, подключайтесь'],
    [26, 101, 'А домашку на когда сдавать?', true], [34, 107, 'Всем привет, я опоздала немного'],
    [42, 104, 'Слышно хорошо'], [50, 109, 'а презентацию скинете?'],
    [60, T, 'Ребята, пишем ответ на задание 6 цифрами без пробелов 👇'],
    [75, 102, 'а будет разбор 25 задания сегодня?', true], [90, 105, 'можно помедленнее пожалуйста 🙏'],
    [104, 108, 'ахахах'], [105, 108, 'ахахах'], [106, 108, 'АХАХАХ'], [107, 108, 'лол'], [108, 108, 'ахахах'], [109, 108, 'ахах'],
    [125, 103, 'А чем традиционное общество отличается от индустриального? Там же тоже было производство', true],
    [140, 106, 'в чате кто-нибудь из Казани есть?'], [150, 110, 'я из Казани)'],
    [160, 108, 'скучно, го лучше в доту'], [175, 107, 'А в задании 6 обязательно писать цифры по возрастанию?', true],
    [190, T, 'Верно — 134. Разбираем, почему не 4'], [200, 101, 'понятно 👍'], [215, 104, 'можно ещё раз пример?'],
    [230, 102, 'я не понял почему 4 неверно', true], [250, 111, 'а у меня 13 получилось, это сколько баллов?', true],
    [265, 105, 'спасибо, очень понятно объясняете ❤️'], [285, 103, 'А на пробнике такое будет?', true],
    [300, 112, 'всем привет, что я пропустил?'], [310, 106, '+'],
    [320, T, 'Перерыв 5 минут, потом задание 8'], [330, 107, 'я тоже не понимаю про постиндустриальное', true],
    [345, 109, 'а можно вопрос не по теме? как готовиться к сочинению?', true], [360, 108, 'а когда закончим?'],
    [380, 110, 'сделайте громче пожалуйста'], [395, 111, 'всё слышно нормально'],
  ];
  const ambient = [
    'а можно повторить?', 'спасибо!', 'понятно', 'записываю', '+', 'а где это в учебнике?', 'интересно',
    'а это в каком задании бывает?', 'ничего не понятно 😭', 'а если два варианта верные?', '🔥🔥🔥', 'круто',
    'а можно ещё пример?', 'у меня всё правильно!', 'а ДЗ большое будет?',
  ];
  const ambientQ = ['а это точно будет на ЕГЭ?', 'а как отличить смешанную экономику от рыночной?', 'а критерии тут какие?'];

  let elapsed = 0;
  let lastTick = Date.now();
  let step = 0;
  let nextAmbient = 410;
  setInterval(() => {
    const t = Date.now();
    elapsed += ((t - lastTick) / 1000) * speed();
    lastTick = t;
    while (step < script.length && script[step][0] <= elapsed) {
      const [, who, text, q] = script[step++];
      if (who === T && viewerIsTeacher) continue; // преподаватель здесь — вы
      add(who, text, { q: !!q });
    }
    if (step >= script.length && elapsed >= nextAmbient) {
      const q = Math.random() < 0.2;
      const who = pick(free());
      if (who) add(who, q ? pick(ambientQ) : pick(ambient), { q });
      nextAmbient = elapsed + rnd(12, 35);
    }
  }, 400);
})();
