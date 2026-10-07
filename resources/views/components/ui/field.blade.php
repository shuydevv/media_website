{{--
    x-ui.field — обёртка «подпись + поле + подсказка + ошибка» для
    нестандартного содержимого (несколько полей в ряд, своя разметка).
    Обычные поля проще писать через x-ui.input / x-ui.select / x-ui.textarea —
    они рисуют ту же обёртку сами.

    Пропы:
      label: подпись
      note:  серая приписка к подписи («необязательно» и т.п.)
      hint:  подсказка под полем
      error: ключ ошибки валидации в точечной нотации (title, schedule.0.day)
      for:   id поля, к которому привязана подпись
--}}
@props([
    'label' => null,
    'note' => null,
    'hint' => null,
    'error' => null,
    'for' => null,
])

<div {{ $attributes }}>
    @if ($label !== null)
        <label class="ui-label" @if ($for) for="{{ $for }}" @endif>
            {{ $label }}@if ($note) <span class="ui-label-note">({{ $note }})</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($hint !== null)
        <p class="ui-hint">{{ $hint }}</p>
    @endif
    @if ($error)
        @error($error)
            <p class="ui-error">{{ $message }}</p>
        @enderror
    @endif
</div>
