// Диагностика глазами ученика: проходит тест через интерфейс в headless Chrome,
// на каждый вопрос отвечает заведомо правильно (ключи — из docs/diagnostic/questions.json и questions_beginner.json)
// и сверяет вердикт сервера. Ловит расхождения страницы и сервера, которых не видят
// phpunit-тесты API (так нашли «Ответить» → пропуск).
//
// Нужен локальный сервер с DIAG_ALLOW_ANONYMOUS (по умолчанию в local):
//   cd public && php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
// Запуск (из main-app): node tests/e2e/diagnostic-correct.mjs [прохождений=4]
// Переменные: DIAG_URL (http://127.0.0.1:8765/diag), CHROME (путь к chrome.exe).
// Выход 1 — есть верные ответы, засчитанные неверно.
import { spawn } from 'node:child_process';
import { readFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const RUNS = process.argv[2] || '4';
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const URL_ = process.env.DIAG_URL || 'http://127.0.0.1:8765/diag';
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PROFILE = mkdtempSync(join(tmpdir(), 'diag-e2e-'));
const BANK = Object.fromEntries(['questions.json', 'questions_beginner.json']
  .flatMap((f) => JSON.parse(readFileSync(join(ROOT, 'docs/diagnostic', f), 'utf8')).questions).map((q) => [q.id, q]));
const chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=9351', `--user-data-dir=${PROFILE}`, 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let ws, id = 0; const pending = new Map(); const errors = [];
const send = (method, params = {}) => new Promise((r) => { const n = ++id; pending.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
const ev = async (e) => (await send('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true })).result?.result?.value;
// Перехват ответов API: последний JSON — в window.__last.
const HOOK = `(() => { const f = window.fetch; window.__log = []; window.fetch = function (u, o) { if (String(u).includes('/answer')) window.__req = o && o.body; return f.apply(this, arguments).then(r => { const c = r.clone(); c.json().then(j => { window.__last = j; window.__log.push(j); }).catch(() => {}); return r; }); }; })()`;

function solver(q) {
  // JS-код, который решает вопрос через DOM и жмёт «Ответить».
  const S = JSON.stringify;
  const clickText = (sel, t) => `[...document.querySelectorAll(${S(sel)})].find(n => n.textContent.includes(${S(t)}))?.click();`;
  const submit = `setTimeout(() => [...document.querySelectorAll('#bar button')].find(b => b.textContent.trim() === 'Ответить')?.click(), 150);`;
  switch (q.mechanic) {
    case 'swipe': {
      const label = q.answer ? (q.skin === 'chat' ? 'Это правда' : 'Верно') : (q.skin === 'chat' ? 'Это не так' : 'Неверно');
      return `[...document.querySelectorAll('button')].find(b => b.textContent.includes(${S(label)}) && !b.disabled)?.click();`;
    }
    case 'single': {
      if (q.skin === 'who') { const sp = q.speakers.find((s) => s.id === q.correct); return clickText('.options .speaker', sp.text) + submit; }
      const o = q.options.find((o) => o.id === q.correct); return clickText('.options .opt', o.text) + submit;
    }
    case 'multi':
      return q.correct.map((cid) => clickText('.options .opt', q.options.find((o) => o.id === cid).text)).join('') + submit;
    case 'buckets':
      return q.cards.map((c) => {
        const b = q.buckets.find((b) => b.id === q.mapping[c.id]);
        return clickText('.tag.drag', c.text) + `document.querySelector('[data-bucket=${S(b.id)}]').click();`;
      }).join('') + submit;
    case 'fix':
      return q.spots.filter((s) => s.shown !== s.correct).map((s) => {
        const idx = q.spots.findIndex((x) => x.id === s.id);
        return `document.querySelectorAll('.spot')[${idx}].click(); [...document.querySelectorAll('.picker .tag')].find(b => b.textContent.trim() === ${S(s.correct)})?.click();`;
      }).join('') + submit;
  }
}

(async () => {
  let url;
  for (let i = 0; i < 50 && !url; i++) { try { url = (await (await fetch('http://127.0.0.1:9351/json/list')).json()).find((t) => t.type === 'page')?.webSocketDebuggerUrl; } catch {} await sleep(200); }
  ws = new WebSocket(url); await new Promise((r) => ws.addEventListener('open', r));
  ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
    if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.exception?.description); });
  await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  await send('Page.addScriptToEvaluateOnNewDocument', { source: HOOK });
  const checked = {}; const wrong = [];
  for (let run = 0; run < +RUNS; run++) {
    await send('Network.clearBrowserCookies');
    await send('Page.navigate', { url: URL_ }); await sleep(4500);
    for (let step = 0; step < 120; step++) {
      const st = await ev(`(() => { const l = window.__last || {}; const s = l.state || l; return JSON.stringify({ screen: s.screen, q: s.question && s.question.id, fb: l.correct !== undefined ? { c: l.correct, sk: l.skipped } : null }); })()`);
      const s = JSON.parse(st || '{}');
      if (s.screen === 'result') break;
      const btn = (t) => `(() => { const b = [...document.querySelectorAll('button')].find(b => b.textContent.trim() === ${JSON.stringify(t)} && !b.disabled); if (b) { b.click(); return true; } return false; })()`;
      if (await ev(btn('11 класс'))) { await sleep(1200); continue; }
      // Уровень: чётные прогоны — основной тест, нечётные — начальный.
      if (s.screen === 'level') { await ev(`document.querySelectorAll('.level-btn')[${run % 2 ? 0 : 1}]?.click()`); await sleep(1200); continue; }
      if (s.screen === 'target') { await ev(btn('Пока не знаю')) || await ev(`[...document.querySelectorAll('#bar button')].pop()?.click()`); await sleep(1500); continue; }
      if (await ev(`!!document.querySelector('.countdown') || !!document.querySelector('.done-chip')`)) { await ev(`document.getElementById('screen').click()`); await sleep(900); continue; }
      if (await ev(btn('Дальше'))) { await sleep(900); continue; }
      if (s.screen === 'question' && s.q && await ev(`!!document.querySelector('.q') && !document.querySelector('.q .ok, .q .bad, .q .yes, .q .no')`)) {
        const q = BANK[s.q];
        await ev(`window.__last = null`);
        await ev(solver(q)); await sleep(1600);
        const r = JSON.parse(await ev(`JSON.stringify(window.__last || {})`));
        checked[q.id] = true;
        if (r.correct !== true) wrong.push({ id: q.id, mech: q.mechanic + ':' + (q.skin || ''), server: r.correct, stale: r.stale || false, sent: await ev('window.__req'), solution: JSON.stringify(r.solution) });
        continue;
      }
      await sleep(700);
    }
  }
  console.log(JSON.stringify({ checked: Object.keys(checked).length, nWrong: wrong.length, wrong: wrong.slice(0, 8), errors: errors.slice(0, 5) }, null, 1));
  chrome.kill(); process.exit(wrong.length ? 1 : 0);
})();
