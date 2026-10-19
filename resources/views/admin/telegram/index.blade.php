@extends('admin.layouts.main')

@section('title', 'Telegram-бот')

@section('content')
<x-ui.page-header title="Telegram-бот">
    Воронка пробного урока в &#64;poltavskiy_ege_bot. Диалоги — в темах админ-группы в Telegram, здесь — цифры по шагам.
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'analytics'])

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
        <x-ui.stat label="Досмотрели / оценили">
            {{ $pctTotal($total->watched) }} / {{ $pctTotal($total->rated) }}
            <x-slot:sub>оценок: 🔥 {{ (int) $total->great }} · 👍 {{ (int) $total->good }} · 👎 {{ (int) $total->bad }}, на сайте — {{ (int) $total->rated_on_site }}</x-slot:sub>
        </x-ui.stat>
        <x-ui.stat label="Написали в диалог">
            {{ $pctTotal($total->wrote) }}
            <x-slot:sub>{{ (int) $total->wrote }} чел., ответили {{ (int) $total->answered }}</x-slot:sub>
        </x-ui.stat>
    </div>
</x-ui.card>

<h2 class="sans-medium text-lg text-zinc-900 mb-2">Заявки с сайта</h2>
@if ($leads->isEmpty())
    <x-ui.empty class="mb-6">Заявок из формы «Записаться» пока нет</x-ui.empty>
@else
    <x-ui.table class="mb-6">
        <thead>
            <tr><th>Имя</th><th>Контакт</th><th>Кнопка</th><th>Когда</th><th>В группе</th></tr>
        </thead>
        <tbody>
            @foreach ($leads as $lead)
                <tr>
                    <td data-primary>{{ $lead->name }}</td>
                    <td data-label="Контакт"><a href="{{ $lead->telegramUrl() }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $lead->contactLabel() }}</a></td>
                    <td data-label="Кнопка">{{ $lead->cta_label ?? '—' }}</td>
                    <td data-label="Когда" class="md:whitespace-nowrap">{{ $lead->created_at->format('d.m H:i') }}</td>
                    <td data-label="В группе">
                        <span>@if ($lead->sent_at) <x-ui.badge tone="green">доставлена</x-ui.badge> @else <x-ui.badge tone="orange">в очереди</x-ui.badge> @endif</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<h2 class="sans-medium text-lg text-zinc-900 mb-2">По меткам рекламы</h2>
@if ($bySource->isEmpty())
    <x-ui.empty class="mb-6">Пока никто не запускал бота</x-ui.empty>
@else
    <x-ui.table class="mb-6">
        <thead>
            <tr>
                <th>Метка</th><th>Старты</th><th>Открыли урок</th><th>Открыли бонус</th><th>Досмотрели</th><th>Оценили</th><th>🔥 / 👍 / 👎</th><th>Написали</th><th>Заблокировали</th>
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
                <th>Основной урок</th><th>Старты</th><th>Открыли урок</th><th>Открыли бонус</th><th>Досмотрели</th><th>Оценили</th><th>🔥 / 👍 / 👎</th><th>Написали</th><th>Заблокировали</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($byLesson as $row)
                @include('admin.telegram._funnel-row', ['row' => $row, 'label' => e($row->lesson_title)])
            @endforeach
        </tbody>
    </x-ui.table>

    <div class="flex items-baseline justify-between gap-2 mb-2">
        <h2 class="sans-medium text-lg text-zinc-900">Последние контакты</h2>
        <a href="{{ route('admin.telegram.contacts.index') }}" class="text-sm text-blue-600 hover:underline">Все контакты и этапы →</a>
    </div>
    <x-ui.table>
        <thead>
            <tr><th>Кто</th><th>Метка</th><th>Старт</th><th>Урок</th><th>Оценка</th><th>Диалог</th></tr>
        </thead>
        <tbody>
            @foreach ($recent as $c)
                <tr>
                    <td data-primary>
{{ $c->statusEmoji() }} <a href="{{ route('admin.telegram.contacts.show', $c) }}" class="text-blue-600 hover:underline">{{ $c->displayName() }}</a>
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
                    <td data-label="Оценка">{{ $c->rating || $c->bonus_rating ? collect([$c->rating, $c->bonus_rating])->filter()->map(fn ($r) => \App\Service\Telegram\FunnelTexts::ratingLabel($r))->implode(' · ') : '—' }}</td>
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
