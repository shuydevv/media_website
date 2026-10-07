{{--
    Общие поля формы упражнения — create.blade.php и edit.blade.php.
    $exercise — редактируемое упражнение или null при создании.

    Классы btn_* / block_* / *_select / *_option — крючки для
    public/js/admin/tabs.js и select_options.js, менять нельзя. Активную
    кнопку типа tabs.js помечает классами border-2 border-gray-600 — они
    упомянуты здесь, чтобы Tailwind не выкинул их из сборки (public/js он не
    сканирует).
--}}
@php
    $exercise = $exercise ?? null;
    $typeBtn = 'rounded-xl border border-zinc-300 bg-white px-4 py-3 min-h-11 text-sm text-left text-zinc-800 hover:bg-zinc-50 transition';
@endphp

<x-ui.card class="space-y-4">
    <x-ui.input name="ex_number" label="Номер задания в экзамене" value="{{ old('ex_number', $exercise?->ex_number) }}" placeholder="Введите номер задания" wrap="max-w-xs" />
    <x-ui.textarea name="title" label="Условие задания" rows="6" class="min-h-40"
                   placeholder="Введите текст задания (вопрос)">{{ old('title', $exercise?->title) }}</x-ui.textarea>
</x-ui.card>

<x-ui.card>
    <h2 class="sans-medium text-lg text-zinc-900 mb-3">Какое задание вы хотите создать?</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <button type="button" class="btn_answers {{ $typeBtn }}">С выбором правильных ответов</button>
        <button type="button" class="btn_columns {{ $typeBtn }}">На соответствие (с двумя колонками)</button>
        <button type="button" class="btn_text {{ $typeBtn }}">Вторая часть</button>
    </div>
</x-ui.card>

<div class="block_answers hidden">
    <x-ui.card>
        <h2 class="sans-medium text-lg text-zinc-900 mb-3">Тестовое задание на соответствие (с двумя колонками)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-4">
                <x-ui.input name="content_column_1_title" label="Заголовок левой колонки" placeholder="Введите заголовок"
                            value="{{ old('content_column_1_title', $exercise?->content_column_1_title) }}" />
                <x-ui.textarea name="content_column_1_content" label="Варианты ответа" rows="6" class="min-h-40"
                               placeholder="Введите текст задания">{{ old('content_column_1_content', $exercise?->content_column_1_content) }}</x-ui.textarea>
            </div>
            <div class="space-y-4">
                <x-ui.input name="content_column_2_title" label="Заголовок правой колонки" placeholder="Введите заголовок"
                            value="{{ old('content_column_2_title', $exercise?->content_column_2_title) }}" />
                <x-ui.textarea name="content_column_2_content" label="Варианты ответа" rows="6" class="min-h-40"
                               placeholder="Введите текст задания">{{ old('content_column_2_content', $exercise?->content_column_2_content) }}</x-ui.textarea>
            </div>
        </div>
    </x-ui.card>
</div>

<div class="block_columns hidden">
    <x-ui.card>
        <h2 class="sans-medium text-lg text-zinc-900 mb-3">Тестовое задание с вариантами ответов</h2>
        <x-ui.textarea name="content_options" rows="6" class="min-h-40" aria-label="Варианты ответа"
                       placeholder="Варианты ответа">{{ old('content_options', $exercise?->content_options) }}</x-ui.textarea>
    </x-ui.card>
</div>

<div class="block_text hidden">
    <x-ui.card>
        <h2 class="sans-medium text-lg text-zinc-900 mb-3">Текст (из второй части)</h2>
        <x-ui.textarea name="text_spoiler" rows="6" class="min-h-40" aria-label="Текст из второй части"
                       placeholder="Вставьте текст">{{ old('text_spoiler', $exercise?->text_spoiler) }}</x-ui.textarea>
    </x-ui.card>
</div>

<div class="block_last hidden space-y-4">
    <x-ui.card class="space-y-4">
        <h2 class="sans-medium text-lg text-zinc-900">Ответ и пояснение</h2>
        <x-ui.input name="answer" label="Ответ на задание" placeholder="Ответ (только цифры)" value="{{ old('answer', $exercise?->answer) }}"
                    hint="Только цифры. Для второй части заполните только пояснение." />
        <x-ui.textarea name="comment" label="Пояснение к ответу" rows="6" class="min-h-40"
                       placeholder="Введите пояснение">{{ old('comment', $exercise?->comment) }}</x-ui.textarea>
    </x-ui.card>

    <x-ui.card class="space-y-4">
        <h2 class="sans-medium text-lg text-zinc-900">Предмет, раздел и тема</h2>

        <x-ui.select name="category_id" label="Категория (предмет)" class="category_select">
            <option value disabled selected>Не выбрано</option>
            @foreach ($categories as $category)
                <option class="category_option" value="{{ $category->id }}">{{ $category->title }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select name="section_id" label="Раздел" class="section_select">
            <option class="section_option_default" value disabled selected>Не выбрано</option>
            @foreach ($sections as $section)
                <option class="section_option" id="{{ $section->category_id }}" value="{{ $section->id }}">{{ $section->title }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select name="topic_id" label="Тема" class="topic_select">
            <option value disabled selected>Не выбрано</option>
            @foreach ($topics as $topic)
                <option class="topic_option" id="{{ $topic->section_id }}" value="{{ $topic->id }}" @selected(old('topic_id', $exercise?->topic_id) == $topic->id)>{{ $topic->title }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.card>

    <x-ui.card class="space-y-4">
        <h2 class="sans-medium text-lg text-zinc-900">Изображение (если требуется)</h2>
        @if ($exercise?->main_image)
            <img class="w-60 max-w-full rounded-lg border border-gray-200" src="{{ asset('storage/' . $exercise->main_image) }}" alt="Изображение к упражнению">
        @endif
        <x-ui.input type="file" name="main_image" id="multiple_files" label="Изображение" accept="image/*" />
    </x-ui.card>
</div>
