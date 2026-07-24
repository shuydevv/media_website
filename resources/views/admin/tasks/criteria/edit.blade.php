@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-1">
    <h1 class="text-2xl font-semibold">Задание #{{ $task->id }} — критерии проверки</h1>
    <a href="{{ route('admin.tasks.edit', $task) }}" class="text-sm text-blue-600 hover:underline">← Содержание задания</a>
  </div>

  <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 text-blue-900 text-sm px-3 py-2">
    Эти критерии общие для
    @if($task->number)
      всех заданий <strong>№ {{ $task->number }}</strong>
    @else
      всех заданий без номера
    @endif
    в категории <strong>{{ $task->category->title ?? '—' }}</strong>.
    @if($siblingCount > 1)
      Сейчас таких заданий в банке: {{ $siblingCount }} — правка ниже применится сразу ко всем.
    @endif
  </div>

  @if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-800 px-3 py-2">{{ session('success') }}</div>
  @endif

  <form method="post" action="{{ route('admin.tasks.criteria.update', $task) }}" class="space-y-4">
    @csrf @method('PUT')

    <div>
      <label class="block text-sm font-medium mb-1">Баллы за задание</label>
      <p class="text-xs text-gray-500 mb-2">Общие для всех заданий с этим номером — указывать баллы у каждого отдельного задания не нужно.</p>
      <input type="number" name="max_score" min="1" step="1" value="{{ old('max_score', $criteria->max_score ?? 1) }}" class="w-32 border rounded-lg px-3 py-2">
      @error('max_score')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Критерии</label>
      <textarea name="criteria" rows="8" class="w-full border rounded-lg px-3 py-2">{{ old('criteria', $criteria->criteria) }}</textarea>
      @error('criteria')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">AI-шаблон «Обоснование баллов» (опционально)</label>
      <textarea name="ai_rationale_template" rows="4" class="w-full border rounded-lg px-3 py-2">{{ old('ai_rationale_template', $criteria->ai_rationale_template) }}</textarea>
      @error('ai_rationale_template')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Комментарий</label>
      <textarea name="comment" rows="4" class="w-full border rounded-lg px-3 py-2" placeholder="На что обратить внимание при проверке; студент с ручным типом задания увидит этот текст сразу при самопроверке">{{ old('comment', $criteria->comment) }}</textarea>
      @error('comment')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <hr class="my-6">

    <div>
      <label class="block text-sm font-medium mb-1">Уникальные критерии только для этого задания <span class="text-gray-400 font-normal">(редкое исключение — обычно не нужно)</span></label>
      <p class="text-xs text-gray-500 mb-2">Заполните, только если у именно этого вопроса критерии отличаются от общих для номера выше. Если поле пустое — используются общие критерии.</p>
      <textarea name="criteria_override" rows="4" class="w-full border rounded-lg px-3 py-2">{{ old('criteria_override', $task->criteria_override) }}</textarea>
      @error('criteria_override')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div class="flex items-center gap-2">
      <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Сохранить</button>
      <a href="{{ route('admin.tasks.show', $task) }}" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Отмена</a>
    </div>
  </form>
</div>
@endsection
