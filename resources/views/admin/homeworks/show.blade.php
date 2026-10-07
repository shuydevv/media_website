@extends('admin.layouts.main')

@section('title', 'Домашка')

@section('content')
@php
    $typeLabels = [
        'test' => 'Тест с вариантами',
        'text_with_questions' => 'Текст с вопросами',
        'matching' => 'Соотнесение',
        'image_auto' => 'Картинка (автопроверка)',
        'image_manual' => 'Картинка (ручная проверка)',
        'written' => 'Развёрнутый ответ',
        'table' => 'Таблица',
    ];
@endphp

<x-ui.page-header :title="$homework->title" :back="route('admin.homeworks.index')" back-label="Домашки">
    @if($homework->description)
        {{ $homework->description }}
    @endif

    <x-slot:actions>
        <x-ui.button href="{{ route('admin.homeworks.edit', $homework) }}" size="xs">Редактировать</x-ui.button>
        <x-ui.action-form :action="route('admin.homeworks.duplicate', $homework)" method="POST" variant="secondary">Дублировать</x-ui.action-form>
    </x-slot:actions>
</x-ui.page-header>

<x-ui.card class="mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-ui.stat label="Курс" size="md">{{ $homework->course->title ?? '—' }}</x-ui.stat>
        <x-ui.stat label="Урок" size="md">{{ $homework->lesson->title ?? '—' }}</x-ui.stat>
        <x-ui.stat label="Тип" size="md">{{ $homework->type === 'mock' ? 'Пробник' : 'Обычное ДЗ' }}</x-ui.stat>
    </div>
</x-ui.card>

<h2 class="sans-medium text-xl md:text-2xl text-zinc-900 mb-3">Задания</h2>

