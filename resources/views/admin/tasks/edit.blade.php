@extends('admin.layouts.main')

@section('title', 'Задание #'.$task->id)

@section('content')
<div class="max-w-3xl">
  <x-ui.page-header :title="'Задание #'.$task->id.' — содержание'" :back="route('admin.tasks.show', $task)" back-label="К заданию">
    Критерии проверки и баллы за задание редактируются на отдельной странице — общие для всех заданий с этим номером.
    <x-slot:actions>
      <x-ui.button href="{{ route('admin.tasks.criteria.edit', $task) }}" variant="secondary" size="xs">Критерии проверки</x-ui.button>
    </x-slot:actions>
  </x-ui.page-header>

  <form method="post" action="{{ route('admin.tasks.update', $task) }}" enctype="multipart/form-data" class="space-y-4">
    @csrf @method('PUT')
    @include('admin.tasks.partials.bank-fields', ['task' => $task])
    <x-task-content-fields name="" :task="$task" :number-options="$numberOptions" />

    <x-ui.checkbox name="is_public" :checked="(bool) old('is_public', $task->is_public)">
      Публиковать на сайте (доступно на публичной странице банка заданий)
    </x-ui.checkbox>

    <x-ui.form-actions>
      <x-ui.button type="submit" size="xs">Сохранить</x-ui.button>
      <x-ui.button href="{{ route('admin.tasks.show', $task) }}" variant="ghost" size="xs">Отмена</x-ui.button>
    </x-ui.form-actions>
  </form>
</div>

@include('admin.tasks.partials.task-editor-script')
@endsection
