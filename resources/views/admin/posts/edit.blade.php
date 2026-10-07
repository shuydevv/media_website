@extends('admin.layouts.main')

@section('title', 'Редактирование поста')

{{-- Ошибки показывает _form_errors — со своей припиской про сброс файлов. --}}
@section('own-errors', '1')

@section('content')
<div class="max-w-4xl">
    <x-ui.page-header title="Редактировать пост" :back="route('admin.post.show', $post)" back-label="К посту" />

    @include('admin.posts._form_errors')

    <form action="{{ route('admin.post.update', $post->id) }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')

        @include('admin.posts._form', ['post' => $post, 'images' => $images])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Обновить пост</x-ui.button>
            <x-ui.button href="{{ route('admin.post.show', $post) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
