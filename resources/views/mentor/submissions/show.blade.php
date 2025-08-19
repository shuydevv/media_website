{{-- resources/views/mentor/submissions/show.blade.php --}}
@extends('layouts.main')

@section('content')
@php
  /** @var \App\Models\Submission $submission */
  // подтянем недостающие связи
  $submission->loadMissing('user', 'homework.lesson.courseSession.course');

  $homework = $submission->homework;

  // коллекции задач
  $tasksRaw = $homework->tasks ?? [];
  $tasksCol = collect($tasksRaw)->map(fn($t) => is_array($t) ? (object)$t : $t);

  $manualTypes = ['written','image_written','image_manual'];
  $autoTasks   = $tasksCol->filter(fn($t) => !in_array($t->type ?? '', $manualTypes, true))->values();
  $manualTasks = $tasksCol->filter(fn($t) =>  in_array($t->type ?? '', $manualTypes, true))->values();

  $answers     = $submission->answers ?? [];
  $perTaskRes  = $submission->per_task_results ?? [];

  $getPerTask = function($taskId, $key, $default = null) use ($perTaskRes) {
      return $perTaskRes[$taskId][$key] ?? $default;
  };

  $maxOf = function($coll) {
      return (int) $coll->sum(fn($t) => (int)($t->max_score ?? 1));
  };

  $autoMax     = max(0, $maxOf($autoTasks));
  $manualMax   = max(0, $maxOf($manualTasks));
  $autoScore   = (int)($submission->autocheck_score ?? 0);
  $manualScore = (int)($submission->manual_score ?? 0);
  $totalMax    = $autoMax + $manualMax;
  $totalScore  = (int)($submission->total_score ?? ($autoScore + $manualScore));

  // утилита url для медиа (если где-то нужны картинки в заданиях)
  $storageUrl = function ($path) {
    if (!$path) return null;
    $isFull = \Illuminate\Support\Str::startsWith($path, ['http://','https://','/storage/','data:']);
    return $isFull ? $path : \Illuminate\Support\Facades\Storage::url($path);
  };
@endphp

