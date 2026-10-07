@extends('admin.layouts.main')

@section('title', 'Новый урок')

@section('content')
<div class="max-w-3xl">
    <x-ui.page-header title="Создание урока" :back="route('admin.lessons.index')" back-label="Уроки" />

    <form action="{{ route('admin.lessons.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.select name="course_id" id="course_id" label="Курс" required>
                <option value="">Выберите курс</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                @endforeach
            </x-ui.select>

            {{-- Сессии подгружаются после выбора курса --}}
            <x-ui.select name="course_session_id" id="course_session_id" label="Сессия" required disabled>
                <option value="">Сначала выберите курс</option>
            </x-ui.select>
        </x-ui.card>

        @include('admin.lessons.partials.form-fields', ['lesson' => null])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить урок</x-ui.button>
            <x-ui.button href="{{ route('admin.lessons.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>

<script>
    document.getElementById('course_id').addEventListener('change', function () {
        const courseId = this.value;
        const sessionSelect = document.getElementById('course_session_id');

        if (!courseId) {
            sessionSelect.innerHTML = '<option value="">Сначала выберите курс</option>';
            sessionSelect.disabled = true;
            return;
        }

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
