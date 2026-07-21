<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Дизайн-система — текущее состояние</title>
    @vite('resources/css/app.css')
    <style>
        /* Служебная страница-каталог, не часть студенческого/админского UI —
           поэтому свой минимальный каркас, а не layouts.main/admin.layouts.main:
           нужна полная ширина под ряды свотчей, а не колонка в 2/3 экрана. */
        body {
            background: #fafafa;
        }
        .ds-nav {
            position: sticky;
            top: 0;
            z-index: 30;
            background: #18181b;
        }
        .ds-nav a {
            color: #a1a1aa;
            text-decoration: none;
        }
        .ds-nav a:hover {
            color: #fff;
        }
        .ds-swatch-label {
            font-family: ui-monospace, monospace;
        }
    </style>
</head>
<body class="text-gray-900">

    <div class="ds-nav px-6 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
        <a href="{{ route('main.index') }}" class="text-white font-medium">← Админ-панель</a>
        <a href="#buttons">Кнопки</a>
        <a href="#cards">Карточки</a>
        <a href="#colors">Цвета</a>
        <a href="#icons">Иконки</a>
        <a href="#typography">Типографика</a>
        <a href="#empty-states">Пустые состояния</a>
        <a href="#status-badges">Статусы/бейджи</a>
        <a href="#list-items">Карточки-события</a>
        <a href="#forms">Формы</a>
        <a href="#tabs">Табы</a>
        <a href="#overlays">Модалки/дропдауны</a>
        <a href="#tables">Таблицы</a>
        <a href="#misc">Аватары/тосты/прогресс</a>
        <a href="#leftover">Незавершённая миграция</a>
    </div>

    <div class="max-w-6xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-semibold mb-3">Дизайн-система: текущее состояние</h1>
        <p class="max-w-3xl text-gray-600 mb-10">
            Это не целевая система, а срез того, что реально используется в коде сейчас —
            снимок «до», по которому будем строить набор компонентов <code class="ds-swatch-label">x-ui.*</code>.
            Каждый свотч ниже — реальная комбинация классов, скопированная из конкретного файла,
            без изменений. Дальше это станет основой для отбора одного варианта на каждую роль.
        </p>

        {{-- ================= КНОПКИ ================= --}}
        <section id="buttons" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Кнопки</h2>
            <p class="text-gray-600 mb-6">17 разных комбинаций для «основного действия», вторичных и заблокированных кнопок.</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Семья A — тёмный нейтральный (zinc), 6 вариантов</h3>
            <div class="flex flex-wrap items-start gap-4 mb-8">
                <div>
                    <button class="px-3 py-2 rounded-lg bg-zinc-900 text-white text-sm hover:bg-zinc-800">Кнопка</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">billing/show.blade.php:59<br>px-3 py-2 rounded-lg</p>
                </div>
                <div>
                    <button class="px-6 py-4 rounded-xl bg-zinc-800 border text-white hover:bg-zinc-900">Перейти к уроку</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">courses/show.blade.php:167<br>px-6 md:px-8 py-4 rounded-xl border</p>
                </div>
                <div>
                    <button class="w-full px-3 py-4 rounded-xl bg-zinc-800 border text-white hover:bg-zinc-900">Перейти к уроку</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">courses/show.blade.php:253, dashboard.blade.php:697<br>w-full px-3 py-4 rounded-xl border</p>
                </div>
                <div>
                    <button class="px-6 py-4 rounded-xl border-2 bg-zinc-800 border-zinc-800 text-white hover:bg-zinc-900">Скачать конспект</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">lessons/show.blade.php:61,93,100<br>border-2 (не border) + border-zinc-800</p>
                </div>
                <div>
                    <button class="rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800">Сохранить</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">profile/show.blade.php:267,445,481<br>rounded-lg px-4 py-3 font-medium</p>
                </div>
                <div>
                    <button class="rounded-lg px-3 py-2 text-sm font-medium bg-zinc-900 hover:bg-zinc-800 text-white">Покормить</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">fish-card.blade.php:45<br>rounded-lg px-3 py-2 text-sm</p>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Семья B — синяя (только визард домашек), 4 варианта</h3>
            <div class="flex flex-wrap items-start gap-4 mb-8">
                <div>
                    <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Перерешать работу</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">submissions/show.blade.php:160<br>px-4 py-2 rounded-lg</p>
                </div>
                <div>
                    <button class="px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700">Завершить</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">finish-region.blade.php:78<br>px-5 py-3 rounded-xl</p>
                </div>
                <div>
                    <button class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">Далее</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">question-region.blade.php:157,243<br>px-4 py-2 rounded-xl</p>
                </div>
                <div>
                    <button class="w-full px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">Далее</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">question-region.blade.php:302<br>w-full px-4 py-2 rounded-xl</p>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Семья C — розовая (одна кнопка), 1 вариант</h3>
            <div class="flex flex-wrap items-start gap-4 mb-8">
                <div>
                    <button class="w-full px-3 py-4 rounded-xl bg-rose-600 border text-white hover:bg-rose-700">Оплатить сейчас</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">dashboard.blade.php:692<br>единственное место с rose как основным действием</p>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Вторичные/контурные, 4 варианта</h3>
            <div class="flex flex-wrap items-start gap-4 mb-8">
                <div>
                    <button class="rounded-lg px-3 py-1.5 border">Фильтр</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">billing/show.blade.php:72<br>rounded-lg px-3 py-1.5</p>
                </div>
                <div>
                    <button class="rounded-xl border-gray-300 border px-4 py-2">Назад</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">question-region.blade.php:293<br>rounded-xl px-4 py-2</p>
                </div>
                <div>
                    <button class="rounded-lg border-gray-300 border px-3 py-1.5">Отмена</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">question-region.blade.php:52<br>rounded-lg px-3 py-1.5</p>
                </div>
                <div>
                    <button class="rounded-full border px-3 py-1.5">Все</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">homeworks/index.blade.php:30-32<br>rounded-full — единственное место с этим radius</p>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Заблокированные, 2 варианта</h3>
            <div class="flex flex-wrap items-start gap-4">
                <div>
                    <button disabled class="bg-gray-200 text-gray-500 cursor-not-allowed px-4 py-2 rounded-lg">Недоступно</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">submissions/show.blade.php:165, finish-region.blade.php:89</p>
                </div>
                <div>
                    <button disabled class="bg-gray-100 text-gray-400 cursor-not-allowed rounded-lg px-3 py-2 text-sm">Покормить</button>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2 max-w-[220px]">fish-card.blade.php:45 (условный класс)<br>другой оттенок серого, чем сосед слева</p>
                </div>
            </div>
        </section>

        {{-- ================= КАРТОЧКИ ================= --}}
        <section id="cards" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Карточки</h2>
            <p class="text-gray-600 mb-6">14 вариантов рамки/фона/тени/паддинга для визуально одной и той же роли — «блок контента».</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <div class="bg-white border rounded-2xl p-5">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">bg-white border rounded-2xl p-5</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">billing/show:31,84; dashboard:111,127,167 — без тени</p>
                </div>
                <div>
                    <div class="rounded-2xl bg-blue-50 border border-blue-200 shadow-sm p-5">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">bg-blue-50 border-blue-200 shadow-sm</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">courses/show.blade.php:80</p>
                </div>
                <div>
                    <div class="rounded-2xl bg-gray-50 border border-gray-200 shadow-sm p-5">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">bg-gray-50 border-gray-200 shadow-sm</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">courses/show.blade.php:211</p>
                </div>
                <div>
                    <div class="rounded-2xl border bg-white p-3">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">border bg-white p-3/p-4, без тени</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">dashboard.blade.php:673 — меньше паддинг, чем у соседей</p>
                </div>
                <div>
                    <div class="bg-white border rounded-2xl p-6 shadow-sm">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">.profile-card — p-6 shadow-sm</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">profile/show.blade.php:238,323,453</p>
                </div>
                <div>
                    <div class="rounded-2xl border bg-white p-4 hover:shadow-sm transition">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">тень только на hover</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">homeworks/index:90, notifications/index:50</p>
                </div>
                <div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 py-6">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">border-gray-200 явно, p-4 py-6</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">submissions/show.blade.php:139,149</p>
                </div>
                <div>
                    <div class="rounded-2xl shadow bg-white p-5">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">shadow (не shadow-sm), без border вообще</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">submissions/show.blade.php:177</p>
                </div>
                <div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">border, без тени</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">submissions/show.blade.php:306 — соседствует с карточкой выше, та же роль</p>
                </div>
                <div>
                    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">flex-1 bg-blue-50 p-4 sm:p-6</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">lessons/show.blade.php:48,73</p>
                </div>
                <div>
                    <div class="p-6 rounded-xl border bg-white text-gray-600">
                        Пустое состояние
                        <div class="text-sm">rounded-xl, не rounded-2xl</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">billing/show:24, dashboard:658</p>
                </div>
                <div>
                    <div class="bg-white rounded-xl border px-4 pt-6 pb-4">
                        <div class="font-medium">Заголовок</div>
                        <div class="text-sm text-gray-600">rounded-xl обёртка свайпера</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">dashboard.blade.php:496</p>
                </div>
                <div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        Пустое состояние
                        <div class="text-sm text-gray-600">rounded-xl, другой радиус</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">submissions/show.blade.php:893</p>
                </div>
                <div>
                    <div class="p-6 rounded-2xl border bg-white text-gray-600 text-center">
                        Пустое состояние
                        <div class="text-sm">rounded-2xl + text-center</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">homeworks/index:36, notifications/index:21</p>
                </div>
            </div>
        </section>

        {{-- ================= ЦВЕТА ================= --}}
        <section id="colors" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Цвета</h2>
            <p class="text-gray-600 mb-6">Нейтральный текст должен быть одной палитрой — на деле их две.</p>

            <div class="flex flex-wrap gap-8 mb-8">
                <div class="text-center">
                    <div class="text-gray-600 text-lg font-medium">Aa текст</div>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">text-gray-*<br>166 использований, 14 файлов</div>
                </div>
                <div class="text-center">
                    <div class="text-zinc-800 text-lg font-medium">Aa текст</div>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">text-zinc-*<br>4 использования: dashboard:498,655, lessons/show:65</div>
                </div>
                <div class="text-center">
                    <div class="text-slate-600 text-lg font-medium">Aa текст</div>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">text-slate-*<br>0 использований — для справки</div>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Цвета рамок в карточках/бейджах</h3>
            <div class="flex flex-wrap gap-4">
                <div class="w-20 h-14 rounded-lg border border-gray-200 flex items-center justify-center text-xs">gray-200</div>
                <div class="w-20 h-14 rounded-lg border border-blue-200 flex items-center justify-center text-xs">blue-200</div>
                <div class="w-20 h-14 rounded-lg border border-amber-200 flex items-center justify-center text-xs">amber-200</div>
                <div class="w-20 h-14 rounded-lg border border-emerald-200 flex items-center justify-center text-xs">emerald-200</div>
                <div class="w-20 h-14 rounded-lg border border-rose-200 flex items-center justify-center text-xs">rose-200</div>
                <div class="w-20 h-14 rounded-lg border border-purple-200 flex items-center justify-center text-xs">purple-200</div>
                <div class="w-20 h-14 rounded-lg border border-zinc-300 flex items-center justify-center text-xs">zinc-300</div>
                <div class="w-20 h-14 rounded-lg border-2 border-zinc-800 flex items-center justify-center text-xs">zinc-800</div>
            </div>
        </section>

        {{-- ================= ИКОНКИ ================= --}}
        <section id="icons" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Иконки</h2>
            <p class="text-gray-600 mb-6">Четыре параллельных источника для одних и тех же по смыслу состояний.</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Инлайн SVG — 5 разных толщин обводки</h3>
            <div class="flex flex-wrap items-end gap-8 mb-8">
                <div class="text-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 mx-auto">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <path d="M21 15l-5-5L5 21"></path>
                    </svg>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">stroke-width 1.6<br>courses/show:92,219</div>
                </div>
                <div class="text-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 mx-auto">
                        <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                        <path d="M9 3v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V3"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">stroke-width 1.8<br>bottom-nav, lesson-image-badges, profile</div>
                </div>
                <div class="text-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 mx-auto">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">stroke-width 2<br>student-back-button</div>
                </div>
                <div class="text-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 mx-auto text-rose-500">
                        <path d="M6 6l12 12M18 6L6 18"></path>
                    </svg>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">stroke-width 3<br>submissions/show (крестик)</div>
                </div>
                <div class="text-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 mx-auto text-emerald-500">
                        <path d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">stroke-width 4<br>submissions/show (галочка)</div>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">img-иконки, webp-иллюстрации, эмодзи</h3>
            <div class="flex flex-wrap items-end gap-8">
                <div class="text-center">
                    <img src="{{ asset('img/Date_range.svg') }}" alt="" class="w-8 h-8 mx-auto">
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">img/*.svg<br>Date_range, Camera, Return, like, crying, person</div>
                </div>
                <div class="text-center">
                    <img src="{{ asset('img/hand-holding-notes.webp') }}" alt="" class="w-14 h-14 mx-auto">
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">webp-иллюстрация<br>lessons/show:51,76</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl">🎉 🔒</div>
                    <div class="ds-swatch-label text-xs text-gray-500 mt-1">голый эмодзи<br>homeworks/index:37, profile-fish-background-item:23</div>
                </div>
            </div>
        </section>

        {{-- ================= ТИПОГРАФИКА ================= --}}
        <section id="typography" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Типографика</h2>
            <p class="text-gray-600 mb-6">Заголовок h1 оформлен по-разному почти на каждой странице.</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">H1 — 7 вариантов</h3>
            <div class="space-y-4 mb-10">
                <div>
                    <div class="text-2xl font-semibold">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">billing/show:9, homeworks/index:27, notifications/index:11 — serif по умолчанию, без переопределения шрифта</p>
                </div>
                <div>
                    <div class="text-2xl font-semibold" style="font-family: sans-serif;">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">profile/show:5 — .profile-heading, шрифт переопределён отдельным &lt;style&gt;</p>
                </div>
                <div>
                    <div class="text-2xl md:text-3xl font-sans font-medium text-gray-900">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">courses/show.blade.php — font-sans utility, font-medium (не semibold)</p>
                </div>
                <div>
                    <div class="text-2xl md:text-4xl font-medium font-sans">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">lessons/show:7 — другой md-размер, чем у courses/show</p>
                </div>
                <div>
                    <div class="text-2xl font-semibold">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">submissions/show:119 — снова serif, без переопределения</p>
                </div>
                <div>
                    <div class="text-xl sm:text-2xl font-medium" style="font-family: sans-serif;">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">finish-region.blade.php:29 — третий механизм принудительного sans: класс .sans на &lt;span&gt;</p>
                </div>
                <div>
                    <div class="text-lg sm:text-xl font-medium" style="font-family: sans-serif;">Заголовок страницы</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">question-region.blade.php:45 — тот же .sans-механизм, меньший размер</p>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-700 mb-3">H2/H3 — 5 вариантов</h3>
            <div class="space-y-4">
                <div>
                    <div class="text-base md:text-xl tracking-wide font-normal font-sans text-zinc-800">Раздел дашборда</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">dashboard:498,655 — font-normal, text-zinc-800</p>
                </div>
                <div>
                    <div class="text-xs md:text-base font-sans font-semibold text-blue-900">Следующее занятие</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">courses/show — бейдж-заголовок поверх картинки, свой масштаб</p>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <div class="text-xl font-medium text-gray-900">Название урока</div>
                    <p class="ds-swatch-label text-xs text-amber-800 mt-1">
                        ⚠ courses/show.blade.php:231 — класс <code>sans</code> (без префикса <code>font-</code>) — опечатка,
                        такого Tailwind-класса не существует, заголовок молча съезжает на serif по умолчанию.
                        Это баг, а не просто расхождение стиля.
                    </p>
                </div>
                <div>
                    <div class="text-lg font-semibold">Заголовок блока</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">submissions/show:179,308,541</p>
                </div>
                <div>
                    <div class="font-medium text-lg text-gray-900">Заголовок блока</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">billing/show:32,85, profile/show:239,275,324,454 — обычный &lt;div&gt;, не тег заголовка</p>
                </div>
            </div>
        </section>

        {{-- ================= ПУСТЫЕ СОСТОЯНИЯ ================= --}}
        <section id="empty-states" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Пустые состояния</h2>
            <p class="text-gray-600 mb-6">Одно и то же по смыслу сообщение «данных нет» оформлено четырьмя способами.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Ближайшее занятие не запланировано.</p>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">courses/show:67 — голый текст, без обёртки</p>
                </div>
                <div>
                    <div class="p-6 rounded-xl border bg-white text-gray-600">Активных курсов нет.</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">billing/show:24-26 — карточка rounded-xl</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Платежей пока нет.</p>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">billing/show:88 — gray-500, не gray-600 как сосед выше</p>
                </div>
                <div>
                    <div class="rounded-xl border border-dashed border-gray-200 px-3 py-3 text-gray-500 text-sm">Уроков пока не запланировано</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">student-dashboard-event-card:41-43 — пунктирная рамка, свой вариант</p>
                </div>
                <div>
                    <div class="p-6 rounded-2xl border bg-white text-gray-600 text-center">Домашек в очереди нет 🎉</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">homeworks/index:36-38 — rounded-2xl, text-center, + эмодзи</p>
                </div>
                <div>
                    <div class="p-6 rounded-2xl border bg-white text-gray-600 text-center">Уведомлений пока нет</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">notifications/index:21-23 — совпадает с соседом слева</p>
                </div>
                <div>
                    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600">Заданий нет.</div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-1">submissions/show:893-895 — rounded-xl, другой радиус, чем у остальных на той же странице</p>
                </div>
            </div>
        </section>

        {{-- ================= СТАТУСЫ / БЕЙДЖИ ================= --}}
        <section id="status-badges" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Статусы и бейджи</h2>
            <p class="text-gray-600 mb-6">
                Пять независимых систем для «показать статус/тип цветом» — каждая со своей картой цветов,
                определённой в своём файле заново.
            </p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">1. Пилюля-статус (homeworks/notifications) — согласованы между собой</h3>
            <div class="flex flex-wrap gap-2 mb-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Не начато</span>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">В процессе</span>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700">Просрочено</span>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">На проверке</span>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Проверено</span>
            </div>
            <p class="ds-swatch-label text-xs text-gray-500 mb-8">homeworks/index.blade.php:46-52, notifications/index.blade.php:27-37 — одна и та же карта цветов, но каждый файл определяет её заново своим PHP-массивом</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">2. Инлайн-статус в расписании — не похож ни на один другой</h3>
            <div class="flex flex-wrap gap-2 mb-2">
                <span class="inline-block px-1.5 pt-[1px] pb-[3px] text-xs border border-red-300 rounded text-red-700">Срок истёк</span>
                <span class="inline-block px-1.5 pt-[1px] pb-[3px] rounded bg-emerald-500/20 text-emerald-700">Выполнена</span>
            </div>
            <p class="ds-swatch-label text-xs text-gray-500 mb-8">dashboard.blade.php:578-582 — контурный для «просрочено», заливка для «выполнено»: два разных приёма внутри одного и того же файла для одной и той же роли</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">3. Круглые бейджи-иконки на картинке урока — единственный не-текстовый вариант</h3>
            <div class="flex gap-2 mb-2">
                <span class="w-8 h-8 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center text-red-500">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><rect x="6" y="3" width="12" height="18" rx="2"></rect><path d="M9 3v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V3"></path><path d="M9 12l2 2 4-4"></path></svg>
                </span>
                <span class="w-8 h-8 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center text-gray-700">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H18a1 1 0 0 1 1 1v17a1 1 0 0 1-1 1H6.5A2.5 2.5 0 0 1 4 18.5v-14Z"></path><path d="M8 7h8M8 11h8M8 15h5"></path></svg>
                </span>
            </div>
            <p class="ds-swatch-label text-xs text-gray-500 mb-8">student/partials/lesson-image-badges.blade.php — цвет несёт смысл через сам значок, а не через текст/фон, как везде остальные</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">4. Billing — статус вообще без бейджа, только цвет текста</h3>
            <div class="flex flex-wrap gap-4 mb-2">
                <span class="text-xs text-emerald-600">Успешно</span>
                <span class="text-xs text-rose-600">Не удалось</span>
                <span class="text-xs text-gray-400">В обработке</span>
            </div>
            <p class="ds-swatch-label text-xs text-gray-500">billing/show.blade.php:108-118 — единственное место, где статус вообще не оформлен как элемент, просто цвет слова</p>
        </section>

        {{-- ================= КАРТОЧКИ-СОБЫТИЯ (пример из запроса) ================= --}}
        <section id="list-items" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Карточки-события в расписании и в «ближайших»</h2>
            <p class="text-gray-600 mb-6">
                Конкретный пример дублирования: карта цветов «по предмету» (blue/purple/orange/yellow/red)
                определена <strong>дважды</strong> — один раз в общем партиале, второй раз заново внутри
                свайпера расписания — с похожей, но не идентичной итоговой вёрсткой.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">partials/student-dashboard-event-card.blade.php</h3>
                    <div class="h-full min-w-0 flex flex-col justify-center gap-1 rounded-xl border bg-blue-100 border-blue-200 px-3 py-3 max-w-xs">
                        <div class="flex items-center justify-between gap-2 min-w-0 text-xs text-blue-700">
                            <span class="font-medium truncate">Урок</span>
                            <span class="shrink-0 whitespace-nowrap">18:00</span>
                        </div>
                        <div class="text-sm font-medium text-gray-900 leading-snug truncate">Производная функции</div>
                        <div class="text-xs text-gray-600 truncate">Математика</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">gap-1, без иконки, без статус-бейджа, есть truncate/min-w-0 на каждом уровне</p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-2">dashboard.blade.php — слайд расписания (:574-602)</h3>
                    <div class="bg-blue-100 border border-blue-200 rounded-xl px-3 py-3 text-left space-y-2 max-w-xs">
                        <div class="flex items-center text-xs text-blue-700">
                            <span class="mr-1">🔔</span>
                            <span>Урок</span>
                            <span class="ml-2 inline-block px-1.5 pt-[1px] pb-[3px] text-xs border border-red-300 rounded text-red-700">Срок истёк</span>
                            <span class="ml-auto text-gray-400">18:00</span>
                        </div>
                        <div class="font-medium text-base text-gray-800 leading-snug">Производная функции</div>
                        <div>
                            <span class="inline-block bg-white text-gray-700 text-xs px-2 pt-0.5 pb-1 rounded-md">Математика</span>
                        </div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">space-y-2, эмодзи-иконка, свой статус-бейдж, предмет — таблетка, а не голый текст; нет truncate/min-w-0 — в слайде фиксированной ширины пока не стреляло, но это тот же риск переполнения, что чинили в event-card</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">
                Итого: одна и та же карта цветов (<code class="ds-swatch-label">$bgMap/$borderMap/$textMap</code> для
                blue/purple/orange/yellow/red) продублирована в обоих файлах дословно, а верстка вокруг нее
                — независимая и слегка разная. Кандидат №1 на единый <code class="ds-swatch-label">x-ui.event-card</code>.
            </p>
        </section>

        {{-- ================= ФОРМЫ ================= --}}
        <section id="forms" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Формы и поля ввода</h2>
            <p class="text-gray-600 mb-6">Как минимум 6 разных систем для текстовых полей и переключателей.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <input type="text" placeholder="Имя рыбки" class="mt-1 w-full border rounded-lg px-3 py-2 input-focus">
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">«Официальный» input-focus (app.css:265-277) — profile/show.blade.php, 7 использований, синяя обводка на фокусе</p>
                </div>
                <div>
                    <input type="text" placeholder="Введите код" class="border rounded px-2 py-1.5 text-sm">
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">billing/show.blade.php:70 — голый border, без input-focus, без фокус-кольца</p>
                </div>
                <div>
                    <textarea rows="3" placeholder="Ваш ответ" class="w-full border rounded-xl px-3 py-2 text-sm"></textarea>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">question-region.blade.php:155 — свой rounded-xl, тоже без input-focus</p>
                </div>
                <div>
                    <div class="flex gap-2">
                        <div class="w-10 h-12 rounded-lg border-2 flex items-center justify-center text-lg font-medium" style="border-color:#2563eb;">1</div>
                        <div class="w-10 h-12 rounded-lg border-2 flex items-center justify-center text-lg font-medium" style="border-color:#e5e7eb;">2</div>
                        <div class="w-10 h-12 rounded-lg border-2 flex items-center justify-center text-lg font-medium" style="border-color:#e5e7eb;">3</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">«PIN-box» (app.css:249-260) — настоящий &lt;input&gt; спрятан за экраном, видимые квадраты — div-ы</p>
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" class="checkbox-custom" checked> Уведомлять по email
                    </label>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">checkbox-custom (app.css:281-314) — appearance:none + рисованная галочка</p>
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="radio" name="ds-radio-demo"> Раз в месяц</label>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">billing/show.blade.php:52,56 — совсем без стилей, чистый браузерный радио-баттон</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-6">
                Отдельно: тумблеров (iOS-style on/off) нигде нет, <code class="ds-swatch-label">&lt;select&gt;</code> нигде нет,
                <code class="ds-swatch-label">type="date"</code> нигде нет — на этих ролях просто пусто, не с чем сравнивать.
            </p>

            <h3 class="text-lg font-medium text-gray-700 mt-8 mb-3">Флеш-сообщения / алерты — 2 системы</h3>
            <div class="flex flex-col gap-3 max-w-md">
                <div class="text-green-600 text-sm">Профиль обновлён</div>
                <p class="ds-swatch-label text-xs text-gray-500 -mt-2">dashboard/billing/profile — голый цветной текст, без фона/рамки</p>
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">Ответ обязателен для заполнения</div>
                <p class="ds-swatch-label text-xs text-gray-500 -mt-2">question-region.blade.php:86, finish-region.blade.php:39 — оформленный алерт-бокс</p>
            </div>
        </section>

        {{-- ================= ТАБЫ ================= --}}
        <section id="tabs" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Табы / переключатели</h2>
            <p class="text-gray-600 mb-6">Два разных визуальных языка для одной и той же идеи «активный/неактивный переключатель».</p>

            <div class="mb-6">
                <div class="inline-flex p-1 gap-0.5 bg-gray-100 rounded-xl">
                    <button class="px-4 py-2 rounded-lg text-sm font-medium bg-white text-gray-900 shadow-sm">Персонаж</button>
                    <button class="px-4 py-2 rounded-lg text-sm font-medium text-gray-500">Аккаунт</button>
                </div>
                <p class="ds-swatch-label text-xs text-gray-500 mt-2">profile/show.blade.php:231-234 — «сегментный контрол», активная вкладка белая на серой дорожке, состояние в localStorage</p>
            </div>
            <div>
                <div class="flex flex-wrap gap-2">
                    <button class="px-3 py-1.5 rounded-full text-sm border bg-zinc-900 text-white border-zinc-900">Нужно сделать</button>
                    <button class="px-3 py-1.5 rounded-full text-sm border">На проверке</button>
                    <button class="px-3 py-1.5 rounded-full text-sm border">Сделано</button>
                </div>
                <p class="ds-swatch-label text-xs text-gray-500 mt-2">homeworks/index.blade.php:29-32 — отдельные обведённые пилюли, активная — сплошная чёрная; без localStorage, по умолчанию «todo»</p>
            </div>
        </section>

        {{-- ================= МОДАЛКИ / ДРОПДАУНЫ ================= --}}
        <section id="overlays" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Модалки, дропдауны, всплывающие панели</h2>
            <p class="text-gray-600 mb-6">Три независимые реализации «окна поверх контента», ни одна не переиспользует другую.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div class="rounded-xl border border-gray-200 p-4 bg-white">
                    <div class="font-medium mb-2">Модалка результата ответа</div>
                    <div class="text-gray-600 space-y-1 ds-swatch-label text-xs">
                        <div>question-region.blade.php:277-314</div>
                        <div>bg-black/40, rounded-2xl (Tailwind)</div>
                        <div>закрытие: только кнопкой</div>
                        <div>анимация: GSAP-таймлайн</div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 bg-white">
                    <div class="font-medium mb-2">Модалка покупки фона</div>
                    <div class="text-gray-600 space-y-1 ds-swatch-label text-xs">
                        <div>profile/show.blade.php:308-317</div>
                        <div>rgba(17,24,39,.5), border-radius:16px (свой CSS)</div>
                        <div>закрытие: клик снаружи + Escape</div>
                        <div>анимация: нет</div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 bg-white">
                    <div class="font-medium mb-2">Панель уведомлений (дропдаун)</div>
                    <div class="text-gray-600 space-y-1 ds-swatch-label text-xs">
                        <div>partials/student-notification-bell.blade.php</div>
                        <div>position:absolute, свой CSS</div>
                        <div>закрытие: клик снаружи + Escape</div>
                        <div>единственный настоящий dropdown (не full-screen)</div>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mt-4">Кастомных тултипов/поповеров нигде нет — только нативный <code class="ds-swatch-label">title="..."</code>. Тумблеров нет.</p>
        </section>

        {{-- ================= ТАБЛИЦЫ ================= --}}
        <section id="tables" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Таблицы</h2>
            <p class="text-gray-600 mb-6">Единственное место с настоящими HTML-таблицами (задание-«заполни таблицу») — продублировано в двух файлах с мелкими отличиями.</p>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-700">Год</th>
                            <th class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-700">Событие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="odd:bg-white">
                            <td class="px-3 py-2 border border-gray-200">1799</td>
                            <td class="px-3 py-2 border border-gray-200">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold">?</span>
                                <span class="text-gray-500 text-xs">— заполнить</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="ds-swatch-label text-xs text-gray-500 mt-2">
                task-prompt.blade.php:92-121 (заполнение, font-semibold, border-gray-100 на обёртке) vs submissions/show.blade.php:661-694
                (просмотр результата, font-medium, border-gray-200 на обёртке) — тот же макет таблицы держится в двух местах отдельно
            </p>
        </section>

        {{-- ================= ПРОЧЕЕ: АВАТАРЫ / ТОСТЫ / ПРОГРЕСС / ПАГИНАЦИЯ ================= --}}
        <section id="misc" class="mb-16 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Аватары, тосты, прогресс, пагинация</h2>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Аватар/маскот-круг — одна форма, четыре независимых места</h3>
            <div class="flex flex-wrap items-end gap-6 mb-8">
                <div class="text-center">
                    <span class="w-16 h-16 rounded-full border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-400 mx-auto">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path></svg>
                    </span>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">profile/show:328-337<br>реальный аватар ученика</p>
                </div>
                <div class="text-center">
                    <span class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto">
                        <img src="{{ asset('img/like.svg') }}" alt="" class="w-9 h-9">
                    </span>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">submissions/show:115<br>маскот-результат</p>
                </div>
                <div class="text-center">
                    <span class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto">
                        <img src="{{ asset('img/cool.svg') }}" alt="" class="w-9 h-9">
                    </span>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">layouts/main.blade.php:186<br>маскот в тосте</p>
                </div>
                <div class="text-center">
                    <span class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto">
                        <img src="{{ asset('img/person.svg') }}" alt="" class="w-9 h-9">
                    </span>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">question-region.blade.php:280-283<br>маскот в модалке результата</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-8">Одна и та же форма (круг, серый фон, картинка по центру) собирается заново в четырёх файлах — ни разу не переиспользуя друг друга.</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Прогресс — две несовместимые системы</h3>
            <div class="flex flex-wrap items-center gap-10 mb-8">
                <div class="w-48">
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-500" style="width: 62%"></div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">fish-card.blade.php:31-33 — линейный DOM/CSS бар</p>
                </div>
                <div class="text-center">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center" style="background: conic-gradient(#7c3aed 0% 74%, #ede9fe 74% 100%);">
                        <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center text-sm font-semibold">74%</div>
                    </div>
                    <p class="ds-swatch-label text-xs text-gray-500 mt-2">submissions/show.blade.php — Chart.js canvas-полукруг («pill gauge»), 2 использования (авто/ручная проверка), только приблизительная имитация здесь — реальный на canvas</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-8">Ни одна не переиспользует другую — линейный бар не может показать то же, что показывает canvas-гейдж, и наоборот; итоговый балл (самое важное число) не использует ни одну из них, а оформлен обычным текстом.</p>

            <h3 class="text-lg font-medium text-gray-700 mb-3">Пагинация — единственное место, дефолтная Laravel-тема</h3>
            <p class="text-sm text-gray-600">
                notifications/index.blade.php:73 вызывает <code class="ds-swatch-label">@{{ $notifications->links() }}</code>
                без указания вида — рендерится опубликованный <code class="ds-swatch-label">vendor/pagination/tailwind.blade.php</code>,
                нетронутый шаблон из коробки Laravel (rounded-md, ring, тёмная тема) — визуально не связан с остальным сайтом.
                Больше пагинации в student-вьюхах нет.
            </p>
        </section>

        {{-- ================= НЕЗАВЕРШЁННАЯ МИГРАЦИЯ ================= --}}
        <section id="leftover" class="mb-8 scroll-mt-16">
            <h2 class="text-2xl font-semibold mb-1">Незавершённая миграция на обычный CSS</h2>
            <p class="text-gray-600 mb-6">
                В части файлов уже пришлось заменить <code class="ds-swatch-label">sm:/md:/lg:</code> и классы с квадратными
                скобками на обычный <code class="ds-swatch-label">&lt;style&gt;</code> с media query — в этом браузере часть таких
                классов ненадёжно применяется. Эти места ещё не переведены:
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="text-left border-b border-gray-200 text-gray-500">
                            <th class="py-2 pr-4">Файл</th>
                            <th class="py-2 pr-4">Классы</th>
                            <th class="py-2">Что переключают</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2 pr-4 ds-swatch-label">courses/show.blade.php:81,84,107,157</td>
                            <td class="py-2 pr-4 ds-swatch-label">flex-col md:flex-row, w-full md:w-1/2, md:block hidden</td>
                            <td class="py-2">направление и ширина колонок hero-карточки</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 ds-swatch-label">lessons/show.blade.php:26,28,36,41,46,48,73</td>
                            <td class="py-2 pr-4 ds-swatch-label">md:grid-cols-7, md:col-span-5/2, min-h-[400px] md:min-h-full, flex-col md:/sm:flex-row</td>
                            <td class="py-2">раскладка видео/чата и карточек конспекта/домашки</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 ds-swatch-label">dashboard.blade.php:663</td>
                            <td class="py-2 pr-4 ds-swatch-label">grid sm:grid-cols-2 lg:grid-cols-2</td>
                            <td class="py-2">сетка карточек курсов (lg дублирует sm — мёртвый брейкпоинт)</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 ds-swatch-label">submissions/show.blade.php:137,175,183,185,194,312,314,323,828,861</td>
                            <td class="py-2 pr-4 ds-swatch-label">md:grid-cols-2, flex-col md:flex-row, max-w-[240px], top-[58px]</td>
                            <td class="py-2">раскладка карточек баллов и позиционирование текста над графиком</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4 ds-swatch-label">submissions/partials/task-prompt.blade.php:135,165</td>
                            <td class="py-2 pr-4 ds-swatch-label">md:grid-cols-2, max-h-[360px] sm:max-h-[380px]</td>
                            <td class="py-2">раскладка вопроса на сопоставление, высота картинки</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="border-t border-gray-200 pt-8 text-sm text-gray-500">
            17 вариантов кнопок, 14 вариантов карточек, 7 вариантов H1, 4 источника иконок,
            5 систем статус-бейджей, 6 систем полей ввода, 2 табов, 3 модалок/дропдаунов,
            2 таблиц, 2 систем прогресса и 4 копии одного и того же аватара-круга —
            следующий шаг: свести каждую роль к одному <code class="ds-swatch-label">x-ui.*</code>-компоненту.
        </div>
    </div>
</body>
</html>
