@extends('admin.layouts.main')

@section('title', 'Новое оповещение')

@section('content')
<div class="max-w-2xl">
    <x-ui.page-header title="Создать оповещение" :back="route('admin.announcements.index')" back-label="Оповещения" />

    <form action="{{ route('admin.announcements.store') }}" method="post" class="space-y-4">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.textarea name="message" label="Текст оповещения" rows="3"
                           placeholder="Например: занятие 12 марта переносится на 14 марта">{{ old('message') }}</x-ui.textarea>

            <x-ui.input type="datetime-local" name="expires_at" label="Скрыть автоматически после" note="необязательно" value="{{ old('expires_at') }}" />
        </x-ui.card>

        <x-ui.card>
            <h2 class="sans-medium text-lg text-zinc-900 mb-3">Кому показать</h2>

            <x-ui.checkbox name="all_students" id="all_students" :checked="(bool) old('all_students')">Всем ученикам</x-ui.checkbox>

            <div class="mt-4 pt-4 border-t border-zinc-100" id="course-picker">
                <div class="ui-label">Или только ученикам курсов</div>
                <div class="space-y-2">
                    @foreach ($courses as $course)
                        <x-ui.checkbox name="course_ids[]" :value="$course->id" id="course-{{ $course->id }}"
                                       :checked="collect(old('course_ids'))->contains($course->id)">{{ $course->title }}</x-ui.checkbox>
                    @endforeach
                </div>
                @error('course_ids')
                    <p class="ui-error">{{ $message }}</p>
                @enderror
            </div>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Отправить оповещение</x-ui.button>
            <x-ui.button href="{{ route('admin.announcements.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>

<script>
    (function () {
        var allStudents = document.getElementById('all_students');
        var coursePicker = document.getElementById('course-picker');

        function sync() {
            coursePicker.style.display = allStudents.checked ? 'none' : '';
        }

        allStudents.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
