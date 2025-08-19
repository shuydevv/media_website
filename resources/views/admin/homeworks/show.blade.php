@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">{{ $homework->title }}</h1>

    @if($homework->description)
        <div class="mb-6 text-gray-700">
            {{ $homework->description }}
        </div>
    @endif

    <div class="mb-4 text-sm text-gray-500">
        Курс: {{ $homework->course->title ?? '—' }}<br>
        Урок: {{ $homework->lesson->title ?? '—' }}<br>
        Тип: {{ $homework->type === 'mock' ? 'Пробник' : 'Обычное ДЗ' }}
    </div>

    <h2 class="text-lg font-semibold mb-4">Список заданий</h2>

    @forelse($homework->tasks as $index => $task)
        <div class="border rounded p-4 mb-6 bg-gray-50">
            <div class="mb-2 font-semibold">
                №{{ $index + 1 }} — {{ ucfirst($task->type) }}
            </div>

            @if($task->question_text)
                <div class="mb-3">
                    <strong>Вопрос / текст:</strong><br>
                    {!! nl2br(e($task->question_text)) !!}
                </div>
            @endif

            {{-- Варианты ответа --}}
            @php
                $options = $task->options ?? [];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    $options = is_array($decoded) ? $decoded : [];
                }
            @endphp
            @if(!empty($options) && in_array($task->type, ['multiple_choice','image_auto']))
                <div class="mb-3">
                    <strong>Варианты ответа:</strong>
                    <ul class="list-disc pl-5">
                        @foreach($options as $opt)
                            <li>{{ $opt }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Соотнесение --}}
            @php
                $matchesRaw = $task->matches ?? [];
                if (is_string($matchesRaw)) {
                    $decoded = json_decode($matchesRaw, true);
                    $matchesRaw = is_array($decoded) ? $decoded : [];
                }
                $left  = isset($matchesRaw['left'])  && is_array($matchesRaw['left'])  ? $matchesRaw['left']  : [];
                $right = isset($matchesRaw['right']) && is_array($matchesRaw['right']) ? $matchesRaw['right'] : [];
            @endphp
            @if($task->type === 'matching')
                <div class="mb-3">
                    <strong>Соотнесение:</strong>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <div>
                            <div class="font-medium">Левая колонка</div>
                            <ul class="list-disc pl-5">
                                @foreach($left as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div>
                            <div class="font-medium">Правая колонка</div>
                            <ul class="list-disc pl-5">
                                @foreach($right as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Таблица --}}
            @php
                $tableData = $task->table ?? [];
                if (is_string($tableData)) {
                    $decoded = json_decode($tableData, true);
                    $tableData = is_array($decoded) ? $decoded : [];
                }
            @endphp
            @if($task->type === 'table' && !empty($tableData))
                <div class="mb-3">
                    <strong>Таблица:</strong>
                    <table class="border-collapse border border-gray-300 mt-2">
                        @foreach(array_chunk($tableData, 3) as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td class="border border-gray-300 px-2 py-1">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif

            {{-- Изображение --}}
            @if($task->image_path)
                <div class="mb-3">
                    <strong>Изображение:</strong><br>
                    <img src="{{ asset('storage/'.$task->image_path) }}" alt="task image" class="max-w-xs mt-2 border">
                </div>
            @endif

            {{-- Правильный ответ --}}
            @if($task->answer)
                <div class="mt-2 text-green-700">
                    <strong>Правильный ответ:</strong> {{ $task->answer }}
                </div>
            @endif
        </div>
    @empty
        <div class="text-gray-500">Заданий нет</div>
    @endforelse
    <div class="mt-6">
    <a href="{{ route('admin.homeworks.edit', $homework) }}"
       class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Редактировать
    </a>
</div>

</div>
@endsection
