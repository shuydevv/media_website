@extends('admin.layouts.main')

@section('title', 'Категории')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Категории',
        'items' => $categories,
        'showRoute' => 'admin.category.show',
        'createRoute' => 'admin.category.create',
        'createLabel' => 'Создать категорию',
        'emptyText' => 'Категорий пока нет.',
    ])
@endsection
