@extends('admin.layouts.main')

@section('title', 'Тэги')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $tag->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.tag.index'),
        'backLabel' => 'Тэги',
        'rows' => [
            'ID' => $tag->id,
            'Название' => $tag->title,
        ],
        'editUrl' => route('admin.tag.edit', $tag),
        'deleteUrl' => route('admin.tag.delete', $tag->id),
        'deleteConfirm' => 'Удалить тэг?',
    ])
@endsection
