{{--
    x-ui.button — единая кнопка вместо 17 независимых комбинаций классов
    (см. /admin/design-system). Рендерится как <a>, если передан href, иначе
    как <button> (по умолчанию type="button", но явный type="submit" на теге
    переопределяет значение по умолчанию через $attributes->merge()).

    Пропы:
      variant: primary (zinc, по умолчанию) | accent (blue) | danger (rose) | outline (белый фон, обводка+текст zinc)
      size:    md (по умолчанию) | sm ("мини" — profile/show.blade.php)
      block:   full-width (w-full)
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'block' => false,
    'disabled' => false,
])

@php
    $variants = [
        'primary' => 'bg-zinc-800 hover:bg-zinc-900 text-white',
        'accent'  => 'bg-blue-600 hover:bg-blue-700 text-white',
        'danger'  => 'bg-rose-600 hover:bg-rose-700 text-white',
        'outline' => 'bg-white border border-zinc-800 text-zinc-800 hover:bg-zinc-50',
    ];

    $sizeClasses = $size === 'sm'
        ? 'rounded-lg px-4 py-3'
        : 'rounded-xl py-4 ' . ($block ? 'px-3' : 'px-6 md:px-8');

    // Задизейбленное состояние всегда одного вида, независимо от variant —
    // раньше это решалось в двух чуть разных оттенках серого в разных
    // местах (gray-200/gray-500 vs gray-100/gray-400). disabled — формальный
    // проп (не голый $attributes->has()), иначе :disabled="false" всё равно
    // считался бы «есть атрибут» — has() смотрит на наличие ключа, а не на
    // истинность значения.
    $colorClasses = $disabled
        ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
        : ($variants[$variant] ?? $variants['primary']);

    $classes = trim(implode(' ', [
        $colorClasses,
        $sizeClasses,
        $block ? 'w-full block' : 'inline-block',
        'text-center font-medium tracking-wide transition',
    ]));
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }} @disabled($disabled)>{{ $slot }}</button>
@endif
