{{-- resources/views/student/dashboard.blade.php --}}
@extends('layouts.main') {{-- замени на свой layout, если другой --}}

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    @if(session('success'))
        <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Ширина колонок — обычным <style>, не Tailwind-классом с квадратными
         скобками (md:grid-cols-[...]): в этом браузере часть таких классов
         ненадёжно применяется, уже несколько раз ловили на хедере и нижнем
         меню. Так 2-я и 3-я карточки гарантированно получают одинаковую
         ширину (1fr каждая). --}}
    <style>
        #dashboard-cards-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 768px) {
            #dashboard-cards-grid {
                /* Карточка 2 (события) шире, чем раньше (1fr → 1.3fr), чтобы
                   заголовки того же text-base, что и в расписании, не
                   обрезались лишний раз — место забрано у карточки 3
                   (пока пустая), а не у маскота, чей размер завязан на
                   квадратное изображение рыбы. */
                grid-template-columns: 1.7fr 1.3fr 0.7fr;
            }
        }

        /* Клик по «Покормить»: волна из точки клика на самой кнопке, и
           крошки корма, падающие сверху на маскота (спавнятся и анимируются
           через GSAP в скрипте ниже, как и конфетти на странице результата
           домашки — student/submissions/show.blade.php). #fish-card (вместе
           с кнопкой) целиком пересоздаётся при каждом htmx-свапе, поэтому
           обработчик клика навешан через делегирование на document, а не на
           саму кнопку. */
        #greeting-mascot {
            position: relative;
            overflow: hidden;
        }
        .fish-feed-btn {
            position: relative;
            overflow: hidden;
        }
        .fish-feed-ripple {
            position: absolute;
            border-radius: 9999px;
            background: rgba(255, 255, 255, .55);
            transform: scale(0);
            pointer-events: none;
            animation: fish-ripple-wave .6s ease-out forwards;
        }
        @keyframes fish-ripple-wave {
            to {
                transform: scale(2.6);
                opacity: 0;
            }
        }

        /* Баннер левел-апа — прямо в рамке маскота (#greeting-mascot), не
           по центру экрана: фокус внимания должен оставаться на персонаже,
           не уходить в сторону общего toast-root. */
        .fish-levelup-banner {
            position: absolute;
            left: 50%;
            bottom: 10px;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1px;
            padding: 6px 14px;
            border-radius: 10px;
            background: rgba(17, 24, 39, .82);
            color: #fff;
            text-align: center;
            pointer-events: none;
            z-index: 10;
            white-space: nowrap;
        }
        .fish-levelup-banner-title {
            font-size: 13px;
            font-weight: 600;
            color: #fbbf24;
        }

        /* Мобильная версия карточек 1/2 — обычным media-query, не Tailwind-
           префиксами (sm:/md:): в этом браузере часть таких классов
           ненадёжно применяется, уже несколько раз ловили на других
           страницах. На узких экранах маскот и блок кормления в карточке 1
           не помещаются в один ряд по половине ширины каждый (кнопке
           "Покормить" и прогресс-бару тесно) — на мобильном они идут друг
           под другом на всю ширину карточки. */
        @media (max-width: 480px) {
            .dashboard-card {
                padding: 1rem;
            }
            .dashboard-mascot-row {
                flex-direction: column;
                align-items: stretch;
                gap: 1.5rem;
            }
            .dashboard-mascot-row #greeting-mascot {
                width: 100%;
            }
        }
    </style>
    <div id="dashboard-cards-grid">
        {{-- Карточка 1: маскот-рыба (пошире остальных) — интерфейс кормления
             расположен так же, как раньше в отдельной карточке 3 (см.
             partials/fish-card.blade.php: тот же flex-1/flex-col/mt-auto). --}}
        <x-ui.card class="dashboard-card dashboard-mascot-row flex md:gap-6 gap-3">
            <div id="greeting-mascot" class="w-1/2 aspect-square shrink-0 rounded-xl bg-gray-100 flex items-center justify-center {{ $fishBalance > 0 ? 'cursor-pointer' : '' }}" style="background-image: url('{{ $fishBackgroundImage }}'); background-size: cover; background-position: center;" title="Покормить">
                <img id="fish-mascot-img"
                     src="{{ $fishMascotImage }}"
                     data-default-src="{{ $fishMascotImage }}"
                     data-eating-src="{{ $fishMascotEatingImage }}"
                     alt="" class="w-full h-full object-contain">
            </div>
            @include('student.partials.fish-card')
        </x-ui.card>

        {{-- Карточка 2: ближайшие события — урок и домашка в одном общем
             дизайне (см. partials/student-dashboard-event-card.blade.php).
             min-w-0 на каждом уровне — иначе длинный заголовок без пробелов
             может распереть колонку грида шире контейнера (truncate внутри
             partial-а тут бессилен, если родитель по цепочке не даёт сжаться). --}}
        <x-ui.card class="dashboard-card flex flex-col min-w-0">
            <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-2">Ближайшие события</div>
            <div class="flex-1 flex flex-col gap-3 min-w-0">
                @php
                    $nlLesson = $nextLesson['lesson'] ?? null;
                    $nlHref = $nlLesson && Route::has('student.lessons.show') ? route('student.lessons.show', $nlLesson) : null;
                @endphp
                <div class="flex-1 min-w-0">
                    @include('partials.student-dashboard-event-card', [
                        'item' => $nextLesson,
                        'type' => $nextLesson['type'] ?? null,
                        'color' => $nextLesson['color'] ?? null,
                        'title' => $nextLesson['title'] ?? null,
                        'subject' => $nextLesson['subject'] ?? 'Курс',
                        'dateLabel' => ($nextLesson['date'] ?? '') . ' · ' . ($nextLesson['time'] ?? ''),
                        'href' => $nlHref,
                        'emptyText' => 'Уроков пока не запланировано',
                    ])
                </div>

                @php
                    $nhHomework = $nextHomework['homework'] ?? null;
                    $nhHref = $nhHomework && Route::has('student.submissions.create') ? route('student.submissions.create', $nhHomework) : null;
                @endphp
                <div class="flex-1 min-w-0">
                    @include('partials.student-dashboard-event-card', [
                        'item' => $nextHomework,
                        'type' => $nextHomework['type'] ?? null,
                        'color' => $nextHomework['color'] ?? null,
                        'title' => $nextHomework['title'] ?? null,
                        'subject' => $nextHomework['subject'] ?? 'Курс',
                        'dateLabel' => ($nextHomework['date'] ?? '') . ' · ' . ($nextHomework['time'] ?? ''),
                        'href' => $nhHref,
                        'emptyText' => 'Домашек в очереди нет 🎉',
                    ])
                </div>
            </div>
        </x-ui.card>

        {{-- Карточка 3: пока пустая --}}
        <x-ui.card class="dashboard-card min-w-0"></x-ui.card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.gsap === 'undefined') return;
            var mascot = document.getElementById('greeting-mascot');
            if (!mascot) return;
            gsap.fromTo(mascot,
                { scale: 0.5, rotate: -8, autoAlpha: 0 },
                { scale: 1, rotate: 0, autoAlpha: 1, duration: 0.5, ease: 'elastic.out(1, .5)' }
            );
        });

        // Таймер возврата картинки маскота с "eating" на "default" после
        // кормления — общий для всех кликов (не локальная переменная внутри
        // обработчика), чтобы серия быстрых кликов продлевала его, а не
        // плодила гонки между несколькими независимыми таймерами.
        var fishRevertTimer = null;

        // src, который был показан прямо перед последним кормлением — нужен
        // для перехода при левел-апе (см. обработчик 'fish-level-up' ниже):
        // к моменту, когда он срабатывает, #fish-mascot-img уже пересоздан
        // OOB-свапом с картинкой НОВОГО уровня (OOB применяется раньше, чем
        // htmx триггерит событие), так что "старую" картинку неоткуда взять
        // из DOM — только из этой переменной, захваченной заранее в клике.
        var fishPreLevelUpSrc = null;

        // Клик по «Покормить»: волна на кнопке + корм, падающий на маскота +
        // сам маскот на время анимации переключается на позу "eating".
        // #fish-card (вместе с кнопкой) целиком пересоздаётся htmx-свапом на
        // каждое кормление — обработчик навешан делегированием на document,
        // а не на саму кнопку, иначе он бы слетал после первого же клика.
        document.addEventListener('click', function (evt) {
            // Клик по самому маскоту — тот же результат, что и клик по
            // кнопке «Покормить»: находим АКТУАЛЬНУЮ кнопку (она
            // пересоздаётся при каждом htmx-свапе #fish-card) и эмулируем
            // клик по ней вместо того, чтобы дублировать hx-post и
            // disabled-логику ещё и на маскоте — так источник истины
            // остаётся один. Синтетический клик снова дойдёт до этого же
            // обработчика (target уже будет кнопкой), без рекурсии.
            if (evt.target.closest('#greeting-mascot')) {
                var mascotBtn = document.querySelector('.fish-feed-btn');
                if (mascotBtn && !mascotBtn.disabled) {
                    mascotBtn.click();
                }
                return;
            }

            var btn = evt.target.closest('.fish-feed-btn');
            if (!btn || btn.disabled) return;

            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'fish-feed-ripple';
            ripple.style.width = size + 'px';
            ripple.style.height = size + 'px';
            ripple.style.left = (evt.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (evt.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            ripple.addEventListener('animationend', function () { ripple.remove(); });

            // Поза "ест" на время падения крошек. Ищем элемент заново (не
            // храним ссылку между кликами) — на левел-апе #fish-mascot-img
            // целиком пересоздаётся OOB-свапом (см. FishController::feed()),
            // старая ссылка на узел стала бы "мёртвой". По той же причине
            // таймер возврата ниже тоже ищет узел заново в момент срабатывания,
            // а не захватывает его в замыкании.
            var feedImg = document.getElementById('fish-mascot-img');
            if (feedImg && feedImg.dataset.eatingSrc) {
                fishPreLevelUpSrc = feedImg.dataset.eatingSrc;
                feedImg.src = fishPreLevelUpSrc;
            }
            clearTimeout(fishRevertTimer);
            fishRevertTimer = setTimeout(function () {
                var img = document.getElementById('fish-mascot-img');
                if (img && img.dataset.defaultSrc) {
                    img.src = img.dataset.defaultSrc;
                }
            }, 2400); // чуть дольше максимальной длительности анимации крошек ниже

            var mascot = document.getElementById('greeting-mascot');
            if (!mascot || typeof window.gsap === 'undefined') return;

            // Крошки корма, сыплющиеся на маскота — та же техника, что и у
            // конфетти на странице результата домашки (много мелких div-ов,
            // каждый со своим GSAP-твином и случайными размером/цветом/
            // траекторией), только контейнер — сам #greeting-mascot
            // (position:relative; overflow:hidden), а не весь экран.
            var crumbColors = ['#D97706', '#B45309', '#F59E0B', '#92400E'];
            var crumbCount = 22;
            var w = mascot.clientWidth;
            var h = mascot.clientHeight;

            for (var i = 0; i < crumbCount; i++) {
                var crumb = document.createElement('div');
                var size = 3 + Math.random() * 4;
                var color = crumbColors[Math.floor(Math.random() * crumbColors.length)];
                var startX = Math.random() * w;
                crumb.style.cssText = 'position:absolute; top:-8px; left:' + startX + 'px; width:' + size + 'px; height:' + size + 'px; background:' + color + '; border-radius:' + (Math.random() > .5 ? '50%' : '2px') + '; pointer-events:none; z-index:5; opacity:0;';
                mascot.appendChild(crumb);

                (function (el) {
                    var driftX = (Math.random() - 0.5) * 24;
                    var rotation = (Math.random() - 0.5) * 360;
                    var fallTo = h * (0.5 + Math.random() * 0.4);
                    var duration = 1.1 + Math.random() * 0.6;
                    var delay = Math.random() * 0.4;

                    gsap.to(el, {
                        y: fallTo,
                        x: driftX,
                        rotation: rotation,
                        opacity: 1,
                        duration: duration,
                        delay: delay,
                        ease: 'power1.in',
                        onComplete: function () {
                            gsap.to(el, { opacity: 0, duration: .3, onComplete: function () { el.remove(); } });
                        },
                    });
                })(crumb);
            }
        });

        // Левел-ап: сервер шлёт отдельное htmx-событие 'fish-level-up' (не
        // общий 'toast' из main.blade.php — тот всплывает по центру экрана,
        // а внимание должно оставаться на самом маскоте). Картинки нового
        // уровня приходят в детали события (evt.detail), а не через OOB-свап
        // DOM-узла: OOB подменяет узел мгновенно и никак не согласован по
        // времени с запуском этого обработчика — картинка успевала смениться
        // раньше, чем стартовала анимация, и появление выглядело мгновенным.
        // Теперь узел #fish-mascot-img один и тот же всё время, а src меняет
        // сам GSAP-таймлайн ниже — ровно в момент, когда картинка уже скрыта
        // (scale:0), так что подмена невидима и её "проявляет" анимация роста.
        document.body.addEventListener('fish-level-up', function (evt) {
            var mascot = document.getElementById('greeting-mascot');
            var img = document.getElementById('fish-mascot-img');
            if (!mascot || !img) return;

            var detail = evt.detail || {};

            function applyNewImageSources() {
                if (detail.defaultSrc) {
                    img.dataset.defaultSrc = detail.defaultSrc;
                    img.src = detail.defaultSrc;
                }
                if (detail.eatingSrc) {
                    img.dataset.eatingSrc = detail.eatingSrc;
                }
            }

            // Баннер — в той же рамке, что и маскот, не по центру экрана.
            var banner = document.createElement('div');
            banner.className = 'fish-levelup-banner';
            banner.innerHTML = '<span class="fish-levelup-banner-title">Новый уровень!</span>';
            mascot.appendChild(banner);

            if (typeof window.gsap === 'undefined') {
                applyNewImageSources();
                setTimeout(function () { banner.remove(); }, 2600);
                return;
            }

            gsap.fromTo(banner,
                { y: 10, autoAlpha: 0, scale: .85 },
                { y: 0, autoAlpha: 1, scale: 1, duration: .4, delay: .55, ease: 'back.out(2)' }
            );
            gsap.to(banner, { autoAlpha: 0, y: -6, duration: .35, delay: 2.6, onComplete: function () { banner.remove(); } });

            // Вспышка на весь блок маскота.
            var flash = document.createElement('div');
            flash.style.cssText = 'position:absolute; inset:0; background:radial-gradient(circle, rgba(255,255,255,.95), rgba(255,255,255,0) 72%); pointer-events:none; z-index:9; opacity:0;';
            mascot.appendChild(flash);

            // Смена картинки — не мгновенная подмена, а сжатие старой в точку
            // и распускание новой из точки на том же месте. "Призрак" старой
            // позы — отдельный временный <img> поверх настоящего, со src,
            // захваченным в момент клика (fishPreLevelUpSrc); сам img сперва
            // прячем (scale:0) и проявляем уже в таймлайне, подставив ему
            // новый src через tl.call() ровно пока он невидим.
            gsap.set(img, { scale: 0, autoAlpha: 0 });

            var ghost = null;
            if (fishPreLevelUpSrc) {
                ghost = document.createElement('img');
                ghost.src = fishPreLevelUpSrc;
                ghost.alt = '';
                ghost.className = 'w-full h-full object-contain';
                ghost.style.cssText = 'position:absolute; inset:0; z-index:6; pointer-events:none;';
                mascot.appendChild(ghost);
            }

            var tl = gsap.timeline({ onComplete: function () { flash.remove(); } });

            tl.to(flash, { opacity: 1, duration: .18 });
            if (ghost) {
                tl.to(ghost, {
                    scale: 0, rotation: -14, duration: .4, ease: 'power2.in',
                    onComplete: function () { ghost.remove(); },
                }, '<');
            }
            tl.call(applyNewImageSources)
              .to(flash, { opacity: 0, duration: .3 })
              .to(img, { scale: 1, autoAlpha: 1, duration: .6, ease: 'elastic.out(1, .5)' }, '<')
              .to(img, { scale: 1.08, duration: .16, ease: 'sine.inOut', yoyo: true, repeat: 3 }, '-=0.15');

            // Расходящиеся кольца.
            for (var i = 0; i < 3; i++) {
                (function (i) {
                    var ring = document.createElement('div');
                    ring.style.cssText = 'position:absolute; left:50%; top:50%; width:30%; height:30%; margin-left:-15%; margin-top:-15%; border-radius:50%; border:3px solid #f59e0b; pointer-events:none; z-index:7; opacity:0;';
                    mascot.appendChild(ring);
                    gsap.fromTo(ring,
                        { scale: .4, opacity: .9 },
                        {
                            scale: 2.8, opacity: 0, duration: 1.1, delay: .5 + i * .18, ease: 'power2.out',
                            onComplete: function () { ring.remove(); },
                        }
                    );
                })(i);
            }

            // Золотые искры, разлетающиеся из центра.
            var sparkleColors = ['#fbbf24', '#f59e0b', '#fde68a', '#ffffff'];
            var w = mascot.clientWidth;
            var h = mascot.clientHeight;

            for (var s = 0; s < 26; s++) {
                var sparkle = document.createElement('div');
                var size = 3 + Math.random() * 5;
                var color = sparkleColors[Math.floor(Math.random() * sparkleColors.length)];
                sparkle.style.cssText = 'position:absolute; left:50%; top:50%; width:' + size + 'px; height:' + size + 'px; background:' + color + '; border-radius:50%; pointer-events:none; z-index:8; opacity:0; box-shadow:0 0 4px ' + color + ';';
                mascot.appendChild(sparkle);

                (function (el) {
                    var angle = Math.random() * Math.PI * 2;
                    var distance = (Math.min(w, h) / 2) * (0.6 + Math.random() * 0.6);
                    var dx = Math.cos(angle) * distance;
                    var dy = Math.sin(angle) * distance;
                    var delay = .45 + Math.random() * .3;

                    gsap.fromTo(el,
                        { x: 0, y: 0, opacity: 1, scale: 0 },
                        {
                            x: dx, y: dy, scale: 1, duration: .5 + Math.random() * .3, delay: delay, ease: 'power2.out',
                            onComplete: function () {
                                gsap.to(el, { opacity: 0, duration: .3, onComplete: function () { el.remove(); } });
                            },
                        }
                    );
                })(sparkle);
            }
        });
    </script>

    {{-- <div class="w-full py-3 sm:py-6">
    <div class="max-w-6xl mx-auto bg-white rounded-xl border px-2 sm:px-4 py-4 sm:py-6">
        <div class="flex justify-between items-end mb-4 border-b border-gray-200 pb-2">
            <h2 class="text-xl md:text-2xl lg:text-3xl tracking-wide font-medium font-oktyabrina text-zinc-800"><img class="inline-block relative bottom-1 mr-1" src="{{ asset('img/Date_range.svg') }}" alt=""> Расписание уроков</h2>
            <div class="flex gap-3">
                <button id="swiper-prev" class="text-2xl text-gray-500 hover:text-gray-700 disabled:text-gray-300" disabled>&larr;</button>
                <button id="swiper-next" class="text-2xl text-gray-500 hover:text-gray-700">&rarr;</button>
            </div>
        </div>

        <div class="swiper mySwiper pt-8">
            <div class="swiper-wrapper">
                @php
                    $days = [
                        ['day' => 'Пн', 'date' => '17 июня', 'highlight' => false, 'items' => [
                            ['type' => 'Вебинар', 'subject' => 'История', 'title' => 'Разбор заданий', 'time' => '15:00', 'color' => 'blue', 'status' => 'past'],
                            ['type' => 'Домашка (выполнена)', 'subject' => 'Математика', 'title' => 'Тригонометрия', 'time' => 'до 23:59', 'color' => 'yellow', 'status' => 'completed']
                        ]],
                        ['day' => 'Вт', 'date' => '18 июня', 'highlight' => false, 'items' => [
                            ['type' => 'Домашка (просрочена)', 'subject' => 'Русский', 'title' => 'Словообразование', 'time' => 'до 22:00', 'color' => 'red', 'status' => 'overdue']
                        ]],
                        ['day' => 'Ср', 'date' => '19 июня', 'highlight' => true, 'items' => [
                            ['type' => 'Вебинар', 'subject' => 'Физика', 'title' => 'Законы Ньютона', 'time' => '18:00', 'color' => 'blue'],
                            ['type' => 'Пробник', 'subject' => 'Обществознание', 'title' => 'Анализ графиков', 'time' => '19:00', 'color' => 'orange']
                        ]],
                        ['day' => 'Чт', 'date' => '20 июня', 'highlight' => false, 'items' => []],
                        ['day' => 'Пт', 'date' => '21 июня', 'highlight' => false, 'items' => [
                            ['type' => 'Домашка', 'subject' => 'Английский', 'title' => 'Эссе', 'time' => 'до 21:00', 'color' => 'yellow']
                        ]]
                    ];
                @endphp

                @foreach ($days as $day)
                    <div class="swiper-slide">
                        <div class="flex flex-col gap-4 w-full pr-2">
                            <div class="text-center font-medium text-sm text-gray-700">
                                <div class="block sm:hidden">
                                    <span class="{{ $day['highlight'] ? 'text-indigo-600 font-semibold' : 'text-gray-700' }}">{{ $day['day'] }}</span>
                                    <span class="text-gray-400"> · {{ $day['date'] }}</span>
                                </div>
                                <div class="hidden sm:block">
                                    <div class="{{ $day['highlight'] ? 'text-indigo-600 font-semibold' : 'text-gray-700' }}">{{ $day['day'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $day['date'] }}</div>
                                </div>
                            </div>

                            @if (empty($day['items']))
                                <div class="border border-dashed border-gray-300 rounded-xl px-3 py-4 text-left text-gray-600 space-y-2">
                                    <div class="flex items-center text-xs">
                                        <span class="icon mr-2">🌿</span>
                                        <span class="tracking-wide text-gray-500">Выходной</span>
                                    </div>
                                    <div class="text-base text-gray-500">
                                        Нет запланированных занятий. Можно отдохнуть 🌤
                                    </div>
                                </div>
                            @else
                                @foreach ($day['items'] as $item)
                                    @php
                                        $status = $item['status'] ?? null;
                                        $opacity = $status === 'completed' ? 'opacity-50' : '';
                                        $border = 'border-' . $item['color'] . '-200';
                                        $bg = 'bg-' . $item['color'] . '-100';
                                        $text = 'text-' . $item['color'] . '-800';
                                    @endphp
                                    <div class="{{ $bg }} border {{ $border }} rounded-xl px-3 py-3 text-left space-y-2 {{ $opacity }}">
                                        <div class="flex items-center text-xs {{ $text }}">
                                            <span class="icon mr-1">🔔</span>
                                            <span>{{ $item['type'] }}</span>
                                            <span class="ml-auto text-gray-400">{{ $item['time'] }}</span>
                                        </div>
                                        <div class="font-medium text-base text-gray-800 leading-snug">{{ $item['title'] }}</div>
                                        <div>
                                            <span class="inline-block bg-white text-gray-700 text-xs px-2 py-0.5 rounded-md shadow-sm">{{ $item['subject'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div> --}}

    <div class="w-full md:mb-16 mb-12">
    <x-ui.card class="max-w-6xl mx-auto">
        <div class="flex justify-between items-end mb-4 border-b border-gray-200 pb-2 px-1">
            <h2 class="sans-medium text-xl md:text-2xl tracking-wide text-zinc-900"><img class="inline-block relative bottom-1 mr-1" src="{{ asset('img/Date_range.svg') }}" alt=""> Расписание уроков</h2>
            <div class="flex gap-2">
                <button id="swiper-prev" class="w-9 h-9 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white" disabled aria-label="Предыдущие дни">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M15 18l-6-6 6-6"></path></svg>
                </button>
                <button id="swiper-next" class="w-9 h-9 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white" aria-label="Следующие дни">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M9 18l6-6-6-6"></path></svg>
                </button>
            </div>
        </div>

        <div class="swiper mySwiper pt-8">
            <div class="swiper-wrapper">


                @foreach ($days as $day)
  <div class="swiper-slide" data-highlight="{{ !empty($day['highlight']) ? 1 : 0 }}">
    <div class="flex flex-col gap-4 w-full pr-2">
      <div class="text-center font-medium text-sm text-zinc-700">
        <div class="block sm:hidden capitalize">

          <span class="uppercase {{ $day['highlight'] ? 'text-apple-indigo-600 font-medium ' : ' text-zinc-700' }}">
            {{ $day['day'] }}
          </span>
          <span class="text-zinc-400 "> · {{ $day['date'] }}</span>
        </div>
        <div class="hidden sm:block">
          <div class="uppercase font-medium {{ $day['highlight'] ? 'text-apple-indigo-600' : 'text-zinc-700' }}">{{ $day['day'] }}</div>
          <div class="capitalize font-normal text-xs text-zinc-400">{{ $day['date'] }}</div>
        </div>
      </div>

      @if (empty($day['items']))
        <div class="border border-dashed border-gray-300 rounded-2xl px-3 py-4 text-left text-zinc-600 space-y-2">
          <div class="flex items-center gap-2 text-xs">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-zinc-400 shrink-0"><circle cx="12" cy="12" r="4"></circle><path d="M12 3v2M12 19v2M5 5l1.4 1.4M17.6 17.6L19 19M3 12h2M19 12h2M5 19l1.4-1.4M17.6 6.4L19 5"></path></svg>
            <span class="tracking-wide text-zinc-500">Выходной</span>
          </div>
          <div class="text-base text-zinc-500">Сегодня нет занятий и домашек. Можно отдохнуть</div>
        </div>
      @else
        @foreach ($day['items'] as $item)
          @php
            $status  = $item['status'] ?? null; // 'completed' | 'overdue' | null

            // Статические классы Tailwind (чтобы их не выпилил purge).
            // Ключи — семантические роли, не названия цветов: у "просрочено"
            // и "выполнено" приоритет над цветом типа (задаётся в
            // DashboardController) и перекрашивает карточку целиком, а не
            // просто гасит её прозрачностью поверх исходного цвета типа.
            $color     = $item['color'] ?? 'theory';
            $bgMap     = [
            'theory'    => 'bg-apple-blue-50',
            'practice'  => 'bg-apple-purple-50',
            'homework'  => 'bg-apple-orange-100',
            'mock'      => 'bg-apple-indigo-50',
            'overdue'   => 'bg-apple-red-50',
            'completed' => 'bg-apple-green-100',
            ];
            // У "выполнено"/"просрочено" рамка заметно темнее, чем у цветов
            // типа (-200) — это статус, а не просто категория, и он должен
            // читаться как обводка, а не как чуть более тёмный край.
            // apple-orange-300 у домашки — чуть контрастнее стандартной -200.
            // apple-red-450/650 у просрочено — приглушённые (saturation
            // ниже, не просто светлее/темнее) варианты специально под этот
            // статус: обычные -400/-700 слишком "неоновые" для красной рамки
            // и текста на видном месте (см. tailwind.config.js).
            $borderMap = [
            'theory'    => 'border-apple-blue-200',
            'practice'  => 'border-apple-purple-200',
            'homework'  => 'border-apple-orange-300',
            'mock'      => 'border-apple-indigo-200',
            'overdue'   => 'border-apple-red-450',
            'completed' => 'border-apple-green-400',
            ];
            $textMap   = [
            'theory'    => 'text-apple-blue-700',
            'practice'  => 'text-apple-purple-700',
            'homework'  => 'text-apple-orange-700',
            'mock'      => 'text-apple-indigo-700',
            'overdue'   => 'text-apple-red-650',
            'completed' => 'text-apple-green-700',
            ];

            $bg     = $bgMap[$color]     ?? $bgMap['theory'];
            $border = $borderMap[$color] ?? $borderMap['theory'];
            $text   = $textMap[$color]   ?? $textMap['theory'];

            // ссылки (если контроллер положил объекты)
            $lesson   = $item['lesson']   ?? null;
            $homework = $item['homework'] ?? null;
            $title    = $item['title'] ?? '—';
          @endphp

          <div class="{{ $bg }} border {{ $border }} rounded-2xl px-3 py-3 text-left space-y-2">
             <div class="flex items-center text-xs {{ $text }}">
               <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 mr-1 shrink-0"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 01-3.46 0"></path></svg>
               <span>{{ $item['type'] }}</span>
               @if($status === 'overdue')
                 <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white text-apple-red-650">Просрочена</span>
               @elseif($status === 'completed')
                 <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white text-apple-green-700">Выполнена</span>
               @endif
               <span class="ml-auto text-zinc-400">{{ $item['time'] }}</span>
             </div>

            {{-- Заголовок: если это урок — линк на урок; если домашка — линк на форму/результат --}}
            <div class="font-medium text-base text-zinc-800 leading-snug">
              @if($lesson && Route::has('student.lessons.show'))
                <a href="{{ route('student.lessons.show', $lesson) }}" class="hover:underline">{{ $title }}</a>
              @elseif($homework && Route::has('student.submissions.create'))
                <a href="{{ route('student.submissions.create', $homework) }}" class="hover:underline">{{ $title }}</a>
              @else
                {{ $title }}
              @endif
            </div>

            <div>
              <span class="inline-block bg-white text-zinc-700 text-xs px-2 pt-0.5 pb-1 rounded-full">
                {{ $item['subject'] ?? 'Курс' }}
              </span>
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </div>
@endforeach
            </div>
        </div>
    </x-ui.card>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>

<script>

   // NEW: найдём индекс опорного дня (highlight)
   const slidesInDom = document.querySelectorAll('.mySwiper .swiper-slide');
   const initialIndex = Array.from(slidesInDom).findIndex(
     el => el.dataset.highlight === '1'
   );

    const swiper = new Swiper(".mySwiper", {
        slidesPerView: 1.15,
        centeredSlides: false,
        spaceBetween: 10,
        breakpoints: {
            640: { slidesPerView: 2.1 },
            768: { slidesPerView: 3 },
            1024: { slidesPerView: 4 },
        },
        navigation: false,
        initialSlide: initialIndex > -1 ? initialIndex : 0,
    });

    const prevBtn = document.getElementById('swiper-prev');
    const nextBtn = document.getElementById('swiper-next');

    function updateButtons() {
        prevBtn.disabled = swiper.isBeginning;
        nextBtn.disabled = swiper.isEnd;
        prevBtn.classList.toggle('text-zinc-300', swiper.isBeginning);
        nextBtn.classList.toggle('text-zinc-300', swiper.isEnd);
    }

    swiper.on('slideChange', updateButtons);

    prevBtn.addEventListener('click', () => swiper.slidePrev());
    nextBtn.addEventListener('click', () => swiper.slideNext());

    updateButtons();
</script>

    <h2 class="sans-medium text-xl md:text-2xl tracking-wide md:mb-4 mb-3 mt-4 text-zinc-900">Мои курсы</h2>

    @if($courses->isEmpty())
        <x-ui.card class="text-zinc-600">
            Пока нет активных курсов. Если у вас есть промокод — активируйте его на странице
            <a href="{{ route('promo.redeem.form') }}" class="text-blue-600 underline">Активация промокода</a>.
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
            @foreach($courses as $course)
                @php
    $expiresAtRaw = data_get($course, 'pivot.expires_at'); // безопасно достаём поле
    $expiresAt    = $expiresAtRaw ? \Illuminate\Support\Carbon::parse($expiresAtRaw) : null;
    $expiresSoon  = $expiresAt?->isAfter(now()) && $expiresAt?->diffInDays(now()) <= 3;
    $next         = $course->nextSession;
    $isBlocked    = in_array($course->id, $blockedCourseIds ?? [], true);
                @endphp

                <x-ui.card class="flex flex-col min-w-0">
                    {{-- обложка, если есть --}}
                    @if(!empty($course->main_image))
                        <img src="{{ asset('storage/'.$course->main_image) }}" alt="{{ $course->title }}"
                             class="w-full object-cover rounded-xl mb-3 {{ $isBlocked ? 'grayscale' : '' }}">
                    @endif

                    <div class="flex items-start justify-between gap-2 mb-1 min-w-0">
                        <h3 class="sans-medium text-lg text-zinc-900 truncate min-w-0">{{ $course->title }}</h3>
                        @if($isBlocked)
                            <span class="shrink-0 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700">
                                Доступ приостановлен
                            </span>
                        @endif
                    </div>
                    <p class="text-base text-zinc-600 line-clamp-2 md:mb-8 mb-6">{{ $course->description }}</p>
                    <div class="mt-auto flex gap-2">
                        @if($isBlocked && Route::has('billing.overdue'))
                            <x-ui.button href="{{ route('billing.overdue', $course) }}" variant="danger" block>
                                Оплатить, чтобы продолжить
                            </x-ui.button>
                        @elseif(Route::has('student.courses.show'))
                            <x-ui.button href="{{ route('student.courses.show', $course) }}" block>
                                Перейти к курсу
                            </x-ui.button>
                        @endif

                        @if(isset($expiresSoon) && $expiresSoon && Route::has('checkout.course.show'))
                            <a href="{{ route('checkout.course.show', $course) }}"
                               class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                Продлить доступ
                            </a>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
    <div class="flex justify-between md:mt-12 mt-8 items-center border-t pt-4">
    <div class="font-oktyabrina md:text-2xl text-xl">Школа Полтавского</div>
                        <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600">Выйти</button>
                    </form>
    </div>

</div>
@endsection
