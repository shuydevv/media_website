@extends('admin.layouts.main')

@section('title', 'Новое упражнение')

@section('content')
<div class="max-w-4xl">
    <x-ui.page-header title="Создать упражнение" :back="route('admin.exercise.index')" back-label="Упражнения" />

    <form action="{{ route('admin.exercise.store') }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf

        @include('admin.exercises._form', ['exercise' => null])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать задание</x-ui.button>
            <x-ui.button href="{{ route('admin.exercise.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>

<script src="{{ asset('/js/admin/tabs.js') }}"></script>
<script src="{{ asset('/js/admin/select_options.js') }}"></script>
@endsection
