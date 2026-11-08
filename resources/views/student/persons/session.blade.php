{{-- resources/views/student/persons/session.blade.php
     Каркас сессии тренажёра личностей — тот же, что у тренажёров терминов и
     дат, и тот же общий скрипт (student/terms/partials/session-script):
     карточки стопкой, маскот с кольцом прогресса, автопереход, итог.
     Своё здесь — стили (.prs-*, свои, а не Tailwind: новые классы требовали
     бы пересборки CSS) и небольшой скрипт задания «Соответствие». --}}
@extends('layouts.main')

@section('title', $kindLabel.' — Школа Полтавского')

@section('back_url', route('student.persons.index'))

@section('content')
<style>
  @media (max-width: 1279px) {
    #student-bottom-nav, #student-bottom-nav-reveal { display: none !important; }
    body.student-portal { padding-bottom: 0 !important; }
  }
</style>
@include('student.persons.partials.styles')
<div id="terms-app">
  @include('student.persons.partials.region')
</div>
@endsection

@push('page-scripts')
  @include('student.terms.partials.session-script')
  @include('student.persons.partials.match-script')
  @include('student.persons.partials.rank-script')
@endpush
