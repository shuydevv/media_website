@extends('admin.layouts.main')

@section('title', 'Уроки')

@section('content')
<x-ui.page-header title="Уроки">
    <x-slot:actions>
        <x-ui.button href="{{ route('admin.lessons.create') }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            Создать урок
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

<form method="GET" action="{{ route('admin.lessons.index') }}" class="mb-4 flex flex-wrap gap-2">
    <x-ui.select name="course_id" onchange="this.form.submit()" wrap="w-full sm:w-72" aria-label="Курс">
        <option value="">Все курсы</option>
        @foreach($courses as $course)
            <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->title }}</option>
        @endforeach
    </x-ui.select>
    <noscript><x-ui.button type="submit" size="xs">Показать</x-ui.button></noscript>
</form>

@if($lessons->isEmpty())
    <x-ui.empty>{{ request('course_id') ? 'У этого курса уроков пока нет.' : 'Уроки ещё не добавлены.' }}</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr>
                <th>Тема</th>
                <th>Курс</th>
                <th>Дата</th>
                <th>ID</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($lessons as $lesson)
                <tr>
                    <td data-primary>
                        <a href="{{ route('admin.lessons.edit', $lesson->id) }}" class="font-medium text-zinc-900 hover:underline">{{ $lesson->title ?: 'Урок без названия' }}</a>
                    </td>
                    <td data-label="Курс">{{ $lesson->session?->course?->title ?? '—' }}</td>
                    <td data-label="Дата" class="md:whitespace-nowrap">{{ $lesson->session?->date ? \Illuminate\Support\Carbon::parse($lesson->session->date)->format('d.m.Y') : '—' }}</td>
                    <td data-label="ID урока / сессии" class="text-zinc-500 md:whitespace-nowrap">#{{ $lesson->id }} / #{{ $lesson->session_id }}</td>
                    <td data-actions>
                        <div class="ui-table-actions md:justify-end -ml-3.5 md:ml-0">
                            <x-ui.button href="{{ route('admin.lessons.edit', $lesson->id) }}" variant="ghost" size="xs">Изменить</x-ui.button>
                            <x-ui.action-form :action="route('admin.lessons.destroy', $lesson->id)" confirm="Удалить урок?">
                                <span class="text-apple-red-650">Удалить</span>
                            </x-ui.action-form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<div class="mt-4">{{ $lessons->appends(request()->query())->links('pagination.ui') }}</div>
@endsection
