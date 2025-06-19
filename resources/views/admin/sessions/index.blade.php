@extends('admin.layouts.main')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold mb-6">Занятия</h1>

    {{-- Флеш-сообщения --}}
    @if (session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    {{-- Фильтр по курсу --}}
    <form method="GET" action="{{ route('admin.sessions.index') }}" class="mb-4">
        <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Фильтр по курсу</label>
        <select name="course_id" id="course_id" onchange="this.form.submit()" class="border rounded px-3 py-2 w-full max-w-sm">
            <option value="">Все курсы</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>
                    {{ $course->title }}
                </option>
            @endforeach
        </select>
    </form>

    {{-- Таблица занятий --}}
    <div class="overflow-x-auto bg-white shadow rounded-lg">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="text-left px-4 py-3">Дата</th>
                    <th class="text-left px-4 py-3">Начало</th>
                    <th class="text-left px-4 py-3">Длительность</th>
                    <th class="text-left px-4 py-3">Курс</th>
                    <th class="text-left px-4 py-3">Статус</th>
                    <th class="text-left px-4 py-3">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $session->date }}</td>
                        <td class="px-4 py-3">{{ $session->start_time }}</td>
                        <td class="px-4 py-3">{{ $session->duration_minutes }} мин</td>
                        <td class="px-4 py-3">{{ $session->course->title ?? '-' }}</td>
                        <td class="px-4 py-3 capitalize text-gray-700">{{ $session->status }}</td>
                        <td class="px-4 py-3 flex gap-2">
                            <a href="{{ route('admin.sessions.edit', $session->id) }}" class="text-blue-600 hover:underline">Редактировать</a>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3 text-gray-500" colspan="6">Занятий не найдено.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Пагинация --}}
    <div class="mt-6">
        {{ $sessions->appends(request()->query())->links() }}
    </div>
</div>
@endsection
