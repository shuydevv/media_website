@extends('admin.layouts.main')

@section('title', 'Упражнения')

@section('content')
    @include('admin.partials.simple-index', [
        'title' => 'Упражнения',
        'items' => $exercises,
        'showRoute' => 'admin.exercise.show',
        'createRoute' => 'admin.exercise.create',
        'createLabel' => 'Создать упражнение',
        'emptyText' => 'Упражнений пока нет.',
        'meta' => fn ($exercise) => $exercise->ex_number ? '№ '.$exercise->ex_number.' в экзамене' : null,
    ])
@endsection
