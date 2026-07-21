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
    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-3">Уровень {{ $fishLevel }} · {{ $fishLevelName }}</div>

    <div class="flex-1 flex flex-col">
        {{-- Имя — главный заголовок карточки, поэтому крупнее и жирнее
             остального (эйробров сверху — мелкий и приглушённый, низ —
             служебная строка корма/кнопки: три чётких уровня важности). --}}
        <div class="text-xl font-semibold text-gray-900 truncate">{{ $fishName }}</div>

        <div class="mt-5">
            @if($fishProgress['isMax'])
                <div class="text-xs text-gray-500">Максимальный уровень!</div>
            @else
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $fishProgressPercent }}%"></div>
                </div>
                <div class="text-xs text-gray-500 mt-1.5">{{ $fishProgress['current'] }} / {{ $fishProgress['needed'] }} до следующего уровня</div>
            @endif
        </div>

        <div class="mt-auto pt-5 flex items-center justify-between gap-3">
            <div class="text-sm text-gray-600">Корм: <span class="font-medium text-gray-900">{{ $fishBalance }}</span></div>
            <button type="button"
                    hx-post="{{ route('student.fish.feed') }}"
                    hx-target="#fish-card"
                    hx-swap="outerHTML"
                    {{ $fishBalance <= 0 ? 'disabled' : '' }}
                    class="fish-feed-btn rounded-lg px-3 py-2 text-sm font-medium transition {{ $fishBalance > 0 ? 'bg-zinc-900 text-white hover:bg-zinc-800' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                Покормить
            </button>
        </div>
    </div>
</div>
