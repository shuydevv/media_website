@extends('admin.layouts.main')

@section('title', 'Посты')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Посты',
        'items' => $posts,
        'showRoute' => 'admin.post.show',
        'createRoute' => 'admin.post.create',
        'createLabel' => 'Создать пост',
        'emptyText' => 'Постов пока нет.',
        'meta' => fn ($post) => $post->path ? '/posts/'.$post->path : null,
    ])
@endsection
