@extends('admin.layouts.main')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Создание урока</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.lessons.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

                {{-- Выбор курса --}}
        <div class="mb-4">
            <label for="course_id" class="block text-sm font-medium">Курс</label>
            <select name="course_id" id="course_id" class="w-full border rounded px-3 py-2" required>
                <option value="">Выберите курс</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                @endforeach
            </select>
        </div>

        {{-- Сессии (будут подгружены динамически) --}}
        <div class="mb-4">
            <label for="course_session_id" class="block text-sm font-medium">Сессия</label>
            <select name="course_session_id" id="course_session_id" class="w-full border rounded px-3 py-2" required disabled>
                <option value="">Сначала выберите курс</option>
            </select>
        </div>
{{-- 
        Привязка к сессии --}}
        {{-- <div class="mb-4">
            <label for="course_session_id" class="block text-sm font-medium">Сессия</label>
            <select name="course_session_id" id="course_session_id" required class="w-full border rounded px-3 py-2">
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}">
                        {{ $session->date }} — {{ $session->course->title }}
                    </option>
                @endforeach
            </select>
        </div> --}}

        {{-- Тема урока --}}
        <div class="mb-4">
            <label for="title" class="block text-sm font-medium">Тема урока</label>
            <input type="text" name="title" id="title" class="w-full border rounded px-3 py-2" required>
        </div>

        {{-- Описание --}}
        <div class="mb-4">
            <label for="description" class="block text-sm font-medium">Описание</label>
            <textarea name="description" id="description" rows="4" class="w-full border rounded px-3 py-2">{{ old('description', $lesson->description ?? '') }}</textarea>
        </div>

        {{-- Тип урока --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Тип урока</label>
            <select name="lesson_type" class="w-full border rounded px-3 py-2">
                <option value="">— не выбран —</option>
                <option value="theory" @selected(old('lesson_type', $lesson->lesson_type ?? '') === 'theory')>Теория</option>
                {{-- <option value="webinar" @selected(old('lesson_type', $lesson->lesson_type ?? '') === 'webinar')>Вебинар</option> --}}
                <option value="practice" @selected(old('lesson_type', $lesson->lesson_type ?? '') === 'practice')>Практика</option>
            </select>
        </div>

        {{-- Ссылка на трансляцию --}}
        <div class="mb-4">
            <label for="meet_link" class="block text-sm font-medium">Ссылка на трансляцию (https://kinescope.io/(ВСТАВЬТЕ ТОЛЬКО ЭТИ СИМВОЛЫ))</label>
            <input type="text" name="meet_link" id="meet_link" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Ссылка на запись --}}
        <div class="mb-4">
            <label for="recording_link" class="block text-sm font-medium">Ссылка на запись (https://kinescope.io/[ВСТАВЬТЕ ТОЛЬКО ЭТИ СИМВОЛЫ])</label>
            <input type="text" name="recording_link" id="recording_link" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Выжимка --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Выжимка — короткий урок (https://kinescope.io/[ВСТАВЬТЕ ТОЛЬКО ЭТИ СИМВОЛЫ])</label>
            <input type="text" name="short_class" value="{{ old('short_class') }}"
                class="w-full border rounded px-3 py-2" placeholder="Напр.: Урок 3.1 или Блок A" />
        </div>

        {{-- Ссылка на конспект --}}
        <div class="mb-4">
            <label for="notes_link" class="block text-sm font-medium">Ссылка на конспект (Полная ссылка на файлообменник)</label>
            <input type="url" name="notes_link" id="notes_link" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Изображение --}}
        <div class="mb-6">
            <label for="image" class="block text-sm font-medium">Изображение</label>
            <input type="file" name="image" id="image" class="w-full text-sm text-gray-600 mt-1">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Сохранить урок
        </button>
    </form>
</div>

<script>
    document.getElementById('course_id').addEventListener('change', function () {
        const courseId = this.value;
        const sessionSelect = document.getElementById('course_session_id');

        sessionSelect.disabled = true;
        sessionSelect.innerHTML = '<option>Загрузка...</option>';

        fetch(`/admin/api/courses/${courseId}/sessions`)
            .then(response => response.json())
            .then(data => {
                sessionSelect.innerHTML = '';

                if (data.length === 0) {
                    sessionSelect.innerHTML = '<option>Нет доступных занятий</option>';
                } else {
                    data.forEach(session => {
                        const option = document.createElement('option');
                        option.value = session.id;
                        option.textContent = `${session.date} (${session.start_time})`;
                        sessionSelect.appendChild(option);
                    });
                }

                sessionSelect.disabled = false;
            })
            .catch(() => {
                sessionSelect.innerHTML = '<option>Ошибка загрузки</option>';
                sessionSelect.disabled = false;
            });
    });
</script>

@endsection
