{{--
    x-ui.empty — пустое состояние списка («ничего не найдено», «пока нет»):
    один вид вместо четырёх (см. «Пустые состояния» на /admin/design-system).
    Слот — текст; необязательный слот action — кнопка под ним.
--}}
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-zinc-300 bg-white px-5 py-10 text-center text-sm text-zinc-500']) }}>
    {{ $slot }}
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
