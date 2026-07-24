@extends('admin.layouts.main')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-1">Импорт домашки</h1>
    <p class="text-sm text-gray-500 mb-6">
        JSON-файл вида <code>{"title": "...", "course_id": N, "type": "homework", "due_at": "2026-09-01 12:00",
        "tasks": [...]}</code>. Каждый элемент <code>tasks</code> — либо <code>{"task_id": N}</code>
        (взять существующее задание из банка), либо полный объект содержания (те же поля, что при импорте
        в банк: <code>type</code>, <code>question_text</code>, <code>options</code>, <code>matches</code>,
        <code>table_content</code>, <code>image_auto_options</code>, <code>answer</code>, <code>hint</code>,
        <code>max_score</code>, опционально <code>"save_to_bank": true</code>). Изображения не переносятся.
    </p>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.homeworks.import.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">JSON-файл</label>
            <input type="file" name="file" accept=".json,application/json" class="w-full border rounded px-3 py-2" required>
        </div>
        <div class="flex items-center gap-2 pt-2">
            <button class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Загрузить</button>
            <a href="{{ route('admin.homeworks.index') }}" class="px-4 py-2 rounded border hover:bg-gray-50">Отмена</a>
        </div>
    </form>

    @isset($results)
        <div class="mt-8">
            <h2 class="text-lg font-semibold mb-2">
                Домашка «{{ $homework->title }}» создана.
                Заданий: {{ $created }} из {{ $total }} сохранено.
            </h2>
            <div class="space-y-2">
                @foreach($results as $r)
                    @if($r['ok'])
                        <div class="text-sm px-3 py-2 rounded bg-green-50 border border-green-200 text-green-800">
                            Строка {{ $r['index'] + 1 }}: сохранено ({{ $r['note'] }})
                        </div>
                    @else
                        <div class="text-sm px-3 py-2 rounded bg-red-50 border border-red-200 text-red-800">
                            Строка {{ $r['index'] + 1 }}: {{ $r['message'] }}
                        </div>
                    @endif
                @endforeach
            </div>
            <a href="{{ route('admin.homeworks.edit', $homework) }}" class="inline-block mt-4 text-blue-600 hover:underline">Открыть домашку в редакторе →</a>
        </div>
    @endisset
</div>
@endsection
