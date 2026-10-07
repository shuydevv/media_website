@extends('admin.layouts.main')

@section('title', 'Домашки')

@section('content')
<x-ui.page-header title="Домашние задания">
    <x-slot:actions>
        <x-ui.button href="{{ route('admin.homeworks.import') }}" variant="secondary" size="xs">Импорт</x-ui.button>
        <x-ui.button href="{{ route('admin.homeworks.create') }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            Создать
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

@if ($homeworks->isEmpty())
    <x-ui.empty>Домашних заданий пока нет.</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr>
                <th>Название</th>
                <th>ID</th>
                <th>Тип</th>
                <th>Создано</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($homeworks as $homework)
                <tr>
                    <td data-primary>
                        <a href="{{ route('admin.homeworks.show', $homework->id) }}" class="font-medium text-zinc-900 hover:underline">{{ $homework->title }}</a>
                    </td>
                    <td data-label="ID" class="text-zinc-500">#{{ $homework->id }}</td>
                    <td data-label="Тип">
                        <span><x-ui.badge :tone="$homework->type === 'mock' ? 'purple' : 'gray'">{{ $homework->type === 'mock' ? 'Пробник' : 'Домашка' }}</x-ui.badge></span>
                    </td>
                    <td data-label="Создано" class="text-zinc-500 md:whitespace-nowrap">{{ $homework->created_at->format('d.m.Y H:i') }}</td>
                    <td data-actions>
                        <div class="ui-table-actions md:justify-end -ml-3.5 md:ml-0">
                            <x-ui.button href="{{ route('admin.homeworks.edit', $homework->id) }}" variant="ghost" size="xs">Изменить</x-ui.button>
                            <x-ui.action-form :action="route('admin.homeworks.destroy', $homework->id)" confirm="Удалить это домашнее задание?">
                                <span class="text-apple-red-650">Удалить</span>
                            </x-ui.action-form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

@if (method_exists($homeworks, 'links'))
    <div class="mt-4">{{ $homeworks->links('pagination.ui') }}</div>
@endif
@endsection
