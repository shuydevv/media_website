@extends('admin.layouts.main')

@section('title', 'Сессии')

@section('content')
<x-ui.page-header title="Сессии">
    <x-slot:actions>
        <x-ui.button href="{{ route('admin.sessions.create') }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            Создать сессию
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

<form method="GET" action="{{ route('admin.sessions.index') }}" class="mb-4 grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3 items-end">
    <x-ui.select name="course_id" label="Курс" onchange="this.form.submit()" wrap="col-span-2 md:col-span-1">
        <option value="">Все курсы</option>
        @foreach($courses as $course)
            <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->title }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.select name="status" label="Статус" onchange="this.form.submit()">
        <option value="">Все статусы</option>
        <option value="active" @selected(request('status') === 'active')>Активные</option>
        <option value="cancelled" @selected(request('status') === 'cancelled')>Отменённые</option>
    </x-ui.select>

    <x-ui.input type="date" name="date" label="Дата" value="{{ request('date') }}" onchange="this.form.submit()" />

    <div class="col-span-2 md:col-span-1 flex gap-2">
        <x-ui.button type="submit" variant="secondary" size="xs">Применить</x-ui.button>
        @if(request()->hasAny(['course_id','date','status']))
            <x-ui.button href="{{ route('admin.sessions.index') }}" variant="ghost" size="xs">Сбросить</x-ui.button>
        @endif
    </div>
</form>

@if($sessions->isEmpty())
    <x-ui.empty>Сессий не найдено.</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr>
                <th>Дата</th>
                <th>Курс</th>
                <th>Длительность</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($sessions as $session)
                <tr>
                    <td data-primary class="md:whitespace-nowrap">
                        {{ \Illuminate\Support\Carbon::parse($session->date)->format('d.m.Y') }}
                        <span class="font-normal text-zinc-500">· {{ substr((string) $session->start_time, 0, 5) }}</span>
                    </td>
                    <td data-label="Курс">{{ $session->course->title ?? '—' }}</td>
                    <td data-label="Длительность">{{ $session->duration_minutes }} мин</td>
                    <td data-label="Статус">
                        <span>
                            @if($session->status === 'cancelled')
                                <x-ui.badge tone="red">Отменена</x-ui.badge>
                            @elseif($session->status === 'active')
                                <x-ui.badge tone="green">Активна</x-ui.badge>
                            @else
                                <x-ui.badge>{{ $session->status }}</x-ui.badge>
                            @endif
                            @unless($session->lesson)
                                <x-ui.badge tone="orange">нет урока</x-ui.badge>
                            @endunless
                        </span>
                    </td>
                    <td data-actions>
                        <div class="ui-table-actions md:justify-end -ml-3.5 md:ml-0">
                            <x-ui.button href="{{ route('admin.sessions.edit', $session->id) }}" variant="ghost" size="xs">Изменить</x-ui.button>
                            <x-ui.action-form :action="route('admin.sessions.destroy', $session->id)"
                                              :confirm="$session->lesson ? 'К этой сессии привязан урок — сначала удалите его отдельно. Всё равно попробовать удалить сессию?' : 'Удалить это занятие?'">
                                <span class="text-apple-red-650">Удалить</span>
                            </x-ui.action-form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<div class="mt-4">
    {{ $sessions->appends(request()->query())->links('pagination.ui') }}
</div>
@endsection
