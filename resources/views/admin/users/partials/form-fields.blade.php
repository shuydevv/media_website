{{--
    Общие поля формы пользователя — create.blade.php и edit.blade.php.
    $user       — редактируемый пользователь или null при создании
    $enrolledUntil — [course_id => 'Y-m-d'] уже выданных доступов (только edit)
--}}
@php
    $user = $user ?? null;
    $enrolledUntil = $enrolledUntil ?? [];
@endphp

<x-ui.card class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.input name="first_name" label="Имя" value="{{ old('first_name', $user?->first_name) }}" required autocomplete="off" />
        <x-ui.input name="last_name" label="Фамилия" value="{{ old('last_name', $user?->last_name) }}" autocomplete="off" />
    </div>

    <x-ui.input name="email" label="Email" value="{{ old('email', $user?->email) }}" required autocomplete="off" inputmode="email" />

    <x-ui.input name="name" label="Имя пользователя в телеграм" value="{{ old('name', $user?->name) }}" placeholder="@username" autocomplete="off"
                :hint="$user ? null : 'Необязательно — если не знаете, ученик укажет сам при первом входе.'" />

    <x-ui.input name="phone" type="tel" label="Телефон" value="{{ old('phone', $user?->phone) }}" placeholder="+7 999 123-45-67" autocomplete="off" />

    <x-ui.select name="role" label="Роль">
        @foreach ($roles as $id => $role)
            <option value="{{ $id }}" @selected(old('role', $user?->role) == $id)>{{ $role }}</option>
        @endforeach
    </x-ui.select>
</x-ui.card>

<x-ui.card>
    <h2 class="sans-medium text-lg text-zinc-900">Доступ к курсам</h2>
    <p class="text-sm text-zinc-500 mt-1 mb-4">
        {{ $user ? 'Уже выданные курсы отмечены — можно поменять дату или добавить новые.' : 'Необязательно. Можно выбрать несколько — у каждого своя дата.' }}
    </p>

    <div class="space-y-2">
        @foreach ($courses as $course)
            @php
                $currentUntil = $enrolledUntil[$course->id] ?? null;
                $isChecked = collect(old('course_ids', $currentUntil !== null ? [$course->id] : []))->contains($course->id);
            @endphp
            <div class="course-row flex flex-wrap items-center gap-x-3 gap-y-2 border border-zinc-200 rounded-xl px-3 py-2.5">
                <div class="flex-1 min-w-[200px]">
                    <x-ui.checkbox name="course_ids[]" :value="$course->id" :checked="$isChecked" class="course-toggle">{{ $course->title }}</x-ui.checkbox>
                </div>
                <input type="date" name="access_until[{{ $course->id }}]"
                       value="{{ old('access_until.'.$course->id, $currentUntil) }}"
                       class="course-access-date ui-input ui-input-sm w-full sm:w-44" aria-label="Доступ до"
                       @disabled(! $isChecked)>
                @error('access_until.'.$course->id) <p class="ui-error w-full mt-0">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
</x-ui.card>

@push('page-scripts')
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
@endpush
