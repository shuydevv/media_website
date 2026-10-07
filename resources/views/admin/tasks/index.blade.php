@extends('admin.layouts.main')

@section('title', 'Банк заданий')

@section('content')
@php
    $typeLabels = [
        'test' => 'Тест',
        'text_with_questions' => 'Текст с вопросами',
        'matching' => 'Соотнесение',
        'image_auto' => 'Картинка (авто)',
        'image_manual' => 'Картинка (ручная)',
        'written' => 'Развёрнутый ответ',
        'table' => 'Таблица',
    ];
    $hasFilters = filled($filters['search'] ?? null) || filled($filters['category_id'] ?? null) || filled($filters['number'] ?? null);
@endphp

<x-ui.page-header title="Банк заданий">
    <x-slot:actions>
        <x-ui.button href="{{ route('admin.tasks.import') }}" variant="secondary" size="xs">Импорт</x-ui.button>
        <x-ui.button href="{{ route('admin.tasks.create') }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            Создать
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

<form method="get" class="mb-4 flex flex-wrap gap-2">
    <x-ui.input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Поиск (категория/номер)" wrap="flex-1 min-w-[200px]" />
    <x-ui.select name="category_id" wrap="flex-1 min-w-[160px] sm:flex-none" aria-label="Категория">
        <option value="">Все категории</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? null)==$cat->id)>{{ $cat->title }}</option>
        @endforeach
    </x-ui.select>
    <x-ui.input name="number" value="{{ $filters['number'] ?? '' }}" placeholder="№ в ЕГЭ" inputmode="numeric" wrap="w-28" aria-label="Номер в ЕГЭ" />
    <x-ui.button type="submit" size="xs">Найти</x-ui.button>
    @if($hasFilters)
        <x-ui.button href="{{ route('admin.tasks.index') }}" variant="secondary" size="xs">Сброс</x-ui.button>
    @endif
</form>

@if($tasks->isEmpty())
    <x-ui.empty>{{ $hasFilters ? 'Ничего не найдено' : 'В банке пока нет заданий' }}</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr>
                <th>Вопрос</th>
                <th>ID</th>
                <th>Категория</th>
                <th>Номер</th>
                <th>Тип</th>
                <th>Публично</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $t)
                <tr>
                    <td data-primary class="md:max-w-xs">
                        <a href="{{ route('admin.tasks.show', $t) }}" class="text-zinc-900 hover:underline md:block md:truncate">
                            {{ \Illuminate\Support\Str::limit(strip_tags((string) $t->question_text), 60) ?: 'Без текста вопроса' }}
                        </a>
                    </td>
                    <td data-label="ID" class="text-zinc-500">#{{ $t->id }}</td>
                    <td data-label="Категория">{{ $t->category?->title ?? '—' }}</td>
                    <td data-label="Номер в ЕГЭ">{{ $t->number ?? '—' }}</td>
                    <td data-label="Тип">{{ $typeLabels[$t->type] ?? ($t->type ?? '—') }}</td>
                    <td data-label="Публично">
                        <span><x-ui.badge :tone="$t->is_public ? 'green' : 'gray'">{{ $t->is_public ? 'Да' : 'Нет' }}</x-ui.badge></span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<div class="mt-4">{{ $tasks->withQueryString()->links('pagination.ui') }}</div>
@endsection
