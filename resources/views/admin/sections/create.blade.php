@extends('admin.layouts.main')

@section('title', 'Создать раздел')

@section('content')
<div class="max-w-xl">
    <x-ui.page-header title="Создать раздел" :back="route('admin.section.index')" back-label="Разделы" />

    <form action="{{ route('admin.section.store') }}" method="post">
        @csrf

        <x-ui.card class="space-y-4">
            <x-ui.input name="title" label="Название раздела" value="{{ old('title') }}" placeholder="Введите название" required />

            <x-ui.select name="category_id" label="Категория">
                @foreach ($categories as $option)
                    <option value="{{ $option->id }}" @selected(old('category_id') == $option->id)>{{ $option->title }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать раздел</x-ui.button>
            <x-ui.button href="{{ route('admin.section.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
