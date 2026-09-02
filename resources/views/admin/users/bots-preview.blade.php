@extends('admin.layouts.main')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Удаление ботов</h1>
        <p class="text-sm text-zinc-500 mt-1">
            Критерий: без подтверждённого email/телефона, без записи на курс, без оплат,
            без сданных домашек и попыток решения заданий. Удаление необратимо — soft delete
            для пользователей не включён.
        </p>
    </div>
    <a href="{{ route('admin.user.index') }}" class="px-4 py-2 rounded-lg text-zinc-700 hover:bg-zinc-100">Назад</a>
</div>

@if($totalCount === 0)
    <div class="bg-white border rounded-2xl p-6 shadow-sm text-zinc-600">
        Кандидатов под критерий не найдено.
    </div>
@else
    @if($totalCount > $candidates->count())
        <div class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            Всего найдено {{ $totalCount }}, показаны первые {{ $candidates->count() }}. Удалите эту партию
            и откройте страницу заново, чтобы обработать остальных.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.user.bots.destroy') }}" id="bots-form">
        @csrf
        @method('DELETE')

        <div class="flex items-center gap-3 mb-3">
            <button type="button" id="select-all" class="text-sm text-zinc-600 hover:text-zinc-900 underline">Выбрать все</button>
            <button type="button" id="select-none" class="text-sm text-zinc-600 hover:text-zinc-900 underline">Снять всё</button>
        </div>

        <div class="overflow-x-auto bg-white rounded-2xl shadow-sm ring-1 ring-black/5">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-left text-zinc-600">
                <tr>
                    <th class="px-4 py-3 w-10"></th>
                    <th class="px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">Имя</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Телефон</th>
                    <th class="px-4 py-3 font-medium">Создан</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                @foreach($candidates as $user)
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" checked class="bot-checkbox">
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500">#{{ $user->id }}</td>
                        <td class="px-4 py-3 text-zinc-900">{{ $user->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-zinc-700">{{ $user->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-zinc-700">{{ $user->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-zinc-500">{{ optional($user->created_at)->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <button type="submit" id="destroy-btn"
                    class="px-4 py-3 bg-rose-600 text-white rounded-lg hover:bg-rose-700 font-medium">
                Удалить выбранных
            </button>
        </div>
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
