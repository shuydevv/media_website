@extends('admin.layouts.main')

@section('title', $contact->displayName())

@section('content')
@php
    $stageTone = ['not_opened' => 'gray', 'opened' => 'orange', 'watched' => 'blue', 'rated' => 'purple', 'wrote' => 'green', 'blocked' => 'red'];
    $kindTone = ['action' => 'text-zinc-900', 'user' => 'text-green-700', 'admin' => 'text-blue-700', 'bot' => 'text-zinc-600', 'planned' => 'text-orange-700'];
@endphp

<x-ui.page-header :title="$contact->displayName()" :back="route('admin.telegram.contacts.index')" back-label="Все контакты">
    @if ($contact->username) {{ '@'.$contact->username }} · @endif
    метка {{ $contact->source ?? '—' }} · запустил бота {{ $contact->started_at->format('d.m.Y H:i') }}
    <x-slot:actions>
        @if ($topicUrl)
            <x-ui.button href="{{ $topicUrl }}" size="xs" target="_blank" rel="noopener">Открыть тему в Telegram</x-ui.button>
        @elseif (! $contact->blocked_at)
            {{-- Тема заводится только по первому сообщению человека; здесь — чтобы написать первым самому. --}}
            <form method="post" action="{{ route('admin.telegram.contacts.dialog', $contact) }}"
                  data-confirm="Создать тему для {{ $contact->displayName() }} в админ-группе? После этого напишите ему в теме — сообщение уйдёт от бота, а автосообщения воронки ему больше не придут.">
                @csrf
                <x-ui.button type="submit" size="xs">Начать диалог</x-ui.button>
            </form>
        @endif
    </x-slot:actions>
</x-ui.page-header>

<x-ui.card class="mb-6">
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <x-ui.badge :tone="$stageTone[$contact->stageKey()]">{{ \App\Models\TelegramContact::STAGES[$contact->stageKey()] }}</x-ui.badge>
        @if ($contact->awaitingReply()) <x-ui.badge tone="orange">ждёт ответа</x-ui.badge> @endif
        @if ($contact->topic_id) <x-ui.badge tone="green">тема есть</x-ui.badge> @else <x-ui.badge>темы нет</x-ui.badge> @endif
    </div>
    @include('admin.telegram.contacts._lessons', ['c' => $contact])
    @if ($next = $contact->nextScheduled())
        <div class="mt-3 text-sm text-orange-700">⏰ Дальше: {{ $next[0] }} — {{ $next[1]->format('d.m H:i') }}</div>
    @endif
</x-ui.card>

<h2 class="sans-medium text-lg text-zinc-900 mb-3">История</h2>
<ol class="relative border-l border-zinc-200 ml-2 space-y-4">
    @foreach ($timeline as $e)
        <li class="ml-5">
            <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full bg-white border border-zinc-200 text-xs">{{ $e['icon'] }}</span>
            <div class="text-xs text-zinc-400 tabular-nums">{{ $e['at']->format('d.m.Y H:i') }}</div>
            <div class="text-sm {{ $kindTone[$e['kind']] ?? 'text-zinc-900' }} {{ $e['kind'] === 'planned' ? 'italic' : '' }}">{{ $e['text'] }}</div>
            @if ($e['detail'])
                <div class="text-sm text-zinc-500 whitespace-pre-line mt-0.5">{{ $e['detail'] }}</div>
            @endif
        </li>
    @endforeach
</ol>
@endsection
