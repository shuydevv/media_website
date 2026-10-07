@extends('admin.layouts.main')

@section('title', 'Новая домашка')

@section('content')
<div class="max-w-4xl">
    <x-ui.page-header title="Создание домашнего задания" :back="route('admin.homeworks.index')" back-label="Домашки" />

    @php($i = 0)

    {{-- Черновик из localStorage — на случай случайной перезагрузки/закрытия
         вкладки посреди заполнения. Появляется, только если найден
         непустой черновик; ничего не подставляется в поля без явного клика
         "Восстановить" — тихая перезапись того, что человек видит на
         экране прямо сейчас, была бы хуже, чем отсутствие черновика. --}}
    <div id="draft-banner" class="hidden mb-4 px-4 py-3 rounded-xl border border-apple-orange-200 bg-apple-orange-50 text-sm text-apple-orange-800 flex items-center justify-between gap-x-4 gap-y-2 flex-wrap">
        <span id="draft-banner-text"></span>
        <div class="flex items-center gap-3 shrink-0">
            <button type="button" id="draft-restore-btn" class="min-h-9 text-apple-blue-700 hover:underline font-medium">Восстановить</button>
            <button type="button" id="draft-discard-btn" class="min-h-9 text-zinc-500 hover:underline">Удалить черновик</button>
        </div>
    </div>

    <form id="homework-create-form" action="{{ route('admin.homeworks.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <x-ui.card class="mb-6">
        {{-- Название --}}
        <div class="mb-4">
            <label class="ui-label">Название</label>
            <input type="text" name="title" class="ui-input" required>
        </div>

        {{-- Описание --}}
        <div class="mb-4">
            <label class="ui-label">Описание</label>
            <textarea name="description" class="ui-input"></textarea>
        </div>

        {{-- Курс --}}
        <div class="mb-4">
            <label class="ui-label">Курс</label>
            <select name="course_id" id="course_id" class="ui-input">
                @foreach($courses as $course)
                <option value="{{ $course->id }}" data-category="{{ $course->category_id }}">
                    {{ $course->title }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Урок --}}
        <div class="mb-4">
            <label for="lesson_id" class="ui-label">Урок</label>
            <select class="ui-input" name="lesson_id" id="lesson_id" required></select>
        </div>


        {{-- Тип домашней работы --}}
        <div class="mb-6">
            <label class="ui-label">Тип</label>
            <select name="type" class="ui-input" required>
                <option value="homework">Обычное домашнее задание</option>
                <option value="mock">Пробник</option>
            </select>
        </div>

        {{-- Номер пробника — только для type=mock, задаётся вручную --}}
        <div class="mb-6">
            <label class="ui-label">Номер пробника</label>
            <input type="number" min="1" name="mock_number" class="ui-input"
                value="{{ old('mock_number') }}" placeholder="Например, 5 — только для пробников">
        </div>

        {{-- Дедлайн --}}
        <div class="mb-4">
            <label class="ui-label">Дедлайн</label>
            <input type="datetime-local" name="due_at"
                class="ui-input"
                value="{{ old('due_at', isset($homework->due_at) ? $homework->due_at->format('Y-m-d\TH:i') : '') }}">
        </div>

        </x-ui.card>

        <h2 class="sans-medium text-xl md:text-2xl text-zinc-900 mb-3">Задания</h2>

        {{-- Список заданий --}}
        <div id="tasks-container" class="space-y-4">
            <div class="task-item rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 relative">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="sans-medium text-lg text-zinc-900">Задание</h2>
                    {{-- Порядок — стрелками, а не вручную вводимым числом:
                         реальный порядок задаётся положением карточки в
                         списке, стрелки просто переставляют карточки
                         местами (см. task-editor скрипт ниже). --}}
                    <div class="flex items-center gap-1">
                        <button type="button" class="task-move-up w-10 h-10 md:w-8 md:h-8 flex items-center justify-center rounded-lg border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100 disabled:opacity-30 disabled:cursor-not-allowed" title="Переместить вверх">↑</button>
                        <button type="button" class="task-move-down w-10 h-10 md:w-8 md:h-8 flex items-center justify-center rounded-lg border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100 disabled:opacity-30 disabled:cursor-not-allowed" title="Переместить вниз">↓</button>
                    </div>
                </div>
                <input type="hidden" name="tasks[0][order]" class="task-order-input" value="1">

                {{-- Источник задания: своё содержание (как раньше) или уже
                     существующее в банке — переиспользуется, автоматически
                     доступно в личном кабинете и (если помечено) публично. --}}
                <div class="mb-4">
                    <label class="inline-flex items-center gap-2 mr-6 min-h-10 text-sm cursor-pointer">
                        <input type="radio" name="tasks[0][source]" value="own" class="task-source-toggle" checked>
                        Создать новое
                    </label>
                    <label class="inline-flex items-center gap-2 min-h-10 text-sm cursor-pointer">
                        <input type="radio" name="tasks[0][source]" value="bank" class="task-source-toggle">
                        Из банка заданий
                    </label>
                </div>

                <div class="task-own-fields">
                    <x-task-content-fields name="tasks[0]" :task="null" :number-options="$numberOptions" />
                </div>

                {{-- ID вместо выпадающего списка — в банке могут быть сотни
                     заданий, тянуть и рендерить их все в <select> не годится. --}}
                <div class="task-bank-fields hidden mb-4">
                    <label class="ui-label">Задание из банка — ID</label>
                    <input type="number" name="tasks[0][task_id]" class="task-id-input ui-input"
                           min="1" step="1" placeholder="Например, 42" value="{{ old('tasks.0.task_id') }}">
                    <div class="task-id-preview text-xs mt-1"></div>
                    <div class="mt-1 text-sm">
                        <a href="{{ route('admin.tasks.create') }}" target="_blank" class="text-apple-blue-700 hover:underline">Создать новое в банке →</a>
                        <a href="{{ route('admin.tasks.index') }}" target="_blank" class="text-apple-blue-700 hover:underline ml-3">Найти ID в банке →</a>
                    </div>
                </div>

                {{-- Баллы — общие для обоих режимов. По умолчанию берутся из
                     критериев (общие на весь номер в ЕГЭ); это поле — редкое
                     точечное исключение именно для этой домашки. --}}
                <div>
                    <label class="ui-label">Баллы <span class="ui-label-note">(необязательно — переопределяет баллы по умолчанию из критериев только в этой домашке)</span></label>
                    <input type="number" name="tasks[0][max_score]" class="ui-input" min="1" step="1">
                </div>

                <div class="mt-4 flex items-center justify-between flex-wrap gap-2">
                    <label class="task-save-to-bank-wrap inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" class="checkbox-custom" name="tasks[0][save_to_bank]" value="1">
                        Также сохранить в банк заданий
                    </label>
                    <button type="button" class="delete-task min-h-10 px-2 -mr-2 text-apple-red-650 text-sm hover:underline">Удалить задание</button>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <x-ui.button id="add-task" variant="secondary" size="xs">+ Добавить задание</x-ui.button>
        </div>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить домашнее задание</x-ui.button>
            <x-ui.button href="{{ route('admin.homeworks.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
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

    document.querySelectorAll('.task-item').forEach(item => {
        const checked = item.querySelector('.task-source-toggle:checked');
        if (checked) toggleSource(item, checked.value);
    });

    document.addEventListener('change', e => {
        if (e.target.classList.contains('task-source-toggle')) {
            toggleSource(e.target.closest('.task-item'), e.target.value);
        }
    });

    // Порядок заданий — реальное положение карточек в #tasks-container,
    // стрелки просто переставляют DOM-узлы местами. tasks[N][order] —
    // скрытое поле, всегда пересчитывается по итоговому положению, руками
    // его никто не заполняет.
    function renumberTasks() {
        const items = [...document.querySelectorAll('#tasks-container .task-item')];
        items.forEach((item, i) => {
            const input = item.querySelector('.task-order-input');
            if (input) input.value = i + 1;
            item.querySelector('.task-move-up').disabled = (i === 0);
            item.querySelector('.task-move-down').disabled = (i === items.length - 1);
        });
    }
    renumberTasks();
    // Нужна извне — черновик из localStorage восстанавливает карточки в
    // исходном порядке создания, а не в сохранённом визуальном (см. ниже),
    // и пересчитывает и то, и другое этой же функцией.
    window.renumberTasks = renumberTasks;

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
            renumberTasks();
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
        newTask.querySelector('.task-id-preview').textContent = '';

        toggleSource(newTask, 'own');

        tasksContainer.appendChild(newTask);
        window.initTaskContentFields && window.initTaskContentFields(newTask.querySelector('.task-content-fields'));

        taskIndex++;
        renumberTasks();
    });

    document.addEventListener('click', e => {
        if (e.target.classList.contains('delete-task')) {
            if (document.querySelectorAll('.task-item').length === 1) return alert('Нельзя удалить последнее задание');
            e.target.closest('.task-item').remove();
            renumberTasks();
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

{{-- Черновик формы в localStorage — на случай случайной перезагрузки/
     закрытия вкладки. Файлы (картинки) принципиально не восстановимы через
     localStorage (File — не сериализуется в JSON) и не сохраняются вовсе;
     CSRF-токен тоже не сохраняется — форма всегда использует токен
     ТЕКУЩЕЙ загруженной страницы, а не токен на момент сохранения черновика
     (он мог протухнуть). --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const DRAFT_KEY = 'hw_draft_create_v1';
    const form = document.getElementById('homework-create-form');
    const banner = document.getElementById('draft-banner');
    const bannerText = document.getElementById('draft-banner-text');
    const restoreBtn = document.getElementById('draft-restore-btn');
    const discardBtn = document.getElementById('draft-discard-btn');
    if (!form) return;

    function waitFor(check, tries, interval) {
        tries = tries || 40;
        interval = interval || 100;
        return new Promise(resolve => {
            let n = 0;
            (function tick() {
                if (check() || n++ >= tries) return resolve();
                setTimeout(tick, interval);
            })();
        });
    }

    function serializeForm() {
        const fields = [];
        form.querySelectorAll('[name]').forEach(el => {
            if (el.type === 'file' || el.name === '_token') return;
            if (el.type === 'radio' || el.type === 'checkbox') {
                fields.push({ name: el.name, type: el.type, value: el.value, checked: el.checked });
            } else {
                fields.push({ name: el.name, type: el.type, value: el.value });
            }
        });
        return { savedAt: new Date().toISOString(), fields };
    }

    function hasContent(draft) {
        return draft.fields.some(f => {
            if (f.type === 'radio' || f.type === 'checkbox') return false;
            return (f.value || '').trim() !== '';
        });
    }

    // Восстановление черновика само проставляет значения полей и шлёт
    // input/change-события (чтобы отработали автовысота/превью/pin-виджет/
    // конструктор таблицы) — без этого флага те же события всплывали бы до
    // слушателя автосохранения и через 500мс переписывали бы в localStorage
    // уже очищенный clearDraft() черновик прямо во время восстановления.
    let isRestoring = false;
    let saveTimer = null;
    function saveDraft() {
        if (isRestoring) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            try {
                localStorage.setItem(DRAFT_KEY, JSON.stringify(serializeForm()));
            } catch (e) { /* хранилище недоступно/переполнено — просто не сохраняем */ }
        }, 500);
    }

    function clearDraft() {
        clearTimeout(saveTimer);
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        banner?.classList.add('hidden');
    }

    function loadDraft() {
        try {
            const raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return null;
            const draft = JSON.parse(raw);
            return (draft && Array.isArray(draft.fields)) ? draft : null;
        } catch (e) { return null; }
    }

    function taskIndexesInDraft(draft) {
        const idx = new Set();
        draft.fields.forEach(f => {
            const m = f.name.match(/^tasks\[(\d+)\]/);
            if (m) idx.add(Number(m[1]));
        });
        return [...idx].sort((a, b) => a - b);
    }

    function setAndDispatch(el, value, checked) {
        if (el.type === 'radio' || el.type === 'checkbox') {
            if (!checked) return;
            el.checked = true;
        } else {
            el.value = value;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    async function restoreDraft(draft) {
        isRestoring = true;
        const byName = {};
        draft.fields.forEach(f => { (byName[f.name] = byName[f.name] || []).push(f); });

        const indexes = taskIndexesInDraft(draft);
        const neededCount = indexes.length || 1;

        // Дорастить число карточек кликами по "Добавить задание" — это та
        // же самая клонирующая логика, что и при обычном ручном добавлении
        // (переименование полей, настройка pin-виджета и т.д.), ничего не
        // дублируем.
        const addBtn = document.getElementById('add-task');
        while (document.querySelectorAll('#tasks-container .task-item').length < neededCount && addBtn) {
            addBtn.click();
        }

        const titleEl = form.querySelector('[name="title"]');
        if (titleEl && byName['title']) setAndDispatch(titleEl, byName['title'][0].value);
        const descEl = form.querySelector('[name="description"]');
        if (descEl && byName['description']) setAndDispatch(descEl, byName['description'][0].value);
        const hwTypeEl = form.querySelector('select[name="type"]');
        if (hwTypeEl && byName['type']) setAndDispatch(hwTypeEl, byName['type'][0].value);
        const dueAtEl = form.querySelector('[name="due_at"]');
        if (dueAtEl && byName['due_at']) setAndDispatch(dueAtEl, byName['due_at'][0].value);

        // Курс/урок — урок подгружается асинхронно после смены курса,
        // значение можно проставить только когда список уже пришёл.
        const courseSelect = document.getElementById('course_id');
        const lessonSelect = document.getElementById('lesson_id');
        if (courseSelect && byName['course_id']) {
            courseSelect.value = byName['course_id'][0].value;
            courseSelect.dispatchEvent(new Event('change', { bubbles: true }));
            await waitFor(() => lessonSelect && lessonSelect.options.length > 0);
        }
        if (lessonSelect && byName['lesson_id']) {
            lessonSelect.value = byName['lesson_id'][0].value;
        }

        // Карточки заданий — восстанавливаем в исходном порядке создания
        // (по индексу в имени поля), визуальный порядок из стрелок
        // применяем отдельным проходом ниже: стрелки переставляют только
        // положение карточки в DOM, а не индекс в именах её полей.
        indexes.forEach(i => {
            const prefix = `tasks[${i}]`;
            const root = document.querySelectorAll('#tasks-container .task-item')[i];
            if (!root) return;

            const sourceField = (byName[`${prefix}[source]`] || []).find(f => f.checked);
            if (sourceField) {
                const radio = root.querySelector(`[name="${prefix}[source]"][value="${sourceField.value}"]`);
                if (radio) setAndDispatch(radio, null, true);
            }

            const typeField = byName[`${prefix}[type]`];
            const restoredType = typeField ? typeField[0].value : '';
            const typeSelect = root.querySelector('.task-type');
            // Смена типа очищает все поля содержания (см. clearContentFields
            // в task-editor-script.blade.php) — обязательно ДО того, как
            // проставляем восстановленные значения ниже, иначе они тут же
            // затрутся в пустоту.
            if (typeSelect && restoredType) setAndDispatch(typeSelect, restoredType);

            // По имени — не по отдельным полям: answer, например, встречается
            // ДВАЖДЫ в разметке (квадратики + textarea, взаимно disabled по
            // типу) с одним и тем же name, так что у него в byName будет два
            // элемента массива. Сопоставляем их позиционно с find'ом всех
            // текущих DOM-узлов того же имени (в том же порядке разметки) —
            // иначе, обрабатывая записи по одной через querySelectorAll,
            // вторая (пустая, от неактивной textarea) запись затирала бы
            // значение первой (от активных квадратиков) на том же элементе.
            // task_id теперь обычный <input> (не <select>, подгружаемый
            // асинхронно) — восстанавливается тем же общим путём, ничего
            // особого ему не нужно.
            Object.keys(byName)
                .filter(name => name.startsWith(prefix + '[')
                    && !name.endsWith('[source]') && !name.endsWith('[type]') && !name.endsWith('[order]'))
                .forEach(name => {
                    if (name.endsWith('[answer]') && restoredType === 'table') return; // выводится из таблицы
                    const entries = byName[name];
                    root.querySelectorAll(`[name="${name}"]`).forEach((el, i) => {
                        if (entries[i]) setAndDispatch(el, entries[i].value, entries[i].checked);
                    });
                });

            if (restoredType === 'table') {
                window.renderTableBuilder && window.renderTableBuilder(root.querySelector('.task-content-fields'));
            }
        });

        // Визуальный порядок — по сохранённым tasks[N][order].
        const container = document.getElementById('tasks-container');
        const items = [...container.querySelectorAll('.task-item')];
        items
            .map((item, i) => {
                const orderField = byName[`tasks[${i}][order]`];
                return { item, order: orderField ? (Number(orderField[0].value) || (i + 1)) : (i + 1) };
            })
            .sort((a, b) => a.order - b.order)
            .forEach(({ item }) => container.appendChild(item));
        window.renumberTasks && window.renumberTasks();

        clearDraft();
        isRestoring = false;
    }

    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);
    form.addEventListener('submit', clearDraft);

    const draft = loadDraft();
    if (draft && hasContent(draft) && banner && bannerText) {
        const when = new Date(draft.savedAt);
        bannerText.textContent = `Найден несохранённый черновик от ${when.toLocaleString('ru-RU')}. Изображения нужно будет выбрать заново.`;
        banner.classList.remove('hidden');
        restoreBtn?.addEventListener('click', () => { banner.classList.add('hidden'); restoreDraft(draft); });
        discardBtn?.addEventListener('click', clearDraft);
    }
});
</script>

@endsection
