@extends('admin.layouts.main')

@section('content')
@if(session('success'))
    <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
        {{ session('success') }}
    </div>
@endif

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Пользователи</h1>

    <div class="flex items-center gap-2">
        <a href="{{ route('admin.user.bots.preview') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-zinc-100 text-zinc-700 rounded-lg hover:bg-zinc-200">
            Удалить ботов
        </a>
        <a href="{{ route('admin.user.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700">
            <x-icon name="plus" class="w-4 h-4" />
            Создать пользователя
        </a>
    </div>
</div>

<form method="GET" class="mb-4">
    <div class="flex gap-2">
        <input
            type="text"
            name="q"
            value="{{ $q ?? '' }}"
            placeholder="Поиск: имя, email, телефон…"
            class="w-full max-w-md bg-white border border-zinc-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300"
        >
        <button class="px-4 py-2 bg-zinc-900 text-white rounded-lg hover:bg-zinc-800">Искать</button>
        @if(!empty($q))
            <a href="{{ route('admin.user.index') }}"
               class="px-4 py-2 bg-zinc-100 text-zinc-700 rounded-lg hover:bg-zinc-200">Сброс</a>
        @endif
    </div>
</form>

<div class="overflow-x-auto bg-white rounded-2xl shadow-sm ring-1 ring-black/5">
    <table class="min-w-full text-sm">
        <thead class="bg-zinc-50 text-left text-zinc-600">
        <tr>
            <th class="px-4 py-3 font-medium">ID</th>
            <th class="px-4 py-3 font-medium">Пользователь</th>
            <th class="px-4 py-3 font-medium">Контакты</th>
            <th class="px-4 py-3 font-medium">Роль</th>
            <th class="px-4 py-3 font-medium">Верификации</th>
            <th class="px-4 py-3 font-medium">Временная зона</th>
            <th class="px-4 py-3 font-medium">Статус</th>
            <th class="px-4 py-3 font-medium w-36">Действия</th>
        </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
        @php
            // Подписи ролей (подстрой под свою схему)
            $roleLabels = [
                1 => 'Админ',
                0 => 'Пользователь',
            ];
        @endphp

        @forelse ($users as $user)
            <tr class="hover:bg-zinc-50">
                <td class="px-4 py-3 font-mono text-xs text-zinc-500">#{{ $user->id }}</td>

                <td class="px-4 py-3">
                    <div class="font-medium text-zinc-900">
                        {{ $user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '—' }}
                    </div>
                    <div class="text-xs text-zinc-500">
                        Создан: {{ optional($user->created_at)->format('d.m.Y H:i') ?? '—' }}
                    </div>
                </td>

                <td class="px-4 py-3">
                    <div class="text-zinc-900">{{ $user->email ?? '—' }}</div>
                    <div class="text-xs text-zinc-500">{{ $user->phone ?? '—' }}</div>
                </td>

                <td class="px-4 py-3">
                    @php $roleText = $roleLabels[$user->role] ?? $user->role; @endphp
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                        {{ (string)$user->role === '1'
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-zinc-100 text-zinc-700' }}">
                        {{ $roleText }}
                    </span>
                </td>

                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1 text-xs
                            {{ $user->email_verified_at ? 'text-emerald-700' : 'text-zinc-500' }}">
                            <x-icon name="check-verified-01" class="w-4 h-4" />
                            Email {{ $user->email_verified_at ? 'OK' : '—' }}
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs
                            {{ $user->phone_verified_at ? 'text-emerald-700' : 'text-zinc-500' }}">
                            <x-icon name="phone-01" class="w-4 h-4" />
                            Телефон {{ $user->phone_verified_at ? 'OK' : '—' }}
                        </span>
                    </div>
                </td>

                <td class="px-4 py-3 text-zinc-700">
                    <div>{{ $user->timezone ?: '—' }}</div>
                    <div class="text-xs text-zinc-500">{{ strtoupper($user->locale ?? 'ru') }}</div>
                </td>

                <td class="px-4 py-3">
                    @if($user->deleted_at)
                        <span class="inline-flex px-2 py-1 rounded-full bg-rose-50 text-rose-700 text-xs">Удалён</span>
                    @else
                        <span class="inline-flex px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs">Активен</span>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <div class="flex gap-2">
                        <a href="{{ route('admin.user.show', $user) }}"
                           class="px-2 py-1 rounded-md text-zinc-700 hover:bg-zinc-100">Открыть</a>
                        <a href="{{ route('admin.user.edit', $user) }}"
                           class="px-2 py-1 rounded-md text-zinc-700 hover:bg-zinc-100">Изменить</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-8 text-center text-zinc-500">Ничего не найдено</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $users->links() }}
</div>
@endsection
