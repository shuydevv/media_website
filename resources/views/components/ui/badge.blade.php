{{--
    x-ui.badge — пилюля-статус (форма — из раздела «Статусы и бейджи»
    /admin/design-system, цвета — целевая палитра apple-*: роль каждого цвета
    описана там же, в таблице «Предлагаемые роли»).

    Проп:
      tone: gray (по умолчанию) | blue (в процессе/инфо) | green (успех)
            | red (ошибка/просрочено) | orange (внимание/на проверке) | purple | indigo
--}}
@props([
    'tone' => 'gray',
])

@php
    $tones = [
        'gray'   => 'bg-zinc-100 text-zinc-700',
        'blue'   => 'bg-apple-blue-50 text-apple-blue-700',
        'green'  => 'bg-apple-green-100 text-apple-green-800',
        'red'    => 'bg-apple-red-50 text-apple-red-650',
        'orange' => 'bg-apple-orange-100 text-apple-orange-800',
        'purple' => 'bg-apple-purple-50 text-apple-purple-700',
        'indigo' => 'bg-apple-indigo-50 text-apple-indigo-700',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ' . ($tones[$tone] ?? $tones['gray'])]) }}>{{ $slot }}</span>
