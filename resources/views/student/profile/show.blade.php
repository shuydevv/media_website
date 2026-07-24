@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
    <h1 class="sans-medium text-2xl md:text-3xl mb-6 text-zinc-900">Профиль</h1>

    @if (session('success'))
        <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    {{-- Вкладки — обычным CSS/JS, не Alpine/htmx: переключение чисто
         визуальное (show/hide), состояние запоминается в localStorage, чтобы
         редактирование персонажа/аккаунта переживало перезагрузку страницы
         после сохранения формы. По умолчанию (ничего не запомнено) —
         вкладка «Персонаж». --}}
    <style>
        /* h1/h2 в кабинете ученика уже не уходят в serif — см.
           .student-portal h1, .student-portal h2 в app.css. Точечный
           .profile-heading здесь больше не нужен. */

        /* Переключатель вкладок — сегментированный контрол (серый трек +
           белая "таблетка" активного пункта), а не пара кнопок: раньше
           активная вкладка была залита тёмным и визуально не отличалась от
           обычной кнопки действия на странице. */
        .profile-tabs {
            display: inline-flex;
            padding: 4px;
            gap: 2px;
            background: #f3f4f6;
            border-radius: 12px;
        }
        .profile-tab-btn {
            padding: 8px 18px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 500;
            color: #71717a; /* zinc-500 */
            background: transparent;
            border: none;
            cursor: pointer;
            transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
        }
        .profile-tab-btn:hover {
            color: #18181b; /* zinc-900 */
        }
        .profile-tab-btn.profile-tab-btn-active {
            background: #fff;
            color: #18181b; /* zinc-900 */
            box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
        }
        .profile-tab-panel[hidden] {
            display: none;
        }
        .fish-bg-thumb-btn {
            display: block;
            width: 100%;
            padding: 0;
            margin: 0;
            border: 2px solid transparent;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            background: none;
            line-height: 0;
        }
        .fish-bg-thumb-btn.fish-bg-thumb-selected {
            border-width: 6px;
            border-color: #3b82f6;
        }
        .fish-bg-thumb-wrap {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }
        .fish-bg-thumb-wrap img {
            display: block;
            filter: grayscale(65%);
            opacity: .55;
        }
        .fish-bg-lock-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(17, 24, 39, .75);
            color: #fff;
            font-size: 22px;
            line-height: 1;
            padding: 4px 8px;
            border-radius: 8px;
        }
        .fish-bg-buy-form {
            position: absolute;
            left: 50%;
            bottom: 8px;
            transform: translateX(-50%);
            margin: 0;
        }
        .fish-bg-buy-btn {
            display: inline-block;
            width: auto;
            white-space: nowrap;
            border: none;
            border-radius: 9999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            color: #fff;
            background: rgba(17, 24, 39, .85);
            cursor: pointer;
            transition: background-color .15s ease;
        }
        .fish-bg-buy-btn:hover {
            background: rgba(17, 24, 39, .95);
        }
        .fish-bg-buy-btn:disabled {
            background: rgba(107, 114, 128, .5);
            cursor: not-allowed;
        }
        .fish-bg-buy-btn:disabled:hover {
            background: rgba(107, 114, 128, .5);
        }

        /* Фоны на десктопе — сетка 3 в ряд (fish-bg-grid-desktop), на
           мобильном — карусель-слайдер (fish-bg-swiper, тот же Swiper, что
           уже используется на дашборде для расписания). Переключение между
           ними — обычным media-query, не Tailwind-префиксами (sm:/md:): в
           этом браузере часть таких классов ненадёжно применяется, уже
           несколько раз ловили на других страницах. Swiper инициализируется
           в JS только когда карусель реально видна (см. скрипт внизу) —
           на скрытом (display:none) контейнере он не может измерить ширину. */
        .fish-bg-swiper {
            display: none;
        }
        @media (max-width: 480px) {
            .profile-card {
                padding: 1rem;
            }
            .fish-bg-grid-desktop {
                display: none;
            }
            .fish-bg-swiper {
                display: block;
            }
            .fish-bg-buy-btn {
                padding: 8px 18px;
                font-size: 14px;
            }
            .fish-bg-lock-badge {
                font-size: 24px;
                padding: 5px 9px;
            }
        }

        /* Модалка-подтверждение покупки фона — защита от мисклика: сам клик
           по "Купить" форму не отправляет, только открывает эту модалку. */
        .fish-bg-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 16px;
        }
        .fish-bg-modal-overlay[hidden] {
            display: none;
        }
        .fish-bg-modal {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            max-width: 320px;
            width: 100%;
            text-align: center;
        }
        .fish-bg-modal-title {
            font-size: 16px;
            font-weight: 500;
            color: #18181b; /* zinc-900 */
            margin-bottom: 8px;
        }
        .fish-bg-modal-text {
            font-size: 14px;
            color: #52525b; /* zinc-600 */
            margin-bottom: 20px;
        }
        .fish-bg-modal-actions {
            display: flex;
            gap: 8px;
        }
        .fish-bg-modal-btn {
            flex: 1;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color .15s ease;
        }
        .fish-bg-modal-cancel {
            background: #f4f4f5; /* zinc-100 */
            color: #3f3f46; /* zinc-700 */
        }
        .fish-bg-modal-cancel:hover {
            background: #e4e4e7; /* zinc-200 */
        }
        .fish-bg-modal-confirm {
            background: #18181b;
            color: #fff;
        }
        .fish-bg-modal-confirm:hover {
            background: #27272a;
        }
    </style>

    <div class="profile-tabs mb-5">
        <button type="button" class="profile-tab-btn" data-tab="character">Персонаж</button>
        <button type="button" class="profile-tab-btn" data-tab="account">Аккаунт</button>
    </div>

    {{-- Вкладка «Персонаж» --}}
    <div id="profile-tab-character" class="profile-tab-panel" hidden>
        <x-ui.card class="profile-card">
            <div class="sans-medium text-lg text-zinc-900 mb-4">Персонаж</div>

            {{-- Имя — отдельная форма с явным сохранением, как остальные
                 текстовые поля на этой странице. Выбор/покупка фона ниже —
                 не часть этой формы (вложенные <form> в HTML невозможны):
                 у каждого фона своя маленькая форма, кликом сразу
                 применяющая выбор или покупку — так же, как загрузка
                 аватара на вкладке «Аккаунт» отправляется сразу по onchange. --}}
            <form method="POST" action="{{ route('student.profile.character.update') }}" class="mb-8">
                @csrf
                <label class="block max-w-sm">
                    <span class="text-sm text-zinc-700">Имя персонажа</span>
                    {{-- value — сырое $user->fish_name (null, пока не задано
                         своё имя), НЕ $fishName (тот с фолбэком на текущий
                         уровень для отображения). Раньше в value подставлялся
                         уже разрешённый фолбэк — значит, стоило один раз
                         нажать "Сохранить" не меняя поле, и текущее название
                         уровня навсегда застревало как "своё" имя, переставая
                         обновляться при левел-апе. Плейсхолдер по-прежнему
                         показывает текущий уровень как подсказку. --}}
                    <input type="text" name="fish_name" placeholder="{{ $fishLevelName }}"
                           value="{{ old('fish_name', $user->fish_name) }}" maxlength="40"
                           class="mt-1 w-full border rounded-lg px-3 py-2 input-focus">
                    @error('fish_name')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <div class="pt-5">
                    <x-ui.button type="submit" size="sm">
                        Сохранить
                    </x-ui.button>
                </div>
            </form>

            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="sans-medium text-lg text-zinc-900">Фон</span>
                    <span class="text-sm text-zinc-500">Корм: <span class="text-base font-medium text-zinc-900">{{ $fishBalance }}</span></span>
                </div>
                {{-- Десктоп: сетка 3 в ряд. --}}
                <div class="fish-bg-grid-desktop grid grid-cols-3 gap-5 w-full">
                    @foreach ($fishBackgrounds as $slug => $label)
                        <div>
                            @include('student.partials.profile-fish-background-item')
                        </div>
                    @endforeach
                </div>

                {{-- Мобильная: карусель-слайдер (Swiper, инициализируется в
                     скрипте внизу только когда реально видна). --}}
                <div class="swiper fish-bg-swiper">
                    <div class="swiper-wrapper">
                        @foreach ($fishBackgrounds as $slug => $label)
                            <div class="swiper-slide">
                                @include('student.partials.profile-fish-background-item')
                            </div>
                        @endforeach
                    </div>
                </div>

                @error('fish_background')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>
        </x-ui.card>
    </div>

    {{-- Подтверждение покупки фона — защита от мисклика: клик по "Купить" не
         отправляет форму сразу, только открывает эту модалку (см. скрипт внизу). --}}
    <div id="fish-bg-buy-modal" class="fish-bg-modal-overlay" hidden>
        <div class="fish-bg-modal">
            <div class="fish-bg-modal-title">Купить фон?</div>
            <div class="fish-bg-modal-text"></div>
            <div class="fish-bg-modal-actions">
                <button type="button" class="fish-bg-modal-btn fish-bg-modal-cancel">Отмена</button>
                <button type="button" class="fish-bg-modal-btn fish-bg-modal-confirm">Купить</button>
            </div>
        </div>
    </div>

    {{-- Вкладка «Аккаунт» --}}
    <div id="profile-tab-account" class="profile-tab-panel" hidden>
        <div class="flex flex-col gap-4">
            {{-- Личные данные --}}
            <x-ui.card class="profile-card">
                <div class="sans-medium text-lg text-zinc-900 mb-4">Личные данные</div>

                {{-- Аватар --}}
                <div class="flex items-center gap-4 mb-4 pb-4 border-b border-gray-200">
                    @if ($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="Фото профиля"
                             class="w-16 h-16 rounded-full object-cover border border-gray-200 shrink-0">
                    @else
                        <span class="w-16 h-16 rounded-full border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-400 shrink-0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                            </svg>
                        </span>
                    @endif

                    <div class="flex flex-col gap-2">
                        <form method="POST" action="{{ route('student.profile.avatar.update') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                            @csrf
                            <label class="text-sm px-3 py-1.5 rounded-lg border cursor-pointer hover:bg-gray-50 transition">
                                Загрузить фото
                                <input type="file" name="avatar" accept="image/*" class="hidden" onchange="this.form.submit()">
                            </label>
                        </form>
                        @error('avatar')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror

                        @if ($user->avatar_url)
                            <form method="POST" action="{{ route('student.profile.avatar.remove') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-zinc-500 hover:text-red-600 transition block mx-auto">
                                    Удалить фото
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Email — идентификатор входа (вход только по OTP на почту),
                     менять его здесь нельзя: это отдельный процесс с повторным
                     подтверждением, вне текущего объёма. --}}
                <div class="max-w-sm mb-4 pb-4 border-b border-gray-200">
                    <span class="text-sm text-zinc-700">Email</span>
                    <div class="mt-1 flex items-center gap-2 flex-wrap">
                        <span class="text-sm text-zinc-900">{{ $user->email }}</span>
                        @if ($user->hasVerifiedEmail())
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                Подтверждён
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                                Не подтверждён
                            </span>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('student.profile.update') }}" class="space-y-4 max-w-sm">
                    @csrf

                    <label class="block">
                        <span class="text-sm text-zinc-700">Имя</span>
                        <input type="text" name="first_name" placeholder="Иван"
                               value="{{ old('first_name', $user->first_name) }}"
                               class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" required>
                        @error('first_name')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm text-zinc-700">Фамилия</span>
                        <input type="text" name="last_name" placeholder="Иванов"
                               value="{{ old('last_name', $user->last_name) }}"
                               class="mt-1 w-full border rounded-lg px-3 py-2 input-focus">
                        @error('last_name')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm text-zinc-700">Логин в телеграм</span>
                        <input type="text" name="name" placeholder="@username"
                               value="{{ old('name', $user->name) }}"
                               class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" required>
                        @error('name')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="pt-2 border-t border-gray-200">
                        <p class="text-sm text-zinc-500 mt-4 mb-3">Оставьте поля пароля пустыми, если не хотите его менять.</p>

                        <label class="block mb-4">
                            <span class="text-sm text-zinc-700">Текущий пароль</span>
                            <input type="password" name="current_password" placeholder="Введите текущий пароль"
                                   class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" autocomplete="current-password">
                            @error('current_password')
                                <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block mb-4">
                            <span class="text-sm text-zinc-700">Новый пароль</span>
                            <input type="password" name="password" placeholder="Не менее 8 символов"
                                   class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" autocomplete="new-password">
                            @error('password')
                                <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm text-zinc-700">Подтверждение нового пароля</span>
                            <input type="password" name="password_confirmation" placeholder="Повторите новый пароль"
                                   class="mt-1 w-full border rounded-lg px-3 py-2 input-focus" autocomplete="new-password">
                        </label>
                    </div>

                    <div class="pt-2">
                        <x-ui.button type="submit" size="sm">
                            Сохранить
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Уведомления --}}
            <x-ui.card class="profile-card">
                <div class="sans-medium text-lg text-zinc-900 mb-1">Уведомления</div>
                <p class="text-sm text-zinc-500 mb-4">Какие уведомления присылать на почту и показывать в кабинете.</p>

                <form method="POST" action="{{ route('student.profile.notifications.update') }}">
                    @csrf

                    <div class="flex flex-col gap-5">
                        @foreach ($notificationTypes as $group => $types)
                            <div>
                                <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-2">{{ $group }}</div>
                                <div class="flex flex-col gap-3">
                                    @foreach ($types as $type)
                                        <div class="flex items-center gap-2">
                                            <input class="checkbox-custom" type="checkbox" name="enabled[]"
                                                   id="notif-{{ $type['slug'] }}" value="{{ $type['slug'] }}"
                                                   {{ $type['enabled'] ? 'checked' : '' }}>
                                            <label class="text-sm text-zinc-700 cursor-pointer" for="notif-{{ $type['slug'] }}">
                                                {{ $type['label'] }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-6">
                        <x-ui.button type="submit" size="sm">
                            Сохранить
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</div>

<script>
    (function () {
        var STORAGE_KEY = 'profile_active_tab';
        var validTabs = ['character', 'account'];
        var buttons = document.querySelectorAll('.profile-tab-btn');

        function activate(tab) {
            if (validTabs.indexOf(tab) === -1) tab = 'character';

            validTabs.forEach(function (t) {
                var panel = document.getElementById('profile-tab-' + t);
                if (panel) panel.hidden = (t !== tab);
            });
            buttons.forEach(function (btn) {
                btn.classList.toggle('profile-tab-btn-active', btn.dataset.tab === tab);
            });

            try { localStorage.setItem(STORAGE_KEY, tab); } catch (e) {}
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () { activate(btn.dataset.tab); });
        });

        var saved = null;
        try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        activate(saved || 'character');
    })();

    // Подтверждение покупки фона — сам клик по "Купить" форму не отправляет,
    // только открывает модалку; реальный submit — только по кнопке "Купить"
    // внутри неё. Один обработчик на document (делегирование), потому что
    // кнопок "Купить" много (по одной на каждый ещё не открытый фон).
    (function () {
        var modal = document.getElementById('fish-bg-buy-modal');
        if (!modal) return;

        var textEl = modal.querySelector('.fish-bg-modal-text');
        var cancelBtn = modal.querySelector('.fish-bg-modal-cancel');
        var confirmBtn = modal.querySelector('.fish-bg-modal-confirm');
        var pendingForm = null;

        function openModal(form, label, price) {
            pendingForm = form;
            textEl.textContent = 'Открыть фон «' + label + '» за ' + price + ' корма?';
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
            pendingForm = null;
        }

        document.addEventListener('click', function (evt) {
            var btn = evt.target.closest('.fish-bg-buy-btn');
            if (!btn) return;
            evt.preventDefault();
            openModal(btn.closest('form'), btn.dataset.bgLabel || '', btn.dataset.bgPrice || '');
        });

        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (evt) {
            if (evt.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (evt) {
            if (evt.key === 'Escape' && !modal.hidden) closeModal();
        });
        confirmBtn.addEventListener('click', function () {
            if (pendingForm) pendingForm.submit();
            closeModal();
        });
    })();

    // Мобильная карусель фонов — тот же Swiper, что уже используется на
    // дашборде для расписания уроков (подключён глобально в layouts/main.blade.php).
    // Инициализируем лениво, по границе media query, а не всегда: Swiper не
    // может измерить ширину слайдов на скрытом (display:none) контейнере,
    // а на десктопе карусель как раз скрыта в пользу обычной сетки.
    (function () {
        var swiperEl = document.querySelector('.fish-bg-swiper');
        if (!swiperEl || typeof Swiper === 'undefined') return;

        var mq = window.matchMedia('(max-width: 480px)');
        var instance = null;

        function sync() {
            if (mq.matches && !instance) {
                instance = new Swiper(swiperEl, {
                    slidesPerView: 1.15,
                    spaceBetween: 14,
                });
            } else if (!mq.matches && instance) {
                instance.destroy(true, true);
                instance = null;
            }
        }

        sync();
        if (mq.addEventListener) {
            mq.addEventListener('change', sync);
        } else {
            mq.addListener(sync);
        }
    })();
</script>
@endsection
