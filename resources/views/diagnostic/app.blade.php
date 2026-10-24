{{-- Диагностика по обществознанию — Mini App бота diag (ТЗ — docs/diagnostic/).
     Отдельная страница без шапки и меню сайта: открывается внутри Telegram
     поверх чата. Цвета — из темы Telegram (--tg-theme-*, их ставит
     telegram-web-app.js), без Telegram — светлая/тёмная по системе.
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
    {{-- Скрипт Telegram — до своего: он выставляет переменные темы и
         initData. В обычном браузере безвреден (initData пустой). --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
        :root {
            --bg: var(--tg-theme-bg-color, #ffffff);
            --card: var(--tg-theme-secondary-bg-color, #f2f4f7);
            --text: var(--tg-theme-text-color, #16181d);
            --hint: var(--tg-theme-hint-color, #7a808c);
            --accent: var(--tg-theme-button-color, #2f6fed);
            --accent-text: var(--tg-theme-button-text-color, #ffffff);
            --line: rgba(127, 127, 127, .22);
            --ok: #1f9d55;
            --ok-bg: rgba(31, 157, 85, .13);
            --bad: #e5484d;
            --bad-bg: rgba(229, 72, 77, .13);
            --radius: 16px;
            /* Варианты ответа — рамка и лёгкий тон акцента; реплики — сплошная
               заливка --card. Так нажимаемое не сливается с репликами ни в одной
               теме Telegram. */
            --choice-line: color-mix(in srgb, var(--accent) 50%, transparent);
            --choice-bg: color-mix(in srgb, var(--accent) 9%, transparent);
            --choice-sel: color-mix(in srgb, var(--accent) 22%, transparent);
        }
        @media (prefers-color-scheme: dark) {
            :root:not(.tg) {
                --bg: #17191d; --card: #23262c; --text: #eceef2; --hint: #9097a3;
            }
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; background: var(--bg); color: var(--text); }
        body {
            font: 16px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh; min-height: 100dvh;
        }
        button { font: inherit; color: inherit; }
        #app { max-width: 560px; margin: 0 auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; }

        /* Прогресс — по блокам, не по вопросам (блоки разной длины). */
        .top { position: sticky; top: 0; z-index: 5; background: var(--bg); padding: 12px 16px 8px; }
        .top[hidden] { display: none; }
        .segments { display: flex; gap: 4px; }
        .segments i { flex: 1; height: 5px; border-radius: 3px; background: var(--line); transition: background .3s; }
        .segments i.done { background: var(--accent); }
        .segments i.now { background: linear-gradient(90deg, var(--accent) 50%, var(--line) 50%); }
        .top-label { margin-top: 6px; font-size: 13px; color: var(--hint); }

        main { flex: 1; padding: 8px 16px 16px; }
        .bar {
            position: sticky; bottom: 0; z-index: 5; background: var(--bg);
            padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); display: flex; gap: 8px; align-items: center;
            border-top: 1px solid var(--line);
        }
        .bar:empty { display: none; }

        /* Маскот и его реплики — пузыри как в чате. */
        .say { display: flex; gap: 10px; align-items: flex-end; margin: 10px 0; animation: pop .25s ease-out; }
        .say img { width: 64px; height: 64px; flex: none; }
        .bubble { background: var(--card); padding: 10px 14px; border-radius: 18px 18px 18px 6px; max-width: 85%; white-space: pre-line; }
        .bubble b { font-weight: 600; }
        .bubble.ok { background: var(--ok-bg); }
        .bubble.bad { background: var(--bad-bg); }
        @keyframes pop { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        .card { background: var(--card); border-radius: var(--radius); padding: 16px; margin: 10px 0; }
        .prompt { font-weight: 600; margin: 0 0 12px; }
        .task { font-size: 14px; color: var(--hint); margin: -4px 0 10px; }
        .character { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px; color: var(--hint); }
        .avatar { width: 44px; height: 44px; flex: none; border-radius: 50%; background: var(--accent); color: var(--accent-text); display: grid; place-items: center; font-weight: 700; font-size: 18px; }

        .btn {
            border: 0; border-radius: 12px; padding: 13px 16px; background: var(--accent); color: var(--accent-text);
            font-weight: 600; cursor: pointer; flex: 1; transition: opacity .15s, transform .1s;
        }
        .btn:active { transform: scale(.98); }
        .btn[disabled] { opacity: .4; cursor: default; }
        .btn.ghost { background: var(--card); color: var(--text); }
        .btn.choice { background: transparent; background: var(--choice-bg); color: var(--text); border: 1.5px solid var(--line); border-color: var(--choice-line); }
        .bar .grades { flex: 1; margin: 0; }
        .chip-guess {
            flex: none; border: 1px solid var(--line); background: transparent; border-radius: 999px; padding: 10px 12px;
            font-size: 14px; cursor: pointer; color: var(--hint);
        }
        .chip-guess.on { border-color: var(--accent); color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, transparent); }

        .options { display: grid; gap: 8px; }
        /* Вопрос — без карточки, прямо на экране; плашки — у вариантов. */
        .q { margin: 4px 0 12px; }
        .q > .prompt { font-size: 18px; margin: 4px 0 14px; }
        .q > .task { margin: -6px 0 14px; }
        .opt {
            text-align: left; border: 1.5px solid var(--line); border-color: var(--choice-line); background: transparent; background: var(--choice-bg);
            border-radius: 12px; padding: 12px 14px; cursor: pointer; transition: border-color .15s, background .15s;
        }
        .opt.sel { border-color: var(--accent); background: var(--choice-sel); box-shadow: inset 0 0 0 1px var(--accent); }
        .opt.ok { border-color: var(--ok); background: var(--ok-bg); }
        .opt.bad { border-color: var(--bad); background: var(--bad-bg); }
        .opt.missed { border-color: var(--ok); border-style: dashed; }
        .opt[disabled] { cursor: default; }
        .opt .mark { float: right; margin-left: 8px; }
        .fragment { font-size: 15px; }
        .fragment.sel { text-decoration: line-through; text-decoration-color: var(--bad); }
        .counter { font-size: 13px; color: var(--hint); margin-top: 8px; }

        /* «Кто прав?» — реплики персонажей. */
        .speaker { display: flex; gap: 10px; align-items: flex-start; }
        /* Реплика-вариант: форма пузыря (кто говорит), рамка варианта (на неё нажимают). */
        .speaker .bubble { flex: 1; cursor: pointer; border: 1.5px solid var(--line); border-color: var(--choice-line); background: transparent; background: var(--choice-bg); }
        .speaker.sel .bubble { border-color: var(--accent); background: var(--choice-sel); box-shadow: inset 0 0 0 1px var(--accent); }
        .speaker.ok .bubble { border-color: var(--ok); background: var(--ok-bg); box-shadow: none; }
        .speaker.bad .bubble { border-color: var(--bad); background: var(--bad-bg); box-shadow: none; }
        /* Просьба персонажа над вариантами («Помоги выиграть спор») — реплика, не вариант. */
        .q > .speaker .bubble { cursor: default; border: 0; background: var(--card); }

        /* Свайп. */
        .swipe-zone { position: relative; height: 240px; display: grid; place-items: center; margin: 10px 0; touch-action: pan-y; }
        .swipe-card {
            width: 100%; min-height: 180px; background: var(--card); border-radius: 20px; padding: 22px; display: grid;
            place-items: center; text-align: center; font-size: 18px; font-weight: 600; cursor: grab; user-select: none;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .08); transition: transform .25s, background .2s;
        }
        .swipe-card.drag { transition: none; }
        .swipe-card.yes { background: var(--ok-bg); }
        .swipe-card.no { background: var(--bad-bg); }
        .swipe-hint { display: flex; justify-content: space-between; font-size: 13px; color: var(--hint); }

        /* Корзины и чипы. */
        .pool { display: flex; flex-wrap: wrap; gap: 8px; min-height: 10px; margin: 6px 0 12px; }
        .tag {
            border: 1.5px solid var(--line); border-color: var(--choice-line); background: transparent; background: var(--choice-bg);
            border-radius: 10px; padding: 8px 10px; font-size: 14px; cursor: pointer; text-align: left;
        }
        .tag.sel { border-color: var(--accent); background: var(--choice-sel); box-shadow: inset 0 0 0 1px var(--accent); }
        .tag.ok { border-color: var(--ok); background: var(--ok-bg); }
        .tag.bad { border-color: var(--bad); background: var(--bad-bg); }
        .tag .fix { display: block; font-size: 12px; color: var(--ok); margin-top: 2px; }
        .buckets { display: grid; gap: 8px; }
        .bucket { border: 2px dashed var(--line); border-radius: 12px; padding: 10px; cursor: pointer; background: var(--bg); }
        .bucket.target { border-color: var(--accent); }
        .bucket h4 { margin: 0 0 6px; font-size: 14px; }
        .bucket .pool { margin: 0; min-height: 44px; }
        .bucket.over { border-style: solid; border-color: var(--accent); background: var(--choice-sel); }
        /* У каждой карточки своё место: взятая оставляет пустую рамку того же размера. */
        .slot { display: flex; }
        .slot > .tag { flex: 1; }
        .slot.empty { border: 1.5px dashed var(--line); border-radius: 10px; }
        .tag.drag { touch-action: none; user-select: none; -webkit-user-select: none; }
        .tag.dragging {
            position: fixed; z-index: 50; margin: 0; pointer-events: none;
            transform: scale(1.04) rotate(-1.5deg); box-shadow: 0 12px 30px rgba(0, 0, 0, .28);
        }

        /* Переходы и итог. */
        .center { text-align: center; padding: 28px 8px; }
        .center img { width: 160px; height: 160px; }
        .big { font-size: 22px; font-weight: 700; margin: 10px 0 6px; }
        .muted { color: var(--hint); }
        .grades { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }
        .target-value { font-size: 56px; font-weight: 800; text-align: center; margin: 8px 0; }
        input[type=range] { width: 100%; accent-color: var(--accent); }
        .score { font-size: 44px; font-weight: 800; text-align: center; margin: 4px 0; }
        .score small { display: block; font-size: 14px; font-weight: 500; color: var(--hint); }
        .gain { display: grid; grid-template-columns: 1fr auto; gap: 4px 10px; align-items: center; margin: 10px 0; }
        .gain .track { grid-column: 1 / -1; height: 6px; border-radius: 3px; background: var(--line); overflow: hidden; }
        .gain .track i { display: block; height: 100%; background: var(--accent); }
        .gain b { color: var(--accent); }
        .section-title { font-weight: 700; margin: 0 0 4px; }
        .stack { display: grid; gap: 8px; }
        .opt:active, .tag:active, .speaker:active { transform: scale(.98); }

        /* ---- Движение: по одному короткому на событие, без петель. ---- */
        .enter { animation: slide-in .32s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes slide-in { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
        .rise { animation: rise .4s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        /* Верно — мягкая вспышка карточки, неверно — короткая встряска. */
        .flash-ok { animation: flash-ok .7s ease-out; }
        @keyframes flash-ok { 0% { box-shadow: 0 0 0 0 rgba(31, 157, 85, .45); } 100% { box-shadow: 0 0 0 14px rgba(31, 157, 85, 0); } }
        .shake { animation: shake .38s ease-in-out; }
        @keyframes shake { 20% { transform: translateX(-6px); } 40% { transform: translateX(5px); } 60% { transform: translateX(-3px); } 80% { transform: translateX(2px); } }

        /* Рыба: радуется — подпрыгивает, сочувствует — наклоняет голову, на вводе блока — покачивается. */
        .say .hop { animation: hop .5s ease-out; }
        @keyframes hop { 30% { transform: translateY(-9px) rotate(-6deg); } 60% { transform: translateY(0) rotate(3deg); } }
        .say .tilt { animation: tilt .6s ease-in-out; }
        @keyframes tilt { 40% { transform: rotate(-12deg) translateY(2px); } }
        .bob { animation: bob 3s ease-in-out infinite; }
        @keyframes bob { 50% { transform: translateY(-6px); } }

        /* «Печатает…» перед репликой маскота. */
        .typing { display: inline-flex; gap: 4px; padding: 4px 2px; }
        .typing i { width: 7px; height: 7px; border-radius: 50%; background: var(--hint); animation: dot 1s infinite ease-in-out; }
        .typing i:nth-child(2) { animation-delay: .15s; }
        .typing i:nth-child(3) { animation-delay: .3s; }
        @keyframes dot { 0%, 60%, 100% { opacity: .3; transform: none; } 30% { opacity: 1; transform: translateY(-3px); } }

        /* Серия — плашка вверху, сама исчезает. */
        .toast {
            position: fixed; left: 50%; top: 64px; transform: translateX(-50%); z-index: 20; pointer-events: none;
            background: var(--text); color: var(--bg); font-weight: 700; padding: 8px 16px; border-radius: 999px;
            animation: toast 1.8s ease-out both;
        }
        @keyframes toast { 0% { opacity: 0; transform: translate(-50%, -8px) scale(.9); } 15% { opacity: 1; transform: translate(-50%, 0) scale(1.05); }
            25% { transform: translate(-50%, 0) scale(1); } 80% { opacity: 1; } 100% { opacity: 0; transform: translate(-50%, -6px); } }

        /* Прогресс блоков: заливка сегмента плавно. */
        .segments i { position: relative; overflow: hidden; background: var(--line); }
        .segments i::after { content: ""; position: absolute; inset: 0; width: 0; background: var(--accent); transition: width .6s cubic-bezier(.2, .8, .2, 1); }
        .segments i.done::after { width: 100%; }
        .segments i.now::after { width: 35%; }
        .segments i.done, .segments i.now { background: var(--line); }

        /* Радар по блокам на итоге. */
        .radar { display: block; width: 100%; max-width: 340px; margin: 4px auto 0; overflow: visible; }
        .radar .grid { fill: none; stroke: var(--line); }
        .radar .shape { fill: color-mix(in srgb, var(--accent) 22%, transparent); stroke: var(--accent); stroke-width: 2; stroke-linejoin: round;
            transform-origin: center; transform-box: view-box; animation: grow .8s .2s cubic-bezier(.2, .8, .2, 1) both; }
        @keyframes grow { from { transform: scale(0); opacity: 0; } to { transform: none; opacity: 1; } }
        .radar text { font-size: 11px; fill: var(--hint); }
        .gain .track i { transition: width .7s cubic-bezier(.2, .8, .2, 1); }

        /* Конфетти — один короткий залп на итоге. */
        .confetti { position: fixed; inset: 0; pointer-events: none; z-index: 30; overflow: hidden; }
        .confetti i { position: absolute; top: -12px; width: 8px; height: 12px; border-radius: 2px; animation: fall 1.4s cubic-bezier(.3, .6, .6, 1) forwards; }
        @keyframes fall { to { transform: translate(var(--dx), 105vh) rotate(var(--rot)); opacity: .9; } }

        .meme { display: block; max-width: 100%; border-radius: 12px; margin-top: 8px; }

        /* Итог — без карточек: блоки разделены отступами и заголовками. */
        .hero { text-align: center; margin: 14px 0 6px; }
        .hero .score { font-size: 52px; margin: 0; line-height: 1.1; }
        .hero-note { margin: 4px 0 0; }
        .small { font-size: 14px; }
        .res-sec { margin: 22px 0; }
        .res-sec h3 { font-size: 15px; margin: 0 0 8px; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip-ok { background: var(--ok-bg); color: var(--ok); border-radius: 999px; padding: 5px 11px; font-size: 14px; font-weight: 500; }
        .gain-part2 { padding: 10px 12px; border-radius: 12px; background: var(--choice-bg); border: 1.5px solid var(--choice-line); }
        /* Шкала 0–100: диапазон «сейчас», чёрточка — цель, кружок — потенциал. */
        .scale { position: relative; height: 10px; border-radius: 5px; background: var(--line); margin: 22px 6px 44px; text-align: left; }
        .scale .band { position: absolute; top: 0; bottom: 0; border-radius: 5px; background: var(--accent); transition: width .8s cubic-bezier(.2, .8, .2, 1); }
        .scale .tick { position: absolute; top: -6px; width: 3px; height: 22px; margin-left: -1.5px; border-radius: 2px; background: var(--text); }
        .scale .dot { position: absolute; top: 50%; width: 14px; height: 14px; border-radius: 50%; border: 3px solid var(--ok); background: var(--bg); transform: translate(-50%, -50%); }
        .scale .lbl { position: absolute; top: 20px; transform: translateX(-50%); white-space: nowrap; font-size: 12px; color: var(--hint); }
        .scale .lbl.alt { top: 36px; }
        .scale .lbl.pot { color: var(--ok); }
        details.profile { margin: 18px 0; }
        details.profile summary { cursor: pointer; color: var(--hint); font-size: 14px; list-style: none; }
        details.profile summary::after { content: " ▾"; }
        details.profile[open] summary::after { content: " ▴"; }

        /* Заставка блока: тонкая полоска до автоперехода к заданию (длительность — из JS). */
        .countdown { flex: 1; height: 4px; border-radius: 2px; background: var(--line); overflow: hidden; margin: 8px 0; }
        .countdown i { display: block; height: 100%; width: 100%; background: var(--accent); transform-origin: left;
            animation: countdown linear both; }
        @keyframes countdown { from { transform: scaleX(0); } to { transform: scaleX(1); } }

        /* «Заново» в шапке и под итогом — неброской ссылкой. */
        .top-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .link { border: 0; background: none; padding: 6px 0; color: var(--hint); font-size: 13px; cursor: pointer; }
        .link:active { color: var(--accent); }
        .center-link { text-align: center; margin: 4px 0 8px; }

        /* Персонажи: реплики, пост, ответ «на листке». */
        .say .avatar { width: 64px; height: 64px; font-size: 26px; }
        .speech-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px; }
        /* place-items сбросить: свежий Chrome применяет justify-items и к блочной вёрстке. */
        .swipe-card.chat { display: block; place-items: normal; text-align: left; font-weight: 500; }
        .swipe-card.chat .speech-head { font-weight: 600; }
        .q > .speaker { margin-bottom: 14px; }
        /* Ответ персонажа и пост — цитатой: полоска его цвета слева, без подложки. */
        .options.sheet { gap: 0; padding-left: 12px; border-left: 3px solid var(--quote, var(--accent)); }
        .options.sheet .opt { background: none; border: 0; border-bottom: 1px solid var(--line); border-radius: 0; padding: 12px 4px; }
        .options.sheet .opt:last-child { border-bottom: 0; }
        .options.sheet .opt.sel { background: var(--bad-bg); }
        .options.sheet .opt.ok { background: var(--ok-bg); }
        .options.sheet .opt.bad { background: var(--bad-bg); }
        .options.sheet .opt.missed { background: none; box-shadow: inset 3px 0 0 var(--ok); }
        .avatar.emoji { font-size: 26px; }
        .say .avatar.emoji { font-size: 36px; }
        .tag.case { display: flex; flex-direction: column; gap: 2px; }
        .tag.case b { font-size: 12px; color: var(--hint); font-weight: 600; }

        /* «Найди и исправь»: все спорные слова подчёркнуты одинаково. */
        .post { padding: 2px 0 2px 12px; border-left: 3px solid var(--quote, var(--accent)); }
        .post-text { line-height: 1.9; }
        .spot { border-bottom: 2px dotted var(--accent); cursor: pointer; padding: 0 1px; border-radius: 4px 4px 0 0; }
        .spot.sel { background: color-mix(in srgb, var(--accent) 15%, transparent); }
        .spot.changed { border-bottom-style: solid; font-weight: 600; }
        .spot.ok { background: var(--ok-bg); border-bottom: 2px solid var(--ok); }
        .spot.bad { background: var(--bad-bg); border-bottom: 2px solid var(--bad); }
        .spot .fix { color: var(--ok); font-size: 13px; }
        .picker { margin-top: 10px; animation: rise .2s ease-out both; }
        .picker[hidden] { display: none; }

        /* Кому движение мешает (настройка системы) — всё сразу, без анимаций. */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .confetti { display: none; }
        }
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
        // Рыба по настроению — пока везде одна заглушка; придёт арт —
        // заменить пути в DiagnosticController::page(), код трогать не нужно.
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
