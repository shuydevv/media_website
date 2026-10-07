{{--
    Поля содержания урока — общие для create.blade.php и edit.blade.php.
    $lesson — редактируемый урок или null при создании.
    Привязка к курсу/сессии сюда не входит: при создании она выбирается, при
    редактировании только показывается.
--}}
@php
    $lesson = $lesson ?? null;
    $kinescopeHint = 'Только код видео — то, что идёт после https://kinescope.io/';
@endphp

<x-ui.card class="space-y-4">
    <x-ui.input name="title" label="Тема урока" value="{{ old('title', $lesson?->title) }}" required />

    <x-ui.textarea name="description" label="Описание" rows="4">{{ old('description', $lesson?->description) }}</x-ui.textarea>

    <x-ui.select name="lesson_type" label="Тип урока">
        <option value="">— не выбран —</option>
        <option value="theory" @selected(old('lesson_type', $lesson?->lesson_type) === 'theory')>Теория</option>
        <option value="practice" @selected(old('lesson_type', $lesson?->lesson_type) === 'practice')>Практика</option>
    </x-ui.select>
</x-ui.card>

<x-ui.card class="space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Видео и материалы</h2>

    <x-ui.input name="meet_link" label="Трансляция" value="{{ old('meet_link', $lesson?->meet_link) }}" :hint="$kinescopeHint" autocomplete="off" />

    <x-ui.input name="recording_link" label="Запись" value="{{ old('recording_link', $lesson?->recording_link) }}" :hint="$kinescopeHint" autocomplete="off" />

    <x-ui.input name="short_class" label="Выжимка — короткий урок" value="{{ old('short_class', $lesson?->short_class) }}" :hint="$kinescopeHint" autocomplete="off" />

    <x-ui.input type="url" name="notes_link" label="Конспект" value="{{ old('notes_link', $lesson?->notes_link) }}"
                hint="Полная ссылка на файлообменник." placeholder="https://" inputmode="url" />

    <x-ui.input type="file" name="image" :label="$lesson ? 'Новое изображение' : 'Изображение'" accept="image/*" />

    @if ($lesson?->image)
        <div>
            <p class="ui-hint mt-0 mb-1.5">Текущее изображение:</p>
            <img src="{{ asset('storage/' . $lesson->image) }}" alt="Изображение урока" class="w-32 rounded-lg border border-gray-200">
        </div>
    @endif
</x-ui.card>
