@extends('admin.layouts.main')

@section('title', 'Редактировать тему')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Редактировать тему" :back="route('admin.topic.show', $topic)" back-label="Назад" />

    <form action="{{ route('admin.topic.update', $topic->id) }}" method="post">
        @csrf
        @method('PATCH')

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название темы" value="{{ old('title', $topic->title) }}" placeholder="Введите название" required />

            <x-ui.select name="section_id" label="Раздел">
                @foreach ($sections as $option)
                    <option value="{{ $option->id }}" @selected(old('section_id', $topic->section_id) == $option->id)>{{ $option->title }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.topic.show', $topic) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
