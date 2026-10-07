@extends('admin.layouts.main')

@section('title', 'Редактировать категорию')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Редактировать категорию" :back="route('admin.category.show', $category)" back-label="Назад" />

    <form action="{{ route('admin.category.update', $category->id) }}" method="post">
        @csrf
        @method('PATCH')

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название категории" value="{{ old('title', $category->title) }}" placeholder="Введите название" required />
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.category.show', $category) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
