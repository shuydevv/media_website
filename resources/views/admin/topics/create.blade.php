@extends('admin.layouts.main')

@section('title', 'Создать тему')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Создать тему" :back="route('admin.topic.index')" back-label="Темы" />

    <form action="{{ route('admin.topic.store') }}" method="post">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название темы" value="{{ old('title') }}" placeholder="Введите название" required />

            <x-ui.select name="section_id" label="Раздел">
                @foreach ($sections as $option)
                    <option value="{{ $option->id }}" @selected(old('section_id') == $option->id)>{{ $option->title }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать тему</x-ui.button>
            <x-ui.button href="{{ route('admin.topic.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
