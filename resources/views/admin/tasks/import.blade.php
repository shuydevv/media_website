@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-1">Импорт заданий в банк</h1>
  <p class="text-sm text-gray-500 mb-2">
    JSON-файл: один объект задания или массив объектов. Поля — те же, что в форме
    создания: <code>category_id</code> (или <code>category</code> — название категории),
    <code>number</code>, <code>type</code>, <code>question_text</code>, <code>options</code> (массив строк),
    <code>matches</code> (<code>{"left":[...],"right":[...]}</code>), <code>table_content</code> (объект
    <code>{"cols":[...],"rows":[[...]],"blanks":[...]}</code>), <code>image_auto_options</code>,
    <code>answer</code>, <code>hint</code>, <code>is_public</code>, опционально
    <code>"image_url": "https://..."</code> — картинка будет скачана и сохранена автоматически.
    Баллы за задание в JSON не указываются — они общие для номера, настройте их на странице критериев после импорта.
  </p>
  <p class="text-sm text-gray-500 mb-6">
    Необязательный <code>"id"</code> в объекте задания — если указать id существующего задания банка,
    оно не задублируется, а обновится. Без <code>id</code> всегда создаётся новое задание.
    <a href="{{ route('admin.tasks.import.example') }}" class="text-blue-600 hover:underline">Скачать пример файла →</a>
  </p>

  @if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('admin.tasks.import.store') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <div>
      <label class="block text-sm font-medium mb-1">JSON-файл</label>
      <input type="file" name="file" accept=".json,application/json" class="w-full border rounded-lg px-3 py-2" required>
    </div>
    <div class="flex items-center gap-2 pt-2">
      <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Загрузить</button>
      <a href="{{ route('admin.tasks.index') }}" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Отмена</a>
    </div>
  </form>

  @isset($results)
    <div class="mt-8">
      <h2 class="text-lg font-semibold mb-2">Результат: {{ $created }} из {{ $total }} сохранено</h2>
      <div class="space-y-2">
        @foreach($results as $r)
          @if($r['ok'])
            <div class="text-sm px-3 py-2 rounded-lg bg-green-50 border border-green-200 text-green-800">
              Строка {{ $r['index'] + 1 }}@if($r['label']) («{{ $r['label'] }}»)@endif: {{ $r['note'] }} —
              <a href="{{ route('admin.tasks.show', $r['task_id']) }}" class="underline">задание #{{ $r['task_id'] }}</a>
            </div>
          @else
            <div class="text-sm px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-800">
              Строка {{ $r['index'] + 1 }}@if($r['label']) («{{ $r['label'] }}»)@endif: {{ $r['message'] }}
            </div>
          @endif
        @endforeach
      </div>
    </div>
  @endisset
</div>
@endsection
