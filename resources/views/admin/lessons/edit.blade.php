@extends('admin.layouts.main')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Редактирование урока</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.lessons.update', $lesson->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        {{-- Привязка к сессии (не редактируется) --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Сессия</label>
            <div class="px-3 py-2 border rounded bg-gray-100 text-gray-700">
                {{ $lesson->session->date }} — {{ $lesson->session->course->title }}
            </div>
        </div>

        {{-- Тема урока --}}
        <div class="mb-4">
            <label for="title" class="block text-sm font-medium">Тема урока</label>
            <input type="text" name="title" id="title" value="{{ old('title', $lesson->title) }}" class="w-full border rounded px-3 py-2" required>
        </div>

        {{-- Ссылка на трансляцию --}}
        <div class="mb-4">
            <label for="meet_link" class="block text-sm font-medium">Ссылка на трансляцию</label>
            <input type="url" name="meet_link" id="meet_link" value="{{ old('meet_link', $lesson->meet_link) }}" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Ссылка на запись --}}
        <div class="mb-4">
            <label for="recording_link" class="block text-sm font-medium">Ссылка на запись</label>
            <input type="url" name="recording_link" id="recording_link" value="{{ old('recording_link', $lesson->recording_link) }}" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Ссылка на конспект --}}
        <div class="mb-4">
            <label for="notes_link" class="block text-sm font-medium">Ссылка на конспект</label>
            <input type="url" name="notes_link" id="notes_link" value="{{ old('notes_link', $lesson->notes_link) }}" class="w-full border rounded px-3 py-2">
        </div>

        {{-- Изображение --}}
        <div class="mb-6">
            <label for="image" class="block text-sm font-medium">Новое изображение</label>
            <input type="file" name="image" id="image" class="w-full text-sm text-gray-600 mt-1">
            @if ($lesson->image)
                <p class="mt-2 text-sm text-gray-500">Текущее изображение: <span class="text-blue-600">{{ $lesson->image }}</span></p>
            @endif
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Обновить урок
        </button>
    </form>
</div>
@endsection
