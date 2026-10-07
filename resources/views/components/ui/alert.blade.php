{{--
    x-ui.alert — оформленное сообщение (флеш после сохранения, ошибки
    валидации, пояснение к странице) вместо голого цветного текста.
    Флеши session('success'|'error'|'status') и список ошибок админский каркас
    показывает сам — на странице их дублировать не нужно.

    Проп:
      tone: blue (инфо, по умолчанию) | green (успех) | red (ошибка) | orange (внимание) | gray
--}}
@props([
    'tone' => 'blue',
])

@php
    $tones = [
        'blue'   => 'bg-apple-blue-50 border-apple-blue-200 text-apple-blue-800',
        'green'  => 'bg-apple-green-50 border-apple-green-200 text-apple-green-800',
        'red'    => 'bg-apple-red-50 border-apple-red-200 text-apple-red-650',
        'orange' => 'bg-apple-orange-50 border-apple-orange-200 text-apple-orange-800',
        'gray'   => 'bg-zinc-50 border-zinc-200 text-zinc-700',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm ' . ($tones[$tone] ?? $tones['blue'])]) }}>{{ $slot }}</div>
