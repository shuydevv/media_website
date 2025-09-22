@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto bg-white p-6 rounded shadow">
    <h1 class="text-2xl font-bold mb-6">Домашние задания</h1>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    <table class="w-full table-auto border text-sm">
        <thead>
            <tr class="bg-gray-100">
                <th class="p-2 border">ID</th>
                <th class="p-2 border">Название</th>
                <th class="p-2 border">Тип</th>
                <th class="p-2 border">Создано</th>
                <th class="p-2 border">Действия</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($homeworks as $homework)
                <tr>
                    <td class="p-2 border">{{ $homework->id }}</td>
                    <td class="p-2 border">{{ $homework->title }}</td>
                    <td class="p-2 border">{{ $homework->type }}</td>
                    <td class="p-2 border">{{ $homework->created_at->format('d.m.Y H:i') }}</td>
                    <td class="p-2 border whitespace-nowrap space-x-2">
                        {{-- Переход к просмотру --}}
                        <a href="{{ route('admin.homeworks.show', $homework->id) }}"
                           class="text-blue-600 hover:underline">Открыть</a>

                        {{-- Удаление --}}
                        <form action="{{ route('admin.homeworks.destroy', $homework->id) }}" method="POST"
                              class="inline-block"
                              onsubmit="return confirm('Удалить это домашнее задание?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Удалить</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-4 text-center text-gray-500">Домашних заданий пока нет.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <a class="bg-blue-600 rounded text-white px-4 py-3 mt-8 inline-block" href="{{route('admin.homeworks.create')}}">Создать домашку</a>
</div>
@endsection
