@extends('admin.layouts.main')

@section('title', 'Упражнения')

@section('content')
    @include('admin.partials.simple-show', [
        'title' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $exercise->title)), 120) ?: 'Без названия',
        'backUrl' => route('admin.exercise.index'),
        'backLabel' => 'Упражнения',
        'rows' => [
            'ID' => $exercise->id,
            'Название' => $exercise->title,
            'Номер в экзамене' => $exercise->ex_number,
            'Ответ' => $exercise->answer,
        ],
        'editUrl' => route('admin.exercise.edit', $exercise),
        'deleteUrl' => route('admin.exercise.delete', $exercise->id),
        'deleteConfirm' => 'Удалить упражнение безвозвратно?',
    ])
@endsection
