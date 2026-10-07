@extends('admin.layouts.main')

@section('title', 'Редактирование урока')

@section('content')
<div class="max-w-3xl">
    <x-ui.page-header title="Редактирование урока" :back="route('admin.lessons.index')" back-label="Уроки" />

    <form action="{{ route('admin.lessons.update', $lesson->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        {{-- Привязка к сессии не редактируется --}}
        <x-ui.card tone="gray">
            <x-ui.stat label="Сессия" size="md">{{ $lesson->session->date }} — {{ $lesson->session->course->title }}</x-ui.stat>
        </x-ui.card>

        @include('admin.lessons.partials.form-fields', ['lesson' => $lesson])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Обновить урок</x-ui.button>
            <x-ui.button href="{{ route('admin.lessons.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
