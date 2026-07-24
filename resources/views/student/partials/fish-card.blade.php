{{-- resources/views/student/partials/fish-card.blade.php
     Блок кормления рыбы — живёт внутри карточки 1 дашборда, рядом с
     маскотом, занимая место прежней отдельной карточки 3 (и с тем же
     внутренним расположением: подпись сверху, flex-1/mt-auto прижимает
     баланс+кнопку к низу). Без собственной карточной обёртки (bg/border/
     rounded) — её даёт сама карточка 1. Один и тот же партиал рендерится и
     на первом заходе на дашборд, и в ответ на htmx-запрос кормления (см.
     FishController::feed()) — так оба места гарантированно выглядят
     одинаково. Кормление — по 1 корму за нажатие, не весь баланс разом. --}}
@php
    // Название уровня — из App\Service\FishFoodService::levelName(), одно
    // место истины (используется и здесь, и в баннере левел-апа).
    $fishLevelName = app(\App\Service\FishFoodService::class)->levelName($fishLevel);
    $fishProgressPercent = $fishProgress['isMax']
        ? 100
        : (int) round(($fishProgress['needed'] > 0 ? $fishProgress['current'] / $fishProgress['needed'] : 0) * 100);
@endphp
<div id="fish-card" class="flex-1 flex flex-col min-w-0">
    <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-3">Уровень {{ $fishLevel }} · {{ $fishLevelName }}</div>

    <div class="flex-1 flex flex-col">
        {{-- Имя — главный заголовок карточки, поэтому крупнее и жирнее
             остального (эйробров сверху — мелкий и приглушённый, низ —
             служебная строка корма/кнопки: три чётких уровня важности). --}}
        <div class="sans-medium text-lg text-zinc-900 truncate">{{ $fishName }}</div>

        <div class="mt-5">
            @if($fishProgress['isMax'])
                <div class="text-xs text-zinc-500">Максимальный уровень!</div>
            @else
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $fishProgressPercent }}%"></div>
                </div>
                <div class="text-xs text-zinc-500 mt-1.5">{{ $fishProgress['current'] }} / {{ $fishProgress['needed'] }} до следующего уровня</div>
            @endif
        </div>

        <div class="mt-auto pt-5">
            <div class="text-sm text-zinc-600 mb-4">Корм: <span class="font-medium text-zinc-900">{{ $fishBalance }}</span></div>
            <x-ui.button type="button" variant="outline" size="sm" block class="fish-feed-btn"
                    hx-post="{{ route('student.fish.feed') }}"
                    hx-target="#fish-card"
                    hx-swap="outerHTML"
                    :disabled="$fishBalance <= 0">
                Покормить
            </x-ui.button>
        </div>
    </div>
</div>
