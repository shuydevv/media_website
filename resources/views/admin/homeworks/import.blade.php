@extends('admin.layouts.main')

@section('title', 'Импорт домашки')

@section('content')
<div class="max-w-3xl">
    <x-ui.page-header title="Импорт домашки" :back="route('admin.homeworks.index')" back-label="Домашки" />

    <x-ui.card tone="gray" class="mb-6 text-sm text-zinc-600 space-y-2 break-words">
        <p>
            Курс и урок выбираются ниже на этой странице — в самом JSON-файле их указывать не нужно (и они
            игнорируются, если там всё же есть). JSON-файл вида <code>{"title": "...", "type": "homework",
            "due_at": "2026-09-01 12:00", "tasks": [...]}</code>. Каждый элемент <code>tasks</code> — либо
            <code>{"task_id": N}</code> (взять существующее задание из банка), либо полный объект содержания
            (те же поля, что при импорте в банк: <code>type</code>, <code>question_text</code>,
            <code>options</code>, <code>matches</code>, <code>table_content</code>,
            <code>image_auto_options</code>, <code>answer</code>, <code>hint</code>, <code>max_score</code>,
            опционально <code>"image_url": "https://..."</code> — картинка будет скачана и сохранена
            автоматически, <code>"save_to_bank": true</code>).
        </p>
        <p>
            Верхнеуровневый <code>"id"</code> в JSON — необязателен: если указать id существующей домашки, она
            не задублируется, а обновится, причём <code>tasks</code> из файла ПОЛНОСТЬЮ заменят её текущие
            задания. Без <code>id</code> всегда создаётся новая домашка.
            <a href="{{ route('admin.homeworks.import.example') }}" class="text-apple-blue-700 hover:underline whitespace-nowrap">Скачать пример файла →</a>
        </p>
    </x-ui.card>

    <form id="import-form" method="post" action="{{ route('admin.homeworks.import.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label for="import-course-id" class="ui-label">Курс</label>
            <select id="import-course-id" name="course_id" class="ui-input" required>
                <option value="">— выберите курс —</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="import-lesson-id" class="ui-label">
                Урок <span class="ui-label-note">(необязательно)</span>
            </label>
            <select id="import-lesson-id" name="lesson_id" class="ui-input" data-old-value="{{ old('lesson_id') }}">
                <option value="">— без урока —</option>
            </select>
            <p class="ui-hint">
                При повторном импорте поверх существующей домашки (через <code>"id"</code> в файле) — если
                урок здесь не выбран, у домашки останется её прежний урок (если он всё ещё относится к
                выбранному курсу).
            </p>
        </div>
        <div>
            <label class="ui-label">JSON-файл</label>
            <input type="file" name="file" accept=".json,application/json" class="ui-input" required>
        </div>
        <x-ui.checkbox name="confirm_duplicate" id="confirm-duplicate">
            Всё равно создать дубликат, если домашка с таким названием на этом курсе уже есть
        </x-ui.checkbox>
        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs" id="import-submit" class="disabled:opacity-60 disabled:cursor-not-allowed">Загрузить</x-ui.button>
            <x-ui.button href="{{ route('admin.homeworks.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Защита от повторной отправки: при импорте с картинками по image_url
        // запрос может идти долго, а без видимой реакции на клик легко
        // нажать "Загрузить" ещё раз — и без "id" в файле каждая такая
        // отправка создаёт отдельную домашку-дубль. Кнопка блокируется сразу
        // и остаётся такой даже если пользователь всё же дождётся навигации
        // на страницу с результатом (там форма для нового импорта уже не эта).
        const importForm = document.getElementById('import-form');
        const importSubmit = document.getElementById('import-submit');
        if (importForm && importSubmit) {
            importForm.addEventListener('submit', () => {
                if (importForm.dataset.submitted === '1') return;
                importForm.dataset.submitted = '1';
                importSubmit.disabled = true;
                importSubmit.textContent = 'Загрузка…';
            });
        }

        const courseSelect = document.getElementById('import-course-id');
        const lessonSelect = document.getElementById('import-lesson-id');
        if (!courseSelect || !lessonSelect) return;

        // Тот же /lessons?course_id=, что и на странице создания домашки
        // (admin/homeworks/create.blade.php) — переиспользуем один и тот же эндпоинт.
        function renderLessons(list, selectedId) {
            lessonSelect.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = list.length ? '— без урока —' : '— нет уроков у этого курса —';
            lessonSelect.appendChild(empty);
            list.forEach(lesson => {
                const opt = document.createElement('option');
                opt.value = lesson.id;
                opt.textContent = lesson.title;
                if (selectedId && String(lesson.id) === String(selectedId)) opt.selected = true;
                lessonSelect.appendChild(opt);
            });
        }

        function fetchLessons(courseId, selectedId) {
            if (!courseId) return renderLessons([], null);
            fetch(`/lessons?course_id=${courseId}`)
                .then(r => r.json())
                .then(data => renderLessons(data.lessons || [], selectedId));
        }

        // При возврате на страницу после ошибки валидации — восстановить и курс, и урок.
        const oldLessonId = lessonSelect.dataset.oldValue || null;
        if (courseSelect.value) fetchLessons(courseSelect.value, oldLessonId);
        courseSelect.addEventListener('change', () => fetchLessons(courseSelect.value, null));
    });
    </script>

    @isset($results)
        <div class="mt-8">
            <h2 class="sans-medium text-lg text-zinc-900 mb-3">
                Домашка «{{ $homework->title }}» {{ $isUpdate ? 'обновлена' : 'создана' }}.
                Заданий: {{ $created }} из {{ $total }} сохранено.
            </h2>
            <div class="space-y-2">
                @foreach($results as $r)
                    @if($r['ok'])
                        <x-ui.alert tone="green">
                            Строка {{ $r['index'] + 1 }}@if($r['label']) («{{ $r['label'] }}»)@endif: сохранено ({{ $r['note'] }})
                        </x-ui.alert>
                    @else
                        <x-ui.alert tone="red">
                            Строка {{ $r['index'] + 1 }}@if($r['label']) («{{ $r['label'] }}»)@endif: {{ $r['message'] }}
                        </x-ui.alert>
                    @endif
                @endforeach
            </div>
            <a href="{{ route('admin.homeworks.edit', $homework) }}" class="inline-block mt-4 text-apple-blue-700 hover:underline">Открыть домашку в редакторе →</a>
        </div>
    @endisset
</div>
@endsection
