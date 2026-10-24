{{-- Диагностика по обществознанию — Mini App бота diag (ТЗ — docs/diagnostic/).
     Отдельная страница без шапки и меню сайта: открывается внутри Telegram
     поверх чата. Свой игровой вид «Подводный мир» (решение автора), а не цвета
     темы Telegram: фон — океан платформы (config diagnostic.sea), днём и ночью
     свой; ночь — по теме Telegram или системы (класс .night, ставит JS).
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
        /* Nunito — свой файл, не Google Fonts: внешний CDN у части учеников режет ТСПУ. */
        @font-face { font-family: "Nunito"; font-weight: 400 900; font-display: swap;
            src: url("/fonts/nunito/nunito-cyrillic.woff2") format("woff2"); unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116; }
        @font-face { font-family: "Nunito"; font-weight: 400 900; font-display: swap;
            src: url("/fonts/nunito/nunito-latin.woff2") format("woff2"); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }

        :root {
            --sea: url("{{ $sea['light'] }}");
            --sea-shade: linear-gradient(180deg, rgba(4, 40, 90, 0) 0%, rgba(4, 40, 90, .25) 55%, rgba(3, 28, 70, .55) 100%);
            --water: #1d7fd0;          /* фон под картинкой и шапка Telegram */
            --on-water: #ffffff;       /* текст прямо на воде */
            --on-water-soft: rgba(255, 255, 255, .82);
            --paper: rgba(255, 255, 255, .96);   /* плашки, пузыри, карточки */
            --paper-2: #eaf3ff;
            --ink: #0b2545;            /* текст на плашках */
            --ink-soft: #5a7290;
            --edge: #b9cde6;           /* «объёмная» кромка снизу у кнопок */
            --accent: #1f7fe0;
            --accent-edge: #1460ad;
            --accent-soft: #e2efff;
            --sun: #ffc83d;            /* главная кнопка */
            --sun-edge: #d99800;
            --ok: #23a95e;
            --ok-edge: #1a8148;
            --ok-bg: #e2f7ea;
            --bad: #f0484e;
            --bad-edge: #c22f35;
            --bad-bg: #ffe8e9;
            --radius: 18px;
        }
        /* Ночь: свой фон, когда автор его пришлёт (config diagnostic.sea.dark); до тех пор — тот же океан, глубже и темнее. */
        :root.night {
            --sea: url("{{ $sea['dark'] ?? $sea['light'] }}");
            --sea-shade: linear-gradient(180deg, rgba(2, 10, 30, {{ $sea['dark'] ? '.15' : '.55' }}) 0%, rgba(2, 10, 30, {{ $sea['dark'] ? '.35' : '.72' }}) 100%);
            --water: #0b2a52;
            --paper: rgba(15, 34, 66, .94);
            --paper-2: #1a3560;
            --ink: #eaf2ff;
            --ink-soft: #9db3d4;
            --edge: #07152b;
            --accent: #3b95f0;
            --accent-edge: #1d63ad;
            --accent-soft: rgba(59, 149, 240, .2);
            --ok-bg: rgba(35, 169, 94, .22);
            --bad-bg: rgba(240, 72, 78, .22);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        /* Фон воды — у html: у body он перекрыл бы слой .sea (z-index: -1). */
        html { background: var(--water); }
        html, body { margin: 0; color: var(--on-water); }
        body { background: transparent; }
        body {
            font: 600 16px/1.45 "Nunito", ui-rounded, "SF Pro Rounded", -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            min-height: 100vh; min-height: 100dvh;
        }
        button { font: inherit; color: inherit; }

        /* Вода: картинка, затемнение снизу для читаемости и медленные пузырьки. */
        .sea { position: fixed; inset: 0; z-index: -1; background: var(--sea-shade), var(--sea) center top / cover no-repeat, var(--water); overflow: hidden; }
        .sea i { position: absolute; bottom: -24px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, .55); background: rgba(255, 255, 255, .08);
            animation: rise-bubble linear infinite; }
        .sea i:nth-child(1) { left: 8%;  width: 10px; height: 10px; animation-duration: 14s; }
        .sea i:nth-child(2) { left: 22%; width: 6px;  height: 6px;  animation-duration: 11s; animation-delay: -4s; }
        .sea i:nth-child(3) { left: 41%; width: 14px; height: 14px; animation-duration: 18s; animation-delay: -9s; }
        .sea i:nth-child(4) { left: 63%; width: 8px;  height: 8px;  animation-duration: 13s; animation-delay: -2s; }
        .sea i:nth-child(5) { left: 79%; width: 12px; height: 12px; animation-duration: 16s; animation-delay: -11s; }
        .sea i:nth-child(6) { left: 92%; width: 7px;  height: 7px;  animation-duration: 12s; animation-delay: -6s; }
        @keyframes rise-bubble { from { transform: translate(0, 0); opacity: 0; } 10% { opacity: 1; } 50% { transform: translate(12px, -55vh); }
            to { transform: translate(-6px, -110vh); opacity: 0; } }

        #app { max-width: 560px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; }

        /* Шапка: путь из ракушек-блоков, название блока, «Заново». */
        .top { position: sticky; top: 0; z-index: 5; padding: 12px 16px 10px; background: linear-gradient(180deg, rgba(4, 40, 90, .55), rgba(4, 40, 90, 0)); }
        .top[hidden] { display: none; }
        .segments { display: flex; align-items: center; gap: 6px; }
        .segments i { position: relative; flex: 1; height: 8px; border-radius: 4px; background: rgba(255, 255, 255, .28); overflow: visible; }
        .segments i::after { content: ""; position: absolute; inset: 0; width: 0; border-radius: 4px; background: var(--sun);
            box-shadow: 0 0 8px rgba(255, 200, 61, .6); transition: width .6s cubic-bezier(.2, .8, .2, 1); }
        .segments i.done::after { width: 100%; }
        .segments i.now::after { width: 35%; }
        .segments i.done::before { content: "🐚"; position: absolute; right: -6px; top: 50%; transform: translateY(-50%); font-size: 15px; z-index: 1;
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, .3)); animation: pop .4s ease-out; }
        .top-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-top: 8px; }
        .top-label { font-size: 14px; font-weight: 800; color: var(--on-water); text-shadow: 0 1px 2px rgba(0, 0, 0, .25); }

        main { flex: 1; padding: 8px 16px 16px; }
        .bar { position: sticky; bottom: 0; z-index: 5; padding: 14px 16px calc(12px + env(safe-area-inset-bottom)); display: flex; gap: 10px; align-items: center;
            background: linear-gradient(0deg, rgba(3, 28, 70, .6), rgba(3, 28, 70, 0)); }
        .bar:empty { display: none; }

        /* Текст прямо на воде. */
        .muted { color: var(--on-water-soft); }
        .small { font-size: 14px; }
        .counter { font-size: 13px; color: var(--on-water-soft); margin-top: 10px; }
        .task { font-size: 14px; color: var(--on-water-soft); margin: -4px 0 10px; }
        .link { border: 0; background: none; padding: 6px 0; color: var(--on-water-soft); font-size: 13px; font-weight: 700; cursor: pointer; }
        .link:active { color: var(--on-water); }
        .center-link { text-align: center; margin: 6px 0 8px; }

        /* Ведущая и реплики — пузыри на «бумаге». */
        .say { display: flex; gap: 10px; align-items: flex-end; margin: 12px 0; animation: pop .25s ease-out; }
        .say img { width: 64px; height: 64px; flex: none; filter: drop-shadow(0 4px 6px rgba(0, 0, 0, .25)); }
        .bubble { background: var(--paper); color: var(--ink); padding: 11px 15px; border-radius: 20px 20px 20px 6px; max-width: 85%; white-space: pre-line;
            box-shadow: 0 4px 14px rgba(0, 20, 60, .18); }
        .bubble b { font-weight: 800; }
        .bubble.ok { background: var(--ok-bg); box-shadow: inset 0 0 0 2px var(--ok), 0 4px 14px rgba(0, 20, 60, .18); }
        .bubble.bad { background: var(--bad-bg); box-shadow: inset 0 0 0 2px var(--bad), 0 4px 14px rgba(0, 20, 60, .18); }
        :root:not(.night) .bubble.ok, :root:not(.night) .bubble.bad { background: #fff; }
        @keyframes pop { from { opacity: 0; transform: translateY(6px) scale(.97); } to { opacity: 1; transform: none; } }

        .card { background: var(--paper); color: var(--ink); border-radius: var(--radius); padding: 16px; margin: 10px 0; box-shadow: 0 6px 20px rgba(0, 20, 60, .2); }
        .character { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px; color: var(--on-water-soft); }
        .avatar { width: 44px; height: 44px; flex: none; border-radius: 50%; background: var(--accent); color: #fff; display: grid; place-items: center;
            font-weight: 800; font-size: 18px; box-shadow: 0 0 0 3px rgba(255, 255, 255, .9), 0 3px 8px rgba(0, 0, 0, .2); }
        .avatar.emoji { font-size: 26px; }
        .say .avatar { width: 64px; height: 64px; font-size: 26px; }
        .say .avatar.emoji { font-size: 36px; }

        /* Кнопки — «объёмные»: заливка и толстая кромка снизу, при нажатии вдавливаются. */
        .btn {
            border: 0; border-radius: 16px; padding: 14px 16px; background: var(--sun); color: #3a2a00; border-bottom: 4px solid var(--sun-edge);
            font-weight: 900; font-size: 16px; cursor: pointer; flex: 1; transition: transform .08s, border-width .08s, opacity .15s;
        }
        .btn:active { transform: translateY(3px); border-bottom-width: 1px; }
        .btn[disabled] { opacity: .5; cursor: default; }
        .btn.ghost { background: var(--paper); color: var(--ink); border-bottom-color: var(--edge); }
        /* В нижней панели главная кнопка шире второстепенной («Хочу разобраться с этим» — в две строки, не в три). */
        .bar .btn:not(.ghost):not(.choice) { flex: 1.6; }
        /* Ответ (класс, «Это правда / не так») — белая объёмная плашка: синяя слилась бы с водой. */
        .btn.choice { background: var(--paper); color: var(--ink); border-bottom-color: var(--edge); box-shadow: 0 6px 16px rgba(0, 20, 60, .18); }
        .chip-guess {
            flex: none; border: 0; border-bottom: 3px solid var(--edge); background: var(--paper); color: var(--ink-soft); border-radius: 999px;
            padding: 10px 14px; font-size: 14px; font-weight: 800; cursor: pointer;
        }
        .chip-guess.on { background: var(--accent-soft); color: var(--accent); border-bottom-color: var(--accent); }
        .grades { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px; }
        .grades.answers { margin: 16px 0 0 74px; }

        /* Вопрос — без карточки, на воде; плашки — у вариантов. */
        .q { margin: 4px 0 12px; }
        .prompt { margin: 0 0 12px; font-weight: 800; }
        .q > .prompt { font-size: 19px; line-height: 1.35; margin: 6px 0 16px; color: var(--on-water); text-shadow: 0 1px 3px rgba(0, 20, 60, .35); }
        .q > .task { margin: -8px 0 14px; }
        .options { display: grid; gap: 10px; }
        .opt {
            text-align: left; border: 2px solid transparent; border-bottom: 4px solid var(--edge); background: var(--paper); color: var(--ink);
            border-radius: 16px; padding: 13px 15px; cursor: pointer; font-weight: 700; transition: transform .08s, background .15s, border-color .15s;
        }
        .opt:active { transform: translateY(2px); }
        .opt.sel { border-color: var(--accent); border-bottom-color: var(--accent-edge); background: var(--accent-soft); }
        .opt.ok { border-color: var(--ok); border-bottom-color: var(--ok-edge); background: var(--ok-bg); }
        .opt.bad { border-color: var(--bad); border-bottom-color: var(--bad-edge); background: var(--bad-bg); }
        .opt.missed { border-color: var(--ok); border-style: dashed; }
        .opt[disabled] { cursor: default; }
        .opt .mark { float: right; margin-left: 8px; font-weight: 900; }
        .fragment { font-size: 15px; }
        .fragment.sel { text-decoration: line-through; text-decoration-color: var(--bad); text-decoration-thickness: 2px; }

        /* «Кто прав?»: форма пузыря (кто говорит) и объём варианта (на него нажимают). */
        .speaker { display: flex; gap: 10px; align-items: flex-start; }
        .speaker .bubble { flex: 1; cursor: pointer; border: 2px solid transparent; border-bottom: 4px solid var(--edge); transition: transform .08s; }
        .speaker:active .bubble { transform: translateY(2px); }
        .speaker.sel .bubble { border-color: var(--accent); border-bottom-color: var(--accent-edge); background: var(--accent-soft); }
        .speaker.ok .bubble { border-color: var(--ok); border-bottom-color: var(--ok-edge); background: var(--ok-bg); box-shadow: none; }
        .speaker.bad .bubble { border-color: var(--bad); border-bottom-color: var(--bad-edge); background: var(--bad-bg); box-shadow: none; }
        /* Просьба персонажа над вариантами («Помоги выиграть спор») — реплика, не вариант. */
        .q > .speaker { margin-bottom: 16px; }
        .q > .speaker .bubble { cursor: default; border: 0; }
        .speech-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 15px; }

        /* Свайп. */
        .swipe-zone { position: relative; height: 250px; display: grid; place-items: center; margin: 10px 0; touch-action: pan-y; }
        .swipe-card {
            width: 100%; min-height: 190px; background: var(--paper); color: var(--ink); border-radius: 24px; padding: 22px; display: grid;
            place-items: center; text-align: center; font-size: 19px; font-weight: 800; cursor: grab; user-select: none;
            border-bottom: 6px solid var(--edge); box-shadow: 0 10px 30px rgba(0, 20, 60, .28); transition: transform .25s, background .2s;
        }
        .swipe-card.drag { transition: none; }
        .swipe-card.yes { background: var(--ok-bg); border-bottom-color: var(--ok); }
        .swipe-card.no { background: var(--bad-bg); border-bottom-color: var(--bad); }
        /* place-items сбросить: свежий Chrome применяет justify-items и к блочной вёрстке. */
        .swipe-card.chat { display: block; place-items: normal; text-align: left; }
        .swipe-hint { display: flex; justify-content: space-between; font-size: 13px; font-weight: 800; color: var(--on-water-soft); margin-bottom: 8px; }
        .swipe-zone + .swipe-hint + .grades, .q .grades { margin-top: 4px; }

        /* Корзины: карточки — плашки, группы — зоны на воде. */
        .pool { display: flex; flex-wrap: wrap; gap: 8px; min-height: 10px; margin: 6px 0 14px; }
        .tag {
            border: 2px solid transparent; border-bottom: 4px solid var(--edge); background: var(--paper); color: var(--ink); border-radius: 14px;
            padding: 9px 12px; font-size: 14px; font-weight: 700; cursor: pointer; text-align: left;
        }
        .tag.sel { border-color: var(--accent); border-bottom-color: var(--accent-edge); background: var(--accent-soft); }
        .tag.ok { border-color: var(--ok); border-bottom-color: var(--ok-edge); background: var(--ok-bg); }
        .tag.bad { border-color: var(--bad); border-bottom-color: var(--bad-edge); background: var(--bad-bg); }
        .tag .fix { display: block; font-size: 12px; color: var(--ok); margin-top: 2px; font-weight: 800; }
        .tag.case { display: flex; flex-direction: column; gap: 2px; }
        .tag.case b { font-size: 12px; color: var(--ink-soft); font-weight: 800; }
        .buckets { display: grid; gap: 10px; }
        .bucket { border: 2px dashed rgba(255, 255, 255, .55); border-radius: 16px; padding: 10px; cursor: pointer; background: rgba(255, 255, 255, .08); }
        .bucket.target { border-color: rgba(255, 255, 255, .85); }
        .bucket.over { border-style: solid; border-color: var(--sun); background: rgba(255, 200, 61, .18); }
        .bucket h4 { margin: 0 0 6px; font-size: 15px; font-weight: 900; color: var(--on-water); }
        .bucket .pool { margin: 0; min-height: 44px; }
        /* У каждой карточки своё место: взятая оставляет пустую рамку того же размера. */
        .slot { display: flex; }
        .slot > .tag { flex: 1; }
        .slot.empty { border: 2px dashed rgba(255, 255, 255, .5); border-radius: 14px; }
        .tag.drag { touch-action: none; user-select: none; -webkit-user-select: none; }
        .tag.dragging { position: fixed; z-index: 50; margin: 0; pointer-events: none;
            transform: scale(1.05) rotate(-2deg); box-shadow: 0 14px 34px rgba(0, 0, 0, .35); }

        /* Ответ персонажа («Ты эксперт ЕГЭ») и пост («Найди и исправь») — плашкой с полоской его цвета. */
        .options.sheet { gap: 0; background: var(--paper); color: var(--ink); border-radius: 16px; padding: 4px 6px 4px 14px;
            border-left: 5px solid var(--quote, var(--accent)); box-shadow: 0 6px 20px rgba(0, 20, 60, .2); }
        .options.sheet .opt { background: none; border: 0; border-bottom: 1px solid rgba(90, 114, 144, .25); border-radius: 0; padding: 12px 4px; }
        .options.sheet .opt:last-child { border-bottom: 0; }
        .options.sheet .opt.sel { background: var(--bad-bg); }
        .options.sheet .opt.ok { background: var(--ok-bg); }
        .options.sheet .opt.bad { background: var(--bad-bg); }
        .options.sheet .opt.missed { background: none; box-shadow: inset 3px 0 0 var(--ok); }
        .q > .speech-head b { color: var(--on-water); }
        .post { background: var(--paper); color: var(--ink); border-radius: 16px; padding: 12px 14px; border-left: 5px solid var(--quote, var(--accent));
            box-shadow: 0 6px 20px rgba(0, 20, 60, .2); }
        .post-text { line-height: 1.95; font-weight: 700; }
        .spot { border-bottom: 3px dotted var(--accent); cursor: pointer; padding: 0 2px; border-radius: 4px 4px 0 0; }
        .spot.sel { background: var(--accent-soft); }
        .spot.changed { border-bottom-style: solid; font-weight: 900; }
        .spot.ok { background: var(--ok-bg); border-bottom: 3px solid var(--ok); }
        .spot.bad { background: var(--bad-bg); border-bottom: 3px solid var(--bad); }
        .spot .fix { color: var(--ok); font-size: 13px; }
        .picker { margin-top: 12px; animation: rise .2s ease-out both; }
        .picker[hidden] { display: none; }

        /* Заставка блока и переходы. */
        .center { text-align: center; padding: 28px 8px; }
        .center img { width: 170px; height: 170px; filter: drop-shadow(0 8px 14px rgba(0, 0, 0, .3)); }
        .big { font-size: 30px; font-weight: 900; margin: 8px 0 6px; color: var(--on-water); text-shadow: 0 2px 6px rgba(0, 20, 60, .35); }
        .done-chip { display: inline-block; margin-bottom: 10px; padding: 6px 14px; border-radius: 999px; background: var(--ok); color: #fff; font-weight: 900;
            font-size: 14px; border-bottom: 3px solid var(--ok-edge); animation: pop .4s ease-out .15s both; }
        .countdown { flex: 1; height: 6px; border-radius: 3px; background: rgba(255, 255, 255, .3); overflow: hidden; margin: 8px 0; }
        .countdown i { display: block; height: 100%; width: 100%; background: var(--sun); transform-origin: left; animation: countdown linear both; }
        @keyframes countdown { from { transform: scaleX(0); } to { transform: scaleX(1); } }

        /* Цель. */
        .target-value { font-size: 64px; font-weight: 900; text-align: center; margin: 8px 0; color: var(--ink); }
        input[type=range] { width: 100%; accent-color: var(--accent); }

        /* Итог — без карточек, на воде; плашки — у вывода и второй части. */
        .hero { text-align: center; margin: 14px 0 6px; }
        .score { font-size: 60px; font-weight: 900; text-align: center; margin: 0; line-height: 1.05; color: var(--on-water);
            text-shadow: 0 3px 10px rgba(0, 20, 60, .35); }
        .hero-note { margin: 4px 0 0; font-weight: 800; }
        .res-sec { margin: 24px 0; }
        .res-sec h3 { font-size: 16px; font-weight: 900; margin: 0 0 10px; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .chip-ok { background: var(--paper); color: var(--ok); border-radius: 999px; padding: 6px 12px; font-size: 14px; font-weight: 900; border-bottom: 3px solid var(--edge); }
        .gain { display: grid; grid-template-columns: 1fr auto; gap: 4px 10px; align-items: center; margin: 12px 0; font-weight: 800; }
        .gain .track { grid-column: 1 / -1; height: 8px; border-radius: 4px; background: rgba(255, 255, 255, .25); overflow: hidden; }
        .gain .track i { display: block; height: 100%; background: var(--sun); transition: width .7s cubic-bezier(.2, .8, .2, 1); }
        .gain b { color: var(--sun); }
        .gain-part2 { padding: 12px 14px; border-radius: 16px; background: var(--paper); color: var(--ink); border-bottom: 4px solid var(--edge); }
        /* Шкала 0–100: диапазон «сейчас», чёрточка — цель, кружок — потенциал. */
        .scale { position: relative; height: 12px; border-radius: 6px; background: rgba(255, 255, 255, .28); margin: 24px 6px 46px; text-align: left; }
        .scale .band { position: absolute; top: 0; bottom: 0; border-radius: 6px; background: var(--sun); box-shadow: 0 0 10px rgba(255, 200, 61, .6);
            transition: width .8s cubic-bezier(.2, .8, .2, 1); }
        .scale .tick { position: absolute; top: -7px; width: 4px; height: 26px; margin-left: -2px; border-radius: 2px; background: #fff; }
        .scale .dot { position: absolute; top: 50%; width: 16px; height: 16px; border-radius: 50%; border: 4px solid var(--ok); background: #fff; transform: translate(-50%, -50%); }
        .scale .lbl { position: absolute; top: 22px; transform: translateX(-50%); white-space: nowrap; font-size: 12px; font-weight: 800; color: var(--on-water-soft); }
        .scale .lbl.alt { top: 38px; }
        .scale .lbl.pot { color: #b9f5cf; }
        details.profile { margin: 18px 0; }
        details.profile summary { cursor: pointer; color: var(--on-water-soft); font-size: 14px; font-weight: 800; list-style: none; }
        details.profile summary::after { content: " ▾"; }
        details.profile[open] summary::after { content: " ▴"; }
        details.profile[open] .radar { background: var(--paper); border-radius: 16px; padding: 6px; margin-top: 10px; }

        /* Радар по блокам. */
        .radar { display: block; width: 100%; max-width: 340px; margin: 4px auto 0; overflow: visible; }
        .radar .grid { fill: none; stroke: rgba(90, 114, 144, .3); }
        .radar .shape { fill: rgba(31, 127, 224, .25); stroke: var(--accent); stroke-width: 2.5; stroke-linejoin: round;
            transform-origin: center; transform-box: view-box; animation: grow .8s .2s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes grow { from { transform: scale(0); opacity: 0; } to { transform: none; opacity: 1; } }
        .radar text { font-size: 11px; font-weight: 700; fill: var(--ink-soft); }

        /* ---- Движение: по одному короткому на событие. ---- */
        .enter { animation: slide-in .32s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes slide-in { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
        .rise { animation: rise .4s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .flash-ok { animation: flash-ok .7s ease-out; }
        @keyframes flash-ok { 0% { box-shadow: 0 0 0 0 rgba(35, 169, 94, .6); } 100% { box-shadow: 0 0 0 16px rgba(35, 169, 94, 0); } }
        .shake { animation: shake .38s ease-in-out; }
        @keyframes shake { 20% { transform: translateX(-6px); } 40% { transform: translateX(5px); } 60% { transform: translateX(-3px); } 80% { transform: translateX(2px); } }
        .say .hop { animation: hop .5s ease-out; }
        @keyframes hop { 30% { transform: translateY(-10px) rotate(-6deg); } 60% { transform: translateY(0) rotate(3deg); } }
        .say .tilt { animation: tilt .6s ease-in-out; }
        @keyframes tilt { 40% { transform: rotate(-12deg) translateY(2px); } }
        .bob { animation: bob 3s ease-in-out infinite; }
        @keyframes bob { 50% { transform: translateY(-8px); } }
        .typing { display: inline-flex; gap: 4px; padding: 4px 2px; }
        .typing i { width: 7px; height: 7px; border-radius: 50%; background: var(--ink-soft); animation: dot 1s infinite ease-in-out; }
        .typing i:nth-child(2) { animation-delay: .15s; }
        .typing i:nth-child(3) { animation-delay: .3s; }
        @keyframes dot { 0%, 60%, 100% { opacity: .3; transform: none; } 30% { opacity: 1; transform: translateY(-3px); } }
        .toast {
            position: fixed; left: 50%; top: 70px; transform: translateX(-50%); z-index: 20; pointer-events: none;
            background: var(--sun); color: #3a2a00; font-weight: 900; padding: 8px 18px; border-radius: 999px; border-bottom: 4px solid var(--sun-edge);
            animation: toast 1.8s ease-out both;
        }
        @keyframes toast { 0% { opacity: 0; transform: translate(-50%, -8px) scale(.9); } 15% { opacity: 1; transform: translate(-50%, 0) scale(1.08); }
            25% { transform: translate(-50%, 0) scale(1); } 80% { opacity: 1; } 100% { opacity: 0; transform: translate(-50%, -6px); } }
        .confetti { position: fixed; inset: 0; pointer-events: none; z-index: 30; overflow: hidden; }
        .confetti i { position: absolute; top: -12px; width: 8px; height: 12px; border-radius: 2px; animation: fall 1.4s cubic-bezier(.3, .6, .6, 1) forwards; }
        @keyframes fall { to { transform: translate(var(--dx), 105vh) rotate(var(--rot)); opacity: .9; } }
        .meme { display: block; max-width: 100%; border-radius: 12px; margin-top: 8px; }

        /* Кому движение мешает (настройка системы) — всё сразу, без анимаций. */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .confetti, .sea i { display: none; }
        }
    </style>
</head>
<body>
<div class="sea" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
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
            answer: @json(route('diag.answer')),
            target: @json(route('diag.target')),
            improve: @json(route('diag.improve')),
            restart: @json(route('diag.restart')),
        },
    };
</script>
<script src="{{ asset('js/diagnostic.js') }}?v={{ filemtime(public_path('js/diagnostic.js')) }}"></script>
</body>
</html>
