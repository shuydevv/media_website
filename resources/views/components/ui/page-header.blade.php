{{--
    x-ui.page-header — шапка страницы админки: ссылка «назад», заголовок (H1 из
    шкалы шрифтов, см. /admin/design-system), пояснение и действия справа.
    На телефоне действия переносятся под заголовок.

    Пропы:
      title:     текст H1
      back:      URL ссылки «назад» (необязательно)
      backLabel: подпись ссылки (по умолчанию «Назад»)
    Слоты:
      default — пояснение под заголовком
      actions — кнопки справа
--}}
@props([
    'title',
    'back' => null,
    'backLabel' => 'Назад',
])

<div {{ $attributes->merge(['class' => 'mb-5 md:mb-6']) }}>
    @if ($back)
        <a href="{{ $back }}" class="inline-flex items-center gap-1 -ml-1 mb-1 min-h-9 text-sm text-zinc-500 hover:text-zinc-900">
            <x-icon name="chevron-left" class="w-4 h-4 shrink-0" />
            {{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
        <div class="min-w-0">
            <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900 break-words">{{ $title }}</h1>
            @if (trim((string) $slot) !== '')
                <div class="mt-1.5 text-sm text-zinc-500 max-w-3xl">{{ $slot }}</div>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
