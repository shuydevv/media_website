@extends('admin.layouts.main')

@section('title', 'Темы')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Темы',
        'items' => $topics,
        'showRoute' => 'admin.topic.show',
        'createRoute' => 'admin.topic.create',
        'createLabel' => 'Создать тему',
        'emptyText' => 'Тем пока нет.',
    ])
@endsection
