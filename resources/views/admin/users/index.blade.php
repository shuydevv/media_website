@extends('admin.layouts.main')

@section('title', 'Пользователи')

@section('content')
@php
    // Дата → сколько дней прошло и подпись ("сегодня", "вчера", "N дн. назад").
    $ago = function ($at) {
        if (! $at) {
            return null;
        }
        $days = (int) \Illuminate\Support\Carbon::parse($at)->startOfDay()->diffInDays(now()->startOfDay());

        return ['days' => $days, 'text' => $days === 0 ? 'сегодня' : ($days === 1 ? 'вчера' : $days.' дн. назад')];
    };
@endphp

<x-ui.page-header title="Пользователи">
    <x-slot:actions>
        <x-ui.button href="{{ route('admin.user.bots.preview') }}" variant="secondary" size="xs">Удалить ботов</x-ui.button>
        <x-ui.button href="{{ route('admin.user.create') }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            Создать
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

<x-ui.tabs class="mb-4">
    <x-ui.tab href="{{ route('admin.user.index', array_filter(['q' => $q])) }}" :active="$scope === 'students'">Ученики на курсах</x-ui.tab>
    <x-ui.tab href="{{ route('admin.user.index', array_filter(['scope' => 'all', 'q' => $q])) }}" :active="$scope === 'all'">Все пользователи</x-ui.tab>
</x-ui.tabs>

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    @if($scope === 'all')
        <input type="hidden" name="scope" value="all">
    @endif
    <x-ui.input name="q" type="search" value="{{ $q ?? '' }}" placeholder="Поиск: имя, email, телефон…" wrap="flex-1 min-w-[200px]" />
    <x-ui.select name="sort" onchange="this.form.submit()" wrap="w-full sm:w-auto" aria-label="Сортировка">
        @foreach($sorts as $key => $sortLabel)
            <option value="{{ $key }}" @selected($sort === $key)>{{ $sortLabel }}</option>
        @endforeach
    </x-ui.select>
    <x-ui.button type="submit" size="xs">Искать</x-ui.button>
    @if(!empty($q))
        <x-ui.button href="{{ route('admin.user.index', array_filter(['scope' => $scope === 'all' ? 'all' : null])) }}" variant="secondary" size="xs">Сброс</x-ui.button>
    @endif
</form>

@if ($users->isEmpty())
    <x-ui.empty>Ничего не найдено</x-ui.empty>
@else
    <x-ui.table>
        <thead>
        <tr>
            <th class="w-[38%]">Ученик</th>
            <th>Активность</th>
            <th>За последнее время</th>
            <th>Обратная связь</th>
            <th></th>
        </tr>
        </thead>

        <tbody>
        @foreach ($users as $user)
            @php
                $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?: '—');
                // Точная отметка (user_activity_days) копится недавно — до неё
                // единственный след это дата последнего визита на дашборд.
                $seen = $ago($user->activity_days_max_last_seen_at ?? $user->fish_last_active_date);
                $feedback = $ago($user->feedback_max_created_at);
            @endphp
            <tr>
                <td data-primary class="break-words">
                    <a href="{{ route('admin.user.show', $user) }}" class="font-medium text-zinc-900 hover:underline">{{ $fullName }}</a>
                    @unless($user->isStudent())
                        <x-ui.badge tone="orange" class="ml-1">{{ \App\Models\User::getRoles()[$user->role] ?? 'Роль '.$user->role }}</x-ui.badge>
                    @endunless
                    <div class="text-xs font-normal text-zinc-500 mt-0.5">
                        @if($user->name && $user->name !== $fullName)
                            <a href="https://t.me/{{ ltrim($user->name, '@') }}" target="_blank" rel="noopener" class="text-apple-blue-700 hover:underline">{{ $user->name }}</a>
                        @else
                            {{ $user->email ?? '—' }}
                        @endif
                    </div>
                    <div class="text-xs font-normal text-zinc-500 mt-0.5">
                        {{ $user->courses->pluck('title')->implode(', ') ?: 'без курса' }}
                    </div>
                </td>

                @if($user->isStudent())
                    <td data-label="Активность">
                        <div>
                            <div class="{{ $seen && $seen['days'] >= 7 ? 'text-apple-red-650' : 'text-zinc-900' }}">
                                {{ $seen ? $seen['text'] : 'не заходил' }}
                            </div>
                            <div class="text-xs text-zinc-500">активных дней за 14: {{ $user->active_days_14 }}</div>
                        </div>
                    </td>
                    <td data-label="За последнее время" class="text-xs text-zinc-600">
                        <div>
                            <div>работ сдано за 30 дней: <span class="text-zinc-900">{{ $user->submitted_30 }}</span></div>
                            <div>уроков открыто за 14 дней: <span class="text-zinc-900">{{ $user->lessons_14 }}</span></div>
                        </div>
                    </td>
                    <td data-label="Обратная связь">
                        <div class="{{ ! $feedback || $feedback['days'] >= 14 ? 'text-apple-red-650' : 'text-zinc-900' }}">
                            {{ $feedback ? $feedback['text'] : 'не было' }}
                        </div>
                    </td>
                @else
                    <td></td>
                    <td></td>
                    <td></td>
                @endif

                <td data-actions>
                    <x-ui.button href="{{ route('admin.user.edit', $user) }}" variant="ghost" size="xs" class="-ml-3.5 md:ml-0">Изменить</x-ui.button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </x-ui.table>
@endif

<p class="mt-3 text-xs text-zinc-500">
    Красным отмечены ученики, которые не заходили неделю и больше или не получали обратной связи две недели и больше.
    Просмотры уроков и активные дни считаются с момента включения учёта.
</p>

<div class="mt-4">
    {{ $users->links('pagination.ui') }}
</div>
@endsection
