{{-- resources/views/student/partials/profile-fish-food-item.blade.php
     Одна еда в выборе на вкладке «Персонаж» — тот же чип, что у
     аксессуаров (profile-fish-accessory-item.blade.php), только вместо
     эмодзи картинка из public/img/food. Ожидает $slug, $label из внешнего
     @foreach и $fishUnlockedFoods/$fishFood/$fishFoodPrices/
     $fishFoodMinLevels/$fishLevel, расшаренные автоматически (обычный
     @include без явного массива данных). --}}
@php
    $unlocked = in_array($slug, $fishUnlockedFoods, true);
    $price = $fishFoodPrices[$slug] ?? 0;
    $minLevel = $fishFoodMinLevels[$slug] ?? 1;
    // Не дорос — купить нельзя вообще, вместо цены пишем, с какого уровня.
    $levelLocked = !$unlocked && $fishLevel < $minLevel;
    $canAfford = ($fishBalance ?? 0) >= $price;
    $src = app(\App\Service\FishFoodService::class)->foodImageUrl($slug);
@endphp
@if ($unlocked)
    <form method="POST" action="{{ route('student.profile.food.select') }}">
        @csrf
        <input type="hidden" name="fish_food" value="{{ $slug }}">
        <button type="submit" class="fish-acc-chip fish-food-chip mx-auto {{ $fishFood === $slug ? 'fish-acc-chip-selected' : '' }}">
            <img src="{{ $src }}" alt="{{ $label }}" class="w-11 h-11">
        </button>
    </form>
@else
    <form method="POST" action="{{ route('student.profile.food.purchase') }}">
        @csrf
        <input type="hidden" name="fish_food" value="{{ $slug }}">
        {{-- data-food-label/price читает JS модалки подтверждения покупки —
             см. скрипт внизу profile/show.blade.php. --}}
        <button type="submit" class="fish-acc-chip fish-food-chip mx-auto fish-food-locked" data-food-label="{{ $label }}" data-food-price="{{ $price }}" {{ $canAfford && !$levelLocked ? '' : 'disabled' }}>
            <img src="{{ $src }}" alt="{{ $label }}" class="w-11 h-11">
            <span class="fish-food-lock-badge"><x-icon name="lock-01" class="w-3 h-3" /></span>
        </button>
    </form>
@endif
<div class="text-xs font-medium text-zinc-700 text-center whitespace-nowrap mt-1.5">{{ $label }}</div>
{{-- Вторая строка есть всегда (у открытой — пустая), чтобы низ ряда был
     ровным. Цена — отдельной строкой, а не после названия: иначе длинное
     название обрезается вместе с ценой. Иконка корма — та же, что в балансе. --}}
<div class="h-4 mt-0.5 text-xs text-center flex items-center justify-center gap-1 {{ $levelLocked || !$canAfford ? 'text-zinc-400' : 'text-zinc-700' }}">
    @if ($levelLocked)
        с {{ $minLevel }} уровня
    @elseif (!$unlocked)
        {{ $price }}
        <img src="{{ auth()->user()->fish_food_icon_url }}" alt="корма" class="w-3.5 h-3.5 {{ $canAfford ? '' : 'opacity-50' }}">
    @endif
</div>
