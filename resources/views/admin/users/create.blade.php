@extends('admin.layouts.main')

@section('title', 'Новый пользователь')

@section('content')
<div class="max-w-2xl">
    <x-ui.page-header title="Создать пользователя" :back="route('admin.user.index')" back-label="Пользователи" />

    <form action="{{ route('admin.user.store') }}" method="post" class="space-y-4">
        @csrf

        @include('admin.users.partials.form-fields', ['user' => null])

        <x-ui.card>
            <x-ui.checkbox name="skip_invite" id="skip-invite" :checked="(bool) old('skip_invite')">
                Не отправлять приглашение
                <span class="block text-xs text-zinc-500 mt-0.5">Только для внутренней работы в CRM — у пользователя не будет доступа к платформе, пока приглашение не отправят вручную с его страницы.</span>
            </x-ui.checkbox>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs" id="submit-btn">Создать и отправить приглашение</x-ui.button>
            <x-ui.button href="{{ route('admin.user.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>

<script>
(function () {
    var skipInvite = document.getElementById('skip-invite');
    var submitBtn = document.getElementById('submit-btn');
    var sync = function () {
        submitBtn.textContent = skipInvite.checked ? 'Создать без приглашения' : 'Создать и отправить приглашение';
    };
    skipInvite.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
