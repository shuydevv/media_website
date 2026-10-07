@extends('admin.layouts.main')

@section('title', 'Новый курс')

@section('content')
<div class="max-w-3xl">
    <x-ui.page-header title="Создать курс" :back="route('admin.courses.index')" back-label="Курсы" />

    <form method="POST" action="{{ route('admin.courses.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        @include('admin.courses.partials.form-fields', ['course' => null])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить курс</x-ui.button>
            <x-ui.button href="{{ route('admin.courses.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
