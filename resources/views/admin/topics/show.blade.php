@extends('admin.layouts.main')

@section('title', 'Темы')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $topic->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.topic.index'),
        'backLabel' => 'Темы',
        'rows' => [
            'ID' => $topic->id,
            'Название' => $topic->title,
            'Раздел' => $topic->section?->title,
        ],
        'editUrl' => route('admin.topic.edit', $topic),
        'deleteUrl' => route('admin.topic.delete', $topic->id),
        'deleteConfirm' => 'Удалить тему?',
    ])
@endsection
