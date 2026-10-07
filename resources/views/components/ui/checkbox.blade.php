{{--
    x-ui.checkbox — чекбокс с подписью: тот же .checkbox-custom, что на
    странице входа («Запомнить меня»), подпись — слотом. Прочие атрибуты
    (id, class, data-*) уходят на сам <input>.

    Пропы:
      name, value (по умолчанию 1), checked
--}}
@props([
    'name' => null,
    'value' => '1',
    'checked' => false,
])

<label class="ui-check">
    <input type="checkbox" @if ($name) name="{{ $name }}" @endif value="{{ $value }}"
           {{ $attributes->class(['checkbox-custom']) }} @checked($checked)>
    <span class="min-w-0">{{ $slot }}</span>
</label>
