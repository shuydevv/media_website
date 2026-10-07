@extends('admin.layouts.main')

@section('title', 'Редактировать тэг')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Редактировать тэг" :back="route('admin.tag.show', $tag)" back-label="Назад" />

    <form action="{{ route('admin.tag.update', $tag->id) }}" method="post">
        @csrf
        @method('PATCH')

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название тэга" value="{{ old('title', $tag->title) }}" placeholder="Введите название" required />
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.tag.show', $tag) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
