@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-1">
    <h1 class="text-2xl font-semibold">Задание #{{ $task->id }} — содержание</h1>
    <a href="{{ route('admin.tasks.criteria.edit', $task) }}" class="text-sm text-blue-600 hover:underline">Критерии проверки →</a>
  </div>
  <p class="text-sm text-gray-500 mb-6">Критерии проверки и баллы за задание редактируются на отдельной странице — общие для всех заданий с этим номером.</p>

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('admin.tasks.update', $task) }}" enctype="multipart/form-data" class="space-y-4">
    @csrf @method('PUT')
    @include('admin.tasks.partials.bank-fields', ['task' => $task])
    <x-task-content-fields name="" :task="$task" :number-options="$numberOptions" />

    <div>
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $task->is_public))>
        Публиковать на сайте (доступно на публичной странице банка заданий)
      </label>
    </div>

    <div class="flex items-center gap-2 pt-2">
      <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Сохранить</button>
      <a href="{{ route('admin.tasks.show', $task) }}" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Отмена</a>
    </div>
  </form>
</div>

@include('admin.tasks.partials.task-editor-script')
@endsection