<div class="max-w-6xl mx-auto px-4 py-6">
  {{-- Хедер --}}
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold">Проверка попытки</h1>
      <div class="text-gray-500 mt-1 space-y-0.5">
        <div>Домашка: <span class="font-medium text-gray-700">{{ $homework->title }}</span></div>
        <div>Ученик: <span class="font-medium text-gray-700">{{ $submission->user?->name ?? '—' }}</span></div>
        <div>
          Попытка № {{ $submission->attempt_no ?? 1 }}
          · Статус: <span class="font-medium text-gray-700">{{ $submission->status ?? 'pending' }}</span>
        </div>
      </div>
    </div>

    <div class="shrink-0 space-x-2">
      <a href="{{ route('student.courses.show', $submission->homework?->lesson?->courseSession?->course) }}"
         class="text-blue-600 hover:underline">← К курсу</a>

      <form action="{{ route('mentor.submissions.finalize', $submission) }}" method="post" class="inline">
        @csrf
        <button class="inline-flex items-center px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
          Завершить проверку
        </button>
      </form>
    </div>
  </div>

  {{-- Сводка по баллам --}}
  <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-4">
      <div class="text-sm text-gray-500">Автопроверка</div>
      <div class="text-2xl font-semibold">{{ $autoScore }} / {{ $autoMax }}</div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-4">
      <div class="text-sm text-gray-500">Ручная проверка</div>
      <div class="text-2xl font-semibold">{{ $manualScore }} / {{ $manualMax }}</div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-4">
      <div class="text-sm text-gray-500">Итого</div>
      <div class="text-2xl font-semibold">{{ $totalScore }} / {{ $totalMax }}</div>
    </div>
  </div>

  {{-- СПОЙЛЕР: Первая часть (авто) --}}
  <details class="mb-6 rounded-2xl border border-gray-200 bg-white">
    <summary class="list-none cursor-pointer select-none px-5 py-4 flex items-center justify-between">
      <div class="font-medium">Первая часть (автопроверка)</div>
      <div class="text-sm text-gray-600">{{ $autoScore }} / {{ $autoMax }} баллов</div>
    </summary>

    <div class="px-5 pb-5 pt-0">
      @forelse($autoTasks as $i => $t)
        @php
          $tid     = $t->id ?? ("t_auto_$i");
          $max     = (int)($t->max_score ?? 1);
          $score   = (int)$getPerTask($tid,'score', 0);
          $ans     = (string)($answers[$tid] ?? '');
          $correct = (string)($t->answer ?? '');
        @endphp
        <div class="rounded-xl border border-gray-200 p-4 mb-3">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 text-gray-700 text-xs font-semibold">
                {{ $t->order ?? ($i+1) }}
              </span>
              <div class="text-sm font-medium">Задание (авто)</div>
            </div>
            <div class="text-sm font-semibold text-gray-700">{{ $score }} / {{ $max }}</div>
          </div>

          {{-- При желании можно показать условие/изображение, но оставим компактно --}}
          <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
              <div class="text-gray-500">Ответ ученика</div>
              <div class="font-mono text-base">{{ $ans !== '' ? e($ans) : '—' }}</div>
            </div>
            <div>
              <div class="text-gray-500">Правильный ответ</div>
              <div class="font-mono text-base">{{ $correct !== '' ? e($correct) : '—' }}</div>
            </div>
          </div>
        </div>
      @empty
        <div class="text-sm text-gray-500">Автопроверяемых заданий нет.</div>
      @endforelse
    </div>
  </details>

  {{-- ВТОРАЯ ЧАСТЬ: карточки на всю ширину --}}
  <div class="space-y-6">
    @forelse($manualTasks as $i => $t)
      @php
        $tid        = $t->id ?? ("t_manual_$i");
        $max        = (int)($t->max_score ?? 1);
        $question   = (string)($t->question_text ?? '');
        $passage    = (string)($t->passage_text ?? '');
        $mediaPath  = $t->media_path ?? $t->image_path ?? null;
        $mediaUrl   = $storageUrl($mediaPath);
        $studentAns = (string)($answers[$tid] ?? '');

        $savedScore   = (int)$getPerTask($tid,'score', 0);
        $savedComment = (string)($getPerTask($tid,'comment',''));
      @endphp

      <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center justify-between gap-3 mb-2">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold">
              {{ $t->order ?? ($i+1) }}
            </span>
            <h2 class="text-lg font-semibold">Задание (ручная проверка)</h2>
          </div>
          <div class="text-sm text-gray-500">Макс: {{ $max }}</div>
        </div>

        {{-- Текст-условие --}}
        @if($question)
          <div class="text-[15px] text-gray-800 whitespace-pre-wrap mb-4">{{ $question }}</div>
        @endif

        {{-- Пассаж (источник) --}}
        @if($passage)
          <div class="mb-4 p-4 rounded-xl bg-gray-50 border border-gray-200 leading-relaxed whitespace-pre-wrap">
            {{ $passage }}
          </div>
        @endif

        {{-- Картинка, если прикреплена к заданию --}}
        @if($mediaUrl)
          <div class="mb-4">
            <img src="{{ $mediaUrl }}" alt="" class="w-full max-h-[380px] object-contain rounded-xl border">
          </div>
        @endif

        {{-- Ответ ученика --}}
        <div class="mb-4">
          <div class="text-sm text-gray-600 mb-1">Ответ ученика</div>
          @if(in_array($t->type, ['image_written','image_manual']))
            <div class="font-mono text-base bg-white border rounded-lg p-3 break-all">
              {{ $studentAns !== '' ? $studentAns : '—' }}
            </div>
          @else
            <div class="whitespace-pre-wrap text-base bg-white border rounded-lg p-3">
              {{ $studentAns !== '' ? $studentAns : '—' }}
            </div>
          @endif
        </div>

        {{-- Форма оценивания --}}
        <form action="{{ route('mentor.submissions.scoreTask', [$submission, $tid]) }}" method="post" class="mt-2">
          @csrf
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm text-gray-700 mb-1">Баллы (0–{{ $max }})</label>
              <input type="number" name="score" min="0" max="{{ $max }}" value="{{ old('score', $savedScore) }}"
                     class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">Комментарий</label>
              <input type="text" name="comment" value="{{ old('comment', $savedComment) }}"
                     class="w-full border rounded-lg px-3 py-2" placeholder="Краткое замечание для ученика">
            </div>
          </div>

          <div class="mt-3 text-right">
            <button class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
              Сохранить
            </button>
          </div>
        </form>
      </div>
    @empty
      <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
        Заданий для ручной проверки нет.
      </div>
    @endforelse
  </div>

  {{-- Низ страницы --}}
  <div class="mt-6 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('student.courses.show', $submission->homework?->lesson?->courseSession?->course) }}"
       class="text-blue-600 hover:underline">
      ← Вернуться к курсу
    </a>
    <div class="text-sm text-gray-500">
      Отправлено: {{ optional($submission->created_at)->format('d.m.Y H:i') ?? '—' }}
      @if($submission->updated_at && $submission->updated_at != $submission->created_at)
        · Обновлено: {{ optional($submission->updated_at)->format('d.m.Y H:i') }}
      @endif
    </div>
  </div>
</div>
@endsection
