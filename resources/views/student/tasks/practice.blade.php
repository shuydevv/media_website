{{-- resources/views/student/tasks/practice.blade.php
     Самостоятельное прорешивание одного задания из банка — не многошаговый
     wizard, как в домашке (одно задание за раз, без "далее"). Содержание
     вопроса рендерит тот же partial, что и визард/результаты домашки. --}}
@extends('layouts.main')

@php
  $isManual = !$task->isAutoGradable();
  $hintText = \App\Support\Text::normalize($task->hint ?? null);
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-3 sm:px-4 py-5 sm:py-6">

  <div class="flex items-center justify-between gap-3 mb-4">
    <h1 class="sans-medium text-lg sm:text-xl text-zinc-900">Банк заданий</h1>
    <a href="{{ route('student.tasks.index') }}" class="text-sm text-zinc-500 hover:underline">← Ко всем заданиям</a>
  </div>

  <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
    <div class="flex items-center justify-between gap-3 mb-4 sm:mb-5">
      <div class="flex items-center gap-3">
        <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-zinc-100 border border-zinc-200 text-zinc-700">
          {{ $task->category?->title ?? 'Без категории' }} @if($task->number) · № {{ $task->number }} @endif
        </span>
      </div>

      @if($hintText)
        <button type="button" id="hint-toggle" class="text-xs sm:text-sm text-blue-600 hover:underline whitespace-nowrap">
          Показать подсказку
        </button>
      @endif
    </div>

    @if($hintText)
      <div id="hint-box" class="overflow-hidden mb-4" style="height:0;">
        <div class="p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-900 whitespace-pre-wrap">{{ $hintText }}</div>
      </div>
    @endif

    @include('student.submissions.partials.task-prompt', ['task' => $task])

    @if($isManual)
      {{-- Ручная проверка в самопроверке — сразу образцовый ответ и
           комментарий, без очереди куратору (это не домашка). --}}
      @if($revealed)
        <div class="mb-4 p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-sm text-emerald-800">
          <div class="font-medium mb-1">Образцовый ответ</div>
          <div class="whitespace-pre-wrap">{{ $task->answer ?: '—' }}</div>
        </div>
        @if($task->comment)
          <div class="mb-4 p-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-zinc-700">
            <div class="font-medium mb-1 text-zinc-900">Комментарий</div>
            <div class="whitespace-pre-wrap">{{ $task->comment }}</div>
          </div>
        @endif
      @else
        <form method="POST" action="{{ route('student.tasks.check', $task) }}">
          @csrf
          <label class="block text-xs sm:text-sm text-zinc-700 mb-2">Ваш ответ (необязательно — можно сразу посмотреть образцовый)</label>
          <textarea name="answer" rows="5" class="w-full border rounded-xl px-3 py-2 sm:py-3 text-sm sm:text-base">{{ $checkAnswer ?? ($lastAttempt->answer ?? '') }}</textarea>
          <button type="submit" class="mt-4 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
            Показать ответ
          </button>
        </form>
      @endif
    @else
      <form method="POST" action="{{ route('student.tasks.check', $task) }}">
        @csrf
        <label class="block text-xs sm:text-sm text-zinc-700 mb-2">Ваш ответ</label>
        <input type="text" name="answer" autocomplete="off"
               value="{{ $checkAnswer ?? ($lastAttempt->answer ?? '') }}"
               class="w-full border rounded-xl px-3 py-2 sm:py-3 text-sm sm:text-base font-mono tracking-widest"
               inputmode="{{ $task->type === 'image_auto' ? 'text' : 'numeric' }}">
        <button type="submit" class="mt-4 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
          Проверить ответ
        </button>
      </form>

      @if($result)
        @php
          $banner = [
            'ok'      => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'Верно!'],
            'partial' => ['bg-amber-50 border-amber-200 text-amber-800', 'Частично верно.'],
            'fail'    => ['bg-rose-50 border-rose-200 text-rose-800', 'Неверно.'],
          ][$result['status']] ?? ['bg-zinc-50 border-zinc-200 text-zinc-700', ''];
        @endphp
        <div class="mt-4 p-3 rounded-xl border text-sm {{ $banner[0] }}">
          {{ $banner[1] }} {{ $result['score'] }} / {{ $result['max'] }} баллов
        </div>
      @endif
    @endif
  </div>
</div>

@if($hintText)
<script>
(function () {
  const btn = document.getElementById('hint-toggle');
  const box = document.getElementById('hint-box');
  if (!btn || !box) return;
  let open = false;
  btn.addEventListener('click', () => {
    open = !open;
    btn.textContent = open ? 'Скрыть подсказку' : 'Показать подсказку';
    box.style.height = open ? box.scrollHeight + 'px' : '0';
  });
})();
</script>
@endif
@endsection
