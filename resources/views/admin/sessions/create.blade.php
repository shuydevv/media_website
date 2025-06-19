@extends('admin.layouts.main')

@section('content')
<div class="max-w-2xl mx-auto bg-white shadow-md rounded-xl p-6">
    <h1 class="text-xl font-semibold mb-6">Создание занятия</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600">
            <ul class="list-disc pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.sessions.store') }}">
        @csrf

        {{-- Курс --}}
        <div class="mb-4">
            <label for="course_id" class="block text-sm font-medium">Курс</label>
            <select name="course_id" id="course_id" required class="w-full border rounded px-3 py-2">
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                @endforeach
            </select>
        </div>

        {{-- Дата --}}
        <div class="mb-4">
            <label for="date" class="block text-sm font-medium">Дата</label>
            <input type="date" name="date" id="date" class="w-full border rounded px-3 py-2" required>
        </div>

        {{-- Время начала --}}
        <div class="mb-4">
            <label for="start_time" class="block text-sm font-medium">Время начала</label>
            <input type="time" name="start_time" id="start_time" class="w-full border rounded px-3 py-2" required>
        </div>

        {{-- Длительность --}}
        <div class="mb-4">
            <label for="duration_minutes" class="block text-sm font-medium">Длительность (минут)</label>
            <input type="number" name="duration_minutes" id="duration_minutes" class="w-full border rounded px-3 py-2" required min="1">
        </div>

        {{-- Статус --}}
        <div class="mb-4">
            <label for="status" class="block text-sm font-medium">Статус</label>
            <select name="status" id="status" class="w-full border rounded px-3 py-2">
                <option value="active">Активно</option>
                <option value="cancelled">Отменено</option>
            </select>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Сохранить
        </button>
    </form>
</div>
@endsection