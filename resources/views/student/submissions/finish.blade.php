@extends('layouts.main')

@section('title', ($homework->title ?? 'Домашнее задание').' — Школа Полтавского')

@section('content')
<div id="wizard-app">
  @include('student.submissions.partials.finish-region')
</div>
@endsection
