@extends('admin.layouts.main')

@section('title', 'Создать категорию')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Создать категорию" :back="route('admin.category.index')" back-label="Категории" />

    <form action="{{ route('admin.category.store') }}" method="post">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название категории" value="{{ old('title') }}" placeholder="Введите название" required />
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать категорию</x-ui.button>
            <x-ui.button href="{{ route('admin.category.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
