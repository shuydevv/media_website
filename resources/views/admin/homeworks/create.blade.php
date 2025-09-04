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

        {{-- Список заданий --}}
        <div id="tasks-container" class="space-y-8">
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

                {{-- Дополнительный текст (для text_based) --}}
                <div class="mb-4 task-passage hidden">
                    <label class="block text-sm font-medium">Текст задания (художественный / публицистический)</label>
                    <textarea name="tasks[0][passage_text]" class="w-full border rounded px-3 py-2" rows="4"></textarea>
                </div>

                {{-- Вопрос --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Вопрос / текст</label>
                    <textarea name="tasks[0][question_text]" class="w-full border rounded px-3 py-2"></textarea>
                </div>

                {{-- Ответ --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium">Правильный ответ</label>
                    <input type="text" name="tasks[0][answer]" class="w-full border rounded px-3 py-2" required>
                </div>

                {{-- Варианты (multiple_choice) --}}
                <div class="mb-4 task-options hidden">
                    <label class="block text-sm font-medium">Варианты ответа (Каждый вариант ответа с новой строки)</label>
                    <textarea name="tasks[0][options][]" class="w-full border rounded px-3 py-2" rows="6"></textarea>
                </div>

                {{-- Соотнесение --}}
                <div class="mb-4 task-matches hidden">
                    <label class="block text-sm font-medium">Заголовок левой колонки</label>
                    <input type="text" name="tasks[0][left_title]" class="w-full border rounded px-3 py-2 mb-2">

                    <label class="block text-sm font-medium">Левая колонка</label>
                    <textarea name="tasks[0][matches][left][]" class="w-full border rounded px-3 py-2" rows="3"></textarea>

                    <label class="block text-sm font-medium mt-2">Заголовок правой колонки</label>
                    <input type="text" name="tasks[0][right_title]" class="w-full border rounded px-3 py-2 mb-2">

                    <label class="block text-sm font-medium">Правая колонка</label>
                    <textarea name="tasks[0][matches][right][]" class="w-full border rounded px-3 py-2" rows="3"></textarea>
                </div>

                {{-- Таблица --}}
                <div class="mb-4 task-table hidden">
                <label class="block text-sm font-medium">Содержимое таблицы (JSON)</label>
                <textarea name="tasks[0][table_content]" class="w-full border rounded px-3 py-2 font-mono text-xs" rows="10">
{
    "cols": ["Географический объект", "Событие, явление, процесс", "Время, когда произошло событие"],
    "rows": [
        ["ячейка_01", "ячейка_02", "ячейка_03"],
        ["ячейка_04", "ячейка_05", "ячейка_06"],
        ["ячейка_07", "ячейка_08", "ячейка_09"],
        ["ячейка_10", "ячейка_11", "ячейка_12"]
    ]
}</textarea>
                <input type="hidden" name="tasks[0][order_matters]" value="1">
                </div>

                {{-- Изображение --}}
                <div class="mb-4 task-image hidden">
                    <label class="block text-sm font-medium">Изображение</label>
                    <input type="file" name="tasks[0][image]" class="w-full text-sm mt-1">
                </div>

                {{-- image_auto — опции и порядок важен --}}
                <div class="mb-4 task-image-auto-extra hidden">
                    <label class="block text-sm font-medium">Варианты ответа (по одному в строке, необязательно)</label>
                    <textarea name="tasks[0][image_auto_options][]" class="w-full border rounded px-3 py-2" rows="4"></textarea>

                    <div class="mt-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="tasks[0][image_auto_strict]" value="1" class="mr-2">
                            Порядок цифр/ответов важен
                        </label>
                    </div>
                </div>

                {{-- Порядок и номер --}}
                <div class="flex gap-4">
                    {{-- Номер в экзамене (Task) --}}
                    <div>
                    <label class="block text-sm font-medium mb-1">Номер в экзамене</label>
                    <select name="tasks[{{ $i }}][task_id]"
                            class="task-id-select w-full border rounded px-3 py-2"
                            data-current="{{ old('tasks.'.$i.'.task_id') }}">
                        <option value="">— выберите задание —</option>
                        {{-- options подтянет JS через /admin/courses/{course}/tasks --}}
                    </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Порядок</label>
                        <input type="number" name="tasks[0][order]" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium">Баллы</label>
                        <input type="number" name="tasks[0][max_score]" class="w-full border rounded px-3 py-2" min="1" step="1" value="1">
                    </div>
                </div>

                {{-- Текст (пассаж) для "Текст с вопросами" и "Развёрнутый ответ" --}}
                {{-- <div class="mb-4 task-passage hidden">
                    <label class="block text-sm font-medium">Текст (источник)</label>
                    <textarea name="tasks[0][passage_text]" class="w-full border rounded px-3 py-2" rows="5">{{old('tasks.0.passage_text')}}</textarea>
                </div> --}}

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

<script>
document.addEventListener('DOMContentLoaded', () => {
    let taskIndex = 1;

    function toggleFields(container, type) {
        container.querySelectorAll('.task-options, .task-matches, .task-image, .task-table, .task-passage, .task-image-auto-extra')
                 .forEach(el => el.classList.add('hidden'));

        if (type === 'multiple_choice') container.querySelector('.task-options')?.classList.remove('hidden');
        if (type === 'text_based') container.querySelector('.task-passage')?.classList.remove('hidden');
        if (type === 'matching') container.querySelector('.task-matches')?.classList.remove('hidden');
        if (type === 'table') {
            container.querySelector('.task-table')?.classList.remove('hidden');
            container.querySelector('.task-options')?.classList.remove('hidden'); // ← показываем варианты ответа
        }

        if (type === 'text_based' || type === 'written') container.querySelector('.task-passage')?.classList.remove('hidden');

        if (type === 'image_auto') {
            container.querySelector('.task-image')?.classList.remove('hidden');
            container.querySelector('.task-image-auto-extra')?.classList.remove('hidden');
        }
        if (type === 'image_written') container.querySelector('.task-image')?.classList.remove('hidden');
    }

    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-type')) {
            toggleFields(e.target.closest('.task-item'), e.target.value);
        }
    });

    document.getElementById('add-task').addEventListener('click', () => {
        const tasksContainer = document.getElementById('tasks-container');
        const newTask = tasksContainer.firstElementChild.cloneNode(true);

        newTask.querySelectorAll('input, textarea, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/\[\d+]/, `[${taskIndex}]`);
            if (el.type === 'checkbox') el.checked = false;
            else el.value = '';
        });

        newTask.querySelectorAll('.task-options, .task-matches, .task-image, .task-table, .task-passage, .task-image-auto-extra')
               .forEach(el => el.classList.add('hidden'));

        tasksContainer.appendChild(newTask);
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
      opt.textContent = `№ ${t.number ?? '—'} (ID ${t.id})`;
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
