@extends('admin.layouts.main')

@section('title', 'Задание #'.$task->id)

@section('content')
<div class="max-w-3xl">
  <x-ui.page-header :title="'Задание #'.$task->id" :back="route('admin.tasks.index')" back-label="Банк заданий">
    <x-slot:actions>
      <x-ui.button href="{{ route('admin.tasks.edit', $task) }}" size="xs">Содержание</x-ui.button>
      <x-ui.button href="{{ route('admin.tasks.criteria.edit', $task) }}" variant="secondary" size="xs">Критерии</x-ui.button>
      <x-ui.action-form :action="route('admin.tasks.duplicate', $task)" method="POST" variant="secondary">Дублировать</x-ui.action-form>
    </x-slot:actions>
  </x-ui.page-header>

  <x-ui.card>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <x-ui.stat label="Категория" size="md">{{ $task->category?->title ?? '—' }}</x-ui.stat>
      <x-ui.stat label="Номер / тип" size="md">{{ $task->number ?? '—' }} · {{ $task->type ?? '—' }}</x-ui.stat>
      <x-ui.stat label="Баллы" size="md">
        {{ $task->max_score }}
        <x-slot:sub>
          общие для № {{ $task->number ?? '—' }} ·
          <a href="{{ route('admin.tasks.criteria.edit', $task) }}" class="text-apple-blue-700 hover:underline">изменить</a>
        </x-slot:sub>
      </x-ui.stat>
      <x-ui.stat label="Публично на сайте" size="md">{{ $task->is_public ? 'Да' : 'Нет' }}</x-ui.stat>
    </div>
  </x-ui.card>

  @if($task->type)
    <div class="mt-6">
      <h2 class="sans-medium text-lg text-zinc-900 mb-2">Как увидит студент</h2>
      <x-ui.card class="sm:p-6">
        @include('student.submissions.partials.task-prompt', ['task' => $task])
      </x-ui.card>
    </div>
  @endif

  @php
    $criteriaRecord = $task->resolvedCriteriaRecord();
  @endphp
  <x-ui.card class="mt-6">
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <h2 class="sans-medium text-lg text-zinc-900">Критерии</h2>
      @if($task->criteria_override)
        <x-ui.badge tone="orange">уникальные для этого задания</x-ui.badge>
      @elseif($criteriaRecord)
        <x-ui.badge>общие для № {{ $task->number ?? '—' }}</x-ui.badge>
      @endif
    </div>
    @if($task->resolved_criteria)
      <pre class="sans text-sm text-zinc-700 bg-zinc-50 p-3 rounded-lg whitespace-pre-wrap break-words">{{ $task->resolved_criteria }}</pre>
    @else
      <div class="text-sm text-zinc-500">Не заполнены — <a href="{{ route('admin.tasks.criteria.edit', $task) }}" class="text-apple-blue-700 hover:underline">заполнить</a></div>
    @endif
  </x-ui.card>

  @if($criteriaRecord?->comment)
    <x-ui.card class="mt-4">
      <h2 class="sans-medium text-lg text-zinc-900 mb-2">Комментарий</h2>
      <div class="whitespace-pre-wrap text-sm text-zinc-700">{{ $criteriaRecord->comment }}</div>
    </x-ui.card>
  @endif
</div>
@endsection
