@extends('admin.layouts.main')

@section('title', 'Тэги')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Тэги',
        'items' => $tags,
        'showRoute' => 'admin.tag.show',
        'createRoute' => 'admin.tag.create',
        'createLabel' => 'Создать тэг',
        'emptyText' => 'Тэгов пока нет.',
    ])
@endsection
