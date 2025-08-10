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
            <label for="course_id">Курс</label>
            <select class="w-full border rounded px-3 py-2" name="course_id" id="course_id" required>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}"
                        @selected(old('course_id', $homework->course_id) == $course->id)>
                        {{ $course->title }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Урок --}}
        <div class="mb-4">
            <label for="lesson_id">Урок</label>
            <select class="w-full border rounded px-3 py-2" name="lesson_id" id="lesson_id" required>
                @foreach($lessons as $lesson)
                    <option value="{{ $lesson->id }}"
                        @selected(old('lesson_id', $homework->lesson_id) == $lesson->id)>
                        {{ $lesson->title ?? 'Без названия' }}
                    </option>
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
    <div class="task-item border rounded p-4 bg-gray-50">
        <h2 class="text-lg font-semibold mb-4">Задание</h2>

        <input type="hidden" name="tasks[{{ $i }}][id]" value="{{ $t->id }}">

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

        <div class="mb-4">
            <label class="block text-sm font-medium">Вопрос / текст</label>
            <textarea name="tasks[{{ $i }}][question_text]" class="w-full border rounded px-3 py-2">{{ old("tasks.$i.question_text", $t->question_text) }}</textarea>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">Правильный ответ</label>
            <input type="text" name="tasks[{{ $i }}][answer]" class="w-full border rounded px-3 py-2"
                   value="{{ old("tasks.$i.answer", $t->answer) }}" required>
        </div>

        {{-- Варианты (multiple_choice) --}}
        <div class="mb-4 task-options {{ $t->type==='multiple_choice' ? '' : 'hidden' }}">
            <label class="block text-sm font-medium">Варианты ответа (по одному в строке)</label>
            <textarea name="tasks[{{ $i }}][options][]" class="w-full border rounded px-3 py-2" rows="6">{{ is_array($t->options) ? implode("\n", $t->options) : $t->options }}</textarea>
        </div>

        {{-- Соотнесение --}}
        <div class="mb-4 task-matches {{ $t->type==='matching' ? '' : 'hidden' }}">
            <label class="block text-sm font-medium">Левая колонка</label>
            <textarea name="tasks[{{ $i }}][matches][left][]" class="w-full border rounded px-3 py-2" rows="3">{{ isset($t->matches['left']) ? implode("\n", (array)$t->matches['left']) : '' }}</textarea>

            <label class="block text-sm font-medium mt-2">Правая колонка</label>
            <textarea name="tasks[{{ $i }}][matches][right][]" class="w-full border rounded px-3 py-2" rows="3">{{ isset($t->matches['right']) ? implode("\n", (array)$t->matches['right']) : '' }}</textarea>
        </div>

        {{-- Таблица --}}
        <div class="mb-4 task-table {{ $t->type==='table' ? '' : 'hidden' }}">
            <label class="block text-sm font-medium">Содержимое таблицы (3x4, 9 ячеек)</label>
            <textarea name="tasks[{{ $i }}][table][]" class="w-full border rounded px-3 py-2" rows="5">{{ is_array($t->table) ? implode("\n", (array)$t->table) : $t->table }}</textarea>
        </div>

        {{-- Изображение --}}
        <div class="mb-4 task-image {{ in_array($t->type, ['image_auto','image_written']) ? '' : 'hidden' }}">
            <label class="block text-sm font-medium">Изображение</label>
            @if($t->image_path)
                <div class="text-sm mb-1">Текущее: {{ $t->image_path }}</div>
            @endif
            <input type="file" name="tasks[{{ $i }}][image]" class="w-full text-sm mt-1">
        </div>

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
        </div>

        <div class="mt-4 text-right">
            <button type="button" class="delete-task text-red-600 text-sm hover:underline">Удалить задание</button>
        </div>
    </div>
    @php $i++; @endphp
    @empty
        {{-- Если задач нет — отрисуем один пустой шаблон как в create --}}
        <div class="task-item border rounded p-4 bg-gray-50">
            <h2 class="text-lg font-semibold mb-4">Задание</h2>
            <div class="mb-4">
                <label class="block text-sm font-medium">Тип задания</label>
                <select name="tasks[0][type]" class="task-type w-full border rounded px-3 py-2" required>
                    <option value="">Выберите тип</option>
                    <option value="multiple_choice">Тест с вариантами</option>
                    <option value="text_based">Текст с вопросами</option>
                    <option value="matching">Соотнесение</option>
                    <option value="image_auto">Картинка (авто)</option>
                    <option value="image_written">Картинка (ручная)</option>
                    <option value="written">Развёрнутый ответ</option>
                    <option value="table">Таблица</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium">Вопрос / текст</label>
                <textarea name="tasks[0][question_text]" class="w-full border rounded px-3 py-2"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium">Правильный ответ</label>
                <input type="text" name="tasks[0][answer]" class="w-full border rounded px-3 py-2" required>
            </div>
            <div class="mb-4 task-options hidden">
                <label class="block text-sm font-medium">Варианты ответа (по одному в строке)</label>
                <textarea name="tasks[0][options][]" class="w-full border rounded px-3 py-2" rows="6"></textarea>
            </div>
            <div class="mb-4 task-matches hidden">
                <label class="block text-sm font-medium">Левая колонка</label>
                <textarea name="tasks[0][matches][left][]" class="w-full border rounded px-3 py-2" rows="3"></textarea>
                <label class="block text-sm font-medium mt-2">Правая колонка</label>
                <textarea name="tasks[0][matches][right][]" class="w-full border rounded px-3 py-2" rows="3"></textarea>
            </div>
            <div class="mb-4 task-table hidden">
                <label class="block text-sm font-medium">Содержимое таблицы (3x4, 9 ячеек)</label>
                <textarea name="tasks[0][table][]" class="w-full border rounded px-3 py-2" rows="5"></textarea>
            </div>
            <div class="mb-4 task-image hidden">
                <label class="block text-sm font-medium">Изображение</label>
                <input type="file" name="tasks[0][image]" class="w-full text-sm mt-1">
            </div>
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
            <div class="mt-4 text-right">
                <button type="button" class="delete-task text-red-600 text-sm hover:underline">Удалить задание</button>
            </div>
        </div>
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
@endsection


<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = document.querySelectorAll('#tasks-container .task-item').length;

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
        const tasksContainer = document.getElementById('tasks-container');
        const tpl = tasksContainer.firstElementChild.cloneNode(true);

        // убрать скрытый id, чтобы создалась новая задача
        const hiddenId = tpl.querySelector('input[name*="[id]"]');
        if (hiddenId) hiddenId.remove();

        // очистить значения и проставить новые индексы
        tpl.querySelectorAll('input, textarea, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/\[\d+]/, `[${taskIndex}]`);
            if (el.type === 'file') el.value = null;
            else el.value = '';
        });

        tpl.querySelectorAll('.task-options, .task-matches, .task-image, .task-table').forEach(box => box.classList.add('hidden'));

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
            container.remove(); // важный момент: не отправим inputs => контроллер удалит задачу
        }
    });
});
</script>
