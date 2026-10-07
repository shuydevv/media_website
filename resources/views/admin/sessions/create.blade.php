@extends('admin.layouts.main')

@section('title', 'Новая сессия')

@section('content')
<div class="max-w-2xl">
    <x-ui.page-header title="Создание сессии" :back="route('admin.sessions.index')" back-label="Сессии" />

    <form method="POST" action="{{ route('admin.sessions.store') }}">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.select name="course_id" label="Курс" required>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.input type="date" name="date" label="Дата" value="{{ old('date') }}" required />
                <x-ui.input type="time" name="start_time" label="Время начала" value="{{ old('start_time') }}" required />
                <x-ui.input type="number" name="duration_minutes" label="Длительность, минут" value="{{ old('duration_minutes') }}" min="1" inputmode="numeric" required />
            </div>

            <x-ui.select name="status" label="Статус">
                <option value="active" @selected(old('status', 'active') === 'active')>Активно</option>
                <option value="cancelled" @selected(old('status') === 'cancelled')>Отменено</option>
            </x-ui.select>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить</x-ui.button>
            <x-ui.button href="{{ route('admin.sessions.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
