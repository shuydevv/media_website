@extends('admin.layouts.main')

@section('title', 'Курс')

@section('content')
    <x-ui.page-header title="Курс" :back="route('admin.courses.index')" back-label="Курсы" />

    <x-ui.empty>
        Отдельной страницы просмотра у курса нет — вся информация в форме редактирования.
    </x-ui.empty>
@endsection
