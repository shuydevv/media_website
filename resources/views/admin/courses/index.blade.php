@extends('admin.layouts.main')

@section('title', 'Курсы')

@section('content')
    <x-ui.page-header title="Курсы">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.courses.create') }}" size="xs">
                <x-icon name="plus" class="w-4 h-4" />
                Добавить курс
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($courses->isEmpty())
        <x-ui.empty>Курсы пока не добавлены.</x-ui.empty>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($courses as $course)
                <x-ui.card class="flex flex-col">
                    <h2 class="sans-medium text-lg text-zinc-900">{{ $course->title }}</h2>
                    <div class="flex items-center gap-1.5 text-sm text-zinc-500 mt-1">
                        <x-icon name="calendar" class="w-4 h-4 shrink-0" />
                        {{ \Carbon\Carbon::parse($course->start_date)->format('d.m.Y') }}
                        —
                        {{ \Carbon\Carbon::parse($course->end_date)->format('d.m.Y') }}
                    </div>
                    @if ($course->description)
                        <p class="text-sm text-zinc-600 mt-2">{{ \Illuminate\Support\Str::limit($course->description, 180) }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 mt-auto pt-4">
                        <x-ui.button href="{{ route('admin.courses.edit', $course->id) }}" variant="secondary" size="xs">Редактировать</x-ui.button>
                        <x-ui.button href="{{ route('admin.sessions.index', ['course_id' => $course->id]) }}" variant="ghost" size="xs">Сессии</x-ui.button>
                        <x-ui.action-form :action="route('admin.courses.destroy', $course->id)" confirm="Точно удалить курс?" class="ml-auto">
                            <span class="text-apple-red-650">Удалить</span>
                        </x-ui.action-form>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
@endsection
