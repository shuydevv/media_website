@extends('admin.layouts.main')

@section('title', 'Импорт заданий')

@section('content')
<div class="max-w-3xl">
  <x-ui.page-header title="Импорт заданий в банк" :back="route('admin.tasks.index')" back-label="Банк заданий" />

  <x-ui.card tone="gray" class="mb-6 text-sm text-zinc-600 space-y-2 break-words">
    <p>
      JSON-файл: один объект задания или массив объектов. Поля — те же, что в форме
      создания: <code>category_id</code> (или <code>category</code> — название категории),
      <code>number</code>, <code>type</code>, <code>question_text</code>, <code>options</code> (массив строк),
      <code>matches</code> (<code>{"left":[...],"right":[...]}</code>), <code>table_content</code> (объект
      <code>{"cols":[...],"rows":[[...]],"blanks":[...]}</code>), <code>image_auto_options</code>,
      <code>answer</code>, <code>hint</code>, <code>is_public</code>, опционально
      <code>"image_url": "https://..."</code> — картинка будет скачана и сохранена автоматически.
      Баллы за задание в JSON не указываются — они общие для номера, настройте их на странице критериев после импорта.
    </p>
    <p>
      Необязательный <code>"id"</code> в объекте задания — если указать id существующего задания банка,
      оно не задублируется, а обновится. Без <code>id</code> всегда создаётся новое задание.
      <a href="{{ route('admin.tasks.import.example') }}" class="text-apple-blue-700 hover:underline whitespace-nowrap">Скачать пример файла →</a>
    </p>
  </x-ui.card>

  <form method="post" action="{{ route('admin.tasks.import.store') }}" enctype="multipart/form-data">
    @csrf
    <x-ui.input type="file" name="file" label="JSON-файл" accept=".json,application/json" required />
    <x-ui.form-actions>
      <x-ui.button type="submit" size="xs">Загрузить</x-ui.button>
      <x-ui.button href="{{ route('admin.tasks.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
    </x-ui.form-actions>
  </form>

  @isset($results)
    <div class="mt-8">
      <h2 class="sans-medium text-lg text-zinc-900 mb-3">Результат: {{ $created }} из {{ $total }} сохранено</h2>
      <div class="space-y-2">
        @foreach($results as $r)
          @if($r['ok'])
            <x-ui.alert tone="green">
              Строка {{ $r['index'] + 1 }}@if($r['label']) («{{ $r['label'] }}»)@endif: {{ $r['note'] }} —
              <a href="{{ route('admin.tasks.show', $r['task_id']) }}" class="underline">задание #{{ $r['task_id'] }}</a>
            </x-ui.alert>
          @else
            <x-ui.alert tone="red">
              Строка {{ $r['index'] + 1 }}@if($r['label']) («{{ $r['label'] }}»)@endif: {{ $r['message'] }}
            </x-ui.alert>
          @endif
        @endforeach
      </div>
    </div>
  @endisset
</div>
@endsection
