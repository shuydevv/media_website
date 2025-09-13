@extends('admin.layouts.main')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Пользователь #{{ $user->id }}</h1>

    <div class="flex gap-2">
        <a href="{{ route('admin.user.edit', $user->id) }}"
           class="px-4 py-2 bg-zinc-900 text-white rounded-lg hover:bg-zinc-800">Изменить</a>

        @if($user->deleted_at)
            <form method="POST" action="{{ route('admin.user.restore', $user->id) }}">
                @csrf
                <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    Восстановить
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.user.delete', $user->id) }}"
                  onsubmit="return confirm('Точно пометить пользователя как удалённого?');">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700">
                    Удалить
                </button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3">
        {{ session('success') }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Карточка статусов -->
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-black/5 p-5 space-y-4">
        <h2 class="text-lg font-medium">Статусы</h2>
        <div class="flex flex-wrap gap-2">
            @php
                $isAdmin = (string)$user->role === '1' || $user->role === 1;
            @endphp

            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $isAdmin ? 'bg-amber-50 text-amber-700' : 'bg-zinc-100 text-zinc-700' }}">
                Роль: {{ $isAdmin ? 'Админ' : 'Пользователь' }}
            </span>

            @if($user->deleted_at)
                <span class="inline-flex px-2 py-1 rounded-full text-xs bg-rose-50 text-rose-700">
                    Удалён {{ $user->deleted_at?->format('d.m.Y H:i') }}
                </span>
            @else
                <span class="inline-flex px-2 py-1 rounded-full text-xs bg-emerald-50 text-emerald-700">
                    Активен
                </span>
            @endif

            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $user->email_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-700' }}">
                Email: {{ $user->email_verified_at ? 'верифицирован' : 'не верифицирован' }}
            </span>

            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $user->phone_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-700' }}">
                Телефон: {{ $user->phone_verified_at ? 'верифицирован' : 'не верифицирован' }}
            </span>
        </div>
    </div>

    <!-- Основные данные -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm ring-1 ring-black/5 p-5">
        <h2 class="text-lg font-medium mb-4">Профиль</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
                <div class="text-zinc-500">Имя (display)</div>
                <div class="text-zinc-900">{{ $user->name ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Email</div>
                <div class="text-zinc-900">{{ $user->email ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Телефон</div>
                <div class="text-zinc-900">{{ $user->phone ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Имя/Фамилия</div>
                <div class="text-zinc-900">
                    {{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '—' }}
                </div>
            </div>

            <div>
                <div class="text-zinc-500">Часовой пояс</div>
                <div class="text-zinc-900">{{ $user->timezone ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Язык</div>
                <div class="text-zinc-900">{{ strtoupper($user->locale ?? 'ru') }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Создан</div>
                <div class="text-zinc-900">{{ $user->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Обновлён</div>
                <div class="text-zinc-900">{{ $user->updated_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Email подтверждён</div>
                <div class="text-zinc-900">{{ $user->email_verified_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Телефон подтверждён</div>
                <div class="text-zinc-900">{{ $user->phone_verified_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Профиль заполнен</div>
                <div class="text-zinc-900">{{ $user->profile_completed_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Роль (raw)</div>
                <div class="text-zinc-900">{{ $user->role ?? '—' }}</div>
            </div>

            <div class="sm:col-span-2">
                <div class="text-zinc-500">Пароль (hash, сокращённо)</div>
                <div class="text-zinc-900 font-mono break-all">
                    @if($user->password)
                        {{ substr($user->password, 0, 15) }}&hellip;
                    @else
                        —
                    @endif
                </div>
                <div class="text-xs text-zinc-500 mt-1">Полный хэш не показываем по соображениям безопасности.</div>
            </div>

            <div class="sm:col-span-2">
                <div class="text-zinc-500">remember_token</div>
                <div class="text-zinc-900 font-mono break-all">{{ $user->remember_token ?: '—' }}</div>
            </div>

            @if($user->deleted_at)
            <div class="sm:col-span-2">
                <div class="text-zinc-500">deleted_at</div>
                <div class="text-zinc-900">{{ $user->deleted_at?->format('d.m.Y H:i') }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="mt-8">
    <a href="{{ route('admin.user.index') }}" class="text-zinc-600 hover:text-zinc-900 underline">← Ко всем пользователям</a>
</div>
@endsection
