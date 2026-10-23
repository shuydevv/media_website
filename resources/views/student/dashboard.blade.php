{{-- resources/views/student/dashboard.blade.php --}}
@extends('layouts.main') {{-- замени на свой layout, если другой --}}

@section('title', 'Личный кабинет — Школа Полтавского')

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
        /* Две колонки — только с 1030px: уже карточке с рыбой (3/5 строки)
           не хватает ширины на квадрат и две кнопки рядом, кнопки
           наезжали друг на друга. До 1030px карточки идут друг под другом. */
        @media (min-width: 1030px) {
            #dashboard-cards-grid {
                /* Явное соотношение 3:2 (маскот:события), не подобранное на
                   глаз число. Сам квадрат с рыбой при этом НЕ растёт (см.
                   #greeting-mascot ниже — фиксированная ширина, а не w-1/2
                   от карточки) — лишняя ширина карточки уходит в блок
                   уровня/прогресса/кнопки рядом с рыбой, а не в саму
                   картинку, иначе aspect-square тянул бы высоту карточки
                   вслед за шириной.
                */
                grid-template-columns: 3fr 2fr;
            }
        }

        /* Клик по «Покормить»: волна из точки клика на самой кнопке, и
           крошки корма, падающие сверху на маскота (спавнятся и анимируются
           через GSAP в скрипте ниже, как и конфетти на странице результата
           домашки — student/submissions/show.blade.php). #fish-card (вместе
           с кнопкой) целиком пересоздаётся при каждом htmx-свапе, поэтому
           обработчик клика навешан через делегирование на document, а не на
           саму кнопку. */
        /* .fish-stage — квадрат маскота фиксированной ширины (не w-1/2 от
           карточки — иначе aspect-square тянул бы высоту вслед за шириной).
           align-self: flex-start — иначе, когда блок справа выше квадрата,
           flex растягивает его по высоте и квадрат перестаёт быть квадратом. */
        .fish-stage {
            position: relative;
            width: 14rem;
            max-width: 100%;
            flex-shrink: 0;
            align-self: flex-start;
        }
        #greeting-mascot {
            position: relative;
            overflow: hidden;
        }
        .fish-feed-btn {
            position: relative;
            overflow: hidden;
        }
        /* Кнопка "Покормить" — variant="outline" (белый фон), поэтому белая
           рябь на ней была не волной, а невнятной вспышкой без контраста.
           Тёмная рябь малой непрозрачности — тот же классический паттерн
           material-ripple, читается на белом фоне как настоящая волна. */
        .fish-feed-ripple {
            position: absolute;
            border-radius: 9999px;
            background: rgb(var(--c-zinc-800, 39 39 42) / .12);
            transform: scale(0);
            pointer-events: none;
            animation: fish-ripple-wave .5s ease-out forwards;
        }
        @keyframes fish-ripple-wave {
            to {
                transform: scale(2.2);
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
            background: rgb(var(--c-gray-900, 17 24 39) / .82);
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

        /* Реплика рыбы — белое облачко как в мультике: слева от рыбы,
           поверх угла картинки и чуть за её краем, хвостик смотрит на рыбу.
           Место чередуется (выбирается случайно при заходе): .is-top —
           сверху слева, хвостик вниз-вправо; .is-bottom — снизу слева,
           хвостик вверх-вправо. drop-shadow, а не box-shadow, — чтобы тень была и
           у хвостика. Тихая реплика (дел в тренажёрах нет) — без стрелки и
           приглушённым текстом. */
        .fish-bubble {
            position: absolute;
            z-index: 5;
            right: 52%;
            width: max-content;
            max-width: 170px;
            border-radius: 16px;
            background: #fff;
            color: rgb(var(--c-zinc-900, 24 24 27));
            font-size: 13px;
            line-height: 1.35;
            font-weight: 500;
            filter: drop-shadow(0 4px 10px rgba(15, 23, 42, .22));
            transition: transform .15s ease;
        }
        .fish-bubble:hover {
            transform: scale(1.03);
        }
        .fish-bubble.is-top {
            top: -14px;
        }
        .fish-bubble.is-bottom {
            bottom: 6px;
        }
        .fish-bubble::after {
            content: "";
            position: absolute;
            right: 16px;
            border-left: 14px solid transparent;
        }
        .fish-bubble.is-top::after {
            bottom: -11px;
            border-top: 12px solid #fff;
        }
        .fish-bubble.is-bottom::after {
            top: -11px;
            border-bottom: 12px solid #fff;
        }
        /* Слева не хватило места (облачко упёрлось бы в край экрана — так
           бывает на планшете, где карточка с рыбой у самого края): облачко
           встаёт внизу внутри картинки, хвостиком вверх, к рыбе. Решает
           fishFitBubble() в разметке у .fish-stage. */
        .fish-bubble.is-fit {
            left: 8px;
            right: auto;
            max-width: calc(100% - 16px);
        }
        /* Внизу внутри картинки хвостик — по центру и симметричный: рыба
           прямо над облачком. */
        .fish-bubble.is-fit::after {
            right: auto;
            left: 50%;
            margin-left: -8px;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
        }

        /* Высоту строки сетки задаёт соседняя карточка «Ближайшие события»
           (настоящие урок и домашка выше пустых). Квадрат с рыбой растёт до
           этой высоты и остаётся квадратом, блок с кнопками — той же высоты.
           Как: карточка с рыбой — size-контейнер (container-type: size), её
           содержимое не влияет на высоту строки, а квадрат берёт размер от
           высоты карточки (100cqh). Без этого рост квадрата зацикливался:
           квадрат выше → строка выше → квадрат ещё выше. min-height держит
           карточку не ниже квадрата 14rem, когда соседняя карточка низкая.
           Потолок — 20rem и половина ширины карточки, чтобы блоку с
           кнопками хватало места. На телефоне (<600px) рыба над блоком. */
        @media (min-width: 600px) {
            .dashboard-mascot-row {
                container-type: size;
                min-height: 16.5rem;
                align-items: center;
            }
            .dashboard-mascot-row .fish-stage,
            .dashboard-mascot-row #fish-card {
                /* 100cqw - 20rem — блоку с кнопками остаётся не меньше
                   18rem (две кнопки рядом) плюс промежуток 2rem. */
                --fish-side: max(14rem, min(100cqh, calc(100cqw - 20rem), 20rem));
            }
            .dashboard-mascot-row .fish-stage {
                width: var(--fish-side);
                align-self: center;
            }
            .dashboard-mascot-row #fish-card {
                height: var(--fish-side);
                container: fish-column / inline-size;
            }
        }
        /* Две кнопки рядом: боковой отступ 8px. Класс px-2 на кнопке не
           срабатывает — его перебивает px-4 размера sm у x-ui.button. */
        .fish-buttons.grid > * {
            padding-left: .5rem;
            padding-right: .5rem;
        }
        /* Колонка всё равно узкая (квадрат не меньше 14rem) — текст кнопок
           на размер меньше, чтобы «Покормить» и «Тренажёры» не упирались в
           края. */
        @container fish-column (max-width: 300px) {
            .fish-buttons > * {
                font-size: .875rem;
            }
        }

        /* Планшет: между квадратом с рыбой и блоком с кнопками — пошире,
           иначе они слипаются (md:gap-6 включается только с 768px, а до
           1280px и там тесновато). */
        @media (min-width: 600px) and (max-width: 1279px) {
            .dashboard-mascot-row {
                column-gap: 2rem;
            }
        }
        .fish-bubble-link {
            text-align: left;
            display: block;
            padding: 8px 12px;
        }
        .fish-bubble.is-quiet {
            color: rgb(var(--c-zinc-600, 82 82 91));
        }
        /* Крестик — кружок на правом верхнем углу облачка. На ПК виден при
           наведении на облачко, на телефоне (наведения нет) — всегда. */
        .fish-bubble-close {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: #fff;
            color: rgb(var(--c-zinc-500, 113 113 122));
            box-shadow: 0 1px 4px rgba(15, 23, 42, .25);
            transition: opacity .15s ease, color .15s ease;
        }
        .fish-bubble-close:hover {
            color: rgb(var(--c-zinc-900, 24 24 27));
        }
        /* У нижнего облачка хвостик сверху справа — крестик уходит в
           нижний правый угол, чтобы не сливаться с хвостиком. */
        .fish-bubble.is-bottom .fish-bubble-close {
            top: auto;
            bottom: -8px;
        }
        /* На телефоне область нажатия крестика 44px (рекомендуемый минимум
           под палец) при том же видимом кружке: невидимая подложка лежит
           поверх угла облачка, и промах рядом с крестиком закрывает реплику,
           а не уводит в тренажёр. */
        @media (hover: none) {
            .fish-bubble-close::before {
                content: "";
                position: absolute;
                inset: -11px;
            }
        }
        @media (hover: hover) {
            .fish-bubble-close {
                opacity: 0;
            }
            .fish-bubble:hover .fish-bubble-close,
            .fish-bubble-close:focus-visible {
                opacity: 1;
            }
        }
        .fish-bubble-arrow {
            vertical-align: -3px;
            color: #2563eb;
        }

        /* Мобильная версия карточек 1/2 — обычным media-query, не Tailwind-
           префиксами (sm:/md:): в этом браузере часть таких классов
           ненадёжно применяется, уже несколько раз ловили на других
           страницах. На узких экранах маскот и блок кормления в карточке 1
           не помещаются в один ряд по половине ширины каждый (кнопке
           "Покормить" и прогресс-бару тесно) — на мобильном они идут друг
           под другом на всю ширину карточки. */
        @media (max-width: 599px) {
            .dashboard-card {
                padding: 1rem;
            }
            .dashboard-mascot-row {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }
            /* Раньше маскот растягивался на всю ширину карточки (width:100%) —
               при aspect-square на узком экране это давало квадрат ~300px+,
               визуально "гигантский". Компромисс — 230px по центру: рыба
               крупная, а облачку слева от неё хватает места. */
            .dashboard-mascot-row .fish-stage {
                align-self: center;
                width: 230px;
                max-width: 72%;
                margin-top: 10px;
            }
            .dashboard-mascot-row #fish-card {
                width: 100%;
            }
        }
    </style>
    <div id="dashboard-cards-grid">
        {{-- Карточка 1: маскот-рыба (пошире остальных) — интерфейс кормления
             расположен так же, как раньше в отдельной карточке 3 (см.
             partials/fish-card.blade.php: тот же flex-1/flex-col/mt-auto). --}}
        <x-ui.card class="dashboard-card dashboard-mascot-row flex md:gap-6 gap-3">
            {{-- Фиксированная ширина (не w-1/2 от карточки) — иначе
                 aspect-square растягивал бы квадрат вслед за шириной
                 карточки, когда та расширяется (см. комментарий у
                 #dashboard-cards-grid выше): расширяться должна карточка,
                 а не сам маскот. --}}
            {{-- .fish-stage — рамка для маскота и его реплики: облачко лежит
                 поверх картинки и может выходить за её край, а сам
                 #greeting-mascot обрезает содержимое (overflow: hidden). --}}
            @php
                $trainers = app(\App\Service\Trainers\TrainerHub::class)->forUser(auth()->user());
                $fishSays = app(\App\Service\Trainers\FishSpeech::class)->forUser(auth()->user(), $trainers);
                // Облачко сверху или снизу слева — случайно на каждый заход;
                // реплики при кормлении появляются там же.
                $fishSide = random_int(0, 1) ? 'is-top' : 'is-bottom';
            @endphp
            <div class="fish-stage" data-side="{{ $fishSide }}" data-trainers-url="{{ $trainers['url'] }}"
                 data-feed-phrases='@json(config('fish_speech.feeding'), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)'>
            <script>
                // Облачко слева от рыбы не должно упираться в край экрана:
                // если до края меньше 8px — встаёт внизу внутри картинки
                // (.is-fit). Меряем по offsetLeft, а не по getBoundingClientRect:
                // так не мешает масштаб из анимации появления.
                // Сначала облачко сужается (не уже 130px), чтобы поместиться
                // слева, — на телефоне ему обычно не хватает считанных
                // пикселей. Только если и так не влезает — вниз (.is-fit).
                var FISH_EDGE = 8, FISH_MIN_BUBBLE = 130;
                window.fishFitBubble = function (el) {
                    var stage = el.parentElement;
                    el.classList.remove('is-fit', 'is-top', 'is-bottom');
                    el.classList.add(stage.dataset.side || 'is-top');
                    el.style.maxWidth = '';
                    var stageLeft = stage.getBoundingClientRect().left;
                    var left = stageLeft + el.offsetLeft;
                    var right = left + el.offsetWidth;
                    if (left >= FISH_EDGE && right <= document.documentElement.clientWidth - FISH_EDGE) return;

                    // Правый край облачка привязан к рыбе. -1 и допуск в 1px —
                    // запас на округление offsetLeft до целых.
                    var room = Math.floor(right - FISH_EDGE) - 1;
                    if (left < FISH_EDGE && room >= FISH_MIN_BUBBLE) {
                        el.style.maxWidth = room + 'px';
                        if (stageLeft + el.offsetLeft >= FISH_EDGE - 1) return;
                        el.style.maxWidth = '';
                    }
                    el.classList.remove('is-top');
                    el.classList.add('is-bottom', 'is-fit');
                };
                // Точка, из которой облачко вырастает и в которую втягивается, —
                // кончик хвостика.
                window.fishBubbleOrigin = function (el) {
                    if (el.classList.contains('is-fit')) return '50% 0%';
                    return el.classList.contains('is-bottom') ? '88% 0%' : '88% 100%';
                };
                window.addEventListener('resize', function () {
                    document.querySelectorAll('.fish-stage .fish-bubble').forEach(window.fishFitBubble);
                });
            </script>
            <div id="greeting-mascot" class="w-full aspect-square rounded-xl bg-gray-100 flex items-center justify-center {{ $fishBalance > 0 ? 'cursor-pointer' : '' }}" style="background-image: url('{{ $fishBackgroundImage }}'); background-size: cover; background-position: center;" title="Покормить">
                <img id="fish-mascot-img"
                     src="{{ $fishMascotImage }}"
                     data-default-src="{{ $fishMascotImage }}"
                     data-eating-src="{{ $fishMascotEatingImage }}"
                     alt="" class="w-full h-full object-contain">
            </div>
            {{-- Реплика рыбы (App\Service\Trainers\FishSpeech) — белое облачко
                 как в мультике: слева от рыбы, хвостиком к ней. Нажатие ведёт
                 в тренажёр или кормит (data-feed). Крестик прячет реплику,
                 пока ситуация та же (data-key, в браузере ученика); daily —
                 показывается раз в день. Скрипт сразу за облачком — чтобы
                 скрытое не мелькнуло. --}}
            @if($fishSays)
                <div class="fish-bubble {{ $fishSide }} {{ $fishSays['quiet'] ? 'is-quiet' : '' }}"
                     data-key="{{ $fishSays['key'] }}" {{ $fishSays['daily'] ? 'data-daily' : '' }} {{ $fishSays['feed'] ? 'data-feed' : '' }}>
                    @if($fishSays['feed'])
                        <button type="button" class="fish-bubble-link" data-feed-bubble>{{ $fishSays['text'] }}</button>
                    @else
                        <a href="{{ $fishSays['url'] }}" class="fish-bubble-link">
                            {{ $fishSays['text'] }}
                            @unless($fishSays['quiet'])
                                <x-icon name="arrow-narrow-right" class="inline-block w-4 h-4 fish-bubble-arrow" />
                            @endunless
                        </a>
                    @endif
                    <button type="button" class="fish-bubble-close" aria-label="Скрыть">
                        <x-icon name="x-close" class="w-3.5 h-3.5" />
                    </button>
                </div>
                <script>
                    (function () {
                        var bubble = document.currentScript.previousElementSibling;
                        window.fishFitBubble(bubble);
                        try {
                            // ?fish=reset — вернуть скрытые реплики (для проверки).
                            if (/[?&]fish=reset(&|$)/.test(location.search)) {
                                localStorage.removeItem('fishBubbleDismissed');
                                localStorage.removeItem('fishDailySeen');
                            }
                            var key = bubble.dataset.key;
                            if (localStorage.getItem('fishBubbleDismissed') === key) { bubble.remove(); return; }
                            if (bubble.hasAttribute('data-daily')) {
                                // Ключи с датой: вчерашние выкидываем.
                                var today = key.slice(0, 10);
                                var seen = JSON.parse(localStorage.getItem('fishDailySeen') || '[]')
                                    .filter(function (k) { return k.slice(0, 10) === today; });
                                if (seen.indexOf(key) !== -1) { bubble.remove(); return; }
                                seen.push(key);
                                localStorage.setItem('fishDailySeen', JSON.stringify(seen));
                            }
                        } catch (e) {}
                    })();
                </script>
            @endif
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
            // Рыба «заговаривает» чуть позже, чем появляется сама: облачко
            // вырастает из хвостика (он сверху или снизу — см. .fish-bubble). fromTo с явным концом, а не from: from()
            // берёт конечное состояние из текущего вида элемента, и облачко
            // оставалось невидимым.
            var says = document.querySelector('.fish-bubble');
            if (says) {
                gsap.fromTo(says,
                    { scale: 0.4, autoAlpha: 0 },
                    {
                        scale: 1, autoAlpha: 1, duration: 0.5, delay: 0.5,
                        ease: 'back.out(2.2)', clearProps: 'all',
                        transformOrigin: window.fishBubbleOrigin(says),
                    });
            }
        });

        // Крестик на реплике рыбы: запоминаем ситуацию (data-key — с датой),
        // облачко «втягивается» в хвостик. На следующий день или при новой
        // ситуации ключ другой — и рыба заговорит снова.
        document.addEventListener('click', function (evt) {
            var close = evt.target.closest('.fish-bubble-close');
            if (!close) return;
            var bubble = close.closest('.fish-bubble');
            try { localStorage.setItem('fishBubbleDismissed', bubble.dataset.key); } catch (e) {}
            if (typeof window.gsap === 'undefined') { bubble.remove(); return; }
            gsap.to(bubble, {
                scale: 0.3, autoAlpha: 0, duration: 0.25, ease: 'back.in(1.6)',
                transformOrigin: window.fishBubbleOrigin(bubble),
                onComplete: function () { bubble.remove(); },
            });
        });

        // Реплика «покорми меня» (data-feed-bubble) кормит — тем же путём,
        // что и клик по маскоту: эмулируем клик по актуальной кнопке.
        document.addEventListener('click', function (evt) {
            if (!evt.target.closest('[data-feed-bubble]')) return;
            var feedBtn = document.querySelector('.fish-feed-btn');
            if (feedBtn && !feedBtn.disabled) feedBtn.click();
        });

        // Рыба говорит во время кормления (фразы — config/fish_speech.php,
        // 'feeding'). Нажатия с паузой меньше 2 секунд — одна серия: на 1-2
        // нажатия рыба молчит, облачко появляется на 3-м, держится, пока
        // кормят, и меняет текст только на ступенях серии (3-е, 5-е, 8-е и
        // дальше каждое 4-е). Вырос уровень — своя фраза, держится дольше;
        // корм кончился — тоже, но только если серия была от 3 нажатий. Обычная
        // реплика на время серии прячется и потом возвращается — кроме
        // «покорми меня»: рыбу уже покормили.
        var fishTalk = (function () {
            var stage = document.querySelector('.fish-stage');
            if (!stage) return null;
            var phrases = JSON.parse(stage.dataset.feedPhrases || '{}');
            var last = {};
            var count = 0;
            var bubble = null;
            var timer = null;
            var lockedUntil = 0;

            function pick(pool) {
                var list = phrases[pool] || [];
                var options = list.length > 1 ? list.filter(function (p) { return p !== last[pool]; }) : list;
                last[pool] = options[Math.floor(Math.random() * options.length)];
                return last[pool];
            }

            function prompt() {
                return stage.querySelector('.fish-bubble:not(.is-feed)');
            }

            function say(text, holdMs, url) {
                var fresh = !bubble;
                if (fresh) {
                    bubble = document.createElement('div');
                    bubble.className = 'fish-bubble is-feed';
                    stage.appendChild(bubble);
                }
                var inner = document.createElement(url ? 'a' : 'span');
                inner.className = 'fish-bubble-link';
                inner.textContent = text;
                if (url) inner.href = url;
                bubble.replaceChildren(inner);
                window.fishFitBubble(bubble);
                bubble.style.pointerEvents = url ? '' : 'none';

                if (window.gsap) {
                    var origin = window.fishBubbleOrigin(bubble);
                    if (fresh) {
                        gsap.fromTo(bubble, { scale: 0.4, autoAlpha: 0 },
                            { scale: 1, autoAlpha: 1, duration: 0.35, ease: 'back.out(2.2)', transformOrigin: origin });
                    } else {
                        gsap.fromTo(bubble, { scale: 0.92 }, { scale: 1, duration: 0.3, ease: 'back.out(3)', transformOrigin: origin });
                    }
                }
                hold(holdMs);
            }

            function hold(ms) {
                clearTimeout(timer);
                timer = setTimeout(end, ms);
            }

            function end() {
                var old = bubble;
                bubble = null;
                count = 0;
                lockedUntil = 0;
                if (old) {
                    if (window.gsap) {
                        gsap.to(old, {
                            scale: 0.3, autoAlpha: 0, duration: 0.25, ease: 'back.in(1.6)',
                            transformOrigin: window.fishBubbleOrigin(old),
                            onComplete: function () { old.remove(); },
                        });
                    } else {
                        old.remove();
                    }
                }
                var p = prompt();
                if (!p) return;
                if (p.hasAttribute('data-feed')) {
                    p.remove();
                } else if (window.gsap) {
                    gsap.to(p, { autoAlpha: 1, duration: 0.3, delay: 0.25 });
                } else {
                    p.style.visibility = '';
                }
            }

            return {
                // Клик «Покормить» (до ответа сервера).
                fed: function () {
                    count++;
                    if (count === 1) {
                        var p = prompt();
                        if (p) {
                            if (window.gsap) gsap.to(p, { autoAlpha: 0, duration: 0.15 });
                            else p.style.visibility = 'hidden';
                        }
                    }
                    if (Date.now() < lockedUntil) { hold(Math.max(2000, lockedUntil - Date.now())); return; }

                    var pool = count === 3 ? 'third'
                        : count === 5 ? 'fifth'
                        : (count >= 8 && (count - 8) % 4 === 0) ? 'many'
                        : null;
                    if (pool) say(pick(pool), 2000);
                    else hold(2000);
                },
                // Ответ сервера: корм кончился.
                empty: function () {
                    if (Date.now() < lockedUntil) return; // только что вырос уровень — это важнее
                    if (count < 3) return; // на разовое кормление рыба молчит
                    var url = stage.dataset.trainersUrl;
                    say(pick(url ? 'empty_trainer' : 'empty'), url ? 4000 : 2500, url || null);
                    lockedUntil = Date.now() + (url ? 4000 : 2500);
                },
                // Ответ сервера: рыба выросла.
                levelUp: function () {
                    say(pick('level_up'), 3000);
                    lockedUntil = Date.now() + 3000;
                },
            };
        })();

        if (fishTalk) {
            document.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.fish-feed-btn');
                if (btn && !btn.disabled) fishTalk.fed();
            });
            document.body.addEventListener('htmx:afterSwap', function (evt) {
                var card = document.getElementById('fish-card');
                if (card && evt.detail.target && evt.detail.target.id === 'fish-card' && card.dataset.balance === '0') {
                    fishTalk.empty();
                }
            });
            // Левел-ап приходит после свапа; если заодно кончился корм —
            // рост важнее.
            document.body.addEventListener('fish-level-up', function () { fishTalk.levelUp(); });
        }

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

            // Еда, сыплющаяся на маскота — та же техника, что и у
            // конфетти на странице результата домашки (много мелких
            // элементов, каждый со своим GSAP-твином и случайными размером/
            // траекторией), только контейнер — сам #greeting-mascot
            // (position:relative; overflow:hidden), а не весь экран.
            // Картинка — выбранная в профиле еда (data-crumb-src на
            // #fish-card), без неё — прежние цветные крошки.
            var fishCard = document.getElementById('fish-card');
            var crumbSrc = fishCard ? fishCard.dataset.crumbSrc : '';
            var crumbColors = ['#D97706', '#B45309', '#F59E0B', '#92400E'];
            var crumbCount = crumbSrc ? 10 : 22;
            var w = mascot.clientWidth;
            var h = mascot.clientHeight;

            for (var i = 0; i < crumbCount; i++) {
                var crumb;
                if (crumbSrc) {
                    var size = 16 + Math.random() * 10;
                    crumb = document.createElement('img');
                    crumb.src = crumbSrc;
                    crumb.alt = '';
                    crumb.style.cssText = 'position:absolute; top:-' + Math.ceil(size) + 'px; left:' + (Math.random() * (w - size)) + 'px; width:' + size + 'px; height:' + size + 'px; pointer-events:none; z-index:5; opacity:0;';
                } else {
                    var size = 3 + Math.random() * 4;
                    var color = crumbColors[Math.floor(Math.random() * crumbColors.length)];
                    crumb = document.createElement('div');
                    crumb.style.cssText = 'position:absolute; top:-8px; left:' + (Math.random() * w) + 'px; width:' + size + 'px; height:' + size + 'px; background:' + color + '; border-radius:' + (Math.random() > .5 ? '50%' : '2px') + '; pointer-events:none; z-index:5; opacity:0;';
                }
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
            // Пульсация после появления — едва заметное "дыхание", а не
            // долгая тряска: раньше было ±8% размера на 3 повтора (почти
            // секунда мельтешения), теперь один короткий импульс ±2.5%.
            tl.call(applyNewImageSources)
              .to(flash, { opacity: 0, duration: .3 })
              .to(img, { scale: 1, autoAlpha: 1, duration: .6, ease: 'elastic.out(1, .5)' }, '<')
              .to(img, { scale: 1.025, duration: .12, ease: 'sine.inOut', yoyo: true, repeat: 1 }, '-=0.1');

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
        <div class="flex justify-between items-center gap-2 mb-4 border-b border-gray-200 card-line pb-2 px-1">
            <h2 class="sans-medium text-base md:text-2xl md:tracking-wide text-zinc-900 whitespace-nowrap"><x-icon name="calendar" class="inline-block relative bottom-0.5 mr-1 w-4 h-4 md:w-5 md:h-5" /> Расписание уроков</h2>
            <div class="flex gap-2 shrink-0">
                <button id="swiper-prev" class="w-8 h-8 md:w-9 md:h-9 rounded-lg border border-gray-200 card-line bg-white flex items-center justify-center text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white" disabled aria-label="Предыдущие дни">
                    <x-icon name="chevron-left" class="w-4 h-4" />
                </button>
                <button id="swiper-next" class="w-8 h-8 md:w-9 md:h-9 rounded-lg border border-gray-200 card-line bg-white flex items-center justify-center text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white" aria-label="Следующие дни">
                    <x-icon name="chevron-right" class="w-4 h-4" />
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
        <div class="border border-dashed border-gray-300 card-line rounded-2xl px-3 py-4 text-left text-zinc-600 space-y-2">
          <div class="flex items-center gap-2 text-xs">
            <x-icon name="sun" class="w-4 h-4 text-zinc-400 shrink-0" />
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
               <x-icon name="bell-01" class="w-3.5 h-3.5 mr-1 shrink-0" />
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

{{-- Swiper CSS уже в общем бандле (resources/css/app.css, @import 'swiper/css/bundle') —
     здесь раньше был ещё один блокирующий <link> на тот же cdn.jsdelivr.net, дублирующий
     стили. JS теперь с defer — не блокирует рендер того, что ниже (карточки курсов и
     дальше), пока грузится с CDN. --}}
<script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
   // defer откладывает выполнение до конца парсинга — без этой обёртки на момент
   // выполнения скрипта window.Swiper ещё не определён (тот же приём, что и в
   // main/repetitor.blade.php).
   if (typeof window.Swiper === 'undefined') return;

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
}); // конец DOMContentLoaded
</script>

    <h2 class="sans-medium text-lg md:text-2xl tracking-wide md:mb-4 mb-3 mt-4 text-zinc-900">Мои курсы</h2>

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

</div>
@endsection
