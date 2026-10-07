{{--
    x-ui.input — текстовое поле с подписью, подсказкой и ошибкой валидации.
    Все прочие атрибуты (value, required, placeholder, min, id, class …)
    уходят на сам <input>; класс обёртки задаётся пропом wrap.

    Пропы:
      name:  имя поля; по нему же ищется ошибка валидации (tasks[0][x] -> tasks.0.x)
      label: подпись (без неё рисуется только поле — для полей в строке фильтров)
      note:  серая приписка к подписи («необязательно»)
      hint:  подсказка под полем
      type:  тип input (text по умолчанию)
      size:  md | sm (плотные строки: фильтры, поля внутри таблиц)
      wrap:  классы обёртки
--}}
@props([
    'name' => null,
    'label' => null,
    'note' => null,
    'hint' => null,
    'type' => 'text',
    'size' => 'md',
    'wrap' => '',
])

@php
    $errorKey = $name ? trim(str_replace(['[', ']'], ['.', ''], $name), '.') : null;
    $id = $attributes->get('id') ?? ($name ? 'f-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-') : null);
    $invalid = $errorKey && $errors->has($errorKey);
@endphp

<div class="{{ $wrap }}">
    @if ($label !== null)
        <label class="ui-label" for="{{ $id }}">
            {{ $label }}@if ($note) <span class="ui-label-note">({{ $note }})</span>@endif
        </label>
    @endif
    <input type="{{ $type }}" @if ($name) name="{{ $name }}" @endif id="{{ $id }}"
           {{ $attributes->except('id')->class(['ui-input', 'ui-input-sm' => $size === 'sm', 'is-invalid' => $invalid]) }}>
    @if ($hint !== null)
        <p class="ui-hint">{{ $hint }}</p>
    @endif
    @if ($invalid)
        <p class="ui-error">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
