@extends('layouts.main')

@section('title', 'Профиль — Школа Полтавского')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="sans-medium text-xl md:text-3xl mb-4 sm:mb-6 text-zinc-900">Профиль</h1>

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
            background: rgb(var(--c-gray-100, 243 244 246));
            border-radius: 12px;
        }
        .profile-tab-btn {
            padding: 8px 18px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 500;
            color: rgb(var(--c-zinc-500, 113 113 122)); /* zinc-500 */
            background: transparent;
            border: none;
            cursor: pointer;
            transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
        }
        .profile-tab-btn:hover {
            color: rgb(var(--c-zinc-900, 24 24 27)); /* zinc-900 */
        }
        .profile-tab-btn.profile-tab-btn-active {
            background: #fff;
            color: rgb(var(--c-zinc-900, 24 24 27)); /* zinc-900 */
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
        /* Рамка выбора — 3px, как у кружков еды (fish-acc-chip-selected). */
        .fish-bg-thumb-btn.fish-bg-thumb-selected {
            border-width: 3px;
            border-color: #3b82f6;
        }
        .fish-bg-thumb-wrap {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }
        /* Закрытый фон — в цвете, как закрытая еда: картинку нужно видеть,
           чтобы решить, покупать ли. Не хватает корма — бледнее. */
        .fish-bg-thumb-wrap img {
            display: block;
            opacity: .8;
        }
        .fish-bg-thumb-wrap.fish-bg-poor > img {
            opacity: .5;
        }
        /* Иконка корма (бургер) в кнопке "Купить за" — не фон-превью, не должна
           затемняться/сереть или проваливаться на новую строку от общего
           правила ".fish-bg-thumb-wrap img" выше. */
        .fish-bg-buy-btn img {
            display: inline-block;
            filter: none;
            opacity: 1;
            vertical-align: middle;
        }
        /* Замок — тот же светлый кружок, что у еды (.fish-food-lock-badge),
           только крупнее под размер плитки. */
        .fish-bg-lock-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 9999px;
            background: rgb(var(--c-zinc-100, 244 244 245));
            border: 1px solid rgb(var(--c-zinc-300, 212 212 216));
            color: rgb(var(--c-zinc-700, 63 63 70));
        }
        /* На всю ширину плитки + центрирование флексом, а не left:50% +
           translateX(-50%): у абсолютного блока с left:50% браузер
           ограничивает ширину половиной плитки, и кнопка с широким шрифтом
           не помещалась — иконка корма упиралась в край. */
        .fish-bg-buy-form {
            position: absolute;
            left: 8px;
            right: 8px;
            bottom: 8px;
            display: flex;
            justify-content: center;
            margin: 0;
        }
        /* inline-flex, а не inline-block: иначе иконка корма выпирала за
           низ кнопки (у плитки line-height: 0 от .fish-bg-thumb-btn). */
        .fish-bg-buy-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            width: auto;
            white-space: nowrap;
            border: none;
            border-radius: 9999px;
            padding: 8px 16px;
            font-size: 13px;
            line-height: 18px;
            font-weight: 500;
            color: #fff;
            background: rgb(var(--c-gray-900, 17 24 39) / .85);
            cursor: pointer;
            transition: background-color .15s ease;
        }
        .fish-bg-buy-btn:hover {
            background: rgb(var(--c-gray-900, 17 24 39) / .95);
        }
        .fish-bg-buy-btn:disabled,
        .fish-bg-buy-btn:disabled:hover {
            background: rgb(255 255 255 / .85);
            color: rgb(var(--c-zinc-500, 113 113 122));
            cursor: not-allowed;
        }
        .fish-bg-buy-btn:disabled img {
            opacity: .5;
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
        @media (min-width: 768px) {
            .fish-bg-grid-desktop {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        @media (min-width: 1024px) {
            .fish-bg-grid-desktop {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
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
        }

        /* Модалка-подтверждение покупки фона — защита от мисклика: сам клик
           по "Купить" форму не отправляет, только открывает эту модалку. */
        .fish-bg-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgb(var(--c-gray-900, 17 24 39) / .5);
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
            color: rgb(var(--c-zinc-900, 24 24 27)); /* zinc-900 */
            margin-bottom: 8px;
        }
        .fish-bg-modal-text {
            font-size: 14px;
            color: rgb(var(--c-zinc-600, 82 82 91)); /* zinc-600 */
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
            background: rgb(var(--c-zinc-100, 244 244 245)); /* zinc-100 */
            color: rgb(var(--c-zinc-700, 63 63 70)); /* zinc-700 */
        }
        .fish-bg-modal-cancel:hover {
            background: rgb(var(--c-zinc-200, 228 228 231)); /* zinc-200 */
        }
        .fish-bg-modal-confirm {
            background: rgb(var(--c-zinc-900, 24 24 27));
            color: #fff;
        }
        .fish-bg-modal-confirm:hover {
            background: rgb(var(--c-zinc-800, 39 39 42));
        }

        /* Аксессуары — вторая косметическая категория, без картинок (нет
           арта), просто эмодзи в кружке-чипе. Компактнее фонов: сетка сама
           переносится по ширине, отдельной мобильной карусели не нужно. */
        .fish-acc-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }
        .fish-acc-chip {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 9999px;
            border: 2px solid rgb(var(--c-zinc-200, 228 228 231)); /* zinc-200 */
            background: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            position: relative;
            padding: 0;
        }
        .fish-acc-chip.fish-acc-chip-selected {
            border-width: 3px;
            border-color: #3b82f6;
        }
        .fish-acc-chip.fish-acc-locked {
            opacity: .55;
            filter: grayscale(65%);
        }
        .fish-acc-chip:disabled {
            cursor: not-allowed;
        }
        /* Еда — тот же чип, но закрытая не обесцвечивается: картинку нужно
           хорошо видеть, чтобы решить, покупать ли. Закрытость показывают
           светлый замок в углу и цена под названием; не хватает корма —
           картинка бледнее, цена серая. */
        .fish-food-item {
            width: 88px;
        }
        .fish-acc-chip.fish-food-chip {
            width: 76px;
            height: 76px;
        }
        /* Закрытая еда — пунктирная рамка и серая подложка, картинка
           бледнее (но в цвете), чтобы с первого взгляда отличалась от уже
           открытой. */
        .fish-acc-chip.fish-food-locked {
            border-style: dashed;
            border-color: rgb(var(--c-zinc-300, 212 212 216));
            background: rgb(var(--c-zinc-50, 250 250 250));
        }
        .fish-food-locked img {
            opacity: .6;
        }
        .fish-food-lock-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 9999px;
            background: rgb(var(--c-zinc-100, 244 244 245));
            border: 1px solid rgb(var(--c-zinc-300, 212 212 216));
            color: rgb(var(--c-zinc-700, 63 63 70));
        }
        .fish-acc-lock-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            font-size: 13px;
            background: rgb(var(--c-gray-900, 17 24 39) / .75);
            border-radius: 9999px;
            padding: 1px 3px;
            line-height: 1;
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

            {{-- Еда — чем кормить акулу. Тот же чип-ряд, что у аксессуаров
                 (fish-acc-*), но с картинками из public/img/food. Выбранная
                 еда становится иконкой корма во всём интерфейсе. --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="sans-medium text-lg text-zinc-900">Еда</span>
                    <span class="text-sm text-zinc-500 inline-flex items-center gap-1">
                        Корм:
                        <img src="{{ auth()->user()->fish_food_icon_url }}" alt="" class="w-5 h-5 inline-block">
                        <span class="text-base font-medium text-zinc-900">{{ $fishBalance }}</span>
                    </span>
                </div>
                <div class="fish-acc-grid">
                    @foreach ($fishFoods as $slug => $label)
                        <div class="fish-food-item">
                            @include('student.partials.profile-fish-food-item')
                        </div>
                    @endforeach
                </div>
                @error('fish_food')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="mt-8">
                <div class="sans-medium text-lg text-zinc-900 mb-4">Фон</div>
                {{-- Десктоп: сетка 3–5 в ряд по ширине. --}}
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

            {{-- Аксессуары временно скрыты (@if(false), не удалено —
                 включить обратно, просто убрав это условие) --}}
            @if(false)
            {{-- Аксессуары — второй ряд косметики под фонами, тот же
                 принцип (бесплатный дефолт "none" + платные за корм), но
                 без картинок: реального арта нет, вместо него эмодзи-чипы
                 (см. FishFoodService::accessoryEmoji()). --}}
            <div class="mt-8">
                <div class="flex items-center justify-between mb-4">
                    <span class="sans-medium text-lg text-zinc-900">Аксессуар</span>
                </div>
                <div class="fish-acc-grid">
                    @foreach ($fishAccessories as $slug => $label)
                        <div>
                            @include('student.partials.profile-fish-accessory-item')
                        </div>
                    @endforeach
                </div>
                @error('fish_accessory')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>
            @endif
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

    {{-- Подтверждение покупки еды — та же модалка-паттерн, что у фонов. --}}
    <div id="fish-food-buy-modal" class="fish-bg-modal-overlay" hidden>
        <div class="fish-bg-modal">
            <div class="fish-bg-modal-title">Открыть еду?</div>
            <div class="fish-bg-modal-text"></div>
            <div class="fish-bg-modal-actions">
                <button type="button" class="fish-bg-modal-btn fish-bg-modal-cancel">Отмена</button>
                <button type="button" class="fish-bg-modal-btn fish-bg-modal-confirm">Купить</button>
            </div>
        </div>
    </div>

    {{-- Модалка покупки аксессуара — тоже временно скрыта вместе с самой
         секцией аксессуаров выше (@if(false)); JS ниже уже проверяет
         document.getElementById('fish-acc-buy-modal') на null, так что
         скрипт трогать не нужно. --}}
    @if(false)
    {{-- Подтверждение покупки аксессуара — та же модалка-паттерн, что и у
         фонов, отдельный узел + JS-блок ниже, чтобы не путать друг с
         другом форму, ожидающую подтверждения (pendingForm). --}}
    <div id="fish-acc-buy-modal" class="fish-bg-modal-overlay" hidden>
        <div class="fish-bg-modal">
            <div class="fish-bg-modal-title">Открыть аксессуар?</div>
            <div class="fish-bg-modal-text"></div>
            <div class="fish-bg-modal-actions">
                <button type="button" class="fish-bg-modal-btn fish-bg-modal-cancel">Отмена</button>
                <button type="button" class="fish-bg-modal-btn fish-bg-modal-confirm">Купить</button>
            </div>
        </div>
    </div>
    @endif

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
                            <x-icon name="user-01" class="w-7 h-7" />
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
                        <span class="text-sm text-zinc-700">Имя пользователя в телеграм</span>
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

            {{-- Вход без пароля — постоянная личная ссылка (Auth\LoginLinkController).
                 Только ученикам, см. User::canUseLoginLink(). --}}
            @if ($user->canUseLoginLink())
                <x-ui.card class="profile-card">
                    <div class="sans-medium text-lg text-zinc-900 mb-1">Вход без пароля</div>
                    <p class="text-sm text-zinc-500 mb-4">
                        Личная ссылка: открываете её — и вы сразу в кабинете, пароль не нужен.
                        Удобно сохранить в закладки или в «Избранное» в Телеграм.
                        Никому её не пересылайте — по ней можно войти в ваш аккаунт.
                    </p>

                    @if ($user->login_link_url)
                        <div class="flex flex-col sm:flex-row gap-2 max-w-xl">
                            <input type="text" id="login-link-url" readonly value="{{ $user->login_link_url }}"
                                   class="w-full border rounded-lg px-3 py-2 text-sm text-zinc-700 bg-gray-50 input-focus"
                                   onfocus="this.select()">
                            <x-ui.button size="sm" id="login-link-copy" class="shrink-0">
                                Скопировать
                            </x-ui.button>
                        </div>

                        <p class="text-xs text-zinc-500 mt-2">
                            @if ($user->login_token_used_at)
                                Последний вход по ссылке: {{ $user->login_token_used_at->timezone($user->timezone ?: 'Europe/Moscow')->translatedFormat('j F Y, H:i') }}
                                ({{ $user->login_token_used_device }})
                            @else
                                По этой ссылке ещё не входили.
                            @endif
                        </p>

                        <div class="flex flex-wrap items-center gap-4 mt-4">
                            <form method="POST" action="{{ route('student.profile.login-link.issue') }}"
                                  onsubmit="return confirm('Старая ссылка перестанет работать. Перевыпустить?')">
                                @csrf
                                <button type="submit" class="text-sm text-zinc-700 hover:text-zinc-900 underline underline-offset-2 transition">
                                    Перевыпустить ссылку
                                </button>
                            </form>
                            <form method="POST" action="{{ route('student.profile.login-link.revoke') }}"
                                  onsubmit="return confirm('Отключить вход по ссылке?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-zinc-500 hover:text-red-600 transition">
                                    Отключить
                                </button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('student.profile.login-link.issue') }}">
                            @csrf
                            <x-ui.button type="submit" size="sm">
                                Создать ссылку для входа
                            </x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            @endif

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

            {{-- Выход — раньше жил футером на дашборде, убрали оттуда,
                 единственное место теперь здесь. --}}
            <x-ui.card class="profile-card">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:text-red-700 transition">
                        Выйти из аккаунта
                    </button>
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

    (function () {
        var btn = document.getElementById('login-link-copy');
        var input = document.getElementById('login-link-url');
        if (!btn || !input) return;

        btn.addEventListener('click', function () {
            var done = function () {
                var label = btn.textContent;
                btn.textContent = 'Скопировано';
                setTimeout(function () { btn.textContent = label; }, 2000);
            };
            // clipboard API есть только в secure context — на http-деве
            // откатываемся на execCommand.
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(done);
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });
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

    // Подтверждение покупки аксессуара — тот же паттерн, что и у фонов
    // выше, но отдельная модалка/pendingForm, чтобы не смешивать очередь
    // подтверждения между двумя категориями.
    (function () {
        var modal = document.getElementById('fish-acc-buy-modal');
        if (!modal) return;

        var textEl = modal.querySelector('.fish-bg-modal-text');
        var cancelBtn = modal.querySelector('.fish-bg-modal-cancel');
        var confirmBtn = modal.querySelector('.fish-bg-modal-confirm');
        var pendingForm = null;

        function openModal(form, label, price) {
            pendingForm = form;
            textEl.textContent = 'Открыть аксессуар «' + label + '» за ' + price + ' корма?';
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
            pendingForm = null;
        }

        document.addEventListener('click', function (evt) {
            var btn = evt.target.closest('.fish-acc-locked');
            if (!btn) return;
            evt.preventDefault();
            openModal(btn.closest('form'), btn.dataset.accLabel || '', btn.dataset.accPrice || '');
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

    // Подтверждение покупки еды — тот же паттерн, своя модалка/pendingForm.
    (function () {
        var modal = document.getElementById('fish-food-buy-modal');
        if (!modal) return;

        var textEl = modal.querySelector('.fish-bg-modal-text');
        var cancelBtn = modal.querySelector('.fish-bg-modal-cancel');
        var confirmBtn = modal.querySelector('.fish-bg-modal-confirm');
        var pendingForm = null;

        function openModal(form, label, price) {
            pendingForm = form;
            textEl.textContent = 'Открыть «' + label + '» за ' + price + ' корма?';
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
            pendingForm = null;
        }

        document.addEventListener('click', function (evt) {
            var btn = evt.target.closest('.fish-food-locked');
            if (!btn) return;
            evt.preventDefault();
            openModal(btn.closest('form'), btn.dataset.foodLabel || '', btn.dataset.foodPrice || '');
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
    // дашборде для расписания уроков (подключён глобально в layouts/main.blade.php
    // через <script defer>). Обязательно ждём DOMContentLoaded: defer откладывает
    // выполнение библиотеки до конца парсинга документа, а этот <script> — обычный,
    // синхронный, и без обёртки выполнялся бы раньше — window.Swiper на тот момент
    // ещё не определён, и карусель тихо не инициализировалась вообще (баг, из-за
    // которого на мобильном был виден только первый фон и выбрать остальные было
    // нельзя). Тот же приём — в student/dashboard.blade.php и main/repetitor.blade.php.
    // Инициализируем лениво, по границе media query, а не всегда: Swiper не
    // может измерить ширину слайдов на скрытом (display:none) контейнере,
    // а на десктопе карусель как раз скрыта в пользу обычной сетки.
    document.addEventListener('DOMContentLoaded', function () {
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
    });
</script>
@endsection
