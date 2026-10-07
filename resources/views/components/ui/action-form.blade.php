{{--
    x-ui.action-form — кнопка-действие, которая на самом деле POST/DELETE-форма
    («Удалить», «Выключить», «Дублировать»): форма + @csrf + @method +
    x-ui.button одной строкой вместо шести.

    Пропы:
      action:  URL
      method:  DELETE (по умолчанию) | POST | PATCH | PUT
      confirm: текст подтверждения. Уходит в data-confirm (обработчик — в
               admin/layouts/main.blade.php), а не в onsubmit="confirm('…')":
               в тексте бывают имена учеников, кавычка в имени ломала бы JS.
      variant, size: как у x-ui.button (по умолчанию ghost / xs)
    Прочие атрибуты уходят на <form> (class="inline", data-* …).
--}}
@props([
    'action',
    'method' => 'DELETE',
    'confirm' => null,
    'variant' => 'ghost',
    'size' => 'xs',
])

<form method="POST" action="{{ $action }}" @if ($confirm) data-confirm="{{ $confirm }}" @endif {{ $attributes }}>
    @csrf
    @unless (strtoupper($method) === 'POST')
        @method($method)
    @endunless
    <x-ui.button type="submit" :variant="$variant" :size="$size">{{ $slot }}</x-ui.button>
</form>
