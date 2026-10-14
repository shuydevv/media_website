@extends('admin.layouts.main')

@section('title', 'Telegram-бот')

@section('content')
<x-ui.page-header title="Telegram-бот">
    Воронка пробного урока в &#64;poltavskiy_ege_bot. Диалоги — в темах админ-группы в Telegram, здесь — цифры по шагам.
</x-ui.page-header>

<x-ui.tabs class="mb-4">
    <x-ui.tab href="{{ route('admin.telegram.index') }}" :active="true">Аналитика</x-ui.tab>
    <x-ui.tab href="{{ route('admin.telegram.texts.edit') }}">Тексты сообщений</x-ui.tab>
</x-ui.tabs>

@if (in_array(false, $configured, true))
    <x-ui.alert tone="orange" class="mb-4">
        Бот настроен не до конца — в .env на сервере не хватает:
        {{ collect(['token' => 'TELEGRAM_FUNNEL_BOT_TOKEN', 'admin_chat' => 'TELEGRAM_FUNNEL_ADMIN_CHAT_ID', 'secret' => 'TELEGRAM_FUNNEL_WEBHOOK_SECRET'])->filter(fn ($v, $k) => ! $configured[$k])->implode(', ') }}.
    </x-ui.alert>
@endif

<form method="get" class="flex flex-wrap items-end gap-2 mb-4">
    <x-ui.input type="date" name="from" label="С" size="sm" value="{{ $from }}" />
    <x-ui.input type="date" name="to" label="По" size="sm" value="{{ $to }}" />
    <x-ui.button type="submit" size="xs" variant="secondary">Показать</x-ui.button>
    @if ($from || $to)
        <x-ui.button href="{{ route('admin.telegram.index') }}" size="xs" variant="ghost">Сбросить</x-ui.button>
    @endif
</form>

@php
    $pctTotal = fn ($n) => $total->starts > 0 ? round($n / $total->starts * 100) . '%' : '—';
@endphp
<x-ui.card class="mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <x-ui.stat label="Запустили бота">{{ (int) $total->starts }}</x-ui.stat>
        <x-ui.stat label="Открыли урок">
            {{ $pctTotal($total->opened) }}
            <x-slot:sub>{{ (int) $total->opened }} чел.</x-slot:sub>
        </x-ui.stat>
        <x-ui.stat label="Оценили 👍">
            {{ $pctTotal($total->liked) }}
            <x-slot:sub>{{ (int) $total->liked }} чел., 👎 {{ (int) $total->disliked }}</x-slot:sub>
        </x-ui.stat>
        <x-ui.stat label="Написали в диалог">
            {{ $pctTotal($total->wrote) }}
            <x-slot:sub>{{ (int) $total->wrote }} чел., ответили {{ (int) $total->answered }}</x-slot:sub>
        </x-ui.stat>
    </div>
</x-ui.card>

<h2 class="sans-medium text-lg text-zinc-900 mb-2">По меткам рекламы</h2>
@if ($bySource->isEmpty())
    <x-ui.empty class="mb-6">Пока никто не запускал бота</x-ui.empty>
@else
    <x-ui.table class="mb-6">
        <thead>
            <tr>
                <th>Метка</th><th>Старты</th><th>Открыли урок</th><th>Открыли бонус</th><th>👍</th><th>👎</th><th>Написали</th><th>Заблокировали</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bySource as $row)
                @include('admin.telegram._funnel-row', [
                    'row' => $row,
                    'label' => $row->source ? '<span class="font-mono">'.e($row->source).'</span>' : '<span class="text-zinc-400">без метки</span>',
                ])
            @endforeach
        </tbody>
    </x-ui.table>

    <h2 class="sans-medium text-lg text-zinc-900 mb-2">По урокам</h2>
    <x-ui.table class="mb-6">
        <thead>
            <tr>
                <th>Основной урок</th><th>Старты</th><th>Открыли урок</th><th>Открыли бонус</th><th>👍</th><th>👎</th><th>Написали</th><th>Заблокировали</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($byLesson as $row)
                @include('admin.telegram._funnel-row', ['row' => $row, 'label' => e($row->lesson_title)])
            @endforeach
        </tbody>
    </x-ui.table>

    <h2 class="sans-medium text-lg text-zinc-900 mb-2">Последние контакты</h2>
    <x-ui.table>
        <thead>
            <tr><th>Кто</th><th>Метка</th><th>Старт</th><th>Урок</th><th>Оценка</th><th>Диалог</th></tr>
        </thead>
        <tbody>
            @foreach ($recent as $c)
                <tr>
                    <td data-primary>
                        {{ $c->statusEmoji() }} {{ $c->displayName() }}
                        @if ($c->username && ! str_starts_with($c->displayName(), '@'))
                            <span class="text-zinc-400">{{ '@'.$c->username }}</span>
                        @endif
                    </td>
                    <td data-label="Метка" class="font-mono text-xs">{{ $c->source ?? '—' }}</td>
                    <td data-label="Старт" class="md:whitespace-nowrap">{{ $c->started_at->format('d.m H:i') }}</td>
                    <td data-label="Урок">
                        <span>
                            @if ($c->opened_lesson_at) <x-ui.badge tone="green">открыл</x-ui.badge> @else <x-ui.badge>не открыл</x-ui.badge> @endif
                            @if ($c->opened_bonus_at) <x-ui.badge tone="blue">+ бонус</x-ui.badge> @endif
                        </span>
                    </td>
                    <td data-label="Оценка">{{ ['up' => '👍', 'down' => '👎'][$c->rating] ?? '—' }}</td>
                    <td data-label="Диалог">
                        <span>
                            @if ($c->blocked_at)
                                <x-ui.badge tone="red">заблокировал</x-ui.badge>
                            @elseif ($c->awaitingReply())
                                <x-ui.badge tone="orange">ждёт ответа</x-ui.badge>
                            @elseif ($c->user_messages_count > 0)
                                <x-ui.badge tone="green">{{ $c->user_messages_count }} сообщ.</x-ui.badge>
                            @else
                                —
                            @endif
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif
@endsection
