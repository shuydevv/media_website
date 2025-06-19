@extends('admin.layouts.main')

@section('content')

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif
    
    <div class="max-w-6xl mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold mb-6">Список курсов</h1>

        <a href="{{ route('admin.courses.create') }}"
           class="inline-block bg-blue-600 text-white px-4 py-2 rounded mb-4 hover:bg-blue-700 transition">
            + Добавить курс
        </a>

        @if ($courses->isEmpty())
            <p class="text-gray-500">Курсы пока не добавлены.</p>
        @else
            <div class="grid grid-cols-1 gap-4">
                @foreach ($courses as $course)
                    <div class="border rounded p-4 shadow-sm hover:shadow-md transition">
                        <h2 class="text-xl font-semibold mb-1">{{ $course->title }}</h2>
                        <p class="text-sm text-gray-600 mb-2">{{ $course->description }}</p>
                        <div class="text-sm text-gray-500 mb-2">
                            {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}
                            —
                            {{ \Carbon\Carbon::parse($course->end_date)->format('d.m.Y') }}
                        </div>
                        <div class="flex gap-3 mt-2">
                            <a href="{{ route('admin.courses.show', $course->id) }}" class="text-blue-600 hover:underline">Подробнее</a>
                            <a href="{{ route('admin.courses.edit', $course->id) }}" class="text-yellow-600 hover:underline">Редактировать</a>
                            <form method="POST" action="{{ route('admin.courses.destroy', $course->id) }}"
                                onsubmit="return confirm('Точно удалить курс?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Удалить</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
