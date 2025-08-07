@extends('admin.layouts.main')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-md">
    <h1 class="text-2xl font-semibold mb-6">Домашняя работа: {{ $homework->title }}</h1>

    <div class="mb-6">
        <p><strong>Описание:</strong> {{ $homework->description ?? '—' }}</p>
        <p><strong>Тип:</strong> {{ $homework->type === 'mock' ? 'Пробник' : 'Обычная домашка' }}</p>
        <p><strong>Количество заданий:</strong> {{ $homework->tasks->count() }}</p>
    </div>

    <div class="flex items-center gap-4 mb-8">
        <a href="{{ route('admin.homeworks.edit', $homework->id) }}"
           class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Редактировать
        </a>

        <form action="{{ route('admin.homeworks.destroy', $homework->id) }}" method="POST"
              onsubmit="return confirm('Вы уверены, что хотите удалить эту домашнюю работу?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                Удалить
            </button>
        </form>
    </div>

    <h2 class="text-xl font-semibold mb-4">Задания</h2>

    @forelse($homework->tasks as $task)
        <div class="border rounded p-4 mb-4 bg-gray-50">
            <p><strong>Тип:</strong> {{ $task->type }}</p>
            <p><strong>Номер:</strong> {{ $task->task_number ?? '—' }} | <strong>Порядок:</strong> {{ $task->order ?? '—' }}</p>

            @if ($task->question_text)
                <p class="mt-2"><strong>Вопрос:</strong> {{ $task->question_text }}</p>
            @endif

            @if ($task->type === 'multiple_choice' && is_array($task->options))
                <p class="mt-2"><strong>Варианты ответа:</strong></p>
                <ul class="list-disc pl-6">
                    @foreach($task->options as $option)
                        <li>{{ $option }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($task->type === 'matching' && is_array($task->matches))
                <p class="mt-2"><strong>Соотнесение:</strong></p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium">Левая колонка:</p>
                        <ul class="list-disc pl-4">
                            @foreach($task->matches['left'] ?? [] as $left)
                                <li>{{ $left }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm font-medium">Правая колонка:</p>
                        <ul class="list-disc pl-4">
                            @foreach($task->matches['right'] ?? [] as $right)
                                <li>{{ $right }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if ($task->type === 'table' && is_array($task->table))
                <p class="mt-2"><strong>Таблица:</strong></p>
                <div class="grid grid-cols-3 gap-2 mt-2">
                    @foreach($task->table as $cell)
                        <div class="border p-2 bg-white text-sm rounded">{{ $cell }}</div>
                    @endforeach
                </div>
            @endif

            @if ($task->image_path)
                <p class="mt-2"><strong>Изображение:</strong></p>
                <img src="{{ asset('storage/' . $task->image_path) }}" alt="image" class="mt-2 w-48 rounded border">
            @endif

            <p class="mt-2"><strong>Ответ:</strong> {{ $task->answer }}</p>
        </div>
    @empty
        <p>Нет заданий.</p>
    @endforelse
</div>
@endsection
