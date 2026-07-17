@extends('layouts.main')

@section('title', $homework->title ?? 'Домашнее задание')

@section('content')
<div id="wizard-app">
  @include('student.submissions.partials.question-region')
</div>
@endsection
