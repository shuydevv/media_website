{{--
    x-ui.card — статичная карточка-контейнер, эталон — карточка 1 (маскот)
    на дашборде: rounded-2xl border bg-white p-5, без тени. Для кликабельной
    карточки-строки (список/ссылка на всю карточку) см. x-ui.card-link —
    та же геометрия, но с hover-состоянием, отдельный компонент.

    Проп:
      tone: white (по умолчанию) | blue | gray
--}}
@props([
    'tone' => 'white',
])

@php
    $tones = [
        'white' => 'bg-white border-gray-200',
        'blue'  => 'bg-blue-50 border-blue-200',
        'gray'  => 'bg-gray-50 border-gray-200',
    ];

    $toneClasses = $tones[$tone] ?? $tones['white'];
@endphp

<div {{ $attributes->merge(['class' => "rounded-2xl border {$toneClasses} p-5"]) }}>
    {{ $slot }}
</div>
