@extends('admin.layouts.main')

@section('title', 'Тексты бота')

@section('content')
<x-ui.page-header title="Telegram-бот">
    Обычный текст, без разметки. Подставляется: <code>{lesson}</code> — основной урок, <code>{bonus}</code> — бонусный,
    <code>{name}</code> — имя в Telegram. <code>[СМОТРЕТЬ]</code> — текст в квадратных скобках становится ссылкой на урок
    (в приветствии и бонусе; без неё урок уйдёт отдельной кнопкой). Премиум-эмодзи — запись <code>{e:код:🔥}</code>:
    коды пришлёт бот, если написать в админ-группе <code>/emoji</code> и вставить эмодзи в то же сообщение. Подпись «🙋🏼‍♂️ Александр Полтавский:» и жирный шрифт к сообщениям от Александра добавляются сами.
    Пустое поле — вернуть текст по умолчанию.
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'texts'])

<form method="post" action="{{ route('admin.telegram.texts.update') }}">
    @csrf
    @method('PUT')

    @foreach ($groups as $group => $definitions)
    <h2 class="mt-6 mb-3 text-base font-semibold text-zinc-900">{{ $group }}</h2>
    @if ($loop->last)
        <p class="mb-3 max-w-3xl text-sm text-zinc-500">Подстановки бота диагностики: <code>{name}</code> — имя; в итоге — <code>{low}</code>, <code>{high}</code>, <code>{potential}</code>, <code>{weak}</code> (точки роста); в напоминании «начал и бросил» — <code>{block}</code>, <code>{total}</code>.</p>
    @endif
    <div class="space-y-4 max-w-3xl">
        @foreach ($definitions as $key => $def)
            <x-ui.card>
                <x-ui.textarea name="texts[{{ $key }}]" :label="$def['label']" :note="$def['human'] ? 'от Александра' : 'автоматическое'"
                               :hint="$def['hint']" rows="4">{{ old('texts.'.$key, $texts[$key]) }}</x-ui.textarea>
                @if ($texts[$key] !== $def['default'])
                    <details class="mt-2 text-xs text-zinc-500">
                        <summary class="cursor-pointer">Текст по умолчанию</summary>
                        <div class="mt-1 whitespace-pre-line">{{ $def['default'] }}</div>
                    </details>
                @endif
            </x-ui.card>
        @endforeach
    </div>
    @endforeach

    <x-ui.form-actions>
        <x-ui.button type="submit" size="xs">Сохранить</x-ui.button>
    </x-ui.form-actions>
</form>
@endsection
