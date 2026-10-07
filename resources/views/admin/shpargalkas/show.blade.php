@extends('admin.layouts.main')

@section('title', 'Шпаргалки')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $shpargalka->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.shpargalka.index'),
        'backLabel' => 'Шпаргалки',
        'rows' => [
            'ID' => $shpargalka->id,
            'Название' => $shpargalka->title,
            'Путь' => $shpargalka->path,
            'Цена' => $shpargalka->price,
        ],
        'editUrl' => route('admin.shpargalka.edit', $shpargalka),
        'deleteUrl' => route('admin.shpargalka.delete', $shpargalka->id),
        'deleteConfirm' => 'Удалить шпаргалку безвозвратно?',
    ])
@endsection
