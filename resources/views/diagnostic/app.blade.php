{{-- Диагностика по обществознанию — Mini App бота diag (ТЗ — docs/diagnostic/).
     Отдельная страница без шапки и меню сайта: открывается внутри Telegram
     поверх чата. Свой вид, а не цвета темы Telegram: фон — как в кабинете
     ученика, голубоватый с контурным паттерном (config diagnostic.background);
     ночь — по теме Telegram или системы (класс .night, ставит JS).
     Вся логика — public/js/diagnostic.js; сервер — источник правды
     (Diagnostic\DiagnosticController). --}}
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Диагностика ЕГЭ · обществознание</title>
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    {{-- Скрипт Telegram — до своего: он даёт initData и тему. В обычном
         браузере безвреден (initData пустой). --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        :root {
            /* Фон — как в кабинете ученика: голубоватый с контурным паттерном
               (resources/img/pattern.webp, 15%); здесь паттерн заранее «вклеен»
               в цвет фона — файл в 30 раз легче (config diagnostic.background). */
            --bg: #F6FCFF;
            --pattern: url("{{ $background['light'] }}");
            --paper: #ffffff;          /* плашки, пузыри, карточки */
            --ink: #1D2E3A;            /* текст — как в кабинете */
            --hint: #6B8194;
            --line: #DCE9F2;
            --edge: #C9DCEA;           /* «объёмная» кромка снизу у кнопок */
            --dash: #B7D0E2;           /* пунктир: группы и пустые места карточек */
            --accent: #1F7FE0;
            --accent-edge: #1460AD;
            --accent-soft: #E3F0FF;
            --sun: #FFC83D;            /* главная кнопка */
            --sun-edge: #D99800;
            --ok: #23A95E;
            --ok-edge: #1A8148;
            --ok-bg: #E4F7EB;
            --bad: #F0484E;
            --bad-edge: #C22F35;
            --bad-bg: #FFE9EA;
            --radius: 16px;
        }
        :root.night {
            --bg: #0E1A24;
            --pattern: url("{{ $background['dark'] }}");
            --paper: #172736;
            --ink: #E6EFF6;
            --hint: #8EA3B6;
            --line: #243748;
            --edge: #0A131B;
            --dash: rgba(142, 163, 182, .45);
            --accent: #3B95F0;
            --accent-edge: #1D63AD;
            --accent-soft: rgba(59, 149, 240, .18);
            --ok-bg: rgba(35, 169, 94, .2);
            --bad-bg: rgba(240, 72, 78, .2);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { background: var(--bg) var(--pattern) 0 0 / 512px 512px repeat fixed; }
        html, body { margin: 0; color: var(--ink); }
        body {
            font: 16px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh; min-height: 100dvh;
        }
        button { font: inherit; color: inherit; }
        #app { max-width: 560px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; }

        /* Шапка: прогресс по блокам (ракушка на пройденном), название блока, «Заново». */
        .top { position: sticky; top: 0; z-index: 5; padding: 12px 16px 8px; background: var(--bg); }
        .top[hidden] { display: none; }
        .segments { display: flex; align-items: center; gap: 6px; }
        .segments i { position: relative; flex: 1; height: 6px; border-radius: 3px; background: var(--line); }
        /* Заливка отрезка — --fill (доля отвеченных в блоке); ракушка — одна, на конце заливки. */
        .segments i::after { content: ""; position: absolute; inset: 0; width: var(--fill, 0); border-radius: 3px; background: var(--accent);
            transition: width .6s cubic-bezier(.2, .8, .2, 1); }
        .segments i b { position: absolute; left: var(--fill, 0); top: 50%; transform: translate(-50%, -50%); font-size: 13px; z-index: 1;
            transition: left .6s cubic-bezier(.2, .8, .2, 1); }
        .top-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-top: 8px; }
        .top-label { font-size: 13px; color: var(--hint); }

        main { flex: 1; padding: 8px 16px 16px; }
        .bar { position: sticky; bottom: 0; z-index: 5; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); display: flex; gap: 10px;
            align-items: center; background: var(--bg); }
        .bar:empty { display: none; }

        .muted { color: var(--hint); }
        .small { font-size: 14px; }
        .counter { font-size: 13px; color: var(--hint); margin-top: 10px; }
        .task { font-size: 14px; color: var(--hint); margin: -4px 0 10px; }
        .link { border: 0; background: none; padding: 6px 0; color: var(--hint); font-size: 13px; cursor: pointer; }
        .link:active { color: var(--accent); }
        .center-link { text-align: center; margin: 6px 0 8px; }

        /* Ведущая и реплики — пузыри как в чате. */
        .say { display: flex; gap: 8px; align-items: flex-end; margin: 12px 0; animation: pop .25s ease-out; }
        .say img { width: 64px; height: 64px; flex: none; }
        .bubble { background: var(--paper); color: var(--ink); padding: 10px 14px; border-radius: 18px 18px 18px 6px; max-width: 85%; white-space: pre-line;
            box-shadow: 0 1px 3px rgba(20, 60, 100, .08); }
        .bubble b { font-weight: 700; }
        .bubble.ok { background: var(--ok-bg); }
        .bubble.bad { background: var(--bad-bg); }
        @keyframes pop { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        .card { background: var(--paper); border-radius: var(--radius); padding: 16px; margin: 10px 0; box-shadow: 0 1px 3px rgba(20, 60, 100, .08); }
        .character { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px; color: var(--hint); }
        .avatar { width: 44px; height: 44px; flex: none; border-radius: 50%; background: var(--accent); color: #fff; display: grid; place-items: center;
            font-weight: 700; font-size: 18px; }
        .avatar.emoji { background: none; font-size: 38px; line-height: 1; border-radius: 0; }
        /* Арт персонажа — PNG/WebP с прозрачным фоном, без кружка. */
        img.avatar { width: 76px; height: 76px; background: none; border-radius: 0; object-fit: contain; }
        .say .avatar { width: 64px; height: 64px; font-size: 26px; }
        .say .avatar.emoji { width: 72px; height: 72px; font-size: 52px; }
        .say img.avatar { width: 84px; height: 84px; }
        /* Пояснение — на всю ширину рядом с персонажем, а не 85%. */
        .say .bubble { max-width: none; }
        /* В шапке сообщения (чат, пост, ответ на ЕГЭ) — чуть меньше: рядом имя. */
        .speech-head img.avatar { width: 64px; height: 64px; }

        /* Кнопки — «объёмные»: заливка и кромка снизу, при нажатии вдавливаются. */
        .btn {
            border: 0; border-radius: 14px; padding: 13px 16px; background: var(--sun); color: #3a2a00; --e: var(--sun-edge);
            box-shadow: 0 4px 0 var(--e); margin-bottom: 4px;
            font-weight: 700; cursor: pointer; flex: 1; transition: transform .08s, box-shadow .08s, opacity .15s;
        }
        .btn:active { transform: translateY(3px); box-shadow: 0 1px 0 var(--e); }
        /* Выключенная — нейтральная, а не бледная: бледный жёлтый ночью выглядит грязным. */
        .btn[disabled] { background: var(--line); color: var(--hint); --e: var(--edge); cursor: default; }
        .btn.ghost { background: var(--paper); color: var(--ink); --e: var(--edge); }
        /* В нижней панели главная кнопка шире второстепенной («Хочу разобраться с этим» — в две строки, не в три). */
        .bar .btn:not(.ghost):not(.choice) { flex: 1.6; }
        /* Ответ (класс, «Это правда / не так») — синяя объёмная кнопка. */
        .btn.choice { background: var(--accent); color: #fff; --e: var(--accent-edge); }
        .chip-guess {
            flex: none; border: 0; box-shadow: 0 3px 0 var(--e, var(--edge)); margin-bottom: 3px; background: var(--paper); color: var(--hint); border-radius: 999px;
            padding: 10px 14px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .chip-guess.on { background: var(--accent-soft); color: var(--accent); --e: var(--accent); }
        .grades { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px; }
        .grades.answers { margin: 16px 0 0 74px; }

        /* Вопрос — без карточки; плашки — у вариантов. */
        .q { margin: 4px 0 12px; }
        .prompt { margin: 0 0 12px; font-weight: 600; }
        .q > .prompt { font-size: 18px; margin: 4px 0 14px; }
        /* Ведущий вопроса: акула или персонаж — слева от текста. */
        .q-host { display: flex; gap: 12px; align-items: center; margin: 4px 0 14px; }
        .q-host > img { flex: none; width: 72px; height: 72px; object-fit: contain; }
        .q-host .prompt { margin: 0; font-size: 18px; }
        .q > .task { margin: -6px 0 14px; }
        .options { display: grid; gap: 10px; }
        .opt {
            text-align: left; border: 2px solid transparent; box-shadow: 0 4px 0 var(--e, var(--edge)); background: var(--paper); color: var(--ink);
            border-radius: 14px; padding: 12px 14px; cursor: pointer; transition: transform .08s, background .15s, border-color .15s;
        }
        .opt:active { transform: translateY(2px); }
        .opt.sel { border-color: var(--accent); --e: var(--accent-edge); background: var(--accent-soft); }
        .opt.ok { border-color: var(--ok); --e: var(--ok-edge); background: var(--ok-bg); }
        .opt.bad { border-color: var(--bad); --e: var(--bad-edge); background: var(--bad-bg); }
        .opt.missed { border-color: var(--ok); border-style: dashed; }
        .opt[disabled] { cursor: default; }
        .opt .mark { float: right; margin-left: 8px; font-weight: 700; }
        .fragment { font-size: 15px; }
        .fragment.sel { text-decoration: line-through; text-decoration-color: var(--bad); }

        /* «Кто прав?»: форма пузыря (кто говорит) и объём варианта (на него нажимают). */
        .speaker { display: flex; gap: 10px; align-items: flex-start; }
        .speaker .bubble { flex: 1; cursor: pointer; border: 2px solid transparent; border-radius: 16px; box-shadow: 0 4px 0 var(--e, var(--edge)); transition: transform .08s; }
        .speaker:active .bubble { transform: translateY(2px); }
        .speaker.sel .bubble { border-color: var(--accent); --e: var(--accent-edge); background: var(--accent-soft); }
        .speaker.ok .bubble { border-color: var(--ok); --e: var(--ok-edge); background: var(--ok-bg); }
        .speaker.bad .bubble { border-color: var(--bad); --e: var(--bad-edge); background: var(--bad-bg); }
        /* Просьба персонажа над вариантами («Помоги выиграть спор») — реплика, не вариант. */
        .q > .speaker { margin-bottom: 14px; }
        .q > .speaker .bubble { cursor: default; border: 0; box-shadow: 0 1px 3px rgba(20, 60, 100, .08); }
        .speech-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 14px; }

        /* Свайп. */
        .swipe-zone { position: relative; height: 240px; display: grid; place-items: center; margin: 10px 0; touch-action: pan-y; }
        .swipe-card {
            width: 100%; min-height: 180px; background: var(--paper); color: var(--ink); border-radius: 20px; padding: 22px; display: grid;
            place-items: center; text-align: center; font-size: 18px; font-weight: 600; cursor: grab; user-select: none;
            box-shadow: 0 5px 0 var(--e, var(--edge)); transition: transform .25s, background .2s;
        }
        .swipe-card.drag { transition: none; }
        .swipe-card.yes { background: var(--ok-bg); --e: var(--ok); }
        .swipe-card.no { background: var(--bad-bg); --e: var(--bad); }
        /* place-items сбросить: свежий Chrome применяет justify-items и к блочной вёрстке. */
        .swipe-card.chat { display: block; place-items: normal; text-align: left; font-weight: 500; }
        .swipe-card.chat .speech-head { font-weight: 600; }
        .swipe-hint { display: flex; justify-content: space-between; font-size: 13px; color: var(--hint); margin-bottom: 8px; }

        /* Корзины. */
        .pool { display: flex; flex-wrap: wrap; gap: 8px; min-height: 10px; margin: 6px 0 14px; }
        .tag {
            border: 2px solid transparent; box-shadow: 0 4px 0 var(--e, var(--edge)); background: var(--paper); color: var(--ink); border-radius: 12px;
            padding: 8px 11px; font-size: 14px; cursor: pointer; text-align: left;
        }
        .tag.sel { border-color: var(--accent); --e: var(--accent-edge); background: var(--accent-soft); }
        .tag.ok { border-color: var(--ok); --e: var(--ok-edge); background: var(--ok-bg); }
        .tag.bad { border-color: var(--bad); --e: var(--bad-edge); background: var(--bad-bg); }
        .tag .fix { display: block; font-size: 12px; color: var(--ok); margin-top: 2px; }
        .tag.case { display: flex; flex-direction: column; gap: 2px; }
        .tag.case b { font-size: 12px; color: var(--hint); font-weight: 600; }
        .buckets { display: grid; gap: 10px; }
        .bucket { border: 2px dashed var(--dash); border-radius: 14px; padding: 10px; cursor: pointer; background: rgba(255, 255, 255, .5); -webkit-user-select: none; user-select: none; }
        :root.night .bucket { background: rgba(255, 255, 255, .03); }
        .bucket.target { border-color: var(--accent); }
        .bucket.over { border-style: solid; border-color: var(--accent); background: var(--accent-soft); }
        .bucket h4 { margin: 0 0 6px; font-size: 14px; }
        .bucket .pool { margin: 0; min-height: 44px; }
        /* У каждой карточки своё место: взятая оставляет пустую рамку того же размера. */
        /* Карточки — в два столбца: список короче, его легче пролистать. */
        .pool.slots, .bucket .pool { display: grid; grid-template-columns: 1fr 1fr; align-items: stretch; }
        .slot { display: flex; min-width: 0; }
        .slot > .tag { flex: 1; }
        .slot.empty { border: 2px dashed var(--dash); border-radius: 12px; }
        .tag.drag, .tag.drag * { user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; }
        .tag.drag { touch-action: pan-y; transition: transform .15s; }
        /* Долгое нажатие засчитано — карточка «приподнимается» и едет за пальцем. */
        .tag.drag.armed { transform: scale(.96); }
        .tag.dragging { position: fixed; z-index: 50; margin: 0; pointer-events: none; box-sizing: border-box; will-change: transform;
            box-shadow: 0 4px 0 var(--e, var(--edge)), 0 14px 30px rgba(0, 0, 0, .22); transition: box-shadow .15s; }
        .tag.dragging.landing { transition: transform .19s cubic-bezier(.2, .8, .2, 1), width .19s, height .19s, box-shadow .19s;
            box-shadow: 0 4px 0 var(--e, var(--edge)); }

        /* Ответ персонажа («Ты эксперт ЕГЭ») и пост («Найди и исправь») — плашкой с полоской его цвета. */
        .options.sheet { gap: 0; background: var(--paper); border-radius: 14px; padding: 4px 6px 4px 12px; border-left: 4px solid var(--quote, var(--accent)); }
        .options.sheet .opt { background: none; border: 0; box-shadow: none; border-bottom: 1px solid var(--line); border-radius: 0; padding: 12px 4px; }
        .options.sheet .opt:last-child { border-bottom: 0; }
        .options.sheet .opt.sel { background: var(--bad-bg); }
        .options.sheet .opt.ok { background: var(--ok-bg); }
        .options.sheet .opt.bad { background: var(--bad-bg); }
        .options.sheet .opt.missed { background: none; box-shadow: inset 3px 0 0 var(--ok); }
        .post { background: var(--paper); border-radius: 14px; padding: 12px 14px; border-left: 4px solid var(--quote, var(--accent)); }
        .post-text { line-height: 1.9; }
        .spot { border-bottom: 2px dotted var(--accent); cursor: pointer; padding: 0 1px; border-radius: 4px 4px 0 0; }
        .spot.sel { background: var(--accent-soft); }
        .spot.changed { border-bottom-style: solid; font-weight: 600; }
        .spot.ok { background: var(--ok-bg); border-bottom: 2px solid var(--ok); }
        .spot.bad { background: var(--bad-bg); border-bottom: 2px solid var(--bad); }
        .spot .fix { color: var(--ok); font-size: 13px; }
        .picker { margin-top: 10px; animation: rise .2s ease-out both; }
        .picker[hidden] { display: none; }

        /* Заставка блока и переходы. */
        .center { text-align: center; padding: 28px 8px; }
        .center img { width: 160px; height: 160px; }
        .big { font-size: 24px; font-weight: 700; margin: 8px 0 6px; }
        .done-chip { display: inline-block; margin-bottom: 10px; padding: 5px 12px; border-radius: 999px; background: var(--ok-bg); color: var(--ok);
            font-weight: 600; font-size: 14px; animation: pop .4s ease-out .15s both; }
        .countdown { flex: 1; height: 4px; border-radius: 2px; background: var(--line); overflow: hidden; margin: 8px 0; }
        .countdown i { display: block; height: 100%; width: 100%; background: var(--accent); transform-origin: left; animation: countdown linear both; }
        @keyframes countdown { from { transform: scaleX(0); } to { transform: scaleX(1); } }

        /* Цель. */
        .target-value { font-size: 56px; font-weight: 800; text-align: center; margin: 8px 0; }
        input[type=range] { width: 100%; accent-color: var(--accent); }

        /* Итог — без карточек: блоки разделены отступами и заголовками. */
        .hero { text-align: center; margin: 14px 0 6px; }
        .score { font-size: 52px; font-weight: 800; text-align: center; margin: 0; line-height: 1.1; }
        .hero-note { margin: 6px 0 0; font-weight: 600; }
        .goal { display: inline-block; margin-bottom: 4px; padding: 6px 14px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 15px; }
        .res-sec { margin: 22px 0; }
        .res-sec h3 { font-size: 15px; margin: 0 0 8px; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip-ok { background: var(--ok-bg); color: var(--ok); border-radius: 999px; padding: 5px 11px; font-size: 14px; font-weight: 600; }
        .gain { display: grid; grid-template-columns: 1fr auto; gap: 4px 10px; align-items: center; margin: 10px 0; }
        .gain .track { grid-column: 1 / -1; height: 6px; border-radius: 3px; background: var(--line); overflow: hidden; }
        .gain .track i { display: block; height: 100%; background: var(--accent); transition: width .7s cubic-bezier(.2, .8, .2, 1); }
        .gain b { color: var(--accent); }
        .gain.review small { color: var(--hint); font-size: 13px; }
        .gain-part2 { padding: 10px 12px; border-radius: 12px; background: var(--paper); border-left: 4px solid var(--sun); }
        /* Шкала 0–100: диапазон «сейчас», чёрточка — цель, кружок — потенциал. */
        .scale { position: relative; height: 10px; border-radius: 5px; background: var(--line); margin: 22px 6px 44px; text-align: left; }
        .scale .band { position: absolute; top: 0; bottom: 0; border-radius: 5px; background: var(--accent); transition: width .8s cubic-bezier(.2, .8, .2, 1); }
        .scale .tick { position: absolute; top: -6px; width: 3px; height: 22px; margin-left: -1.5px; border-radius: 2px; background: var(--ink); }
        .scale .dot { position: absolute; top: 50%; width: 14px; height: 14px; border-radius: 50%; border: 3px solid var(--ok); background: var(--bg); transform: translate(-50%, -50%); }
        .scale .lbl { position: absolute; top: 20px; transform: translateX(-50%); white-space: nowrap; font-size: 12px; color: var(--hint); }
                .scale .lbl.pot { color: var(--ok); }

        /* Радар по блокам. */
        .radar { display: block; width: 100%; max-width: 340px; margin: 4px auto 0; overflow: visible; }
        .radar .grid { fill: none; stroke: var(--line); }
        .radar .shape { fill: rgba(31, 127, 224, .22); stroke: var(--accent); stroke-width: 2.5; stroke-linejoin: round; }
        .radar .shape-g { transform-origin: 170px 134px; animation: grow .8s .2s cubic-bezier(.2, .8, .2, 1) both; }
        .radar .vertex { fill: var(--accent); stroke: var(--bg); stroke-width: 2; }
        .radar .vertex.weak { fill: var(--bad); }
        @keyframes grow { from { transform: scale(0); opacity: 0; } to { transform: none; opacity: 1; } }
        .radar text { font-size: 11px; fill: var(--hint); }
        .radar text .pct { font-size: 13px; font-weight: 700; fill: var(--accent); }
        .radar text.weak .pct { fill: var(--bad); }

        /* ---- Движение: по одному короткому на событие. ---- */
        .enter { animation: slide-in .32s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes slide-in { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
        .rise { animation: rise .4s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .flash-ok { animation: flash-ok .7s ease-out; }
        @keyframes flash-ok { 0% { box-shadow: 0 0 0 0 rgba(35, 169, 94, .45); } 100% { box-shadow: 0 0 0 14px rgba(35, 169, 94, 0); } }
        .shake { animation: shake .38s ease-in-out; }
        @keyframes shake { 20% { transform: translateX(-6px); } 40% { transform: translateX(5px); } 60% { transform: translateX(-3px); } 80% { transform: translateX(2px); } }
        .say .hop { animation: hop .5s ease-out; }
        @keyframes hop { 30% { transform: translateY(-9px) rotate(-6deg); } 60% { transform: translateY(0) rotate(3deg); } }
        .say .tilt { animation: tilt .6s ease-in-out; }
        @keyframes tilt { 40% { transform: rotate(-12deg) translateY(2px); } }
        .bob { animation: bob 3s ease-in-out infinite; }
        @keyframes bob { 50% { transform: translateY(-6px); } }
        .typing { display: inline-flex; gap: 4px; padding: 4px 2px; }
        .typing i { width: 7px; height: 7px; border-radius: 50%; background: var(--hint); animation: dot 1s infinite ease-in-out; }
        .typing i:nth-child(2) { animation-delay: .15s; }
        .typing i:nth-child(3) { animation-delay: .3s; }
        @keyframes dot { 0%, 60%, 100% { opacity: .3; transform: none; } 30% { opacity: 1; transform: translateY(-3px); } }
        .toast {
            position: fixed; left: 50%; top: 64px; transform: translateX(-50%); z-index: 20; pointer-events: none;
            background: var(--ink); color: var(--bg); font-weight: 700; padding: 8px 16px; border-radius: 999px; animation: toast 1.8s ease-out both;
        }
        @keyframes toast { 0% { opacity: 0; transform: translate(-50%, -8px) scale(.9); } 15% { opacity: 1; transform: translate(-50%, 0) scale(1.05); }
            25% { transform: translate(-50%, 0) scale(1); } 80% { opacity: 1; } 100% { opacity: 0; transform: translate(-50%, -6px); } }
        .confetti { position: fixed; inset: 0; pointer-events: none; z-index: 30; overflow: hidden; }
        .confetti i { position: absolute; top: -12px; width: 8px; height: 12px; border-radius: 2px; animation: fall 1.4s cubic-bezier(.3, .6, .6, 1) forwards; }
        @keyframes fall { to { transform: translate(var(--dx), 105vh) rotate(var(--rot)); opacity: .9; } }
        .meme { display: block; max-width: 100%; border-radius: 12px; margin: 6px 0; }
        /* Мем — в исходных пропорциях: .say img выше задаёт квадрат аватара. */
        .say img.meme { width: auto; height: auto; flex: initial; }
        .say-head { margin-bottom: 6px; }

        /* Кому движение мешает (настройка системы) — всё сразу, без анимаций. */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .confetti { display: none; }
        }
        /* Начальный тест (ТЗ, 5.8): красного нет вообще — неверно выбранное
           не подсвечивается, разбор — в спокойном голубом. После :root.night,
           иначе тёмная тема вернёт свой --bad-bg. */
        :root[data-track="basic"] { --bad: var(--sun); --bad-edge: var(--sun-edge); --bad-bg: #FFF6DC; }
        :root.night[data-track="basic"] { --bad-bg: rgba(255, 200, 61, .16); }
        :root[data-track="basic"] .bubble.bad { background: var(--accent-soft); }
        /* «Какой у тебя уровень?» — кнопки во всю ширину, подсказка под кнопкой. */
        .levels { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
        .levels img { display: block; width: 100%; aspect-ratio: 1; border-radius: 16px; margin-bottom: 8px; cursor: pointer; }
        /* \n в названии уровня — перенос строки («Средний / или выше»). */
        .levels .btn { width: 100%; white-space: pre-line; }
        .levels small { display: block; margin: 6px 4px 0; color: var(--hint); font-size: 13px; line-height: 1.35; }
        /* Итог начального теста. */
        .badges { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 4px 0 8px; }
        .badge { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 999px; background: var(--paper); border: 2px solid var(--sun); font-weight: 600; font-size: 14px; }
        .badge-icon { font-size: 18px; }
        .sea { position: relative; height: 280px; border-radius: var(--radius); overflow: hidden;
            background: radial-gradient(circle at 20% 30%, rgba(255,255,255,.35) 0 2px, transparent 3px) 0 0 / 34px 34px, linear-gradient(180deg, #BFE3FA, #8FCBF2); }
        :root.night .sea { background: radial-gradient(circle at 20% 30%, rgba(255,255,255,.08) 0 2px, transparent 3px) 0 0 / 34px 34px, linear-gradient(180deg, #16405E, #0F2E45); }
        .isle { position: absolute; width: 42%; padding: 0; border: 0; background: none; color: var(--ink); display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform .2s; }
        .isle.sel { transform: scale(1.08); }
        .isle-art svg { display: block; width: 100%; max-width: 110px; }
        .isle .shoal { fill: #F3E2B3; }
        .isle .land { fill: #58B86B; }
        .isle .palm { fill: none; stroke: #2F7D43; stroke-width: 4; stroke-linecap: round; }
        .isle.lvl-2 .land { fill: #9BCF6A; }
        .isle.lvl-0, .isle.lvl-1 { opacity: .55; filter: grayscale(.7); }
        .isle-name { font-size: 12px; font-weight: 700; background: var(--paper); border-radius: 8px; padding: 1px 6px; margin-top: -4px; }
        .isle.sel .isle-name { outline: 2px solid var(--accent); }
        .isle-dots { font-size: 11px; letter-spacing: 2px; color: var(--accent-edge); }
        .isle-info { margin-top: 10px; padding: 12px; border-radius: var(--radius); background: var(--paper); }
        .isle-info h4 { margin: 0 0 4px; font-size: 15px; }
        .fact { display: flex; gap: 8px; margin: 6px 0; font-size: 14px; line-height: 1.35; }
        .say.voice { margin: 8px 0; }
        .route { position: relative; margin-top: 8px; padding-left: 4px; }
        .route::before { content: ''; position: absolute; left: 17px; top: 16px; bottom: 16px; border-left: 2px dashed var(--dash); }
        .stop { position: relative; display: flex; align-items: center; gap: 10px; margin: 10px 0; }
        .stop-icon { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; background: var(--paper); font-size: 16px; }
    </style>
</head>
<body>
<div id="app">
    <header class="top" id="top" hidden>
        <div class="segments" id="segments"></div>
        <div class="top-row">
            <div class="top-label" id="top-label"></div>
            <button class="link" id="restart" type="button">↺ Заново</button>
        </div>
    </header>
    <main id="screen">
        <div class="center"><img src="{{ $mascot }}" alt=""><p class="muted">Загружаю…</p></div>
    </main>
    <footer class="bar" id="bar"></footer>
</div>
<script>
    window.DIAG = {
        mascot: @json($mascot),
        // Акула по настроению — DiagnosticController::page().
        mascots: @json($mascots),
        // Персонажи вопросов — config/diagnostic.php, characters.
        characters: @json($characters),
        urls: {
            session: @json(route('diag.session')),
            profile: @json(route('diag.profile')),
            level: @json(route('diag.level')),
            answer: @json(route('diag.answer')),
            target: @json(route('diag.target')),
            improve: @json(route('diag.improve')),
            restart: @json(route('diag.restart')),
            log: @json(route('diag.log')),
        },
    };

    // Ошибки у ученика — на сервер (DiagnosticController::clientError). До
    // основного скрипта и на старом JS: ловит и то, что он не загрузился.
    // Не больше 10 за открытие; повтор того же текста не шлём.
    (function () {
        var sent = 0, seen = {};
        window.diagReport = function (kind, message, extra) {
            message = String(message || '').slice(0, 2000);
            if (!message || seen[kind + message] || sent >= 10) return;
            seen[kind + message] = true;
            sent++;
            try {
                var tg = window.Telegram && window.Telegram.WebApp;
                fetch(window.DIAG.urls.log, {
                    method: 'POST', keepalive: true, credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ kind: kind, message: message, extra: extra || null, attempt: window.DIAG.attempt || null,
                        platform: tg ? tg.platform : null, version: tg ? tg.version : null })
                })['catch'](function () {});
            } catch (e) {}
        };
        window.addEventListener('error', function (e) {
            var t = e.target;
            if (t && t !== window && (t.src || t.href)) return window.diagReport('resource', 'не загрузился ' + (t.src || t.href));
            window.diagReport('js', e.message, { src: e.filename, line: e.lineno, col: e.colno, stack: e.error && e.error.stack ? String(e.error.stack).slice(0, 1500) : null });
        }, true);
        window.addEventListener('unhandledrejection', function (e) {
            var r = e.reason;
            window.diagReport('promise', r && r.message ? r.message : String(r), { stack: r && r.stack ? String(r.stack).slice(0, 1500) : null });
        });
    })();
</script>
<script src="{{ asset('js/diagnostic.js') }}?v={{ filemtime(public_path('js/diagnostic.js')) }}"></script>
</body>
</html>
