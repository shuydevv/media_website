@extends('admin.layouts.main')

@section('title', 'Шпаргалки')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Шпаргалки',
        'items' => $shpargalkas,
        'showRoute' => 'admin.shpargalka.show',
        'createRoute' => 'admin.shpargalka.create',
        'createLabel' => 'Создать шпаргалку',
        'emptyText' => 'Шпаргалок пока нет.',
    ])
@endsection
