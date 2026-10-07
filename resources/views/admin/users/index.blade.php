@extends('admin.layouts.main')

@section('content')
@php
    $tabClass = fn (bool $active) => 'px-3 py-1.5 rounded-lg text-sm '.($active ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200');
    // Дата → сколько дней прошло и подпись ("сегодня", "вчера", "N дн. назад").
    $ago = function ($at) {
        if (! $at) {
            return null;
        }
        $days = (int) \Illuminate\Support\Carbon::parse($at)->startOfDay()->diffInDays(now()->startOfDay());

        return ['days' => $days, 'text' => $days === 0 ? 'сегодня' : ($days === 1 ? 'вчера' : $days.' дн. назад')];
    };
@endphp

@if(session('success'))
    <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
        {{ session('success') }}
    </div>
@endif

<div class="flex items-center justify-between gap-3 flex-wrap mb-5">
    <h1 class="text-2xl font-semibold">Пользователи</h1>

    <div class="flex items-center gap-2">
        <a href="{{ route('admin.user.bots.preview') }}"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-zinc-100 text-zinc-700 rounded-lg hover:bg-zinc-200">
            Удалить ботов
        </a>
        <a href="{{ route('admin.user.create') }}"
           class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-pink-600 text-white rounded-lg hover:bg-pink-700">
            <x-icon name="plus" class="w-4 h-4" />
            Создать
        </a>
    </div>
</div>

<div class="flex items-center gap-2 flex-wrap mb-3">
    <a href="{{ route('admin.user.index', array_filter(['q' => $q])) }}" class="{{ $tabClass($scope === 'students') }}">Ученики на курсах</a>
    <a href="{{ route('admin.user.index', array_filter(['scope' => 'all', 'q' => $q])) }}" class="{{ $tabClass($scope === 'all') }}">Все пользователи</a>
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    @if($scope === 'all')
        <input type="hidden" name="scope" value="all">
    @endif
    <input
        type="text"
        name="q"
        value="{{ $q ?? '' }}"
        placeholder="Поиск: имя, email, телефон…"
        class="flex-1 min-w-[200px] bg-white border border-zinc-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300"
    >
    <select name="sort" class="border border-zinc-300 rounded-lg px-2 py-2 text-sm bg-white" onchange="this.form.submit()">
        @foreach($sorts as $key => $sortLabel)
            <option value="{{ $key }}" @selected($sort === $key)>{{ $sortLabel }}</option>
        @endforeach
    </select>
    <button class="px-3 py-2 text-sm bg-zinc-900 text-white rounded-lg hover:bg-zinc-800">Искать</button>
    @if(!empty($q))
        <a href="{{ route('admin.user.index', array_filter(['scope' => $scope === 'all' ? 'all' : null])) }}"
           class="px-3 py-2 text-sm bg-zinc-100 text-zinc-700 rounded-lg hover:bg-zinc-200">Сброс</a>
    @endif
</form>

{{-- Без горизонтальной прокрутки: фиксированные доли колонок, длинные значения
     переносятся, а на узком экране колонка "За последнее время" скрывается. --}}
<div class="bg-white rounded-2xl shadow-sm ring-1 ring-black/5 overflow-hidden">
    <table class="w-full table-fixed text-sm">
        <thead class="bg-zinc-50 text-left text-zinc-600">
        <tr>
            <th class="px-3 py-3 font-medium w-[38%]">Ученик</th>
            <th class="px-3 py-3 font-medium">Активность</th>
            <th class="px-3 py-3 font-medium hidden md:table-cell">За последнее время</th>
            <th class="px-3 py-3 font-medium">Обратная связь</th>
        </tr>
        </thead>

        <tbody class="divide-y divide-zinc-100">
        @forelse ($users as $user)
            @php
                $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?: '—');
                // Точная отметка (user_activity_days) копится недавно — до неё
                // единственный след это дата последнего визита на дашборд.
                $seen = $ago($user->activity_days_max_last_seen_at ?? $user->fish_last_active_date);
                $feedback = $ago($user->feedback_max_created_at);
            @endphp
            <tr class="hover:bg-zinc-50 align-top">
                <td class="px-3 py-3 break-words">
                    <a href="{{ route('admin.user.show', $user) }}" class="font-medium text-zinc-900 hover:underline">{{ $fullName }}</a>
                    @unless($user->isStudent())
                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700">{{ \App\Models\User::getRoles()[$user->role] ?? 'Роль '.$user->role }}</span>
                    @endunless
                    <div class="text-xs text-zinc-500 mt-0.5">
                        @if($user->name && $user->name !== $fullName)
                            <a href="https://t.me/{{ ltrim($user->name, '@') }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline">{{ $user->name }}</a>
                        @else
                            {{ $user->email ?? '—' }}
                        @endif
                    </div>
                    <div class="text-xs text-zinc-500 mt-0.5">
                        {{ $user->courses->pluck('title')->implode(', ') ?: 'без курса' }}
                    </div>
                </td>

                <td class="px-3 py-3">
                    @if($user->isStudent())
                        <div class="{{ $seen && $seen['days'] >= 7 ? 'text-rose-700' : 'text-zinc-900' }}">
                            {{ $seen ? $seen['text'] : 'не заходил' }}
                        </div>
                        <div class="text-xs text-zinc-500">активных дней за 14: {{ $user->active_days_14 }}</div>
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </td>

                <td class="px-3 py-3 text-xs text-zinc-600 hidden md:table-cell">
                    @if($user->isStudent())
                        <div>работ сдано за 30 дней: <span class="text-zinc-900">{{ $user->submitted_30 }}</span></div>
                        <div>уроков открыто за 14 дней: <span class="text-zinc-900">{{ $user->lessons_14 }}</span></div>
                    @else
                        <span class="text-zinc-400">—</span>
                    @endif
                </td>

                <td class="px-3 py-3">
                    @if($user->isStudent())
                        <div class="{{ ! $feedback || $feedback['days'] >= 14 ? 'text-rose-700' : 'text-zinc-900' }}">
                            {{ $feedback ? $feedback['text'] : 'не было' }}
                        </div>
                    @else
                        <div class="text-zinc-400">—</div>
                    @endif
                    <a href="{{ route('admin.user.edit', $user) }}" class="text-xs text-zinc-500 hover:text-zinc-900 hover:underline">изменить</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">Ничего не найдено</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<p class="mt-3 text-xs text-zinc-500">
    Красным отмечены ученики, которые не заходили неделю и больше или не получали обратной связи две недели и больше.
    Просмотры уроков и активные дни считаются с момента включения учёта.
</p>

<div class="mt-4">
    {{ $users->links() }}
</div>
@endsection
