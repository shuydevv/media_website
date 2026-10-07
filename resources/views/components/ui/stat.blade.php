{{--
    x-ui.stat — «подпись + значение»: сводные цифры в карточках (CRM, отчёт по
    ученику, главная админки). Подпись — роль Caption из шкалы шрифтов.

    Пропы:
      label: подпись
      size:  lg (крупная цифра, по умолчанию) | md (обычный текст — даты, короткие фразы)
    Слоты:
      default — значение
      sub     — мелкая строка под значением
--}}
@props([
    'label',
    'size' => 'lg',
])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400">{{ $label }}</div>
    <div class="mt-0.5 text-zinc-900 {{ $size === 'lg' ? 'sans-medium text-2xl' : 'text-sm' }}">{{ $slot }}</div>
    @isset($sub)
        <div class="text-xs text-zinc-500 mt-0.5">{{ $sub }}</div>
    @endisset
</div>
