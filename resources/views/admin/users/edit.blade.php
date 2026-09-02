@extends('admin.layouts.main')

@section('content')
<h1 class="sans-medium text-xl text-zinc-900 mb-5">Редактировать пользователя</h1>

@if(session('success'))
    <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 sans max-w-lg">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2 sans max-w-lg">
        {{ session('error') }}
    </div>
@endif

<form action="{{ route('admin.user.update', $user->id) }}" method="post" class="max-w-lg space-y-4">
    @csrf
    @method('PATCH')
    <input type="hidden" name="user_id" value="{{ $user->id }}">

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm text-zinc-600 mb-1 sans">Имя</label>
            <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                   class="w-full border rounded-lg px-3 py-2 input-focus sans" required>
            @error('first_name') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm text-zinc-600 mb-1 sans">Фамилия</label>
            <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                   class="w-full border rounded-lg px-3 py-2 input-focus sans">
            @error('last_name') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Email</label>
        <input type="text" name="email" value="{{ old('email', $user->email) }}"
               class="w-full border rounded-lg px-3 py-2 input-focus sans" required>
        @error('email') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Имя пользователя в телеграм</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" placeholder="@username"
               class="w-full border rounded-lg px-3 py-2 input-focus sans">
        @error('name') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Телефон</label>
        <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+7 999 123-45-67"
               class="w-full border rounded-lg px-3 py-2 input-focus sans">
        @error('phone') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm text-zinc-600 mb-1 sans">Роль</label>
        <select name="role" class="w-full border rounded-lg px-3 py-2 input-focus sans">
            @foreach ($roles as $id => $role)
                <option value="{{ $id }}" {{ old('role', $user->role) == $id ? 'selected' : '' }}>{{ $role }}</option>
            @endforeach
        </select>
        @error('role') <p class="mt-1 text-sm text-rose-600 sans">{{ $message }}</p> @enderror
    </div>

    <div class="border-t pt-4">
        <div class="sans-medium text-sm text-zinc-700 mb-1">Доступ к курсам</div>
        <p class="text-xs text-zinc-400 mb-3 sans">Уже выданные курсы отмечены — можно поменять дату или добавить новые.</p>

        <div class="space-y-2">
            @foreach ($courses as $course)
                @php $currentUntil = $enrolledUntil[$course->id] ?? null; @endphp
                <div class="course-row flex flex-wrap items-center gap-3 border border-zinc-200 rounded-lg px-3 py-2">
                    <label class="flex items-center gap-2 text-sm sans cursor-pointer">
                        <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                               class="checkbox-custom course-toggle" style="width:18px;height:18px;"
                               {{ collect(old('course_ids', $currentUntil !== null ? [$course->id] : []))->contains($course->id) ? 'checked' : '' }}>
                        {{ $course->title }}
                    </label>
                    <input type="date" name="access_until[{{ $course->id }}]"
                           value="{{ old('access_until.'.$course->id, $currentUntil) }}"
                           class="course-access-date border rounded-lg px-2 py-1 input-focus sans text-sm ml-auto">
                    @error('access_until.'.$course->id) <p class="w-full text-sm text-rose-600 sans">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="pt-2">
        <button type="submit" class="rounded-lg px-4 py-3 bg-zinc-900 text-white font-medium hover:bg-zinc-800 transition sans-medium">
            Сохранить изменения
        </button>
    </div>
</form>

<script>
document.querySelectorAll('.course-toggle').forEach(function (checkbox) {
    var dateInput = checkbox.closest('.course-row').querySelector('.course-access-date');
    var sync = function () { dateInput.disabled = !checkbox.checked; };
    checkbox.addEventListener('change', sync);
    sync();
});
</script>

<div class="max-w-lg border-t mt-6 pt-4">
    @if($user->profile_completed_at)
        <p class="text-sm text-zinc-500 sans">
            Пользователь завершил регистрацию {{ $user->profile_completed_at->translatedFormat('j F Y') }}.
            Для восстановления доступа используйте обычное восстановление пароля («Забыли пароль?» на странице входа), не повторное приглашение.
        </p>
    @else
        <p class="text-sm text-zinc-500 sans mb-2">Регистрация ещё не завершена — приглашение можно отправить повторно.</p>
        <form action="{{ route('admin.user.invite', $user) }}" method="post">
            @csrf
            <button type="submit" class="rounded-lg px-3 py-1.5 border sans text-sm text-zinc-600 hover:bg-zinc-50">
                Отправить приглашение повторно
            </button>
        </form>
    @endif

    @if(session('inviteUrl'))
        <div class="mt-4 bg-zinc-50 border border-zinc-200 rounded-lg p-3">
            <p class="text-xs text-zinc-500 sans mb-2">
                Ссылка отправлена на почту, но если письмо не дошло — можно скопировать и отправить ученику вручную (мессенджер и т.п.). Действует 7 дней.
            </p>
            <div class="flex gap-2">
                <input id="invite-url-input" type="text" readonly value="{{ session('inviteUrl') }}"
                       class="w-full border rounded-lg px-3 py-2 text-xs input-focus sans bg-white"
                       onclick="this.select()">
                <button type="button" id="invite-url-copy" class="shrink-0 rounded-lg px-3 py-2 border sans text-sm text-zinc-600 hover:bg-white">
                    Скопировать
                </button>
            </div>
        </div>
        <script>
        document.getElementById('invite-url-copy').addEventListener('click', function () {
            var input = document.getElementById('invite-url-input');
            input.select();
            navigator.clipboard.writeText(input.value).then(function () {
                var btn = document.getElementById('invite-url-copy');
                var original = btn.textContent;
                btn.textContent = 'Скопировано ✓';
                setTimeout(function () { btn.textContent = original; }, 1500);
            }).catch(function () {
                alert('Не удалось скопировать автоматически — выделите и скопируйте ссылку вручную.');
            });
        });
        </script>
    @endif
</div>
@endsection
