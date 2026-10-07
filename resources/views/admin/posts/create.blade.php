@extends('admin.layouts.main')

@section('title', 'Новый пост')

{{-- Ошибки показывает _form_errors — со своей припиской про сброс файлов. --}}
@section('own-errors', '1')

@section('content')
<div class="max-w-4xl">
    <x-ui.page-header title="Создать пост" :back="route('admin.post.index')" back-label="Посты" />

    @include('admin.posts._form_errors')

    <form action="{{ route('admin.post.store') }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf

        @include('admin.posts._form', ['post' => null])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать пост</x-ui.button>
            <x-ui.button href="{{ route('admin.post.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
