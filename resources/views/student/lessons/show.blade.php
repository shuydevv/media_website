@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <a href="{{ route('student.courses.show', $course) }}" class="text-sm text-gray-500 hover:text-gray-700">← Назад к курсу</a>

  <h1 class="text-2xl font-bold mt-4">{{ $lesson->title }}</h1>
  @if($lesson->description)
    <p class="mt-2 text-gray-700">{{ $lesson->description }}</p>
  @endif>

  @if($lesson->homework ?? false)
  <a href="{{ route('student.submissions.create', $lesson->homework) }}"
     class="inline-flex items-center px-3 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
    Сдать домашку
  </a>
@endif

  {{-- Здесь позже добавим материалы и домашку --}}
</div>
@endsection
