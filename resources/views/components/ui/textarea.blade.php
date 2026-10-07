{{--
    x-ui.textarea — многострочное поле с подписью, подсказкой и ошибкой.
    Значение передаётся слотом. Пропы — те же, что у x-ui.input.
--}}
@props([
    'name' => null,
    'label' => null,
    'note' => null,
    'hint' => null,
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
    <textarea @if ($name) name="{{ $name }}" @endif id="{{ $id }}"
              {{ $attributes->except('id')->merge(['rows' => 3])->class(['ui-input', 'is-invalid' => $invalid]) }}>{{ $slot }}</textarea>
    @if ($hint !== null)
        <p class="ui-hint">{{ $hint }}</p>
    @endif
    @if ($invalid)
        <p class="ui-error">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
