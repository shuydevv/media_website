{{-- resources/views/student/partials/profile-fish-accessory-item.blade.php
     Один аксессуар в выборе на вкладке «Персонаж» — по аналогии с
     profile-fish-background-item.blade.php, но без картинки: реального
     арта нет, вместо неё эмодзи-плейсхолдер (см. FishFoodService::
     accessoryEmoji(), заменить на арт можно будет прямо в config/fish.php).
     Ожидает $slug, $label из внешнего @foreach и $fishUnlockedAccessories/
     $fishAccessory/$fishAccessoryEmoji/$fishAccessoryPrices, расшаренные
     автоматически (обычный @include без явного массива данных). --}}
@php
    $unlocked = in_array($slug, $fishUnlockedAccessories, true);
    $emoji = $fishAccessoryEmoji[$slug] ?? '❔';
    $price = $fishAccessoryPrices[$slug] ?? 0;
    $canAfford = ($fishBalance ?? 0) >= $price;
@endphp
@if ($unlocked)
    <form method="POST" action="{{ route('student.profile.accessory.select') }}">
        @csrf
        <input type="hidden" name="fish_accessory" value="{{ $slug }}">
        <button type="submit" class="fish-acc-chip {{ $fishAccessory === $slug ? 'fish-acc-chip-selected' : '' }}">
            <span class="fish-acc-emoji">{{ $emoji }}</span>
        </button>
    </form>
@else
    <form method="POST" action="{{ route('student.profile.accessory.purchase') }}" class="fish-acc-buy-form">
        @csrf
        <input type="hidden" name="fish_accessory" value="{{ $slug }}">
        {{-- data-acc-label/price читает JS для той же модалки подтверждения
             покупки, что и у фонов — см. скрипт внизу profile/show.blade.php. --}}
        <button type="submit" class="fish-acc-chip fish-acc-locked" data-acc-label="{{ $label }}" data-acc-price="{{ $price }}" {{ $canAfford ? '' : 'disabled' }}>
            <span class="fish-acc-emoji">{{ $emoji }}</span>
            <span class="fish-acc-lock-badge"><x-icon name="lock-01" class="w-3 h-3" /></span>
        </button>
    </form>
@endif
<div class="text-xs font-medium text-zinc-700 text-center truncate mt-1">
    {{ $label }}@if (!$unlocked) · {{ $price }}@endif
</div>