<div class="space-y-4">
    @forelse($homework->tasks as $index => $task)
        <x-ui.card>
            <div class="flex items-center gap-2 mb-3">
                <span class="sans-medium text-lg text-zinc-900">№{{ $index + 1 }}</span>
                <x-ui.badge>{{ $typeLabels[$task->type] ?? ucfirst((string) $task->type) }}</x-ui.badge>
            </div>

            @if($task->question_text)
                <div class="mb-3 text-zinc-800">
                    {!! nl2br(e($task->question_text)) !!}
                </div>
            @endif

            {{-- Варианты ответа --}}
            @php
                $options = $task->options ?? [];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    $options = is_array($decoded) ? $decoded : [];
                }
            @endphp
            @if(!empty($options) && in_array($task->type, ['test','image_auto']))
                <div class="mb-3">
                    <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-1">Варианты ответа</div>
                    <ol class="list-decimal pl-5 text-sm text-zinc-700 space-y-0.5">
                        @foreach($options as $opt)
                            <li>{{ $opt }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            {{-- Соотнесение --}}
            @php
                $matchesRaw = $task->matches ?? [];
                if (is_string($matchesRaw)) {
                    $decoded = json_decode($matchesRaw, true);
                    $matchesRaw = is_array($decoded) ? $decoded : [];
                }
                $left  = isset($matchesRaw['left'])  && is_array($matchesRaw['left'])  ? $matchesRaw['left']  : [];
                $right = isset($matchesRaw['right']) && is_array($matchesRaw['right']) ? $matchesRaw['right'] : [];
            @endphp
            @if($task->type === 'matching')
                <div class="mb-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-1">Левая колонка</div>
                        <ul class="list-disc pl-5 text-sm text-zinc-700 space-y-0.5">
                            @foreach($left as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-1">Правая колонка</div>
                        <ul class="list-disc pl-5 text-sm text-zinc-700 space-y-0.5">
                            @foreach($right as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Таблица --}}
            @php
                $tableData = $task->table ?? [];
                if (is_string($tableData)) {
                    $decoded = json_decode($tableData, true);
                    $tableData = is_array($decoded) ? $decoded : [];
                }
            @endphp
            @if($task->type === 'table' && !empty($tableData))
                <div class="mb-3 overflow-x-auto">
                    <table class="border-collapse text-sm">
                        @foreach(array_chunk($tableData, 3) as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td class="border border-gray-200 px-3 py-2">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            {{-- Изображение --}}
            @if($task->image_path)
                <div class="mb-3">
                    <img src="{{ asset('storage/'.$task->image_path) }}" alt="Изображение к заданию" class="max-w-full sm:max-w-xs rounded-lg border border-gray-200">
                </div>
            @endif

            {{-- Правильный ответ --}}
            @if($task->answer)
                <div class="rounded-xl p-3 px-4 text-sm" style="background-color: #e2f4ef">
                    <div class="text-xs mb-1" style="color: #33a885">Правильный ответ</div>
                    <div class="whitespace-pre-wrap break-words text-zinc-800">{{ $task->answer }}</div>
                </div>
            @endif
        </x-ui.card>
    @empty
        <x-ui.empty>Заданий нет</x-ui.empty>
    @endforelse
</div>

{{-- Попытки учеников по этой домашке — можно обнулить, если нужно дать
     пересдать сверх обычного лимита попыток (Homework::attemptsAllowed()). --}}
@if($attemptsByStudent->isNotEmpty())
    <h2 class="sans-medium text-xl md:text-2xl text-zinc-900 mb-3 mt-8">Попытки учеников</h2>

    <x-ui.table>
        <thead>
            <tr>
                <th>Ученик</th>
                <th>Попыток</th>
                <th>Последний балл</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($attemptsByStudent as $row)
                <tr>
                    <td data-primary>
                        {{ $row['student']->name }}
                        <div class="text-xs font-normal text-zinc-500 break-all">{{ $row['student']->email }}</div>
                    </td>
                    <td data-label="Попыток использовано">{{ $row['attemptsUsed'] }} / {{ $homework->attemptsAllowed() }}</td>
                    <td data-label="Последний балл">{{ $row['lastScore'] ?? '—' }}</td>
                    @if($row['inProgress'])
                        <td data-label="Статус"><span><x-ui.badge tone="blue">Есть незавершённая попытка</x-ui.badge></span></td>
                    @else
                        <td></td>
                    @endif
                    <td data-actions>
                        <x-ui.action-form :action="route('admin.homeworks.attempts.reset', [$homework, $row['student']])" variant="danger-soft"
                                          :confirm="'Обнулить все попытки ученика '.$row['student']->name.' по этой домашке? Это удалит все его попытки сдачи, включая незавершённую (если есть). Отменить нельзя.'">Обнулить попытки</x-ui.action-form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

{{-- Ученики, которые записались на курс ПОСЛЕ урока этой домашки — по
     умолчанию не видят её и не могут открыть даже по прямой ссылке
     (см. Homework::isLessonBeforeEnrollment()). Здесь можно точечно
     открыть/закрыть доступ для конкретного ученика. --}}
@if($lateEnrollmentRows->isNotEmpty())
    <h2 class="sans-medium text-xl md:text-2xl text-zinc-900 mb-1 mt-8">Доступ для записавшихся позже</h2>
    <p class="text-sm text-zinc-500 mb-3 max-w-3xl">
        Урок этой домашки прошёл до того, как эти ученики записались на курс — обычная логика
        прячет от них домашку. Здесь можно точечно открыть доступ (или закрыть обратно).
    </p>

    <x-ui.table>
        <thead>
            <tr>
                <th>Ученик</th>
                <th>Записался</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($lateEnrollmentRows as $row)
                <tr>
                    <td data-primary>
                        {{ $row['student']->name }}
                        <div class="text-xs font-normal text-zinc-500 break-all">{{ $row['student']->email }}</div>
                    </td>
                    <td data-label="Записался">{{ $row['enrolledAt']?->format('d.m.Y') ?? '—' }}</td>
                    <td data-label="Статус">
                        <span>
                            @if($row['unlocked'])
                                <x-ui.badge tone="green">Открыта админом</x-ui.badge>
                            @else
                                <x-ui.badge tone="red">Заблокирована</x-ui.badge>
                            @endif
                        </span>
                    </td>
                    <td data-actions>
                        @if($row['unlocked'])
                            <x-ui.action-form :action="route('admin.homeworks.unlocks.destroy', [$homework, $row['student']])" variant="danger-soft"
                                              :confirm="'Закрыть доступ обратно для '.$row['student']->name.'?'">Закрыть доступ</x-ui.action-form>
                        @else
                            <form method="post" action="{{ route('admin.homeworks.unlocks.store', $homework) }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $row['student']->id }}">
                                <x-ui.button type="submit" variant="secondary" size="xs">Открыть доступ</x-ui.button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif
@endsection
