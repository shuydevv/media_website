@extends('admin.layouts.main')

@section('title', 'Рассылка #'.$broadcast->id)

@section('content')
@php
    $statusTone = ['draft' => 'gray', 'scheduled' => 'orange', 'sending' => 'blue', 'done' => 'green', 'stopped' => 'red', 'cancelled' => 'gray'];
    $botIcons = ['lesson' => '🎯', 'diag' => '🧪'];
@endphp

<x-ui.page-header :title="'Рассылка #'.$broadcast->id" :back="route('admin.telegram.broadcasts.index')" back-label="Все рассылки">
    {{ \App\Models\TelegramBroadcast::AUDIENCE_LABELS[$broadcast->audience] }}
    @if ($broadcast->author_name) · автор {{ $broadcast->author_name }} @endif
    · создана {{ $broadcast->created_at->format('d.m.Y H:i') }}
    @if ($topicUrl)
        <x-slot:actions>
            <x-ui.button href="{{ $topicUrl }}" size="xs" target="_blank" rel="noopener">Тема «📣 Рассылка»</x-ui.button>
        </x-slot:actions>
    @endif
</x-ui.page-header>

<x-ui.card class="mb-6">
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <x-ui.badge :tone="$statusTone[$broadcast->status] ?? 'gray'">{{ \App\Models\TelegramBroadcast::STATUS_LABELS[$broadcast->status] }}</x-ui.badge>
        @if ($broadcast->scheduled_at && $broadcast->isEditable()) <x-ui.badge tone="orange">⏰ {{ $broadcast->scheduled_at->format('d.m H:i') }}</x-ui.badge> @endif
        @if ($broadcast->media_type) <x-ui.badge>+ {{ $broadcast->mediaLabel() }}</x-ui.badge> @endif
    </div>
    @if ($broadcast->text)
        <div class="text-sm text-zinc-900 whitespace-pre-line">{{ $broadcast->text }}</div>
    @else
        <div class="text-sm text-zinc-400">Без текста</div>
    @endif
    @if ($stats)
        <div class="mt-3 text-sm text-zinc-600">
            @if ($broadcast->started_at) {{ $broadcast->started_at->format('d.m H:i') }}@if ($broadcast->finished_at)–{{ $broadcast->finished_at->format('H:i') }}@endif · @endif
            доставлено {{ $stats['sent'] }} из {{ $stats['total'] }}
            @if ($stats['blocked']) · заблокировали бота {{ $stats['blocked'] }} @endif
            @if ($stats['failed']) · ошибок {{ $stats['failed'] }} @endif
            @if ($stats['pending']) · не отправлено {{ $stats['pending'] }} @endif
            · ответили {{ $stats['replied'] }}
            @if ($broadcast->stop_reason) <div class="text-red-700 mt-1">Остановлена: {{ $broadcast->stop_reason }}</div> @endif
        </div>
    @else
        <div class="mt-3 text-sm text-zinc-500">Отправка и план — кнопками карточки в теме «📣 Рассылка». Здесь — кто получит и кого исключить.</div>
    @endif
</x-ui.card>

<x-ui.tabs class="mb-3">
    @foreach ($tabs as $key => $label)
        <x-ui.tab href="{{ route('admin.telegram.broadcasts.show', array_filter(['broadcast' => $broadcast, 'tab' => $key, 'q' => $q])) }}" :active="$tab === $key">
            {{ $label }} <span class="text-zinc-400 tabular-nums">{{ $counts[$key] }}</span>
        </x-ui.tab>
    @endforeach
</x-ui.tabs>

<form method="get" class="flex flex-wrap items-end gap-2 mb-4">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <x-ui.input name="q" size="sm" placeholder="Имя, @логин или id" value="{{ $q }}" wrap="w-full sm:w-72" />
    <x-ui.button type="submit" size="xs" variant="outline">Найти</x-ui.button>
    @if ($q !== '')
        <x-ui.button href="{{ route('admin.telegram.broadcasts.show', ['broadcast' => $broadcast, 'tab' => $tab]) }}" size="xs" variant="outline">Сбросить</x-ui.button>
    @endif
</form>

@if ($people->isEmpty())
    <x-ui.empty>Никого нет</x-ui.empty>
@else
    <form method="post" action="{{ route('admin.telegram.broadcasts.people', $broadcast) }}" id="people-form">
        @csrf
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <label class="ui-check text-sm"><input type="checkbox" class="checkbox-custom" data-check-all> <span>Отметить всех на странице</span></label>
            @if ($editable && $tab === 'in')
                <x-ui.button type="submit" name="action" value="here" size="xs" variant="outline">Исключить из этой рассылки</x-ui.button>
            @endif
            @if ($tab !== 'out')
                <x-ui.button type="submit" name="action" value="all" size="xs" variant="outline">Исключить из всех рассылок</x-ui.button>
            @endif
            @if ($editable && $tab === 'out')
                <x-ui.button type="submit" name="action" value="return" size="xs" variant="outline">Вернуть</x-ui.button>
            @endif
        </div>

        <x-ui.table>
            <thead>
                <tr><th></th><th>Кто</th><th>{{ $editable ? ($tab === 'out' ? 'Почему' : 'Бот') : 'Статус' }}</th>@unless ($editable)<th>Ответил</th>@endunless</tr>
            </thead>
            <tbody>
                @foreach ($people as $row)
                    @php
                        $c = $row['contact'];
                        // Заблокировавшего бота вернуть нельзя — пока сам не разблокирует.
                        $checkable = ! (($row['reason'] ?? null) === \App\Service\Telegram\Broadcast\BroadcastAudience::REASON_BLOCKED);
                    @endphp
                    <tr>
                        <td data-actions>
                            @if ($checkable)
                                <input type="checkbox" name="ids[]" value="{{ $c->telegram_user_id }}" class="checkbox-custom" data-person aria-label="Отметить {{ $c->displayName() }}">
                            @endif
                        </td>
                        <td data-primary>
                            {{ $botIcons[$c->bot] ?? '' }}
                            <a href="{{ route('admin.telegram.contacts.show', $c) }}" class="text-blue-600 hover:underline">{{ $c->displayName() }}</a>
                            @if ($c->username && ! str_starts_with($c->displayName(), '@'))
                                <span class="text-zinc-400 text-sm">{{ '@'.$c->username }}</span>
                            @endif
                            @if ($c->broadcast_opt_out_at && $tab !== 'out') <x-ui.badge tone="orange">исключён из всех</x-ui.badge> @endif
                        </td>
                        <td data-label="{{ $editable ? ($tab === 'out' ? 'Почему' : 'Бот') : 'Статус' }}" class="text-sm">
                            @if ($row['note'])
                                <x-ui.badge :tone="$row['tone']">{{ $row['note'] }}</x-ui.badge>
                                @if ($row['recipient']?->error) <div class="text-xs text-zinc-400 mt-0.5">{{ $row['recipient']->error }}</div> @endif
                            @else
                                {{ $c->bot === 'diag' ? 'диагностика' : 'пробный урок' }}
                            @endif
                        </td>
                        @unless ($editable)
                            <td data-label="Ответил" class="text-sm md:whitespace-nowrap">{{ $row['recipient']->replied_at?->format('d.m H:i') ?? '—' }}</td>
                        @endunless
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </form>

    <div class="mt-4">{{ $people->links() }}</div>
@endif

<script>
    document.querySelector('[data-check-all]')?.addEventListener('change', function (e) {
        document.querySelectorAll('[data-person]').forEach(function (box) { box.checked = e.target.checked; });
    });
</script>
@endsection
