@extends('admin.layouts.main')

@section('title', 'Разделы')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Разделы',
        'items' => $sections,
        'showRoute' => 'admin.section.show',
        'createRoute' => 'admin.section.create',
        'createLabel' => 'Создать раздел',
        'emptyText' => 'Разделов пока нет.',
    ])
@endsection
