@extends('admin.layouts.main')

@section('title', 'Разделы')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $section->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.section.index'),
        'backLabel' => 'Разделы',
        'rows' => [
            'ID' => $section->id,
            'Название' => $section->title,
            'Категория' => $section->category?->title,
        ],
        'editUrl' => route('admin.section.edit', $section),
        'deleteUrl' => route('admin.section.delete', $section->id),
        'deleteConfirm' => 'Удалить раздел?',
    ])
@endsection
