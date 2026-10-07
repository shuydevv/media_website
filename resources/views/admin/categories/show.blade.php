@extends('admin.layouts.main')

@section('title', 'Категории')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $category->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.category.index'),
        'backLabel' => 'Категории',
        'rows' => [
            'ID' => $category->id,
            'Название' => $category->title,
        ],
        'editUrl' => route('admin.category.edit', $category),
        'deleteUrl' => route('admin.category.delete', $category->id),
        'deleteConfirm' => 'Удалить категорию?',
    ])
@endsection
