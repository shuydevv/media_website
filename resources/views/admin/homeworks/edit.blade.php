@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Редактирование домашнего задания</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.homeworks.update', $homework->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Название --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Название</label>
            <input type="text" name="title" class="w-full border rounded px-3 py-2" required value="{{ old('title', $homework->title) }}">
        </div>

        {{-- Описание --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Описание</label>
            <textarea name="description" class="w-full border rounded px-3 py-2">{{ old('description', $homework->description) }}</textarea>
        </div>

        {{-- Тип --}}
        <div class="mb-6">
            <label class="block text-sm font-medium">Тип</label>
            <select name="type" class="w-full border rounded px-3 py-2" required>
                <option value="homework" @selected($homework->type === 'homework')>Обычное домашнее задание</option>
                <option value="mock" @selected($homework->type === 'mock')>Пробник</option>
            </select>
        </div>

        {{-- Список заданий --}}
        <div id="tasks-container" class="space-y-8">
            @foreach ($homework->tasks as $i => $task)
                <div class="task-item border rounded p-4 bg-gray-50">
                    <input type="hidden" name="tasks[{{ $i }}][id]" value="{{ $task->id }}">

                    <h2 class="text-lg font-semibold mb-4">Задание</h2>

                    {{-- Тип --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Тип задания</label>
                        <select name="tasks[{{ $i }}][type]" class="task-type w-full border rounded px-3 py-2" required>
                            <option value="multiple_choice" @selected($task->type === 'multiple_choice')>Тест с вариантами</option>
                            <option value="text_based" @selected($task->type === 'text_based')>Текст с вопросами</option>
                            <option value="matching" @selected($task->type === 'matching')>Соотнесение</option>
                            <option value="image_auto" @selected($task->type === 'image_auto')>Картинка (автопроверка)</option>
                            <option value="image_written" @selected($task->type === 'image_written')>Картинка (ручная проверка)</option>
                            <option value="written" @selected($task->type === 'written')>Развёрнутый ответ</option>
                            <option value="table" @selected($task->type === 'table')>Таблица</option>
                        </select>
                    </div>

                    {{-- Вопрос --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Вопрос / текст</label>
                        <textarea name="tasks[{{ $i }}][question_text]" class="w-full border rounded px-3 py-2">{{ $task->question_text }}</textarea>
                    </div>

                    {{-- Ответ --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium">Правильный ответ</label>
                        <input type="text" name="tasks[{{ $i }}][answer]" class="w-full border rounded px-3 py-2" value="{{ $task->answer }}">
                    </div>

                    {{-- Варианты ответа --}}
                    <div class="mb-4 task-options {{ $task->type !== 'multiple_choice' ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium">Варианты ответа</label>
                        <textarea name="tasks[{{ $i }}][options][]" class="w-full border rounded px-3 py-2">{{ is_array($task->options) ? implode("\n", $task->options) : '' }}</textarea>
                    </div>

                    {{-- Соотнесения --}}
                    <div class="mb-4 task-matches {{ $task->type !== 'matching' ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium">Левая колонка</label>
                        <textarea name="tasks[{{ $i }}][matches][left][]" class="w-full border rounded px-3 py-2">{{ implode("\n", $task->matches['left'] ?? []) }}</textarea>

                        <label class="block text-sm font-medium mt-2">Правая колонка</label>
                        <textarea name="tasks[{{ $i }}][matches][right][]" class="w-full border rounded px-3 py-2">{{ implode("\n", $task->matches['right'] ?? []) }}</textarea>
                    </div>

                    {{-- Таблица --}}
                    <div class="mb-4 task-table {{ $task->type !== 'table' ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium">Содержимое таблицы (9 ячеек)</label>
                        <textarea name="tasks[{{ $i }}][table][]" class="w-full border rounded px-3 py-2">{{ implode("\n", $task->table ?? []) }}</textarea>
                    </div>

                    {{-- Изображение --}}
                    <div class="mb-4 task-image {{ !str_starts_with($task->type, 'image') ? 'hidden' : '' }}">
                        <label class="block text-sm font-medium">Новое изображение</label>
                        <input type="file" name="tasks[{{ $i }}][image]" class="w-full text-sm mt-1">

                        @if($task->image_path)
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-1">Текущее изображение:</p>
                                <img src="{{ asset('storage/' . $task->image_path) }}" class="w-32 rounded border">
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium">Номер задания</label>
                            <input type="text" name="tasks[{{ $i }}][task_number]" class="w-full border rounded px-3 py-2" value="{{ $task->task_number }}">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium">Порядок</label>
                            <input type="number" name="tasks[{{ $i }}][order]" class="w-full border rounded px-3 py-2" value="{{ $task->order }}">
                        </div>
                    </div>

                    {{-- Удаление --}}
                    <div class="mt-4 text-right">
                        <button type="button" class="delete-task text-red-600 text-sm hover:underline">Удалить задание</button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Кнопка добавить --}}
        <div class="mt-6">
            <button type="button" id="add-task" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">Добавить задание</button>
        </div>

        {{-- Сохранить --}}
        <div class="mt-6">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Сохранить изменения</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = {{ $homework->tasks->count() }};

    function toggleFields(container, type) {
        container.querySelector('.task-options')?.classList.add('hidden');
        container.querySelector('.task-matches')?.classList.add('hidden');
        container.querySelector('.task-image')?.classList.add('hidden');
        container.querySelector('.task-table')?.classList.add('hidden');

        if (type === 'multiple_choice') container.querySelector('.task-options')?.classList.remove('hidden');
        if (type === 'matching') container.querySelector('.task-matches')?.classList.remove('hidden');
        if (type === 'image_auto' || type === 'image_written') container.querySelector('.task-image')?.classList.remove('hidden');
        if (type === 'table') container.querySelector('.task-table')?.classList.remove('hidden');
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-type')) {
            const container = e.target.closest('.task-item');
            toggleFields(container, e.target.value);
        }
    });

    document.getElementById('add-task').addEventListener('click', () => {
        const container = document.getElementById('tasks-container');
        const newTask = container.firstElementChild.cloneNode(true);

        newTask.querySelectorAll('input, textarea, select').forEach(el => {
            el.name = el.name.replace(/\[\d+]/, `[${taskIndex}]`);
            el.value = '';
        });

        container.appendChild(newTask);
        taskIndex++;
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-task')) {
            const container = e.target.closest('.task-item');
            if (document.querySelectorAll('.task-item').length === 1) {
                alert('Нельзя удалить последнее задание');
                return;
            }
            container.remove();
        }
    });
});
</script>
@endsection
