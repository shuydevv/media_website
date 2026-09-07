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
            @if(!empty($options) && in_array($task->type, ['test','image_auto']))
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

    {{-- Ученики, которые записались на курс ПОСЛЕ урока этой домашки — по
         умолчанию не видят её и не могут открыть даже по прямой ссылке
         (см. Homework::isLessonBeforeEnrollment()). Здесь можно точечно
         открыть/закрыть доступ для конкретного ученика. --}}
    @if($lateEnrollmentRows->isNotEmpty())
        <h2 class="text-lg font-semibold mb-4 mt-8">Доступ для записавшихся позже</h2>
        <p class="text-sm text-gray-500 mb-4">
            Урок этой домашки прошёл до того, как эти ученики записались на курс — обычная логика
            прячет от них домашку. Здесь можно точечно открыть доступ (или закрыть обратно).
        </p>

        @if(session('success'))
            <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 text-red-600 text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <table class="w-full table-auto border text-sm mb-6">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2 border text-left">Ученик</th>
                    <th class="p-2 border text-left">Записался</th>
                    <th class="p-2 border text-left">Статус</th>
                    <th class="p-2 border">Действие</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lateEnrollmentRows as $row)
                    <tr>
                        <td class="p-2 border">{{ $row['student']->name }} ({{ $row['student']->email }})</td>
                        <td class="p-2 border">{{ $row['enrolledAt']?->format('d.m.Y') ?? '—' }}</td>
                        <td class="p-2 border">
                            @if($row['unlocked'])
                                <span class="text-green-700">Открыта админом</span>
                            @else
                                <span class="text-red-600">Заблокирована</span>
                            @endif
                        </td>
                        <td class="p-2 border text-center">
                            @if($row['unlocked'])
                                <form method="post" action="{{ route('admin.homeworks.unlocks.destroy', [$homework, $row['student']]) }}"
                                      onsubmit="return confirm('Закрыть доступ обратно для {{ $row['student']->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Закрыть доступ</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('admin.homeworks.unlocks.store', $homework) }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $row['student']->id }}">
                                    <button type="submit" class="text-blue-600 hover:underline">Открыть доступ</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="mt-6 flex items-center gap-3">
    <a href="{{ route('admin.homeworks.edit', $homework) }}"
       class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Редактировать
    </a>
    <form method="post" action="{{ route('admin.homeworks.duplicate', $homework) }}">
        @csrf
        <button type="submit" class="px-4 py-2 rounded border hover:bg-gray-50">Дублировать</button>
    </form>
</div>

</div>
@endsection
