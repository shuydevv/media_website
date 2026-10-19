@extends('admin.layouts.main')

@section('title', 'Контакты бота')

@section('content')
@php
    $stageTone = ['not_opened' => 'gray', 'opened' => 'orange', 'watched' => 'blue', 'rated' => 'purple', 'wrote' => 'green', 'blocked' => 'red'];
@endphp

<x-ui.page-header title="Telegram-бот">
    Все, кто запускал бота, — и те, у кого в группе ещё нет темы (ничего не писали). Нажмите на человека, чтобы увидеть всю его историю.
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'contacts'])

<div class="flex flex-wrap gap-2 mb-3">
    @foreach (['' => 'Все'] + \App\Models\TelegramContact::STAGES as $key => $label)
        <a href="{{ route('admin.telegram.contacts.index', array_filter(['stage' => $key, 'q' => $q])) }}"
           class="inline-flex items-center gap-1.5 px-3 min-h-9 rounded-lg text-sm border transition
                  {{ $stage === $key ? 'bg-zinc-800 border-zinc-800 text-white' : 'bg-white border-zinc-200 text-zinc-700 hover:border-zinc-400' }}">
            {{ $label }} <span class="{{ $stage === $key ? 'text-zinc-300' : 'text-zinc-400' }} tabular-nums">{{ $counts[$key] }}</span>
        </a>
    @endforeach
</div>

<form method="get" class="flex flex-wrap items-end gap-2 mb-4">
    @if ($stage !== '') <input type="hidden" name="stage" value="{{ $stage }}"> @endif
    <x-ui.input name="q" size="sm" placeholder="Имя, @логин или метка" value="{{ $q }}" wrap="w-full sm:w-72" />
    <x-ui.button type="submit" size="xs" variant="secondary">Найти</x-ui.button>
    @if ($q !== '')
        <x-ui.button href="{{ route('admin.telegram.contacts.index', array_filter(['stage' => $stage])) }}" size="xs" variant="ghost">Сбросить</x-ui.button>
    @endif
</form>

@if ($contacts->isEmpty())
    <x-ui.empty>Никого нет</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr><th>Кто</th><th>Этап</th><th>Уроки</th><th>Дальше</th><th>Старт</th></tr>
        </thead>
        <tbody>
            @foreach ($contacts as $c)
                @php $next = $c->nextScheduled(); @endphp
                <tr>
                    <td data-primary>
                        <a href="{{ route('admin.telegram.contacts.show', $c) }}" class="text-blue-600 hover:underline">{{ $c->displayName() }}</a>
                        @if ($c->username && ! str_starts_with($c->displayName(), '@'))
                            <span class="text-zinc-400 text-sm">{{ '@'.$c->username }}</span>
                        @endif
                        <div class="text-xs text-zinc-400 font-mono">{{ $c->source ?? 'без метки' }}</div>
                    </td>
                    <td data-label="Этап">
                        <span>
                            <x-ui.badge :tone="$stageTone[$c->stageKey()]">{{ \App\Models\TelegramContact::STAGES[$c->stageKey()] }}</x-ui.badge>
                            @if ($c->awaitingReply()) <x-ui.badge tone="orange">ждёт ответа</x-ui.badge> @endif
                        </span>
                    </td>
                    <td data-label="Уроки">@include('admin.telegram.contacts._lessons', ['c' => $c])</td>
                    <td data-label="Дальше" class="text-xs md:text-sm">
                        @if ($next)
                            {{ $next[0] }} <span class="text-zinc-400 md:whitespace-nowrap">— {{ $next[1]->format('d.m H:i') }}</span>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </td>
                    <td data-label="Старт" class="md:whitespace-nowrap text-sm">{{ $c->started_at->format('d.m H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    <div class="mt-4">{{ $contacts->links() }}</div>
@endif
@endsection
