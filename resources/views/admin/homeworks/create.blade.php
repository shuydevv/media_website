@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Создание домашнего задания</h1>

    @php($i = 0)

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

        {{-- Курс --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Курс</label>
            <select name="course_id" id="course_id" class="w-full border rounded px-3 py-2">
                @foreach($courses as $course)
                <option value="{{ $course->id }}" data-category="{{ $course->category_id }}">
                    {{ $course->title }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Урок --}}
        <div class="mb-4">
            <label for="lesson_id">Урок</label>
            <select class="w-full border rounded px-3 py-2" name="lesson_id" id="lesson_id" required></select>
        </div>


        {{-- Тип домашней работы --}}
        <div class="mb-6">
            <label class="block text-sm font-medium">Тип</label>
            <select name="type" class="w-full border rounded px-3 py-2" required>
                <option value="homework">Обычное домашнее задание</option>
                <option value="mock">Пробник</option>
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
            <div class="task-item border rounded p-4 bg-gray-50">
                <h2 class="text-lg font-semibold mb-4">Задание</h2>

                {{-- Источник задания: своё содержание (как раньше) или уже
                     существующее в банке — переиспользуется, автоматически
                     доступно в личном кабинете и (если помечено) публично. --}}
                <div class="mb-4">
                    <label class="inline-flex items-center gap-2 mr-6 text-sm font-medium">
                        <input type="radio" name="tasks[0][source]" value="own" class="task-source-toggle" checked>
                        Только для этой домашки
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium">
                        <input type="radio" name="tasks[0][source]" value="bank" class="task-source-toggle">
                        Из банка заданий
                    </label>
                </div>

                <div class="task-own-fields">
                    <x-task-content-fields name="tasks[0]" :task="null" />

                    <label class="inline-flex items-center gap-2 mt-4 mb-4 text-sm">
                        <input type="checkbox" name="tasks[0][save_to_bank]" value="1">
                        Также сохранить в банк заданий (станет доступно в личном кабинете и в других домашках)
                    </label>
                </div>

                <div class="task-bank-fields hidden mb-4">
                    <label class="block text-sm font-medium mb-1">Задание из банка</label>
                    <select name="tasks[0][task_id]"
                            class="task-id-select w-full border rounded px-3 py-2"
                            data-current="{{ old('tasks.0.task_id') }}">
                        <option value="">— выберите задание —</option>
                        {{-- options подтянет JS через /admin/courses/{course}/tasks --}}
                    </select>
                    <div class="mt-1 text-sm">
                        <a href="{{ route('admin.tasks.create') }}" target="_blank" class="text-blue-600 hover:underline">Создать новое в банке →</a>
                        <button type="button" class="task-bank-refresh ml-3 text-gray-600 hover:underline">Обновить список</button>
                    </div>
                </div>

                {{-- Порядок и баллы — общие для обоих режимов --}}
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Порядок</label>
                        <input type="number" name="tasks[0][order]" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Баллы <span class="text-gray-400 font-normal">(для задания из банка — необязательно, переопределяет баллы по умолчанию)</span></label>
                        <input type="number" name="tasks[0][max_score]" class="w-full border rounded px-3 py-2" min="1" step="1">
                    </div>
                </div>

                <div class="mt-4 text-right">
                    <button type="button" class="delete-task text-red-600 text-sm hover:underline">Удалить задание</button>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <button type="button" id="add-task" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">Добавить задание</button>
        </div>

        <div class="mt-6">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Сохранить домашнее задание</button>
        </div>
    </form>
</div>

@include('admin.tasks.partials.task-editor-script')

<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = 1;

    // Своё содержание / из банка — переключатель на каждой карточке задания.
    // Показ/скрытие полей ПО ТИПУ задания (options/matches/table/...)
    // обслуживает общий task-editor-script.blade.php, не этот файл.
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

    document.querySelectorAll('.task-item').forEach(item => {
        const checked = item.querySelector('.task-source-toggle:checked');
        if (checked) toggleSource(item, checked.value);
    });

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
        const newTask = tasksContainer.firstElementChild.cloneNode(true);

        newTask.querySelectorAll('input, textarea, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/\[\d+]/, `[${taskIndex}]`);
            if (el.type === 'checkbox') el.checked = false;
            else if (el.type === 'radio') el.checked = (el.value === 'own');
            else el.value = '';
        });

        toggleSource(newTask, 'own');

        tasksContainer.appendChild(newTask);
        window.initTaskContentFields && window.initTaskContentFields(newTask.querySelector('.task-content-fields'));

        // заполняем ОПЦИИ ТОЛЬКО для нового селекта, не перезаполняя все
  const firstSel = document.querySelector('select.task-id-select');
  const newSel = newTask.querySelector('select.task-id-select');
  if (firstSel && newSel) {
    newSel.innerHTML = firstSel.innerHTML; // копия опций
    newSel.value = '';
    newSel.setAttribute('data-current','');
  } else {
    // на крайний случай — глобальное обновление (оно теперь сохраняет выбранные)
    window.refreshTaskSelectsForCurrentCourse && window.refreshTaskSelectsForCurrentCourse();
  }
        taskIndex++;
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-task')) {
            if (document.querySelectorAll('.task-item').length === 1) return alert('Нельзя удалить последнее задание');
            e.target.closest('.task-item').remove();
        }
    });

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

    if (courseSelect.value) fetchLessons(courseSelect.value);
    courseSelect.addEventListener('change', function() { fetchLessons(this.value); });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const courseSelect = document.getElementById('course_id');

  async function fetchTasks(courseId) {
    if (!courseId) return [];
    try {
      const res = await fetch(`/admin/courses/${courseId}/tasks`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) return [];
      return await res.json();
    } catch(e) {
      console.error(e);
      return [];
    }
  }

function fillTaskSelects(taskList) {
  document.querySelectorAll('select.task-id-select').forEach(sel => {
    const prev = sel.value || sel.getAttribute('data-current') || '';

    const frag = document.createDocumentFragment();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '— выбрать задание —';
    frag.appendChild(placeholder);

    taskList.forEach(t => {
      const opt = document.createElement('option');
      opt.value = String(t.id);
      const label = [`№ ${t.number ?? '—'}`, t.type, t.preview].filter(Boolean).join(' — ');
      opt.textContent = `${label} (ID ${t.id})`;
      if (prev && String(prev) === String(t.id)) opt.selected = true;
      frag.appendChild(opt);
    });

    sel.innerHTML = '';
    sel.appendChild(frag);

    // если прежнее значение не найдено среди опций — добавим его как «выбранное»
    if (prev && sel.value !== String(prev)) {
      const extra = document.createElement('option');
      extra.value = String(prev);
      extra.textContent = `Выбранное (ID ${prev})`;
      extra.selected = true;
      sel.appendChild(extra);
    }
  });
}


  async function refreshTasks() {
    const courseId = courseSelect ? courseSelect.value : null;
    const list = await fetchTasks(courseId);
    fillTaskSelects(list);
  }

  // Делаем функцию глобальной, чтобы можно было дернуть после добавления новой карточки
  window.refreshTaskSelectsForCurrentCourse = refreshTasks;

  // Первая загрузка
  refreshTasks();

  // Обновление при смене курса
  if (courseSelect) {
    courseSelect.addEventListener('change', refreshTasks);
  }
});

document.addEventListener('change', (e) => {
  if (e.target.matches('select.task-id-select')) {
    e.target.setAttribute('data-current', e.target.value || '');
  }
});
</script>


@endsection
