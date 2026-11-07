{{-- Проверка ответа второй части со станции «Задания» (TaskReviewController).
     Условие, текст, ответ ученика, образец, критерии номера; балл по
     каждому заданию и комментарий. Корм ученику = балл. --}}
@extends('layouts.main')

@section('title', 'Проверка задания — Школа Полтавского')

@section('back_url', route('mentor.submissions.index'))

@php
  $passage = $tasks->pluck('passage_text')->filter()->first();
  $answers = $review->answers ?? [];
  $scores = $review->scores ?? [];
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h1 class="text-2xl font-semibold">
      Задание {{ $tasks->pluck('number')->map(fn ($n) => (int) $n)->implode('+') }}
      <span class="text-gray-500 font-normal">· {{ $numberTitles[(int) $tasks->first()->number] ?? '' }}</span>
    </h1>
    <div class="text-sm text-gray-600">
      {{ $review->user->name ?? 'Ученик #'.$review->user_id }} · отправлено {{ $review->sent_at?->format('d.m.Y H:i') }}
    </div>
  </div>

  @if($lockedByOther)
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2">Этот ответ сейчас проверяет другой куратор.</div>
  @endif
  @if($review->status === 'checked')
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-800 px-3 py-2">
      Уже проверено: {{ $review->score }} из {{ $review->max_score }}@if($review->checker) ({{ $review->checker->name }}, {{ $review->checked_at?->format('d.m.Y H:i') }})@endif. Можно поправить оценку — корм пересчитается.
    </div>
  @endif
  @if($review->self_score !== null)
    <div class="mb-4 text-sm text-gray-600">Самооценка ученика по чек-листу: <b>{{ $review->self_score }} из {{ $review->max_score }}</b></div>
  @endif

  @if($passage)
    <details class="mb-4 rounded-2xl border border-gray-200 bg-white p-4">
      <summary class="cursor-pointer font-semibold text-blue-700">Текст</summary>
      <div class="mt-3 whitespace-pre-line leading-relaxed">{{ $passage }}</div>
    </details>
  @endif

  <form method="POST" action="{{ route('mentor.task-reviews.update', $review) }}">
    @csrf
    @method('PUT')
    @foreach($tasks as $task)
      <section class="mb-4 rounded-2xl border border-gray-200 bg-white p-4">
        <div class="text-sm font-semibold text-blue-700 mb-1">№ {{ (int) $task->number }} · макс. {{ $task->max_score }}</div>
        <div class="whitespace-pre-line leading-relaxed">{{ $task->question_text }}</div>
        @if($task->image_path)
          <img src="{{ asset('storage/'.$task->image_path) }}" alt="" class="mt-3 max-w-full sm:max-w-md rounded-lg border border-gray-200">
        @endif

        <div class="grid gap-3 md:grid-cols-2 mt-4">
          <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Ответ ученика</div>
            <div class="whitespace-pre-line leading-relaxed">{{ $answers[(string) $task->id] ?? '—' }}</div>
          </div>
          <div class="rounded-xl border border-green-200 bg-green-50 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Образец</div>
            <div class="whitespace-pre-line leading-relaxed">{{ $task->answer }}</div>
          </div>
        </div>

        @if($criteria[$task->id] ?? null)
          <details class="mt-3">
            <summary class="cursor-pointer text-sm text-blue-700">Критерии номера</summary>
            <div class="mt-2 whitespace-pre-line text-sm text-gray-700 leading-relaxed">{{ $criteria[$task->id] }}</div>
          </details>
        @endif

        <label class="block mt-4 text-sm font-medium text-gray-700">Балл за задание {{ (int) $task->number }}</label>
        <select name="scores[{{ $task->id }}]" class="mt-1 rounded-lg border border-gray-300 px-3 py-2" @disabled($lockedByOther)>
          @for($s = 0; $s <= $task->max_score; $s++)
            <option value="{{ $s }}" @selected((int) ($scores[(string) $task->id] ?? -1) === $s)>{{ $s }}</option>
          @endfor
        </select>
      </section>
    @endforeach

    @error('scores')<div class="mb-3 text-red-600 text-sm">{{ $message }}</div>@enderror
    <label class="block text-sm font-medium text-gray-700">Комментарий ученику</label>
    <textarea name="comment" rows="5" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" placeholder="Что засчитано, что нет и почему" @disabled($lockedByOther)>{{ old('comment', $review->comment) }}</textarea>

    <div class="flex flex-wrap gap-3 mt-4">
      <button type="submit" class="rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5" @disabled($lockedByOther)>Сохранить оценку</button>
    </div>
  </form>
  @if(! $lockedByOther && $review->status === 'sent')
    <form method="POST" action="{{ route('mentor.task-reviews.unlock', $review) }}" class="mt-3">
      @csrf
      <button type="submit" class="text-sm text-gray-500 underline">Вернуть в очередь, не проверяя</button>
    </form>
  @endif
</div>
@endsection
