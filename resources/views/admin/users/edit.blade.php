@extends('admin.layouts.main')

@section('title', 'Редактирование пользователя')

@section('content')
<div class="max-w-2xl">
    <x-ui.page-header title="Редактировать пользователя" :back="route('admin.user.show', $user)" back-label="К отчёту по ученику" />

    <form action="{{ route('admin.user.update', $user->id) }}" method="post" class="space-y-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="user_id" value="{{ $user->id }}">

        @include('admin.users.partials.form-fields', ['user' => $user])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.user.show', $user) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>

    <x-ui.card tone="gray" class="mt-6">
        <h2 class="sans-medium text-lg text-zinc-900 mb-2">Приглашение</h2>
        @if($user->profile_completed_at)
            <p class="text-sm text-zinc-600">
                Пользователь завершил регистрацию {{ $user->profile_completed_at->translatedFormat('j F Y') }}.
                Для восстановления доступа используйте обычное восстановление пароля («Забыли пароль?» на странице входа), не повторное приглашение.
            </p>
        @else
            <p class="text-sm text-zinc-600 mb-3">
                Регистрация ещё не завершена — приглашение можно отправить повторно.
                @if($user->invite_sent_at)
                    Ссылка сформирована {{ $user->invite_sent_at->format('d.m.Y H:i') }}.
                @endif
            </p>
            <x-ui.action-form :action="route('admin.user.invite', $user)" method="POST" variant="secondary">Отправить приглашение повторно</x-ui.action-form>
        @endif

        @if(session('inviteUrl'))
            <div class="mt-4 pt-4 border-t border-zinc-200">
                <p class="text-xs text-zinc-500 mb-2">
                    Ссылка отправлена на почту, но если письмо не дошло — можно скопировать и отправить ученику вручную (мессенджер и т.п.). Действует 7 дней.
                </p>
                <div class="flex flex-wrap gap-2">
                    <input id="invite-url-input" type="text" readonly value="{{ session('inviteUrl') }}"
                           class="ui-input flex-1 min-w-[200px]" onclick="this.select()" aria-label="Ссылка-приглашение">
                    <x-ui.button id="invite-url-copy" variant="secondary" size="xs">Скопировать</x-ui.button>
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
    </x-ui.card>
</div>
@endsection
