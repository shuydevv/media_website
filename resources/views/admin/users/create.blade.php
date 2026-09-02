@extends('admin.layouts.main')

@section('content')
<h1 class="sans-medium text-xl text-zinc-900 mb-5">Создать пользователя</h1>

<form action="{{ route('admin.user.store') }}" method="post" class="max-w-lg space-y-4">
    @csrf

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm text-zinc-600 mb-1 sans">Имя</label>
            <input type="text" name="first_name" value="{{ old('first_name') }}"
                   class="w-full border rounded-lg px-3 py-2 input-focus sans" required>
            @error('first_name') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm text-zinc-600 mb-1 sans">Фамилия</label>
            <input type="text" name="last_name" value="{{ old('last_name') }}"
                   class="w-full border rounded-lg px-3 py-2 input-focus sans">
            @error('last_name') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Email</label>
        <input type="text" name="email" value="{{ old('email') }}"
               class="w-full border rounded-lg px-3 py-2 input-focus sans" required>
        @error('email') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Имя пользователя в телеграм</label>
        <input type="text" name="name" value="{{ old('name') }}" placeholder="@username"
               class="w-full border rounded-lg px-3 py-2 input-focus sans">
        <p class="text-xs text-zinc-400 mt-1 sans">Необязательно — если не знаете, ученик укажет сам при первом входе.</p>
        @error('name') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Телефон</label>
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+7 999 123-45-67"
               class="w-full border rounded-lg px-3 py-2 input-focus sans">
        @error('phone') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Роль</label>
        <select name="role" class="w-full border rounded-lg px-3 py-2 input-focus sans">
            @foreach ($roles as $id => $role)
                <option value="{{ $id }}" {{ old('role') == $id ? 'selected' : '' }}>{{ $role }}</option>
            @endforeach
        </select>
        @error('role') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div class="border-t pt-4">
        <div class="sans-medium text-sm text-zinc-700 mb-1">Доступ к курсам (необязательно)</div>
        <p class="text-xs text-zinc-400 mb-3 sans">Можно выбрать несколько — у каждого своя дата.</p>

        <div class="space-y-2">
            @foreach ($courses as $course)
                <div class="course-row flex flex-wrap items-center gap-3 border border-zinc-200 rounded-lg px-3 py-2">
                    <label class="flex items-center gap-2 text-sm sans cursor-pointer">
                        <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                               class="checkbox-custom course-toggle" style="width:18px;height:18px;"
                               data-course-id="{{ $course->id }}"
                               {{ collect(old('course_ids'))->contains($course->id) ? 'checked' : '' }}>
                        {{ $course->title }}
                    </label>
                    <input type="date" name="access_until[{{ $course->id }}]"
                           value="{{ old('access_until.'.$course->id) }}"
                           class="course-access-date border rounded-lg px-2 py-1 input-focus sans text-sm ml-auto"
                           disabled>
                    @error('access_until.'.$course->id) <p class="w-full text-sm text-rose-600 sans">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="pt-2">
        <button type="submit" class="rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition sans-medium">
            Создать и отправить приглашение
        </button>
    </div>
</form>

<script>
// Дата доступа активна только для отмеченных курсов — иначе disabled-поле
// не попадёт в отправку формы вовсе, даже если в нём есть значение.
document.querySelectorAll('.course-toggle').forEach(function (checkbox) {
    var dateInput = checkbox.closest('.course-row').querySelector('.course-access-date');
    var sync = function () { dateInput.disabled = !checkbox.checked; };
    checkbox.addEventListener('change', sync);
    sync(); // сразу применить при отрисовке (в т.ч. после ошибки валидации с old())
});
</script>
@endsection
