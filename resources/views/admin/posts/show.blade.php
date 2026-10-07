@extends('admin.layouts.main')

@section('title', 'Посты')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $post->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.post.index'),
        'backLabel' => 'Посты',
        'rows' => [
            'ID' => $post->id,
            'Название' => $post->title,
            'Путь' => $post->path,
            'Описание' => $post->description,
        ],
        'editUrl' => route('admin.post.edit', $post),
        'deleteUrl' => route('admin.post.delete', $post->id),
        'deleteConfirm' => 'Удалить пост безвозвратно?',
    ])
@endsection
