@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Редактирование домашнего задания</h1>

    @if (session('status'))
        <div class="mb-4 text-green-600 text-sm">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.homeworks.update', $homework) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Название --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Название</label>
            <input type="text" name="title" class="w-full border rounded px-3 py-2"
                   value="{{ old('title', $homework->title) }}" required>
        </div>

        {{-- Описание --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Описание</label>
            <textarea name="description" class="w-full border rounded px-3 py-2">{{ old('description', $homework->description) }}</textarea>
        </div>

        {{-- Курс --}}
        <div class="mb-4">
            <label for="course_id" class="block text-sm font-medium">Курс</label>
            <select class="w-full border rounded px-3 py-2" name="course_id" id="course_id" required>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id', $homework->course_id) == $course->id)>{{ $course->title }}</option>
                @endforeach
            </select>
        </div>

        {{-- Урок --}}
        <div class="mb-4">
            <label for="lesson_id" class="block text-sm font-medium">Урок</label>
            <select class="w-full border rounded px-3 py-2" name="lesson_id" id="lesson_id" required>
                @foreach($lessons as $lesson)
                    <option value="{{ $lesson->id }}" @selected(old('lesson_id', $homework->lesson_id) == $lesson->id)>{{ $lesson->title ?? 'Без названия' }}</option>
                @endforeach
            </select>
        </div>

        {{-- Тип --}}
        <div class="mb-6">
            <label class="block text-sm font-medium">Тип</label>
            <select name="type" class="w-full border rounded px-3 py-2" required>
                <option value="homework" @selected(old('type', $homework->type) === 'homework')>Обычное домашнее</option>
                <option value="mock" @selected(old('type', $homework->type) === 'mock')>Пробник</option>
            </select>
        </div>

        {{-- Список заданий --}}
        <div id="tasks-container" class="space-y-8">
            @php $i = 0; @endphp
            @forelse($homework->tasks as $t)
            @php
                // Безопасно распакуем возможные массивы/строки
                $optionsText = is_array($t->options) ? implode("\n", $t->options) : ($t->options ?? '');
                $imageAutoOptionsText = is_array($t->image_auto_options ?? null) ? implode("\n", $t->image_auto_options) : '';
                $tableJson = $t->table_content
                    ? (is_array($t->table_content) ? json_encode($t->table_content, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : (string)$t->table_content)
                    : '';
            @endphp
            <div class="task-item border rounded p-4 bg-gray-50">
                <h2 class="text-lg font-semibold mb-4">Задание</h2>

                <input type="hidden" name="tasks[{{ $i }}][id]" value="{{ $t->id }}">

                {{-- Тип задания --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Тип задания</label>
                    <select name="tasks[{{ $i }}][type]" class="task-type w-full border rounded px-3 py-2" required>
                        <option value="">Выберите тип</option>
                        <option value="multiple_choice" @selected($t->type==='multiple_choice')>Тест с вариантами</option>
                        <option value="text_based" @selected($t->type==='text_based')>Текст с вопросами</option>
                        <option value="matching" @selected($t->type==='matching')>Соотнесение</option>
                        <option value="image_auto" @selected($t->type==='image_auto')>Картинка (авто)</option>
                        <option value="image_written" @selected($t->type==='image_written')>Картинка (ручная)</option>
                        <option value="written" @selected($t->type==='written')>Развёрнутый ответ</option>
                        <option value="table" @selected($t->type==='table')>Таблица</option>
                    </select>
                </div>


                {{-- Формулировка / вопрос --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Вопрос / текст</label>
                    <textarea name="tasks[{{ $i }}][question_text]" class="w-full border rounded px-3 py-2">{{ old("tasks.$i.question_text", $t->question_text) }}</textarea>
                </div>

                {{-- Правильный ответ --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Правильный ответ</label>
                    <input type="text" name="tasks[{{ $i }}][answer]" class="w-full border rounded px-3 py-2"
                           value="{{ old("tasks.$i.answer", $t->answer) }}" required>
                </div>

                {{-- Варианты (multiple_choice) --}}
                <div class="mb-4 task-options {{ $t->type==='multiple_choice' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium">Варианты ответа (по одному в строке)</label>
                    <textarea name="tasks[{{ $i }}][options]" class="w-full border rounded px-3 py-2" rows="6">{{ old("tasks.$i.options", $optionsText) }}</textarea>
                </div>

                {{-- Соотнесение --}}
                <div class="mb-4 task-matches {{ $t->type==='matching' ? '' : 'hidden' }}">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium">Заголовок левой колонки</label>
                            <input type="text" name="tasks[{{ $i }}][left_title]" class="w-full border rounded px-3 py-2"
                                   value="{{ old("tasks.$i.left_title", $t->left_title) }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Заголовок правой колонки</label>
                            <input type="text" name="tasks[{{ $i }}][right_title]" class="w-full border rounded px-3 py-2"
                                   value="{{ old("tasks.$i.right_title", $t->right_title) }}">
                        </div>
                    </div>

                    <label class="block text-sm font-medium mt-3">Левая колонка (по одному в строке)</label>
                    <textarea name="tasks[{{ $i }}][matches][left]" class="w-full border rounded px-3 py-2" rows="4">{{ old("tasks.$i.matches.left", isset($t->matches['left']) ? (is_array($t->matches['left']) ? implode("\n", $t->matches['left']) : $t->matches['left']) : '') }}</textarea>

                    <label class="block text-sm font-medium mt-3">Правая колонка (по одному в строке)</label>
                    <textarea name="tasks[{{ $i }}][matches][right]" class="w-full border rounded px-3 py-2" rows="4">{{ old("tasks.$i.matches.right", isset($t->matches['right']) ? (is_array($t->matches['right']) ? implode("\n", $t->matches['right']) : $t->matches['right']) : '') }}</textarea>

                    {{-- matching — порядок важен всегда --}}
                    <input type="hidden" name="tasks[{{ $i }}][order_matters]" value="1">
                </div>

                {{-- Таблица --}}
                <div class="mb-4 task-table {{ $t->type==='table' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium">Содержимое таблицы (JSON)</label>
                    <textarea name="tasks[{{ $i }}][table_content]" class="w-full border rounded px-3 py-2 font-mono text-xs" rows="8">{{ old("tasks.$i.table_content", $tableJson) }}</textarea>
                    {{-- table — порядок важен всегда --}}
                    <input type="hidden" name="tasks[{{ $i }}][order_matters]" value="1">
                </div>

                {{-- Текст (источник / пассаж) --}}
                <div class="mb-4 task-passage {{ in_array($t->type, ['text_based','written']) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium">
                        {{ $t->type==='text_based' ? 'Текст (источник)' : 'Текст (пассаж)' }}
                    </label>
                    <textarea name="tasks[{{ $i }}][passage_text]" class="w-full border rounded px-3 py-2" rows="5">
                        {{ old("tasks.$i.passage_text", $t->passage_text) }}
                    </textarea>
                </div>

                {{-- Изображение (в условии) --}}
                <div class="mb-4 task-image {{ in_array($t->type, ['image_auto','image_written']) ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium">Изображение</label>
                    @if(!empty($t->media_path))
                        <div class="text-xs text-gray-600 mb-1">Текущее: {{ $t->media_path }}</div>
                    @endif
                    <input type="file" name="tasks[{{ $i }}][image]" class="w-full text-sm mt-1">
                </div>

                {{-- image_auto: опции и "порядок важен" --}}
                <div class="mb-4 task-image-auto-extra {{ $t->type==='image_auto' ? '' : 'hidden' }}">
                    <label class="block text-sm font-medium">Варианты ответа (по одному в строке, необязательно)</label>
                    <textarea name="tasks[{{ $i }}][image_auto_options]" class="w-full border rounded px-3 py-2" rows="4">{{ old("tasks.$i.image_auto_options", $imageAutoOptionsText) }}</textarea>
                    <div class="mt-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="tasks[{{ $i }}][image_auto_strict]" value="1" @checked(old("tasks.$i.image_auto_strict", $t->image_auto_strict))>
                            Порядок цифр/ответов важен
                        </label>
                    </div>
                </div>

                {{-- Порядок и номер --}}
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Номер в пробнике</label>
                        <input type="text" name="tasks[{{ $i }}][task_number]" class="w-full border rounded px-3 py-2"
                               value="{{ old("tasks.$i.task_number", $t->task_number) }}">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Порядок</label>
                        <input type="number" name="tasks[{{ $i }}][order]" class="w-full border rounded px-3 py-2"
                               value="{{ old("tasks.$i.order", $t->order) }}">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Баллы</label>
                        <input type="number" name="tasks[{{ $i }}][max_score]" class="w-full border rounded px-3 py-2"
                            min="1" max="3" value="{{ old("tasks.$i.max_score", $t->max_score ?? 1) }}">
                    </div>
                </div>

                <div class="mt-4 text-right">
                    <button type="button" class="delete-task text-red-600 text-sm hover:underline">Удалить задание</button>
                </div>
            </div>
            @php $i++; @endphp
            @empty
                {{-- если задач нет — можно добавить первый через кнопку ниже --}}
            @endforelse
        </div>

        <div class="mt-6">
            <button type="button" id="add-task" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">
                Добавить задание
            </button>
        </div>

        <div class="mt-6">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Сохранить изменения
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = document.querySelectorAll('#tasks-container .task-item').length;

    function toggleFields(container, type) {
        container.querySelectorAll('.task-options, .task-matches, .task-image, .task-table, .task-passage, .task-image-auto-extra')
                 .forEach(el => el.classList.add('hidden'));

        if (type === 'multiple_choice') container.querySelector('.task-options')?.classList.remove('hidden');
        if (type === 'text_based')     container.querySelector('.task-passage')?.classList.remove('hidden');
        if (type === 'matching')       container.querySelector('.task-matches')?.classList.remove('hidden');
        if (type === 'table')          container.querySelector('.task-table')?.classList.remove('hidden');
        if (type === 'text_based' || type === 'written') container.querySelector('.task-passage')?.classList.remove('hidden');

        if (type === 'image_auto') {
            container.querySelector('.task-image')?.classList.remove('hidden');
            container.querySelector('.task-image-auto-extra')?.classList.remove('hidden');
        }
        if (type === 'image_written')  container.querySelector('.task-image')?.classList.remove('hidden');
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-type')) {
            toggleFields(e.target.closest('.task-item'), e.target.value);
        }
    });

    document.getElementById('add-task').addEventListener('click', () => {
        const tasksContainer = document.getElementById('tasks-container');
        const tpl = tasksContainer.firstElementChild.cloneNode(true);

        // удалить hidden id -> новая задача
        tpl.querySelector('input[name*="[id]"]')?.remove();

        // очистить значения и проставить новые индексы
        tpl.querySelectorAll('input, textarea, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/\[\d+]/, `[${taskIndex}]`);
            if (el.type === 'file') el.value = null;
            else if (el.type === 'checkbox') el.checked = false;
            else el.value = '';
        });

        tpl.querySelectorAll('.task-options, .task-matches, .task-image, .task-table, .task-passage, .task-image-auto-extra')
           .forEach(el => el.classList.add('hidden'));

        tasksContainer.appendChild(tpl);
        taskIndex++;
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-task')) {
            const container = e.target.closest('.task-item');
            if (document.querySelectorAll('.task-item').length === 1) {
                alert('Нельзя удалить последнее задание');
                return;
            }
            container.remove(); // inputs удалятся — контроллер может это трактовать как удаление
        }
    });
});
</script>
@endsection
