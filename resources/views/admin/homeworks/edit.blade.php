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
            <textarea name="description" class="w-full border rounded px-3 py-2">{{old('description', $homework->description)}}</textarea>
        </div>

        {{-- Курс --}}
        <div class="mb-4">
            <label for="course_id" class="block text-sm font-medium">Курс</label>
            <select class="w-full border rounded px-3 py-2" name="course_id" id="course_id" required>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" data-category="{{ $course->category_id }}" @selected(old('course_id', $homework->course_id) == $course->id)>{{ $course->title }}</option>
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

        {{-- Дедлайн --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Дедлайн</label>
            <input type="datetime-local" name="due_at"
                class="w-full border rounded px-3 py-2"
                value="{{ old('due_at', isset($homework->due_at) ? $homework->due_at->format('Y-m-d\TH:i') : '') }}">
        </div>

        {{-- Список заданий --}}
        <div id="tasks-container" class="space-y-8">
            @php $i = 0; @endphp
            @forelse($homework->tasks as $t)
            @php $isBankItem = !empty($t->task_id); @endphp
            <div class="task-item border rounded p-4 bg-gray-50">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Задание <span class="task-item-number">{{ $i + 1 }}</span></h2>
                    {{-- Порядок — стрелками: реальный порядок задаётся
                         положением карточки в списке, стрелки просто
                         переставляют DOM-узлы местами. --}}
                    <div class="flex items-center gap-1">
                        <button type="button" class="task-move-up w-7 h-7 flex items-center justify-center rounded border bg-white hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed" title="Переместить вверх">↑</button>
                        <button type="button" class="task-move-down w-7 h-7 flex items-center justify-center rounded border bg-white hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed" title="Переместить вниз">↓</button>
                    </div>
                </div>

                <input type="hidden" name="tasks[{{ $i }}][id]" value="{{ $t->id }}">
                <input type="hidden" name="tasks[{{ $i }}][order]" class="task-order-input" value="{{ old("tasks.$i.order", $t->order ?? ($i + 1)) }}">

                <div class="mb-4">
                    <label class="inline-flex items-center gap-2 mr-6 text-sm font-medium">
                        <input type="radio" name="tasks[{{ $i }}][source]" value="own" class="task-source-toggle" @checked(!$isBankItem)>
                        Создать новое
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input type="radio" name="tasks[{{ $i }}][source]" value="bank" class="task-source-toggle" @checked($isBankItem)>
                        Из банка заданий
                    </label>
                </div>

                <div class="task-own-fields {{ $isBankItem ? 'hidden' : '' }}">
                    <x-task-content-fields :name="'tasks['.$i.']'" :task="$t" :number-options="$numberOptions" />
                </div>

                {{-- ID вместо выпадающего списка — в банке могут быть сотни
                     заданий, тянуть и рендерить их все в <select> не годится. --}}
                <div class="task-bank-fields {{ $isBankItem ? '' : 'hidden' }} mb-4">
                    <label class="block text-sm font-medium mb-1">Задание из банка — ID</label>
                    <input type="number" name="tasks[{{ $i }}][task_id]" class="task-id-input w-full border rounded px-3 py-2"
                           min="1" step="1" placeholder="Например, 42"
                           value="{{ old('tasks.'.$i.'.task_id', $t->task_id) }}">
                    <div class="task-id-preview text-xs mt-1"></div>
                    <div class="mt-1 text-sm">
                        <a href="{{ route('admin.tasks.create') }}" target="_blank" class="text-blue-600 hover:underline">Создать новое в банке →</a>
                        <a href="{{ route('admin.tasks.index') }}" target="_blank" class="text-blue-600 hover:underline ml-3">Найти ID в банке →</a>
                    </div>
                </div>

                {{-- Баллы — по умолчанию из критериев (общие на весь номер
                     в ЕГЭ); это поле — редкое точечное переопределение
                     только для этой домашки. Предзаполняем СЫРЫМ значением
                     колонки, а не эффективным (через прокси), иначе при
                     каждом сохранении текущее значение по умолчанию тихо
                     "замораживалось" бы как персональное переопределение. --}}
                <div>
                    <label class="block text-sm font-medium">Баллы @if($isBankItem)<span class="text-gray-400 font-normal">(сейчас: {{ $t->max_score }}, по умолчанию из критериев)</span>@endif</label>
                    <input type="number" name="tasks[{{ $i }}][max_score]" class="w-full border rounded px-3 py-2"
                        min="1" step="1" value="{{ old("tasks.$i.max_score", $t->getRawOriginal('max_score')) }}">
                </div>

                <div class="mt-4 flex items-center justify-between flex-wrap gap-2">
                    <label class="task-save-to-bank-wrap inline-flex items-center gap-2 text-sm {{ $isBankItem ? 'hidden' : '' }}">
                        <input type="checkbox" name="tasks[{{ $i }}][save_to_bank]" value="1">
                        Также сохранить в банк заданий
                    </label>
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

@include('admin.tasks.partials.task-editor-script')

<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = document.querySelectorAll('#tasks-container .task-item').length;

    // Своё содержание / из банка — переключатель на каждой карточке задания.
    // Показ/скрытие полей ПО ТИПУ задания обслуживает общий
    // task-editor-script.blade.php, не этот файл.
    function toggleSource(container, source) {
        const own = container.querySelector('.task-own-fields');
        const bank = container.querySelector('.task-bank-fields');
        const saveToBank = container.querySelector('.task-save-to-bank-wrap');
        if (source === 'bank') {
            own?.classList.add('hidden');
            bank?.classList.remove('hidden');
            saveToBank?.classList.add('hidden');
        } else {
            own?.classList.remove('hidden');
            bank?.classList.add('hidden');
            saveToBank?.classList.remove('hidden');
        }
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-source-toggle')) {
            toggleSource(e.target.closest('.task-item'), e.target.value);
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
            else if (el.type === 'radio') el.checked = (el.value === 'own');
            else el.value = '';
        });
        tpl.querySelector('.task-id-preview').textContent = '';

        toggleSource(tpl, 'own');

        tasksContainer.appendChild(tpl);
        window.initTaskContentFields && window.initTaskContentFields(tpl.querySelector('.task-content-fields'));
        taskIndex++;
        renumberTaskOrders();
    });

    // --- Порядок заданий — стрелками, не перетаскиванием и не ручным
    // вводом числа. tasks[N][order] всегда пересчитывается по итоговому
    // положению карточки в #tasks-container. ---
    function renumberTaskOrders() {
        const items = [...document.querySelectorAll('#tasks-container .task-item')];
        items.forEach((item, idx) => {
            const orderInput = item.querySelector('.task-order-input');
            if (orderInput) orderInput.value = idx + 1;
            const numberLabel = item.querySelector('.task-item-number');
            if (numberLabel) numberLabel.textContent = idx + 1;
            item.querySelector('.task-move-up').disabled = (idx === 0);
            item.querySelector('.task-move-down').disabled = (idx === items.length - 1);
        });
    }
    renumberTaskOrders();

    document.addEventListener('click', e => {
        if (e.target.classList.contains('task-move-up') || e.target.classList.contains('task-move-down')) {
            const item = e.target.closest('.task-item');
            const sibling = e.target.classList.contains('task-move-up')
                ? item.previousElementSibling
                : item.nextElementSibling;
            if (!sibling) return;
            if (e.target.classList.contains('task-move-up')) {
                item.parentNode.insertBefore(item, sibling);
            } else {
                item.parentNode.insertBefore(sibling, item);
            }
            renumberTaskOrders();
        }
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-task')) {
            const container = e.target.closest('.task-item');
            if (document.querySelectorAll('.task-item').length === 1) {
                alert('Нельзя удалить последнее задание');
                return;
            }
            container.remove(); // inputs удалятся — контроллер может это трактовать как удаление
            renumberTaskOrders();
        }
    });
});
</script>

@endsection
