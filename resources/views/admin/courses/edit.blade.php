@extends('admin.layouts.main')

@section('title', 'Редактирование курса')

@section('content')
<div class="max-w-3xl">
    <x-ui.page-header title="Редактировать курс" :back="route('admin.courses.index')" back-label="Курсы" />

    <form method="POST" action="{{ route('admin.courses.update', $course->id) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')

        @include('admin.courses.partials.form-fields', ['course' => $course])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.courses.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
