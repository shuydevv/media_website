{{--
    Единый каркас админки: сайдбар слева на десктопе, на телефоне — липкая
    шапка с бургером и выезжающее меню. Раскладка — классы .admin-* из
    resources/css/admin.css, содержимое страниц — компоненты x-ui.*
    (см. /admin/design-system, раздел «Компоненты админки»).

    Секции страницы:
      title   — название раздела: <title> и шапка на телефоне
      content — содержимое
    Стеки: head (стили страницы), page-scripts (скрипты/шаблоны в конце body).

    Флеши session('success'|'status'|'error') и список ошибок валидации
    выводятся здесь один раз — на страницах их повторять не нужно. Если форма
    показывает ошибки сама (свой текст вокруг списка), страница объявляет
    @section('own-errors', '1').
--}}
@php
    $adminNav = [
        null => [
            ['Главная', route('main.index'), 'main.index'],
        ],
        'Ученики' => [
            ['CRM', route('admin.crm.index'), 'admin.crm.*'],
            ['Пользователи', route('admin.user.index'), 'admin.user.*'],
            ['Оповещения', route('admin.announcements.index'), 'admin.announcements.*'],
            ['Промокоды', route('admin.promos.index'), 'admin.promos.*'],
        ],
        'Обучение' => [
            ['Курсы', route('admin.courses.index'), 'admin.courses.*'],
            ['Сессии', route('admin.sessions.index'), 'admin.sessions.*'],
            ['Уроки', route('admin.lessons.index'), 'admin.lessons.*'],
            ['Домашки', route('admin.homeworks.index'), 'admin.homeworks.*'],
            ['Банк заданий', route('admin.tasks.index'), 'admin.tasks.*'],
            ['Проверка работ', route('mentor.submissions.index'), 'mentor.*'],
        ],
        'Сайт' => [
            ['Посты', route('admin.post.index'), 'admin.post.*'],
            ['Шпаргалки', route('admin.shpargalka.index'), 'admin.shpargalka.*'],
            ['Упражнения', route('admin.exercise.index'), 'admin.exercise.*'],
            ['Категории', route('admin.category.index'), 'admin.category.*'],
            ['Разделы', route('admin.section.index'), 'admin.section.*'],
            ['Темы', route('admin.topic.index'), 'admin.topic.*'],
            ['Тэги', route('admin.tag.index'), 'admin.tag.*'],
        ],
        'Система' => [
            ['Дизайн-система', route('admin.design-system'), 'admin.design-system'],
            ['Открыть сайт', route('index'), null],
        ],
    ];
    $adminUser = auth()->user();
    $adminUserName = $adminUser
        ? (trim(($adminUser->first_name ?? '').' '.($adminUser->last_name ?? '')) ?: ($adminUser->name ?: $adminUser->email))
        : '';
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">

    <title>@yield('title', 'Админка') — Школа Полтавского</title>

    @vite('resources/css/app.css')
    @stack('head')
</head>
<body class="admin-portal">
<div class="admin-shell" id="admin-shell">

    <header class="admin-topbar">
        <button type="button" class="admin-topbar-btn" data-admin-nav-open aria-label="Открыть меню" aria-controls="admin-sidebar" aria-expanded="false">
            <x-icon name="menu-01" class="w-6 h-6" />
        </button>
        <div class="sans-medium text-base text-zinc-900 truncate">@yield('title', 'Админка')</div>
    </header>

    <div class="admin-backdrop" data-admin-nav-close></div>

    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="flex items-center justify-between gap-2 pl-6 pr-3 pt-5 pb-1">
            <a href="{{ route('main.index') }}" class="block min-w-0">
                <div class="font-oktyabrina text-xl leading-none tracking-wide text-zinc-800">Школа Полтавского</div>
                <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mt-1.5">Админ-панель</div>
            </a>
            <button type="button" class="admin-topbar-btn admin-sidebar-close" data-admin-nav-close aria-label="Закрыть меню">
                <x-icon name="x-close" class="w-5 h-5" />
            </button>
        </div>

        <nav class="flex-1 px-3 pb-4" aria-label="Разделы админки">
            @foreach ($adminNav as $caption => $links)
                @if ($caption)
                    <div class="admin-nav-caption">{{ $caption }}</div>
                @else
                    <div class="mt-4"></div>
                @endif
                @foreach ($links as [$label, $url, $pattern])
                    @php $isActive = $pattern && request()->routeIs($pattern); @endphp
                    <a href="{{ $url }}" class="admin-nav-link {{ $isActive ? 'is-active' : '' }}" @if ($isActive) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            @endforeach
        </nav>

        <div class="border-t border-zinc-200 px-3 py-3">
            @if ($adminUserName !== '')
                <div class="px-3 pb-1 text-xs text-zinc-400 truncate">{{ $adminUserName }}</div>
            @endif
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit" class="admin-nav-link">Выйти</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-content">
            @foreach (['success' => 'green', 'status' => 'green', 'error' => 'red'] as $flashKey => $flashTone)
                @if (session($flashKey))
                    <x-ui.alert :tone="$flashTone" class="mb-4">{{ session($flashKey) }}</x-ui.alert>
                @endif
            @endforeach

            @if ($errors->any())
                @sectionMissing('own-errors')
                    <x-ui.alert tone="red" class="mb-4">
                        <div class="font-medium mb-1">Не сохранено — проверьте поля:</div>
                        <ul class="list-disc pl-5 space-y-0.5">
                            @foreach ($errors->all() as $errorMessage)
                                <li>{{ $errorMessage }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif
            @endif

            @yield('content')
        </div>
    </main>
</div>

@stack('page-scripts')

<script>
(function () {
    var shell = document.getElementById('admin-shell');
    var openBtn = document.querySelector('[data-admin-nav-open]');

    function setOpen(open) {
        shell.classList.toggle('is-nav-open', open);
        document.body.style.overflow = open ? 'hidden' : '';
        if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (openBtn) openBtn.addEventListener('click', function () { setOpen(true); });
    document.querySelectorAll('[data-admin-nav-close]').forEach(function (el) {
        el.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });

    // Подтверждение для форм с data-confirm (см. x-ui.action-form): текст
    // берётся из атрибута, а не вшивается в onsubmit="confirm('…')".
    document.addEventListener('submit', function (e) {
        var message = e.target.getAttribute && e.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) e.preventDefault();
    });
})();
</script>
</body>
</html>
