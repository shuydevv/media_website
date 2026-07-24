@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-1">Новое задание в банке</h1>
  <p class="text-sm text-gray-500 mb-6">Критерии проверки и баллы за задание заполняются отдельно, после сохранения — общие для всех заданий с этим номером.</p>

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('admin.tasks.store') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    @include('admin.tasks.partials.bank-fields', ['task' => null])
    <x-task-content-fields name="" :task="null" :number-options="$numberOptions" />

    <div>
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_public" value="1" @checked(old('is_public', false))>
        Публиковать на сайте (доступно на публичной странице банка заданий)
      </label>
    </div>

    <div class="flex items-center gap-2 pt-2">
      <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Сохранить</button>
      <a href="{{ route('admin.tasks.index') }}" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Отмена</a>
    </div>
  </form>
</div>

@include('admin.tasks.partials.task-editor-script')
@endsection
