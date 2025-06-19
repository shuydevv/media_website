@extends('admin.layouts.main')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold">Уроки</h1>
        <a href="{{ route('admin.lessons.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            + Создать урок
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 text-green-600 font-medium">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-md p-4">
        @if($lessons->isEmpty())
            <p class="text-gray-600">Уроки ещё не добавлены.</p>
        @else
            <table class="w-full table-auto text-sm text-left">
                <thead>
                    <tr class="text-gray-500 border-b">
                        <th class="py-2">ID</th>
                        <th class="py-2">Курс</th>
                        <th class="py-2">Дата</th>
                        <th class="py-2">Тема</th>
                        <th class="py-2">Сессия</th>
                        <th class="py-2">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lessons as $lesson)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2">{{ $lesson->id }}</td>
                            <td class="py-2">{{ $lesson->session->course->title }}</td>
                            <td class="py-2">{{ $lesson->session->date }}</td>
                            <td class="py-2">{{ $lesson->title ?? '—' }}</td>
                            <td class="py-2">ID: {{ $lesson->session_id }}</td>
                            <td class="py-2 flex gap-2">
                                <a href="{{ route('admin.lessons.edit', $lesson->id) }}" class="text-blue-600 hover:underline">Редактировать</a>
                                <form method="POST" action="{{ route('admin.lessons.destroy', $lesson->id) }}" onsubmit="return confirm('Удалить урок?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
