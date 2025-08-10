@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Создание домашнего задания</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.homeworks.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- Название --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Название</label>
            <input type="text" name="title" class="w-full border rounded px-3 py-2" required>
        </div>

        {{-- Описание --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Описание</label>
            <textarea name="description" class="w-full border rounded px-3 py-2"></textarea>
        </div>


        <div class="mb-4">
        <label for="course_id">Курс</label>
        <select class="w-full border rounded px-3 py-2" name="course_id" id="course_id" required>
            @foreach($courses as $course)
                <option value="{{ $course->id }}">{{ $course->title }}</option>
            @endforeach
        </select>
        </div>

        <div class="mb-4">
        <label for="lesson_id">Урок</label>
        <select class="w-full border rounded px-3 py-2" name="lesson_id" id="lesson_id" required>
            <!-- Уроки будут загружаться динамически, если выбрали курс -->
        </select>
        </div>

        {{-- Тип домашней работы --}}
        <div class="mb-6">
            <label class="block text-sm font-medium">Тип</label>
            <select name="type" class="w-full border rounded px-3 py-2" required>
                <option value="homework">Обычное домашнее задание</option>
                <option value="mock">Пробник</option>
            </select>
        </div>

        {{-- Список заданий --}}
        <div id="tasks-container" class="space-y-8">
            {{-- Один шаблон задания (клонируется JavaScript'ом) --}}
            <div class="task-item border rounded p-4 bg-gray-50">
                <h2 class="text-lg font-semibold mb-4">Задание</h2>

                {{-- Тип задания --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Тип задания</label>
                    <select name="tasks[0][type]" class="task-type w-full border rounded px-3 py-2" required>
                        <option value="">Выберите тип</option>
                        <option value="multiple_choice">Тест с вариантами</option>
                        <option value="text_based">Текст с вопросами</option>
                        <option value="matching">Соотнесение</option>
                        <option value="image_auto">Картинка (автопроверка)</option>
                        <option value="image_written">Картинка (ручная проверка)</option>
                        <option value="written">Развёрнутый ответ</option>
                        <option value="table">Таблица</option>
                    </select>
                </div>

                {{-- Вопрос --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Вопрос / текст</label>
                    <textarea name="tasks[0][question_text]" class="w-full border rounded px-3 py-2"></textarea>
                </div>

                {{-- Ответ (всегда показывается) --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Правильный ответ</label>
                    <input type="text" name="tasks[0][answer]" class="w-full border rounded px-3 py-2" required>
                </div>

                {{-- Варианты ответа (только multiple_choice) --}}
                <div class="mb-4 task-options hidden">
                    <label class="block text-sm font-medium">Варианты ответа (по одному в строке)</label>
                    <textarea name="tasks[0][options][]" class="w-full border rounded px-3 py-2" rows="6"></textarea>
                </div>

                {{-- Соотнесение (matching) --}}
                <div class="mb-4 task-matches hidden">
                    <label class="block text-sm font-medium">Левая колонка</label>
                    <textarea name="tasks[0][matches][left][]" class="w-full border rounded px-3 py-2" rows="3"></textarea>

                    <label class="block text-sm font-medium mt-2">Правая колонка</label>
                    <textarea name="tasks[0][matches][right][]" class="w-full border rounded px-3 py-2" rows="3"></textarea>
                </div>

                {{-- Таблица (table) --}}
                <div class="mb-4 task-table hidden">
                    <label class="block text-sm font-medium">Содержимое таблицы (3x4, 9 ячеек)</label>
                    <textarea name="tasks[0][table][]" class="w-full border rounded px-3 py-2" rows="5"></textarea>
                </div>

                {{-- Изображение (image_*) --}}
                <div class="mb-4 task-image hidden">
                    <label class="block text-sm font-medium">Изображение</label>
                    <input type="file" name="tasks[0][image]" class="w-full text-sm mt-1">
                </div>

                {{-- Порядок и номер задания --}}
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Номер в пробнике</label>
                        <input type="text" name="tasks[0][task_number]" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Порядок</label>
                        <input type="number" name="tasks[0][order]" class="w-full border rounded px-3 py-2">
                    </div>
                </div>
            {{-- Кнопка удаления --}}
            <div class="mt-4 text-right">
                <button type="button" class="delete-task text-red-600 text-sm hover:underline">
                    Удалить задание
                </button>
            </div>
            </div>
        </div>

        <div class="mt-6">
            <button type="button" id="add-task" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">
                Добавить задание
            </button>
        </div>

        <div class="mt-6">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Сохранить домашнее задание
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = 1;

    // Отображение полей в зависимости от типа
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

    // При изменении типа задания
    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-type')) {
            const container = e.target.closest('.task-item');
            toggleFields(container, e.target.value);
        }
    });

    // Добавление нового задания
document.getElementById('add-task').addEventListener('click', () => {
    const tasksContainer = document.getElementById('tasks-container');
    const newTask = tasksContainer.firstElementChild.cloneNode(true);

    // Обновляем индекс и чистим значения
    newTask.querySelectorAll('input, textarea, select').forEach(el => {
        // Обновляем name с новым индексом
        if (el.name) {
            el.name = el.name.replace(/\[\d+]/, `[${taskIndex}]`);
        }
        el.value = '';
    });

    tasksContainer.appendChild(newTask);
    taskIndex++;
});
        // Удаление задания
    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-task')) {
            const container = e.target.closest('.task-item');

            // Если это последнее задание — не удаляем
            if (document.querySelectorAll('.task-item').length === 1) {
                alert('Нельзя удалить последнее задание');
                return;
            }

            container.remove();
        }
    });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const courseSelect = document.getElementById('course_id');
    const lessonSelect = document.getElementById('lesson_id');

    function renderLessons(list) {
        lessonSelect.innerHTML = '';
        if (!list.length) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = 'Нет уроков';
            lessonSelect.appendChild(opt);
            return;
        }
        list.forEach(lesson => {
            const opt = document.createElement('option');
            opt.value = lesson.id;
            opt.textContent = lesson.meta ? `${lesson.meta} — ${lesson.title}` : lesson.title;
            lessonSelect.appendChild(opt);
        });
    }

    function fetchLessons(courseId) {
        if (!courseId) return renderLessons([]);
        fetch(`/lessons?course_id=${courseId}`)
            .then(r => r.json())
            .then(data => renderLessons(data.lessons || []));
    }

    // подгружаем сразу для выбранного по умолчанию курса
    if (courseSelect.value) fetchLessons(courseSelect.value);

    courseSelect.addEventListener('change', function() {
        fetchLessons(this.value);
    });
});
</script>

@endsection
