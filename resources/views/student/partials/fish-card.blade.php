{{-- resources/views/student/partials/fish-card.blade.php
     Блок кормления рыбы — живёт внутри карточки 1 дашборда, рядом с
     маскотом, занимая место прежней отдельной карточки 3 (и с тем же
     внутренним расположением: подпись сверху, flex-1/mt-auto прижимает
     баланс+кнопку к низу). Без собственной карточной обёртки (bg/border/
     rounded) — её даёт сама карточка 1. Один и тот же партиал рендерится и
     на первом заходе на дашборд, и в ответ на htmx-запрос кормления (см.
     FishController::feed()) — так оба места гарантированно выглядят
     одинаково. Кормление — по 1 корму за нажатие, не весь баланс разом.
     Стили (.fish-xp, .fish-nameplate, .fish-btn…) — в dashboard.blade.php. --}}
@php
    $fishService = app(\App\Service\FishFoodService::class);
    // Название уровня — из App\Service\FishFoodService::levelName(), одно
    // место истины (используется и здесь, и в баннере левел-апа).
    $fishLevelName = $fishService->levelName($fishLevel);
    $fishProgressPercent = $fishProgress['isMax']
        ? 100
        : (int) round(($fishProgress['needed'] > 0 ? $fishProgress['current'] / $fishProgress['needed'] : 0) * 100);
    // Цель на шкале опыта: силуэт следующей формы в конце полоски и подпись
    // «Ещё N до <следующей формы>».
    $fishNextImage = $fishProgress['isMax'] ? null : $fishService->mascotImageUrl($fishLevel + 1);
    $fishNextName = config('fish.level_names_genitive.'.$fishLevel, 'нового уровня');
    $fishLeft = $fishProgress['isMax'] ? 0 : max(0, $fishProgress['needed'] - $fishProgress['current']);
    $fishFoodIcon = auth()->user()->fish_food_icon_url;

    // Тренажёры (App\Service\Trainers\TrainerHub) — для кнопки рядом с
    // «Покормить». На дашборде уже посчитаны (там же облачко рыбы), после
    // htmx-кормления считаются здесь.
    $trainers ??= auth()->check() ? app(\App\Service\Trainers\TrainerHub::class)->forUser(auth()->user()) : null;
    $trainersUrl = $trainers['url'] ?? null;
@endphp
{{-- data-balance читает скрипт кормления на дашборде: кончился ли корм
     («всё съедено»); data-crumb-src — картинка падающей на маскота еды
     (выбирается в профиле, см. config('fish.foods')); data-level и
     data-progress — скрипт шкалы опыта: с какого значения доводить
     заливку после htmx-свапа и был ли переход на новый уровень. --}}
<div id="fish-card" class="flex-1 flex flex-col min-w-0"
     data-balance="{{ $fishBalance }}"
     data-level="{{ $fishLevel }}"
     data-progress="{{ $fishProgressPercent }}"
     data-max="{{ $fishProgress['isMax'] ? 1 : 0 }}"
     data-crumb-src="{{ auth()->user()->fish_food_crumb_url }}">
    {{-- Номер уровня — значком на углу маскота (.fish-level-badge в
         dashboard.blade.php), здесь только название формы. --}}
    <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-2">{{ $fishLevelName }}</div>

    <div class="flex-1 flex flex-col min-w-0">
        {{-- Имя — табличкой; карандаш ведёт в профиль переименовать
             (#character — вкладка «Персонаж», поле имени в фокусе). --}}
        <a href="{{ route('student.profile.show') }}#character" class="fish-nameplate" title="Переименовать">
            <span class="truncate">{{ $fishName }}</span>
            <x-icon name="edit-02" class="fish-nameplate-edit w-3.5 h-3.5" />
        </a>

        <div class="mt-5">
            {{-- Шкала опыта. Заливку после кормления доводит скрипт на
                 дашборде (старое значение → новое), а не прыжком. --}}
            <div class="fish-xp {{ $fishProgress['isMax'] ? 'is-max' : '' }}">
                <div class="fish-xp-track">
                    <div class="fish-xp-fill" style="width: {{ $fishProgressPercent }}%"></div>
                </div>
                @if($fishNextImage)
                    <img src="{{ $fishNextImage }}" alt="" class="fish-xp-next" title="Следующая форма — {{ config('fish.level_names.'.$fishLevel) }}">
                @endif
            </div>
            <div class="fish-xp-caption">
                @if($fishProgress['isMax'])
                    Максимальный уровень!
                @else
                    Ещё <b>{{ $fishLeft }}</b>
                    <img src="{{ $fishFoodIcon }}" alt="корма" class="w-3.5 h-3.5 inline-block">
                    до {{ $fishNextName }}
                @endif
            </div>
        </div>

        <div class="mt-auto pt-5">
            {{-- Кнопки с небольшим объёмом: «Покормить» голубая, тренажёры серые (стили —
                 .fish-btn в dashboard.blade.php). Запас корма — в самой
                 кнопке «Покормить» (отдельной строки «Корм: N» больше нет).
                 С тренажёрами «Покормить» тянется, гантель — по содержимому.
                 is-ready — мягкое свечение, пока есть чем кормить. --}}
            <div class="fish-buttons {{ $trainersUrl ? 'grid gap-2' : '' }}" @if($trainersUrl) style="grid-template-columns: 1fr auto" @endif>
                <button type="button" class="fish-btn fish-btn--feed fish-feed-btn {{ $fishBalance > 0 ? 'is-ready' : '' }}"
                        hx-post="{{ route('student.fish.feed') }}"
                        hx-target="#fish-card"
                        hx-swap="outerHTML"
                        @disabled($fishBalance <= 0)>
                    @if($fishBalance > 0)
                        <img src="{{ $fishFoodIcon }}" alt="" class="w-6 h-6">
                        Покормить
                        <span class="fish-btn-stock" aria-label="запас корма: {{ $fishBalance }}">· {{ $fishBalance }}</span>
                    @else
                        {{-- Корма нет — кнопка подсказывает, где его взять. --}}
                        Реши задание
                        <img src="{{ $fishFoodIcon }}" alt="корм" class="w-6 h-6">
                    @endif
                </button>
                @if($trainersUrl)
                    {{-- Вместо слова — гантель; название — для экранного
                         диктора и подсказки при наведении. Счётчик дел во всех
                         тренажёрах — значком за правым верхним углом кнопки
                         (периодически подпрыгивает, см. .fish-btn-count). --}}
                    <a href="{{ $trainersUrl }}" class="fish-btn fish-btn--gym" aria-label="Тренажёры" title="Тренажёры">
                        <img src="{{ asset('img/icons/gym_weight.svg') }}" alt="">
                        @if($trainers['pending'] > 0)
                            <span class="fish-btn-count">{{ $trainers['pending'] }}</span>
                        @endif
                        <span class="sr-only">Тренажёры</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
