@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Создать оповещение</h1>

        <form action="{{route('admin.announcements.store')}}" method="post">
            @csrf

            <div>
                <label class="text-zinc-800 text-sm">Текст оповещения</label>
                <textarea rows="3" class="p-2 block border mb-1 w-full max-w-xl" placeholder="Например: занятие 12 марта переносится на 14 марта" name="message">{{ old('message') }}</textarea>
            </div>
            @error('message')
            <p class="mb-4 text-red-400">*{{ $message }}</p>
            @enderror

            <div class="mt-5">
                <label>
                    <input type="checkbox" id="all_students" name="all_students" value="1" {{ old('all_students') ? 'checked' : '' }}>
                    Отправить всем ученикам
                </label>
            </div>

            <div class="mt-5" id="course-picker">
                <label class="mr-5">Или выберите курсы:</label>
                @foreach ($courses as $course)
                    <div>
                        <input type="checkbox" value="{{ $course->id }}" name="course_ids[]" id="course-{{ $course->id }}"
                               {{ collect(old('course_ids'))->contains($course->id) ? 'checked' : '' }}>
                        <label for="course-{{ $course->id }}">{{ $course->title }}</label>
                    </div>
                @endforeach
            </div>
            @error('course_ids')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            <div class="mt-5">
                <label class="text-zinc-800 text-sm">Скрыть автоматически после (необязательно)</label>
                <input type="datetime-local" class="p-2 block border" name="expires_at" value="{{ old('expires_at') }}">
            </div>
            @error('expires_at')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Отправить оповещение</button>
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
