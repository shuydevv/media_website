{{--
    x-ui.card-link — кликабельная карточка-строка (список домашек/уведомлений):
    та же геометрия, что и x-ui.card (rounded-2xl border bg-white p-5), плюс
    hover-подсветка рамки/тени — это и есть сигнал "по мне можно кликнуть",
    поэтому в отличие от x-ui.card тень здесь появляется только на hover, а не
    всегда. Рендерится как <a>, если передан href, иначе как <button
    type="submit"> (обёртка формы уже снаружи, как в notifications/index).

    Проп:
      highlighted: лёгкая акцентная подсветка (непрочитанное уведомление и т.п.)
--}}
@props([
    'highlighted' => false,
])

@php
    $toneClasses = $highlighted ? 'bg-amber-50/40 border-amber-200' : 'bg-white border-gray-200';

    $classes = "w-full text-left flex items-start justify-between gap-4 rounded-2xl border {$toneClasses} p-5 hover:border-amber-300 hover:shadow-sm transition";
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
