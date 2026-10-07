@extends('admin.layouts.main')

@section('title', 'Новое задание')

@section('content')
<div class="max-w-3xl">
  <x-ui.page-header title="Новое задание в банке" :back="route('admin.tasks.index')" back-label="Банк заданий">
    Критерии проверки и баллы за задание заполняются отдельно, после сохранения — общие для всех заданий с этим номером.
  </x-ui.page-header>

  <form method="post" action="{{ route('admin.tasks.store') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    @include('admin.tasks.partials.bank-fields', ['task' => null])
    <x-task-content-fields name="" :task="null" :number-options="$numberOptions" />

    <x-ui.checkbox name="is_public" :checked="(bool) old('is_public', false)">
      Публиковать на сайте (доступно на публичной странице банка заданий)
    </x-ui.checkbox>

    <x-ui.form-actions>
      <x-ui.button type="submit" size="xs">Сохранить</x-ui.button>
      <x-ui.button href="{{ route('admin.tasks.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
    </x-ui.form-actions>
  </form>
</div>

@include('admin.tasks.partials.task-editor-script')
@endsection
