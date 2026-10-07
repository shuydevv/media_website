{{--
    x-ui.tab — одна вкладка внутри x-ui.tabs. <a>, если передан href, иначе
    <button type="button"> (для вкладок, которые переключает JS).

    Проп:
      active: текущая вкладка
--}}
@props([
    'active' => false,
])

@php
    $classes = 'inline-flex items-center gap-1.5 px-3.5 min-h-10 md:min-h-9 rounded-lg text-sm whitespace-nowrap transition '
        . ($active ? 'bg-white text-zinc-900 shadow-sm font-medium' : 'text-zinc-500 hover:text-zinc-900');
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
