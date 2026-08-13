{{-- resources/views/student/tasks/index.blade.php
     Банк заданий для самостоятельного прорешивания — те же задания, что
     переиспользуются в домашках (App\Models\Task). --}}
@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
  <h1 class="sans-medium text-xl md:text-3xl mb-4 sm:mb-6 text-zinc-900">Банк заданий</h1>

  <form method="get" class="flex flex-wrap gap-2 mb-6">
    <select name="category_id" class="border rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
      <option value="">Все категории</option>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? null) == $cat->id)>{{ $cat->title }}</option>
      @endforeach
    </select>
    <input type="text" name="number" value="{{ $filters['number'] ?? '' }}" placeholder="Номер задания"
           class="border rounded-lg px-3 py-2 text-sm">
    <button class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">Найти</button>
  </form>

  @if($tasks->isEmpty())
    <x-ui.card class="text-zinc-600 text-center">Заданий пока нет.</x-ui.card>
  @else
    <div class="flex flex-col gap-3">
      @foreach($tasks as $task)
        @php
          $status = $attemptedTaskIds[$task->id] ?? null;
          $badge = match(true) {
            $status === 'ok'      => ['Верно выполнено', 'bg-apple-green-100 text-apple-green-700'],
            $status === 'partial' => ['Частично верно', 'bg-apple-orange-100 text-apple-orange-700'],
            $status === 'fail'    => ['Не получилось', 'bg-apple-red-50 text-apple-red-650'],
            $attemptedTaskIds->has($task->id) => ['Пройдено', 'bg-apple-blue-50 text-apple-blue-700'],
            default => [null, null],
          };
        @endphp
        <x-ui.card-link href="{{ route('student.tasks.show', $task) }}">
          <div class="min-w-0 w-full">
            <div class="flex items-center justify-between gap-2 mb-1">
              <div class="text-xs text-zinc-400 uppercase tracking-wide truncate">
                {{ $task->category?->title ?? 'Без категории' }} @if($task->number) · № {{ $task->number }} @endif
              </div>
              @if($badge[0])
                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
              @endif
            </div>
            <div class="text-zinc-900 truncate">{{ \Illuminate\Support\Str::limit(strip_tags((string) $task->question_text), 90) ?: 'Без текста' }}</div>
          </div>
        </x-ui.card-link>
      @endforeach
    </div>

    <div class="mt-6">{{ $tasks->links() }}</div>
  @endif
</div>
@endsection
