@extends('admin.layouts.main')

@section('title', 'Диагностика — Telegram-бот')

@section('content')
@php
    $pct = fn ($n, $of) => $of > 0 ? round($n / $of * 100).'%' : '—';
    $blocks = config('diagnostic.blocks');
    $levelNames = [0 => 'не справился с лёгким', 1 => 'лёгкий', 2 => 'средний', 3 => 'сложный'];
@endphp

<x-ui.page-header title="Telegram-бот">
    Бот диагностики по обществознанию: человек проходит тест в Telegram, дальше — «Хочешь улучшить свои знания?» и живой диалог.
    Период — по дате запуска бота. Числа прогноза пока заглушки (<code>config/diagnostic.php</code>).
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'diagnostic'])

@if (in_array(false, $configured, true))
    <x-ui.alert tone="orange" class="mb-4">
        Бот диагностики настроен не до конца — в .env на сервере не хватает:
        {{ collect($configured)->filter(fn ($ok) => ! $ok)->keys()->implode(', ') }}.
        После — <code>php artisan telegram:webhook diag</code> и добавить бота админом в админ-группу (право управлять темами).
    </x-ui.alert>
@endif

<form method="get" class="flex flex-wrap items-end gap-2 mb-4">
    <x-ui.input type="date" name="from" label="С" size="sm" value="{{ $from }}" />
    <x-ui.input type="date" name="to" label="По" size="sm" value="{{ $to }}" />
    <x-ui.button type="submit" size="xs" variant="secondary">Показать</x-ui.button>
    @if ($from || $to)
        <x-ui.button href="{{ route('admin.telegram.diagnostic') }}" size="xs" variant="ghost">Сбросить</x-ui.button>
    @endif
</form>

<x-ui.card class="mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <x-ui.stat label="Запустили бота">{{ $funnel['starts'] }}</x-ui.stat>
        <x-ui.stat label="Открыли тест / прошли">
            {{ $pct($funnel['opened'], $funnel['starts']) }} / {{ $pct($funnel['finished'], $funnel['starts']) }}
            <x-slot:sub>{{ $funnel['opened'] }} / {{ $funnel['finished'] }} чел.</x-slot:sub>
        </x-ui.stat>
        <x-ui.stat label="«Да, хочу» от прошедших">
            {{ $pct($funnel['yes'], $funnel['finished']) }}
            <x-slot:sub>да {{ $funnel['yes'] }} · пока нет {{ $funnel['no'] }}</x-slot:sub>
        </x-ui.stat>
        <x-ui.stat label="Написали в диалог">
            {{ $pct($funnel['wrote'], $funnel['starts']) }}
            <x-slot:sub>{{ $funnel['wrote'] }} чел.</x-slot:sub>
        </x-ui.stat>
    </div>
</x-ui.card>

<h2 class="sans-medium text-lg text-zinc-900 mb-2">По меткам рекламы</h2>
@if ($bySource === [])
    <x-ui.empty class="mb-6">Пока никто не запускал бота диагностики</x-ui.empty>
