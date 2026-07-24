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
                <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <span class="drag-handle cursor-grab select-none text-gray-400 hover:text-gray-600" draggable="true" title="Перетащите, чтобы изменить порядок">⠿⠿</span>
                    Задание <span class="task-item-number">{{ $i + 1 }}</span>
                </h2>

                <input type="hidden" name="tasks[{{ $i }}][id]" value="{{ $t->id }}">

                <div class="mb-4">
                    <label class="inline-flex items-center gap-2 mr-6 text-sm font-medium">
                        <input type="radio" name="tasks[{{ $i }}][source]" value="own" class="task-source-toggle" @checked(!$isBankItem)>
                        Только для этой домашки
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input type="radio" name="tasks[{{ $i }}][source]" value="bank" class="task-source-toggle" @checked($isBankItem)>
                        Из банка заданий
                    </label>
                </div>

                <div class="task-own-fields {{ $isBankItem ? 'hidden' : '' }}">
                    <x-task-content-fields :name="'tasks['.$i.']'" :task="$t" />

                    <label class="inline-flex items-center gap-2 mt-4 mb-4 text-sm">
                        <input type="checkbox" name="tasks[{{ $i }}][save_to_bank]" value="1">
                        Также сохранить в банк заданий (станет доступно в личном кабинете и в других домашках)
                    </label>
                </div>

                <div class="task-bank-fields {{ $isBankItem ? '' : 'hidden' }} mb-4">
                    <label class="block text-sm font-medium mb-1">Задание из банка</label>
                    <select name="tasks[{{ $i }}][task_id]"
                            class="task-id-select w-full border rounded px-3 py-2"
                            data-current="{{ old('tasks.'.$i.'.task_id', $t->task_id) }}">
                        <option value="">— выберите задание —</option>
                        {{-- options подтянет JS через /admin/courses/{course}/tasks --}}
                    </select>
                    <div class="mt-1 text-sm">
                        <a href="{{ route('admin.tasks.create') }}" target="_blank" class="text-blue-600 hover:underline">Создать новое в банке →</a>
                        <button type="button" class="task-bank-refresh ml-3 text-gray-600 hover:underline">Обновить список</button>
                    </div>
                </div>

                {{-- Порядок и баллы — общие для обоих режимов. Баллы: пусто =
                     взять из банка (для "своих" заданий пусто = 1, см.
                     UpdateController) — поэтому предзаполняем СЫРЫМ
                     значением колонки, а не эффективным (через прокси),
                     иначе при каждом сохранении текущее значение банка
                     тихо "замораживалось" бы как персональное переопределение. --}}
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Порядок</label>
                        <input type="number" name="tasks[{{ $i }}][order]" class="w-full border rounded px-3 py-2"
                               value="{{ old("tasks.$i.order", $t->order) }}">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Баллы @if($isBankItem)<span class="text-gray-400 font-normal">(сейчас: {{ $t->max_score }}, из банка)</span>@endif</label>
                        <input type="number" name="tasks[{{ $i }}][max_score]" class="w-full border rounded px-3 py-2"
                            min="1" step="1" value="{{ old("tasks.$i.max_score", $t->getRawOriginal('max_score')) }}">
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
        if (source === 'bank') {
            own?.classList.add('hidden');
            bank?.classList.remove('hidden');
        } else {
            own?.classList.remove('hidden');
            bank?.classList.add('hidden');
        }
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-source-toggle')) {
            toggleSource(e.target.closest('.task-item'), e.target.value);
        }
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('task-bank-refresh')) {
            window.refreshTaskSelectsForCurrentCourse && window.refreshTaskSelectsForCurrentCourse();
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

        toggleSource(tpl, 'own');

        tasksContainer.appendChild(tpl);
        window.initTaskContentFields && window.initTaskContentFields(tpl.querySelector('.task-content-fields'));
        taskIndex++;
        renumberTaskOrders();
    });

    // --- Drag-n-drop сортировка заданий (только эта страница — здесь уже
    // есть карточки .task-item и числовое поле order). Нативный HTML5
    // drag-and-drop, без сторонних библиотек. Перетаскивание начинается
    // только с ручки .drag-handle — иначе клики/выделение текста в полях
    // формы конфликтовали бы с началом перетаскивания всей карточки. ---
    const tasksContainerForDrag = document.getElementById('tasks-container');
    let draggedItem = null;

    function renumberTaskOrders() {
        document.querySelectorAll('#tasks-container .task-item').forEach((item, idx) => {
            const orderInput = item.querySelector('input[name*="[order]"]');
            if (orderInput) orderInput.value = idx + 1;
            const numberLabel = item.querySelector('.task-item-number');
            if (numberLabel) numberLabel.textContent = idx + 1;
        });
    }

    function elementAfterDragPosition(container, y) {
        const items = [...container.querySelectorAll('.task-item:not(.dragging)')];
        return items.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            }
            return closest;
        }, { offset: -Infinity }).element;
    }

    tasksContainerForDrag.addEventListener('dragstart', (e) => {
        const handle = e.target.closest('.drag-handle');
        if (!handle) { e.preventDefault(); return; }
        draggedItem = handle.closest('.task-item');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
        draggedItem.classList.add('dragging', 'opacity-50');
    });

    tasksContainerForDrag.addEventListener('dragover', (e) => {
        if (!draggedItem) return;
        e.preventDefault();
        const after = elementAfterDragPosition(tasksContainerForDrag, e.clientY);
        if (after == null) {
            tasksContainerForDrag.appendChild(draggedItem);
        } else {
            tasksContainerForDrag.insertBefore(draggedItem, after);
        }
    });

    tasksContainerForDrag.addEventListener('dragend', () => {
        draggedItem?.classList.remove('dragging', 'opacity-50');
        draggedItem = null;
        renumberTaskOrders();
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const courseSelect = document.getElementById('course_id');

  async function fetchTasks(courseId) {
    if (!courseId) return [];
    try {
      const res = await fetch(`/admin/courses/${courseId}/tasks`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      if (!res.ok) return [];
      return await res.json();
    } catch(e) {
      console.error(e);
      return [];
    }
  }

  function fillTaskSelects(taskList) {
    document.querySelectorAll('select.task-id-select').forEach(sel => {
      const current = sel.getAttribute('data-current') || '';
      sel.innerHTML = '<option value="">— выбрать задание —</option>';
      taskList.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        const label = [`№ ${t.number ?? '—'}`, t.type, t.preview].filter(Boolean).join(' — ');
        opt.textContent = `${label} (ID ${t.id})`;
        if (current && String(current) === String(t.id)) opt.selected = true;
        sel.appendChild(opt);
      });
    });
  }

  async function refreshTasks() {
    const list = await fetchTasks(courseSelect?.value);
    fillTaskSelects(list);
  }

  // Делаем функцию глобальной — используется кнопкой "Обновить список" и
  // при добавлении новой карточки задания.
  window.refreshTaskSelectsForCurrentCourse = refreshTasks;

  // Первая загрузка и при смене курса
  refreshTasks();
  courseSelect?.addEventListener('change', refreshTasks);
});
</script>

@endsection
