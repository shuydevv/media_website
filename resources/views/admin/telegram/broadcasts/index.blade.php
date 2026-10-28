@extends('admin.layouts.main')

@section('title', 'Рассылки')

@section('content')
@php
    $statusTone = ['draft' => 'gray', 'scheduled' => 'orange', 'sending' => 'blue', 'done' => 'green', 'stopped' => 'red'];
@endphp

<x-ui.page-header title="Telegram-бот">
    Рассылки пишутся в теме «📣 Рассылка» админ-группы: бот ответит карточкой с числом получателей и отправит только после подтверждения.
    @if (! $topicUrl) Темы ещё нет — напишите в группе <code>/broadcast</code>. @endif
    @if ($topicUrl)
        <x-slot:actions>
            <x-ui.button href="{{ $topicUrl }}" size="xs" target="_blank" rel="noopener">Открыть тему в Telegram</x-ui.button>
        </x-slot:actions>
    @endif
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'broadcasts'])

@if ($broadcasts->isEmpty())
    <x-ui.empty>Рассылок ещё не было</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr><th>Сообщение</th><th>Статус</th><th>Кому</th><th>Доставлено</th><th>Ответили</th><th>Когда</th></tr>
        </thead>
        <tbody>
            @foreach ($broadcasts as $b)
                <tr>
                    <td data-primary>
                        <a href="{{ route('admin.telegram.broadcasts.show', $b) }}" class="text-blue-600 hover:underline">#{{ $b->id }} {{ $b->preview(70) }}</a>
                        @if ($b->author_name) <div class="text-xs text-zinc-400">{{ $b->author_name }}</div> @endif
                    </td>
                    <td data-label="Статус"><x-ui.badge :tone="$statusTone[$b->status] ?? 'gray'">{{ \App\Models\TelegramBroadcast::STATUS_LABELS[$b->status] }}</x-ui.badge></td>
                    <td data-label="Кому" class="text-sm">{{ \App\Models\TelegramBroadcast::AUDIENCE_LABELS[$b->audience] }}</td>
                    <td data-label="Доставлено" class="tabular-nums text-sm">
                        @if ($b->isEditable()) <span class="text-zinc-400">—</span> @else {{ $b->sent_count }} из {{ $b->recipients_count }} @endif
                    </td>
                    <td data-label="Ответили" class="tabular-nums text-sm">{{ $b->isEditable() ? '—' : $b->replied_count }}</td>
                    <td data-label="Когда" class="text-sm md:whitespace-nowrap">
                        @if ($b->started_at) {{ $b->started_at->format('d.m H:i') }}
                        @elseif ($b->scheduled_at) ⏰ {{ $b->scheduled_at->format('d.m H:i') }}
                        @else <span class="text-zinc-400">создан {{ $b->created_at->format('d.m H:i') }}</span> @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    <div class="mt-4">{{ $broadcasts->links() }}</div>
@endif
@endsection
