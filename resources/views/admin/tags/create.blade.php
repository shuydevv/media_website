@extends('admin.layouts.main')

@section('title', 'Создать тэг')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Создать тэг" :back="route('admin.tag.index')" back-label="Тэги" />

    <form action="{{ route('admin.tag.store') }}" method="post">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название тэга" value="{{ old('title') }}" placeholder="Введите название" required />
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать тэг</x-ui.button>
            <x-ui.button href="{{ route('admin.tag.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