@else
    <x-ui.table class="mb-6">
        <thead>
            <tr><th>Метка</th>@foreach (\App\Service\Diagnostic\DiagnosticStats::STEPS as $label)<th>{{ $label }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($bySource as $source => $row)
                <tr>
                    <td data-primary>{!! $source !== '' ? '<code>'.e($source).'</code>' : '<span class="text-zinc-400">без метки</span>' !!}</td>
                    @foreach (\App\Service\Diagnostic\DiagnosticStats::STEPS as $key => $label)
                        <td data-label="{{ $label }}" class="tabular-nums">
                            {{ $row[$key] }}
                            @if ($key !== 'starts') <span class="text-zinc-400">· {{ $pct($row[$key], $row['starts']) }}</span> @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<h2 class="sans-medium text-lg text-zinc-900 mb-1">Где бросают</h2>
<p class="text-sm text-zinc-500 mb-2">Начали и не закончили: последнее прохождение стоит больше {{ \App\Service\Diagnostic\DiagnosticStats::DROPOUT_AFTER_MINUTES }} минут (через 3 часа бот напомнит «Продолжим?» — часть вернётся). Блоки идут в случайном порядке, поэтому — по теме и номеру вопроса в блоке.</p>
@if ($dropOffs['total'] === 0)
    <x-ui.empty class="mb-6">Брошенных прохождений нет</x-ui.empty>
@else
    <x-ui.table class="mb-6">
        <thead><tr><th>Где закрыли</th><th>Сколько</th><th>На каком вопросе блока</th></tr></thead>
        <tbody>
            @foreach ($dropOffs['stages'] as $stage)
                @continue($stage['n'] === 0)
                <tr>
                    <td data-primary>{{ $stage['label'] }}</td>
                    <td data-label="Сколько" class="tabular-nums">{{ $stage['n'] }} <span class="text-zinc-400">· {{ $pct($stage['n'], $dropOffs['total']) }}</span></td>
                    <td data-label="На каком вопросе блока" class="text-sm">
                        @forelse ($stage['positions'] as $position => $n)
                            {{ $position }}-й — {{ $n }}@if (! $loop->last) · @endif
                        @empty
                            <span class="text-zinc-400">—</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<h2 class="sans-medium text-lg text-zinc-900 mb-2">Результаты</h2>
@if ($results['count'] === 0)
    <x-ui.empty class="mb-6">Тест до конца ещё никто не прошёл</x-ui.empty>
@else
    <x-ui.card class="mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-ui.stat label="Прошли">{{ $results['count'] }}</x-ui.stat>
            <x-ui.stat label="Средний балл (середина диапазона)">{{ $results['avg'] }}</x-ui.stat>
            <x-ui.stat label="Классы">
                <x-slot:sub>
                    @foreach ($results['grades'] as $grade => $n)
                        {{ config("diagnostic.grades.{$grade}", $grade) }} — {{ $n }}@if (! $loop->last) · @endif
                    @endforeach
                </x-slot:sub>
            </x-ui.stat>
        </div>
    </x-ui.card>

    <x-ui.table class="mb-6">
        <thead>
            <tr><th>Блок</th>@foreach ($levelNames as $level => $name)<th>{{ $level }} — {{ $name }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($results['levels'] as $block => $counts)
                <tr>
                    <td data-primary>{{ $blocks[$block]['title'] }}</td>
                    @foreach ($counts as $level => $n)
                        <td data-label="{{ $level }} — {{ $levelNames[$level] }}" class="tabular-nums">{{ $n }} <span class="text-zinc-400">· {{ $pct($n, $results['count']) }}</span></td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    <h2 class="sans-medium text-lg text-zinc-900 mb-2">Последние прохождения</h2>
    <x-ui.table class="mb-6">
        <thead><tr><th>Кто</th><th>Класс</th><th>Балл</th><th>Цель</th><th>«Хочешь улучшить?»</th><th>Когда</th></tr></thead>
        <tbody>
            @foreach ($recent as $a)
                <tr>
                    <td data-primary><a href="{{ route('admin.telegram.contacts.show', $a->contact) }}" class="text-blue-600 hover:underline">{{ $a->contact->displayName() }}</a></td>
                    <td data-label="Класс">{{ $a->grade ? config("diagnostic.grades.{$a->grade}", $a->grade) : '—' }}</td>
                    <td data-label="Балл" class="tabular-nums">~{{ $a->result['low'] }}–{{ $a->result['high'] }} <span class="text-zinc-400">→ {{ $a->result['potential'] }}+</span></td>
                    <td data-label="Цель" class="tabular-nums">{{ $a->target_score ?? 'не знает' }}</td>
                    <td data-label="«Хочешь улучшить?»">
                        <span>
                            @if ($a->improve === 'yes') <x-ui.badge tone="green">да</x-ui.badge>
                            @elseif ($a->improve === 'no') <x-ui.badge>пока нет</x-ui.badge>
                            @else <span class="text-zinc-400">не ответил</span> @endif
                        </span>
                    </td>
                    <td data-label="Когда" class="md:whitespace-nowrap text-sm">{{ $a->finished_at->format('d.m H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<h2 class="sans-medium text-lg text-zinc-900 mb-1">Вопросы</h2>
<p class="text-sm text-zinc-500 mb-2">Как часто задаётся вопрос и сколько на нём верных. По этим цифрам после 200–300 прохождений видна реальная сложность: «лёгкий» с 30% верных стоит перенести выше. «Бросили» — сколько человек закрыли тест на этом вопросе: много — вопрос слишком сложный, длинный или непонятный.</p>
@if ($questions === [])
    <x-ui.empty>Ответов пока нет</x-ui.empty>
@else
    <x-ui.table>
        <thead><tr><th>Вопрос</th><th>Блок · уровень</th><th>Задан</th><th>Верно</th><th>Наугад</th><th>Время</th><th>Бросили</th></tr></thead>
        <tbody>
            @foreach ($questions as $q)
                <tr>
                    <td data-primary><span class="font-mono text-xs text-zinc-400">{{ $q['id'] }}</span> {{ $q['prompt'] }}</td>
                    <td data-label="Блок · уровень" class="text-sm">{{ $blocks[$q['block']]['title'] ?? $q['block'] }} · {{ $levelNames[$q['level']] ?? $q['level'] }}</td>
                    <td data-label="Задан" class="tabular-nums">{{ $q['shown'] }}</td>
                    <td data-label="Верно" class="tabular-nums">{{ $q['correct'] }}%</td>
                    <td data-label="Наугад" class="tabular-nums">{{ $q['guess'] }}%</td>
                    <td data-label="Время" class="tabular-nums">{{ $q['ms'] !== null ? round($q['ms'] / 1000).' с' : '—' }}</td>
                    <td data-label="Бросили" class="tabular-nums">{{ $dropOffs['questions'][$q['id']] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif
@endsection
