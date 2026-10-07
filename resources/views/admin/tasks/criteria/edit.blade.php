@extends('admin.layouts.main')

@section('title', 'Критерии — задание #'.$task->id)

@section('content')
<div class="max-w-3xl">
  <x-ui.page-header :title="'Задание #'.$task->id.' — критерии проверки'" :back="route('admin.tasks.show', $task)" back-label="К заданию">
    <x-slot:actions>
      <x-ui.button href="{{ route('admin.tasks.edit', $task) }}" variant="secondary" size="xs">Содержание задания</x-ui.button>
    </x-slot:actions>
  </x-ui.page-header>

  <x-ui.alert class="mb-6">
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
  </x-ui.alert>

  <form method="post" action="{{ route('admin.tasks.criteria.update', $task) }}">
    @csrf @method('PUT')

    <x-ui.card class="space-y-4">
      <x-ui.input type="number" name="max_score" label="Баллы за задание" min="1" step="1"
                  value="{{ old('max_score', $criteria->max_score ?? 1) }}" class="w-32"
                  hint="Общие для всех заданий с этим номером — указывать баллы у каждого отдельного задания не нужно." />

      <x-ui.textarea name="criteria" label="Критерии" rows="8">{{ old('criteria', $criteria->criteria) }}</x-ui.textarea>

      <x-ui.textarea name="ai_rationale_template" label="AI-шаблон «Обоснование баллов»" note="опционально" rows="4">{{ old('ai_rationale_template', $criteria->ai_rationale_template) }}</x-ui.textarea>

      <x-ui.textarea name="comment" label="Комментарий" rows="4"
                     placeholder="На что обратить внимание при проверке; студент с ручным типом задания увидит этот текст сразу при самопроверке">{{ old('comment', $criteria->comment) }}</x-ui.textarea>
    </x-ui.card>

    <x-ui.card class="mt-4">
      <x-ui.textarea name="criteria_override" label="Уникальные критерии только для этого задания" note="редкое исключение — обычно не нужно" rows="4"
                     hint="Заполните, только если у именно этого вопроса критерии отличаются от общих для номера выше. Если поле пустое — используются общие критерии.">{{ old('criteria_override', $task->criteria_override) }}</x-ui.textarea>
    </x-ui.card>

    <x-ui.form-actions>
      <x-ui.button type="submit" size="xs">Сохранить</x-ui.button>
      <x-ui.button href="{{ route('admin.tasks.show', $task) }}" variant="ghost" size="xs">Отмена</x-ui.button>
    </x-ui.form-actions>
  </form>
</div>
@endsection
