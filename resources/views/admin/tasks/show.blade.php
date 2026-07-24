@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <div class="flex items-start justify-between gap-3">
    <h1 class="text-2xl font-semibold">Задание #{{ $task->id }}</h1>
    <div class="flex items-center gap-2 text-sm shrink-0">
      <a href="{{ route('admin.tasks.edit', $task) }}" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50">Содержание</a>
      <a href="{{ route('admin.tasks.criteria.edit', $task) }}" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50">Критерии</a>
      <form method="post" action="{{ route('admin.tasks.duplicate', $task) }}">
        @csrf
        <button type="submit" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50">Дублировать</button>
      </form>
    </div>
  </div>

  @foreach (['success'=>'green','error'=>'red'] as $k=>$c)
    @if(session($k))
      <div class="mt-3 rounded-xl border border-{{ $c }}-200 bg-{{ $c }}-50 text-{{ $c }}-800 px-3 py-2">
        {{ session($k) }}
      </div>
    @endif
  @endforeach

  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
    <div class="rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600">Категория</div>
      <div class="font-medium">{{ $task->category?->title ?? '—' }}</div>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600">Номер / Тип</div>
      <div class="font-medium">{{ $task->number ?? '—' }} · {{ $task->type ?? '—' }}</div>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600 flex items-center gap-2">
        Баллы
        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">общие для № {{ $task->number ?? '—' }}</span>
      </div>
      <div class="font-medium">{{ $task->max_score }} — <a href="{{ route('admin.tasks.criteria.edit', $task) }}" class="text-blue-600 hover:underline text-sm font-normal">изменить</a></div>
    </div>
    <div class="rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600">Публично на сайте</div>
      <div class="font-medium">{{ $task->is_public ? 'Да' : 'Нет' }}</div>
    </div>
  </div>

  @if($task->type)
    <div class="mt-4">
      <div class="text-sm text-gray-600 mb-2">Как увидит студент:</div>
      <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
        @include('student.submissions.partials.task-prompt', ['task' => $task])
      </div>
    </div>
  @endif

  @php
    $criteriaRecord = $task->resolvedCriteriaRecord();
  @endphp
  <div class="mt-4 rounded-xl border bg-white p-4">
    <div class="text-sm text-gray-600 mb-1 flex items-center gap-2">
      Критерии
      @if($task->criteria_override)
        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">уникальные для этого задания</span>
      @elseif($criteriaRecord)
        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">общие для № {{ $task->number ?? '—' }}</span>
      @endif
    </div>
    @if($task->resolved_criteria)
      <pre class="text-sm bg-gray-50 p-3 rounded whitespace-pre-wrap">{{ $task->resolved_criteria }}</pre>
    @else
      <div class="text-sm text-gray-400">Не заполнены — <a href="{{ route('admin.tasks.criteria.edit', $task) }}" class="text-blue-600 hover:underline">заполнить</a></div>
    @endif
  </div>

  @if($criteriaRecord?->comment)
    <div class="mt-4 rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600 mb-1">Комментарий</div>
      <div class="whitespace-pre-wrap text-sm">{{ $criteriaRecord->comment }}</div>
    </div>
  @endif
</div>
@endsection
