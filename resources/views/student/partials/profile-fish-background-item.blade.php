{{-- resources/views/student/partials/profile-fish-background-item.blade.php
     Один фон в выборе на вкладке «Персонаж» — используется и в десктопной
     сетке (fish-bg-grid-desktop), и в мобильной карусели (fish-bg-swiper),
     чтобы разметка карточки не дублировалась в двух местах. Ожидает $slug,
     $label из внешнего @foreach и $fishUnlockedBackgrounds/$fishBackground/
     $fishBackgroundPrices (цена по тирам, не одна на все — см.
     config/fish.php), расшаренные автоматически (обычный @include без
     явного массива данных). --}}
@php
    $unlocked = in_array($slug, $fishUnlockedBackgrounds, true);
    $price = $fishBackgroundPrices[$slug] ?? 20;
    $canAfford = ($fishBalance ?? 0) >= $price;
    $image = app(\App\Service\FishFoodService::class)->backgroundImageUrl($slug);
@endphp
@if ($unlocked)
    <form method="POST" action="{{ route('student.profile.background.select') }}">
        @csrf
        <input type="hidden" name="fish_background" value="{{ $slug }}">
        <button type="submit" class="fish-bg-thumb-btn {{ $fishBackground === $slug ? 'fish-bg-thumb-selected' : '' }}">
            <img src="{{ $image }}" alt="{{ $label }}" class="w-full aspect-square object-cover">
        </button>
    </form>
@else
    <div class="fish-bg-thumb-wrap {{ $canAfford ? '' : 'fish-bg-poor' }}">
        <img src="{{ $image }}" alt="{{ $label }}" class="w-full aspect-square object-cover">
        <span class="fish-bg-lock-badge"><x-icon name="lock-01" class="w-4 h-4" /></span>
        <form method="POST" action="{{ route('student.profile.background.purchase') }}" class="fish-bg-buy-form">
            @csrf
            <input type="hidden" name="fish_background" value="{{ $slug }}">
            {{-- data-bg-label/price читает JS, чтобы показать модалку
                 "уверены?" перед реальной покупкой — см. скрипт внизу
                 profile/show.blade.php. Не хватает корма — кнопка неактивна
                 (disabled уже блокирует клик и submit нативно, модалка
                 просто никогда не откроется), становится светлой и
                 показывает только цену, как у закрытой еды. --}}
            <button type="submit" class="fish-bg-buy-btn" data-bg-label="{{ $label }}" data-bg-price="{{ $price }}" {{ $canAfford ? '' : 'disabled' }}>
                {{ $canAfford ? 'Купить за ' . $price : $price }}
                <img src="{{ auth()->user()->fish_food_icon_url }}" alt="" class="w-4 h-4">
            </button>
        </form>
    </div>
@endif
<div class="text-sm font-medium text-zinc-900 text-center truncate mt-3">{{ $label }}</div>
