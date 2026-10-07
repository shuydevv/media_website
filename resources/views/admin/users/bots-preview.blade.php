@extends('admin.layouts.main')

@section('title', 'Удаление ботов')

@section('content')
<x-ui.page-header title="Удаление ботов" :back="route('admin.user.index')" back-label="Пользователи">
    Критерий: без подтверждённого email/телефона, без записи на курс, без оплат,
    без сданных домашек и попыток решения заданий. Удаление необратимо — soft delete
    для пользователей не включён.
</x-ui.page-header>

@if($totalCount === 0)
    <x-ui.empty>Кандидатов под критерий не найдено.</x-ui.empty>
@else
    @if($totalCount > $candidates->count())
        <x-ui.alert tone="orange" class="mb-4">
            Всего найдено {{ $totalCount }}, показаны первые {{ $candidates->count() }}. Удалите эту партию
            и откройте страницу заново, чтобы обработать остальных.
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('admin.user.bots.destroy') }}" id="bots-form">
        @csrf
        @method('DELETE')

        <div class="flex items-center gap-2 mb-3">
            <x-ui.button id="select-all" variant="secondary" size="xs">Выбрать все</x-ui.button>
            <x-ui.button id="select-none" variant="ghost" size="xs">Снять всё</x-ui.button>
        </div>

        <x-ui.table>
            <thead>
            <tr>
                <th class="w-10"></th>
                <th>Имя</th>
                <th>ID</th>
                <th>Email</th>
                <th>Телефон</th>
                <th>Создан</th>
            </tr>
            </thead>
            <tbody>
            @foreach($candidates as $user)
                <tr>
                    <td>
                        <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" checked class="bot-checkbox checkbox-custom" aria-label="Удалить пользователя #{{ $user->id }}">
                    </td>
                    <td data-primary>{{ $user->name ?: '—' }}</td>
                    <td data-label="ID" class="font-mono text-xs text-zinc-500">#{{ $user->id }}</td>
                    <td data-label="Email"><span class="break-all">{{ $user->email ?? '—' }}</span></td>
                    <td data-label="Телефон">{{ $user->phone ?? '—' }}</td>
                    <td data-label="Создан" class="text-xs text-zinc-500">{{ optional($user->created_at)->format('d.m.Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-ui.table>

        <x-ui.form-actions>
            <x-ui.button type="submit" variant="danger" size="xs" id="destroy-btn">Удалить выбранных</x-ui.button>
        </x-ui.form-actions>
    </form>

    <script>
    (function () {
        var form = document.getElementById('bots-form');
        var checkboxes = () => Array.from(document.querySelectorAll('.bot-checkbox'));

        document.getElementById('select-all').addEventListener('click', function () {
            checkboxes().forEach(function (c) { c.checked = true; });
        });
        document.getElementById('select-none').addEventListener('click', function () {
            checkboxes().forEach(function (c) { c.checked = false; });
        });

        form.addEventListener('submit', function (e) {
            var selected = checkboxes().filter(function (c) { return c.checked; }).length;
            if (selected === 0) {
                e.preventDefault();
                return;
            }
            if (!confirm('Удалить безвозвратно ' + selected + ' пользователей? Это действие нельзя отменить.')) {
                e.preventDefault();
            }
        });
    })();
    </script>
@endif
@endsection
