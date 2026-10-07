@extends('admin.layouts.main')

@section('title', 'Редактировать раздел')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Редактировать раздел" :back="route('admin.section.show', $section)" back-label="Назад" />

    <form action="{{ route('admin.section.update', $section->id) }}" method="post">
        @csrf
        @method('PATCH')

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название раздела" value="{{ old('title', $section->title) }}" placeholder="Введите название" required />

            <x-ui.select name="category_id" label="Категория">
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected(old('category_id', $section->category_id) == $option->id)>{{ $option->title }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.section.show', $section) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
