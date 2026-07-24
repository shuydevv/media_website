{{-- resources/views/exercise/show.blade.php
     Публичная SEO-страница задания из банка (App\Models\Task, is_public=1) —
     та же запись, что видна в личном кабинете (student/tasks/practice.blade.php)
     и может быть частью домашки, просто без формы/авторизации: статичное
     "Показать ответ" вместо интерактивной проверки. --}}
@extends('layouts.main')

@php
  $categoryTitle = $task->category?->title;
@endphp

@section('title'){{ $task->question_text ? \Illuminate\Support\Str::limit(strip_tags($task->question_text), 60) : 'Задание' }} — Школа Полтавского@endsection
@section('description'){{ $task->question_text ? \Illuminate\Support\Str::limit(strip_tags($task->question_text), 150) : '' }}@endsection

@section('content')
<div class="container mx-auto max-w-screen-md px-3 md:mt-16 mt-10 md:mb-16 mb-10">
  <div class="text-sm text-zinc-500 mb-3">
    {{ $categoryTitle ?? 'Банк заданий' }} @if($task->number) · № {{ $task->number }} @endif
  </div>

  <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
    @include('student.submissions.partials.task-prompt', ['task' => $task])

    <div id="exercise-answer-box" class="overflow-hidden" style="height:0;">
      <div class="p-4 rounded-xl bg-blue-50 border border-blue-100">
        <div class="text-sm text-blue-900 font-medium mb-1">Ответ</div>
        <div class="text-sm text-blue-900 whitespace-pre-wrap">{{ $task->answer ?: '—' }}</div>
        @if($task->comment)
          <div class="text-sm text-blue-900 mt-3 whitespace-pre-wrap">{{ $task->comment }}</div>
        @endif
      </div>
      <div class="h-4" aria-hidden="true"></div>
    </div>
    <button type="button" id="exercise-answer-toggle" class="mt-4 inline-flex items-center px-5 py-3 bg-blue-600 text-white rounded-lg tracking-wide hover:bg-blue-700">
      <img class="inline-block mr-2" src="{{ asset('img/show.svg') }}" alt=""> Посмотреть ответ
    </button>
  </div>
</div>

<x-block>
  <x-ad_course :subject="in_array($categoryTitle, ['История','Обществознание'], true) ? $categoryTitle : null" />
</x-block>

<x-material></x-material>
<x-footer />

<script>
(function () {
  const btn = document.getElementById('exercise-answer-toggle');
  const box = document.getElementById('exercise-answer-box');
  if (!btn || !box) return;
  let open = false;
  btn.addEventListener('click', () => {
    open = !open;
    box.style.height = open ? box.scrollHeight + 'px' : '0';
    btn.style.display = open ? 'none' : '';
  });
})();
</script>
@endsection
