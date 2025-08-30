@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <div class="flex items-start justify-between">
    <h1 class="text-2xl font-semibold">Запись #{{ $task->id }}</h1>
    <div class="text-sm">
      <a href="{{ route('admin.tasks.edit', $task) }}" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50">Редактировать</a>
    </div>
  </div>

  @foreach (['success'=>'green','error'=>'red'] as $k=>$c)
    @if(session($k))
      <div class="mt-3 rounded-xl border border-{{ $c }}-200 bg-{{ $c }}-50 text-{{ $c }}-800 px-3 py-2">
        {{ session($k) }}
      </div>
    @endif
  @endforeach

  <div class="mt-4 grid gap-3">
    <div class="rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600">Категория</div>
      <div class="font-medium">{{ $task->category?->title ?? '—' }}</div>
    </div>

    <div class="rounded-xl border bg-white p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <div class="text-sm text-gray-600">Номер</div>
        <div class="font-medium">{{ $task->number ?? '—' }}</div>
      </div>
    </div>

    <div class="rounded-xl border bg-white p-4">
      <div class="text-sm text-gray-600 mb-1">Критерии</div>
      <pre class="text-sm bg-gray-50 p-3 rounded">{{ json_encode($task->criteria, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
    </div>

    @if($task->ai_rationale_template)
      <div class="rounded-xl border bg-white p-4">
        <div class="text-sm text-gray-600 mb-1">AI-шаблон «Обоснование баллов»</div>
        <div class="whitespace-pre-wrap text-sm">{{ $task->ai_rationale_template }}</div>
      </div>
    @endif

    @if($task->comment)
      <div class="rounded-xl border bg-white p-4">
        <div class="text-sm text-gray-600 mb-1">Комментарий (рекомендации)</div>
        <div class="whitespace-pre-wrap text-sm">{{ $task->comment }}</div>
      </div>
    @endif
  </div>
</div>
@endsection
