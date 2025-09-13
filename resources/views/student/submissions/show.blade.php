{{-- resources/views/student/submissions/show.blade.php --}}
@extends('layouts.main')

@section('content')
@php
  /** @var \App\Models\Submission $submission */
  $submission->loadMissing('homework.lesson.courseSession.course');

  $homework = $submission->homework;

  // Приводим задачи к коллекции объектов
  $tasksRaw = $homework->tasks ?? [];
  $tasksCol = collect($tasksRaw)->map(fn($t) => is_array($t) ? (object)$t : $t);
    $storageUrl = function ($path) {
      if (!$path) return null;
      $isFull = \Illuminate\Support\Str::startsWith($path, ['http://','https://','/storage/','data:']);
      return $isFull ? $path : \Illuminate\Support\Facades\Storage::url($path);
  };

  

  // Типы, проверяемые вручную
  $manualTypes = ['written','image_written','image_manual'];

  $autoTasks   = $tasksCol->filter(fn($t) => !in_array($t->type ?? '', $manualTypes, true))->values();
  $manualTasks = $tasksCol->filter(fn($t) =>  in_array($t->type ?? '', $manualTypes, true))->values();

  $answers    = $submission->answers ?? [];
  $perTaskRes = $submission->per_task_results ?? [];

  $getPerTask = function($taskId, $key, $default = null) use ($perTaskRes) {
      return $taskId !== null ? ($perTaskRes[$taskId][$key] ?? $default) : $default;
  };

  $maxOf = function($coll) {
      return (int) $coll->sum(fn($t) => (int)($t->max_score ?? 1));
  };

  $autoMax   = max(0, $maxOf($autoTasks));
  $manualMax = max(0, $maxOf($manualTasks));

  $autoScore = !is_null($submission->autocheck_score)
                ? (int)$submission->autocheck_score
                : (int)$autoTasks->sum(function($t) use ($getPerTask) {
                    $tid = $t->id ?? null;
                    return (int)$getPerTask($tid, 'score', 0);
                  });

$manualScore = ($submission->status === 'checked' && !is_null($submission->manual_score))
  ? (int)$submission->manual_score
  : (int)$manualTasks->sum(function($t) use ($perTaskRes) {
      $tid = $t->id ?? null;
      if ($tid === null) return 0;

      $row = $perTaskRes[$tid] ?? [];
      $skipped   = (bool)($row['skipped'] ?? false);
      $hasScore  = array_key_exists('score', $row) && $row['score'] !== null;
      // $isChecked_task = $hasScore && !$skipped;

      // учитываем только реально выставленные баллы (ключ score есть и не null) и не пропущенные
      return (!$skipped && $hasScore) ? (int)$row['score'] : 0;
    });

  $totalMax   = $autoMax + $manualMax;
$totalScore = ($submission->status === 'checked' && !is_null($submission->total_score))
  ? (int)$submission->total_score
  : ($autoScore + $manualScore);

  $pct = function(int $score, int $max) {
      return $max > 0 ? min(100, max(0, round($score * 100 / $max))) : 0;
  };
  $autoPct   = $pct($autoScore, $autoMax);
  $manualPct = $pct($manualScore, $manualMax);

  // Сводка по ручной части
  $manualCheckedSum = 0;  // сумма баллов по проверенным
  $manualPendingCnt = 0;  // количество ещё не проверенных (включая «пропущено»)
  $manualPendingMax = 0;  // их суммарный максимум
@endphp

@php
  $manualTypes = ['written','image_written','image_manual'];

  $tasksRaw = $homework->tasks ?? [];
  $tasksCol = collect($tasksRaw)->map(fn($t) => is_array($t) ? (object)$t : $t);
  $manualTasks = $tasksCol->filter(fn($t) => in_array($t->type ?? '', $manualTypes, true))->values();

  $perTaskRes = $submission->per_task_results ?? [];

  // Есть ли хоть одно ручное задание, которое ещё не имеет результата или было пропущено
  $hasPendingManual = $manualTasks->contains(function($t, $i) use ($perTaskRes) {
      $tid = $t->id ?? ("t_manual_$i");
      $row = $perTaskRes[$tid] ?? [];
      $hasScore = array_key_exists('score', $row);
      $skipped  = (bool)($row['skipped'] ?? false);
      return !$hasScore || $skipped;
  });

  $studentStatusLabel = $hasPendingManual ? 'Ожидает проверки' : 'Проверено';
@endphp

<div class="max-w-6xl mx-auto px-4 py-6">
  {{-- Заголовок --}}
  <div class="mb-6 flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold">
        Результаты: {{ $homework->title ?? 'Домашнее задание' }}
      </h1>
      <div class="text-gray-500 mt-1">
        Попытка № {{ $submission->attempt_no ?? 1 }} ·
        Статус: <span class="font-medium text-gray-700">{{ $studentStatusLabel }}</span>
      </div>
    </div>
    <div class="shrink-0">
      <a href="{{ route('student.courses.show', $submission->homework?->lesson?->courseSession?->course) }}"
         class="text-blue-600 hover:underline">
        ← Вернуться к курсу
      </a>
    </div>
  </div>

@php
  $attempts_allowed = (int)($homework->attempts_allowed ?? 2);
  $attemptNo   = (int)($submission->attempt_no ?? 1);
  $attemptsLeft = max(0, $attempts_allowed - $attemptNo);
@endphp

<div class="mb-6">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Левая половина: итог --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-4 py-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="text-gray-700">Итог по работе:</div>
        <div class="text-lg font-semibold">
          {{ $totalScore }} / {{ $totalMax }} баллов
        </div>
      </div>
    </div>

    {{-- Правая половина: действие --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-4 flex items-center justify-between gap-3">
      <div class="text-sm text-gray-600">
        @if($attemptsLeft > 0)
          У тебя есть еще одна попытка
        @else
          Лимит попыток исчерпан
        @endif
      </div>

      @if($attemptsLeft > 0)
        <a href="{{ route('student.submissions.create', $homework) }}?retry=1"
          class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
          Перерешать работу
        </a>
      @else
        <button type="button"
                class="px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed"
                disabled>
          Перерешать работу
        </button>
      @endif
    </div>
  </div>
</div>

  {{-- Две колонки --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Левая: автопроверка --}}
    <div class="rounded-2xl shadow bg-white p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Первая часть (автопроверка)</h2>
      </div>

      {{-- Диаграмма + счёт (в одну строку на ПК, в столбик на мобиле) --}}
      <div class="flex flex-col md:flex-row items-center md:items-start gap-4 md:gap-6">
        {{-- ПОЛУКРУГ Chart.js: фиолетовый --}}
        <div class="relative w-full max-w-[240px] h-[140px]">
          <canvas
            class="pill-gauge"
            id="gauge-auto"
            width="260" height="140"
            data-percent="{{ $autoPct }}"
            data-from="#7C3AED"
            data-to="#C084FC"
          ></canvas>
          <div class="absolute left-0 right-0 top-[58px] text-center pointer-events-none">
            <div class="text-2xl font-semibold">{{ $autoPct }}%</div>
            <div class="text-xs text-gray-500">выполнено</div>
          </div>
        </div>

@php
  // Подсчёт статусов по автозаданиям
  $autoStats = ['ok'=>0,'partial'=>0,'fail'=>0];
  foreach ($autoTasks as $i => $t) {
      $tid   = $t->id ?? ("t_auto_$i");
      $max   = (int)($t->max_score ?? 1);
      $score = (int)($perTaskRes[$tid]['score'] ?? 0);
      if ($score === $max)      $autoStats['ok']++;
      elseif ($score > 0)       $autoStats['partial']++;
      else                      $autoStats['fail']++;
  }
@endphp

        <div class="rounded-2xl border-2 border-dashed border-purple-200 bg-white p-3 md:p-4 shadow-sm flex-1 w-full">
          {{-- Крупный счёт --}}
          <div class="flex items-end gap-2">
            <span class="text-lg leading-none self-center relative top-1">
            @if ($autoMax > 0)
              @if ($autoPct > 70)
                <img class="w-5 md:w-6 mr-1.5 md:mr-2" src="{{ asset('/img/noto_fire.svg') }}" alt="fire">
              @elseif ($autoPct > 50)
                <img class="w-5 md:w-6 mr-1.5 md:mr-2" src="{{ asset('/img/like.svg') }}" alt="like">
              @else
                <img class="w-5 md:w-6 mr-1.5 md:mr-2" src="{{ asset('/img/crying.svg') }}" alt="crying">
              @endif
            @endif
            </span>
            <div class="mt-1 md:mt-2 text-2xl md:text-2xl font-extrabold leading-none text-violet-600">
              {{ $autoScore }} / {{ $autoMax }}
            </div>
            <div class="text-sm text-gray-500 md:mb-1 mb-0">баллов</div>
          </div>

          {{-- Чипсы-статусы --}}
          <div class="mt-3 md:mt-4 flex flex-wrap items-center gap-2 text-xs">
            <span style="background-color: #def5ee" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-green-700">
              <svg class="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20 7L9 18l-5-5"/>
              </svg> Верно: {{ $autoStats['ok'] }}
            </span>
            <span style="background-color: #fdf4df" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-yellow-700">
              <svg class="w-4 h-4 text-yellow-600" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Частично: {{ $autoStats['partial'] }}
            </span>
            <span style="background-color: #ffe4e0" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-red-700">
              <svg class="w-3 h-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M18 6L6 18M6 6l12 12"/>
              </svg> Неверно: {{ $autoStats['fail'] }}
            </span>
          </div>
        </div>
      </div>

      {{-- Список авто-заданий --}}
      <div class="mt-5">
        <div class="text-sm font-medium text-gray-600 mb-4">Баллы за задания:</div>
        <div class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-5 gap-3">
          @forelse($autoTasks as $i => $t)
            @php
              $tid   = $t->id ?? ("t_auto_$i");
              $max   = (int)($t->max_score ?? 1);
              $score = (int)($perTaskRes[$tid]['score'] ?? 0);

              if ($score === $max)      $status = 'ok';
              elseif ($score > 0)       $status = 'partial';
              else                      $status = 'fail';
            @endphp

            <div style="background-color: #{{ $status === 'ok' ? 'DEF5EE' : ($status === 'partial' ? 'FDF4DF' : 'FFE4E0') }}" class="flex flex-col items-center justify-between rounded-xl aspect-square p-2">
              <div class="text-xs font-medium text-gray-500">
                № {{ $t->order ?? ($i+1) }}
              </div>

              <div class="flex-1 flex flex-col items-center justify-center gap-1">
                @if($status === 'ok')
                  <div class="border-2 border-green-500 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20 7L9 18l-5-5"/>
                    </svg>
                  </div>
                @elseif($status === 'partial')
                  <div class="border-2 border-yellow-500 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                  </div>
                @else
                  <div class="border-2 border-red-500 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                  </div>
                @endif

                <span class="text-lg font-bold {{ $status === 'ok' ? 'text-green-600' : ($status === 'partial' ? 'text-yellow-600' : 'text-red-600') }}">
                  {{ $score }} / {{ $max }}
                </span>
              </div>
            </div>
          @empty
            <div class="text-sm text-gray-500">Автопроверяемых заданий нет.</div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Правая: ручная проверка --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Вторая часть (проверка куратором)</h2>
      </div>

      {{-- В одну строку на ПК, в столбик на мобиле --}}
      <div class="flex flex-col md:flex-row items-center md:items-start gap-4 md:gap-6">
        {{-- ПОЛУКРУГ Chart.js: зелёный --}}
        <div class="relative w-full max-w-[240px] h-[140px]">
          <canvas
            class="pill-gauge"
            id="gauge-manual"
            width="260" height="140"
            data-percent="{{ $manualPct }}"
            data-from="#10B981"
            data-to="#34D399"
          ></canvas>
          <div class="absolute left-0 right-0 top-[58px] text-center pointer-events-none">
            <div class="text-2xl font-semibold">{{ $manualPct }}%</div>
            <div class="text-xs text-gray-500">выполнено</div>
          </div>
        </div>

@php
  $manualTotals = ['ok'=>0,'partial'=>0,'fail'=>0,'pending'=>0];

  foreach ($manualTasks as $i => $t) {
      $tid = $t->id ?? ("t_manual_$i");
      $max = max(0, (int)($t->max_score ?? 1));

      $row     = $perTaskRes[$tid] ?? [];
      $skipped = (bool)($row['skipped'] ?? false);
      $hasRes  = array_key_exists('score', $row);

      // Если нет результата или задача пропущена — «Ожидает»
      if (!$hasRes || $skipped) {
          $manualTotals['pending']++;
          continue;
      }

      $score = (int)($row['score'] ?? null);

      if ($max === 0) {
          // Нулевой максимум: считаем как «pending» (или выбери нужную бизнес-логику)
          $manualTotals['pending']++;
      } elseif ($score >= $max) {
          $manualTotals['ok']++;
      } elseif ($score > 0) {
          $manualTotals['partial']++;
      } else {
          $manualTotals['fail']++;
      }
  }
@endphp

        {{-- Карточка счёта в стиле левой --}}
        <div class="rounded-2xl border-2 border-dashed border-emerald-200 bg-white p-3 md:p-4 shadow-sm flex-1 w-full">
          <div class="flex items-end gap-2">
            <span class="text-lg leading-none self-center relative top-1">
              @if ($manualMax > 0 && !$hasPendingManual)
                @if ($manualPct > 70)
                  <img class="w-5 md:w-6 mr-1.5 md:mr-2" src="{{ asset('/img/noto_fire.svg') }}" alt="fire">
                @elseif ($manualPct > 50)
                  <img class="w-5 md:w-6 mr-1.5 md:mr-2" src="{{ asset('/img/like.svg') }}" alt="like">
                @else
                  <img class="w-5 md:w-6 mr-1.5 md:mr-2" src="{{ asset('/img/crying.svg') }}" alt="crying">
                @endif
              @endif
            </span>
            <div class="mt-1 md:mt-2 text-2xl md:text-2xl font-extrabold leading-none text-emerald-600">
              {{ $manualScore }} / {{ $manualMax }}
            </div>
            <div class="text-sm text-gray-500 md:mb-1 mb-0">баллов</div>
          </div>

          {{-- Чипсы-статусы (без отдельного «админ») --}}
{{-- Чипсы вместо "Проверено/Ожидает": --}}
<div class="mt-3 md:mt-4 flex flex-wrap items-center gap-2 text-xs">
  <span style="background-color:#def5ee" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-green-700">
    <svg class="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20 7L9 18l-5-5"/>
    </svg> Верно: {{ $manualTotals['ok'] }}
  </span>

  <span style="background-color:#fdf4df" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-yellow-700">
    <svg class="w-4 h-4 text-yellow-600" viewBox="0 0 24 24" fill="none">
      <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg> Частично: {{ $manualTotals['partial'] }}
  </span>

  <span style="background-color:#ffe4e0" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-red-700">
    <svg class="w-3 h-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M18 6L6 18M6 6l12 12"/>
    </svg> Неверно: {{ $manualTotals['fail'] }}
  </span>

  <span style="background-color:#E5E7EB" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-gray-700">
    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
      <path d="M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg> Ожидает: {{ $manualTotals['pending'] }}
  </span>
</div>
        </div>
      </div>

      {{-- Список ручных заданий — цветные квадраты; непроверенные и пропущенные = серые --}}
      <div class="mt-5">
        <div class="text-sm font-medium text-gray-600 mb-4">Баллы за задания:</div>
        <div class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-5 gap-3">
          @forelse($manualTasks as $i => $t)
            @php
              $tid   = $t->id ?? ("t_manual_$i");
              $max   = (int)($t->max_score ?? 1);

              $row     = $perTaskRes[$tid] ?? [];
              $score   = $row['score'] ?? null;             // может быть 0
              $skipped = (bool)($row['skipped'] ?? false);

              $hasResult = array_key_exists('score', $row); // «сохранено» (даже 0)
              $isChecked = $hasResult && !$skipped;

              // Плитка: по требованию — «пропущено» и «не начато» = одинаково серые
              $tile = [
                'color'      => 'E5E7EB', // серый фон ожидания
                'icon'       => 'wait',
                'label'      => '',
                'scoreText'  => '?',
                'scoreClass' => 'text-gray-700',
              ];

              if ($isChecked) {
                  $scoreInt = (int)$score;
                  if ($scoreInt === $max) {
                      $tile['color']      = 'DEF5EE'; // зелёный
                      $tile['icon']       = 'ok';
                      $tile['label']      = '';
                      $tile['scoreText']  = "{$scoreInt} / {$max}";
                      $tile['scoreClass'] = 'text-green-600';
                  } elseif ($scoreInt > 0) {
                      $tile['color']      = 'FDF4DF'; // частично
                      $tile['icon']       = 'partial';
                      $tile['label']      = '';
                      $tile['scoreText']  = "{$scoreInt} / {$max}";
                      $tile['scoreClass'] = 'text-yellow-600';
                  } else { // 0
                      $tile['color']      = 'FFE4E0'; // красный
                      $tile['icon']       = 'fail';
                      $tile['label']      = '';
                      $tile['scoreText']  = "0 / {$max}";
                      $tile['scoreClass'] = 'text-red-600';
                  }
              }

              // Счётчики сводки
              if ($isChecked) {
                $manualCheckedSum += (int) $score;
              } else {
                $manualPendingCnt += 1;
                $manualPendingMax += $max;
              }

              // Ответ ученика (не выводим тут, но можно использовать при расширении)
              $ans = (string)($answers[$tid] ?? '');
            @endphp

            <div style="background-color: #{{ $tile['color'] }}" class="flex flex-col items-center justify-between rounded-xl aspect-square p-2">
              {{-- Номер задания --}}
              <div class="text-xs font-medium text-gray-500">
                № {{ $t->order ?? ($i+1) }}
              </div>

              {{-- Центр: иконка/подпись/баллы --}}
              <div class="flex-1 flex flex-col items-center justify-center gap-1 text-center">
                @if($tile['icon'] === 'ok')
                  <div class="border-2 border-green-500 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20 7L9 18l-5-5"/>
                    </svg>
                  </div>
                @elseif($tile['icon'] === 'partial')
                  <div class="border-2 border-yellow-500 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01M12 20a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                  </div>
                @elseif($tile['icon'] === 'fail')
                  <div class="border-2 border-red-500 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                  </div>
                @else {{-- wait (универсальный для «не начато» и «пропущено») --}}
                  <div class="border-2 border-gray-400 rounded-full p-1 mt-2">
                    <svg class="w-3 h-3 text-gray-600" viewBox="0 0 24 24" fill="none">
                      <path d="M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                @endif

                @if($tile['scoreText'])
                  <span class="text-lg font-bold {{ $tile['scoreClass'] }}">{{ $tile['scoreText'] }}</span>
                  <span class="text-[11px] text-gray-600">{{ $tile['label'] }}</span>
                @else
                  <span class="text-[12px] font-medium text-gray-700">{{ $tile['label'] }}</span>
                @endif
              </div>
            </div>
          @empty
            <div class="text-sm text-gray-500">Заданий для ручной проверки нет.</div>
          @endforelse
        </div>
      </div>

      {{-- Сводка по ручной части --}}
      @if($manualPendingCnt > 0)
      <div class="mt-4 p-3 rounded-lg bg-yellow-50 border border-yellow-100">
        <div class="text-sm">
          {{-- <div>
            <strong>Проверено:</strong> {{ $manualCheckedSum }}
            @if($manualMax>0) / {{ $manualMax }} @endif
          </div> --}}
            <div class="text-yellow-700">
              Некоторые задания ещё на проверке. Итоговый результат обновится, когда задания будут проверены до конца.
            </div>
        </div>
      </div>
      @endif
    </div>
  </div>


  {{-- ===== Детализация по каждому заданию (под карточками результатов) ===== --}}
<div class="mt-8">
  <h3 class="text-lg font-semibold mb-4">Подробно по заданиям</h3>

  @forelse($tasksCol as $i => $t)
    @php
      $tid          = $t->id ?? ("t_{$i}");
      $type         = (string)($t->type ?? 'unknown');
      $max          = (int)($t->max_score ?? 1);
      $orderMatters = (bool)($t->order_matters ?? in_array($type, ['matching','table'], true));

      $row      = $perTaskRes[$tid] ?? [];
      // КЛЮЧЕВОЕ: score считается «есть» только если ключ присутствует и он НЕ null
      $hasScore = array_key_exists('score', $row) && $row['score'] !== null;
      $score    = $hasScore ? (int)$row['score'] : null;
      $skipped  = (bool)($row['skipped'] ?? false);

      // Ответы
      $studentAns = $row['answer']  ?? ($submission->answers[$tid] ?? null);
      $correctAns = $row['correct'] ?? ($t->answer ?? null);

      // Пояснение куратора (может храниться в reason/comment)
      $mentorNote   = $row['comment'] ?? null;

      $mentorReason = $row['reason']  ?? '';

      // Медиа (для image_* типов)
      $mediaPath  = $t->image_path ?? $t->image_path ?? null;
      $mediaUrl   = $storageUrl($mediaPath);

      // Статус бейджа
      $badge = [
        'bg'   => 'bg-gray-100',
        'text' => 'text-gray-700',
        'name' => 'Ожидает проверки',
      ];
      if ($skipped || !$hasScore) {
        $badge = ['bg'=>'bg-gray-100','text'=>'text-gray-700','name'=>'Ожидает проверки'];
      } else {
        if ($score >= $max) {
          $badge = ['bg'=>'bg-emerald-50','text'=>'text-emerald-700','name'=>"Верно: {$score} / {$max}"];
        } elseif ($score > 0) {
          $badge = ['bg'=>'bg-amber-50','text'=>'text-amber-700','name'=>"Частично верно: {$score} / {$max}"];
        } else {
          $badge = ['bg'=>'bg-rose-50','text'=>'text-rose-700','name'=>"Неверно: 0 / {$max}"];
        }
      }

      // Читабельные заголовки
      $titleNo = $t->order ?? ($i + 1);
      $questionText = $t->question_text ?? null;
      $passageText  = $t->passage_text  ?? null;

      // Утилита нормализации многострочного текста
      $norm = function($s) {
        if ($s === null || $s === '') return '—';
        $s = (string)$s;
        $s = preg_replace('/^\xEF\xBB\xBF/u', '', $s);
        $s = str_replace(["\r\n","\r"], "\n", $s);
        $s = str_replace("\xC2\xA0", ' ', $s);
        return trim($s) === '' ? '—' : $s;
      };
    @endphp

    <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 mb-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 sm:gap-4 mb-4 sm:mb-5">
          <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-gray-100 border border-gray-200 text-gray-700">
            №{{ $titleNo }} в ЕГЭ
          </span>
          <span class="text-base sm:text-lg font-semibold text-gray-900">
            Задание №{{ $titleNo }}
          </span>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badge['bg'] }} {{ $badge['text'] }}">
          {{ $badge['name'] }}
        </span>
      </div>

      {{-- Текст/медиа задания (если есть) --}}
      @if($questionText || $passageText || $mediaUrl)
        <div class="mb-6 space-y-3">
          @if($passageText)
            <div class="mb-6 p-3 rounded-lg bg-gray-50 border text-base whitespace-pre-wrap">{{ $norm($passageText) }}</div>
          @endif
          @if($questionText)
            <div class=" text-base text-gray-800 whitespace-pre-wrap">{{ $norm($questionText) }}</div>
          @endif
          @if($mediaUrl)
            <div>
              <img src="{{ $mediaUrl }}" alt="" class="w-full max-h-[320px] object-contain rounded-lg border">
            </div>
          @endif
        </div>
      @endif

      {{-- table (детализация карточки задания) --}}
@if($type === 'table')
  @php
    $tableRaw2 = $t->table_content ?? null;
    if (is_string($tableRaw2)) {
      $decoded2 = json_decode($tableRaw2, true);
      $table2 = is_array($decoded2) ? $decoded2 : [];
    } elseif (is_array($tableRaw2)) {
      $table2 = $tableRaw2;
    } else {
      $table2 = [];
    }

    $cols2   = is_array($table2['cols'] ?? null) ? $table2['cols'] : [];
    $rows2   = is_array($table2['rows'] ?? null) ? $table2['rows'] : [];
    if (empty($cols2) && !empty($rows2) && is_array($rows2[0] ?? null)) {
      $cols2 = array_map(fn($i) => 'Колонка '.($i+1), range(0, count($rows2[0])-1));
    }

    $blanks2 = is_array($table2['blanks'] ?? null) ? $table2['blanks'] : [];
    $blankMap2 = [];
    foreach ($blanks2 as $b) {
      if (isset($b['r'], $b['c'])) $blankMap2[$b['r'].'_'.$b['c']] = $b['key'] ?? '';
    }
  @endphp

  <div class="overflow-auto rounded-xl border border-gray-200 mt-1 mb-5 sm:mb-6">
    <table class="min-w-full border-collapse">
      @if(!empty($cols2))
        <thead class="bg-gray-50">
          <tr>
            @foreach($cols2 as $c)
              <th class="border border-gray-200 px-3 py-2 sm:py-3 text-left text-xs sm:text-sm font-medium text-gray-700">{{ $c }}</th>
            @endforeach
          </tr>
        </thead>
      @endif
      <tbody>
        @forelse($rows2 as $rIdx => $row)
          <tr class="odd:bg-white ">
            @foreach((array)$row as $cIdx => $cell)
              @php
                $k = $rIdx.'_'.$cIdx;
                $isBlank = array_key_exists($k, $blankMap2);
              @endphp
              <td class="px-3 py-2 sm:py-3 align-top border border-gray-200">
                <div class="text-sm sm:text-[15px] text-gray-800 whitespace-pre-wrap">
                  {{ (string)$cell }}
                </div>
              </td>
            @endforeach
          </tr>
        @empty
          <tr>
            <td class="px-3 py-3 text-xs sm:text-sm text-gray-500">Таблица не задана</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif


      @php
  // текущий объект задания в карточке
  $cur = $t ?? $task ?? null;
  $curType = $cur->type ?? $type ?? '';
  // источники вариантов — как на странице create
  $rawOptions = $cur->options ?? $cur->image_auto_options ?? null;
  $options = [];
  if (is_array($rawOptions)) {
    $options = array_values(array_filter(array_map('trim', $rawOptions), fn($v) => $v !== '' && $v !== null));
  } elseif (is_string($rawOptions)) {
    $decoded = json_decode($rawOptions, true);
    if (is_array($decoded)) {
      $options = array_values(array_filter(array_map('trim', $decoded), fn($v) => $v !== '' && $v !== null));
    } else {
      $lines = preg_split("/\r\n|\r|\n/", $rawOptions);
      $options = array_values(array_filter(array_map('trim', $lines), fn($v) => $v !== '' && $v !== null));
    }
  }
@endphp

@if(!empty($options))
  <div class="mt-3 sm:mt-4 text-gray-900 text-sm sm:text-base flex flex-col flex-wrap gap-2 sm:gap-3 items-start">
    @foreach($options as $opt)
      <div class="px-2 sm:px-3 py-0.5 sm:py-1 rounded-lg border border-gray-200 bg-gray-50">{{ $opt }}</div>
    @endforeach
  </div>
@endif

@php
  $cur = $t ?? $task ?? null;
  $curType = $cur->type ?? $type ?? '';
@endphp

@if($curType === 'matching')
  @php
    $left  = [];
    $right = [];
    // как на странице create
    if (!empty($cur->matches['left'])) {
      $left = is_array($cur->matches['left'])
        ? $cur->matches['left']
        : preg_split("/\r\n|\r|\n/", (string)$cur->matches['left']);
    }
    if (!empty($cur->matches['right'])) {
      $right = is_array($cur->matches['right'])
        ? $cur->matches['right']
        : preg_split("/\r\n|\r|\n/", (string)$cur->matches['right']);
    }
    $letters = ['А','Б','В','Г','Д','Е','Ж','З','И','К','Л','М'];
  @endphp

  <div class="grid md:grid-cols-2 gap-4 sm:gap-6 mt-3 sm:mt-4 mb-4">
    <div class="rounded-xl border bg-white">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-gray-700">{{ $cur->left_title ?? 'Левая колонка' }}</div>
      <div class="divide-y">
        @forelse($left as $iL => $val)
          <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-gray-500 mr-2">{{ $letters[$iL] ?? ($iL+1) }}.</span> {{ $val }}
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-gray-500">Нет элементов</div>
        @endforelse
      </div>
    </div>

    <div class="rounded-xl border bg-white">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-gray-700">{{ $cur->right_title ?? 'Правая колонка' }}</div>
      <div class="divide-y">
        @forelse($right as $iR => $val)
          <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-gray-500 mr-2">{{ $iR+1 }}.</span> {{ $val }}
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-gray-500">Нет элементов</div>
        @endforelse
      </div>
    </div>
  </div>
@endif


      {{-- Ответы и пояснения --}}
      <div class="mt-4">
{{-- Ответы и пояснения (для авто — «квадратики», для ручных — как было) --}}
@php
  // Определяем тип текущего задания
  $curType   = $t->type ?? ($task->type ?? '');
  $isManual  = in_array($curType, ['written','image_written','image_manual'], true);

  // Достаём per-task результаты
  $curId   = $t->id ?? ($task->id ?? null);
  $row     = $perTaskRes[$curId] ?? [];
  $max     = (int)($t->max_score ?? ($task->max_score ?? 1));
  $score   = array_key_exists('score',$row) ? $row['score'] : null; // важно: null ≠ 0
  $skipped = (bool)($row['skipped'] ?? false);

  // Статус для цвета рамки у ответа ученика (только для автопроверки)
  $status = 'pending';
  if (!$isManual) {
    if ($score === null || $skipped) {
      $status = 'pending';
    } elseif ((int)$score >= $max) {
      $status = 'ok';
    } elseif ((int)$score > 0) {
      $status = 'partial';
    } else {
      $status = 'fail';
    }
  }

  $borderClass = [
    'ok'      => 'border-green-500',
    'partial' => 'border-yellow-500',
    'fail'    => 'border-red-500',
    'pending' => 'border-gray-300',
  ][$status];

  // Разбиваем строки ответов на символы для «квадратиков» (только авто)
  $toChars = function ($s) {
    $s = (string)($s ?? '');
    return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  };
  $stuChars  = $toChars($studentAns);
  $corrChars = $toChars($correctAns);
  $boxesLen  = max(count($stuChars), count($corrChars), 1);
  $up = fn($ch) => mb_strtoupper($ch);
@endphp

@if(!$isManual)
  {{-- АВТОПРОВЕРКА: выводим ответ ученика и правильный ответ «квадратиками» --}}
  <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Ваш ответ (цветная обводка по статусу) --}}
    <div class="rounded-xl mt-2">
      <div class="text-xs text-gray-500 mb-2">Ваш ответ</div>
      <div class="flex flex-wrap gap-2">
        @for($i=0; $i<$boxesLen; $i++)
          <div class="w-9 h-9 sm:w-10 sm:h-10 border-2 {{ $borderClass }} rounded-lg flex items-center justify-center text-base sm:text-lg font-semibold select-none">
            {{ $up($stuChars[$i] ?? '') }}
          </div>
        @endfor
      </div>
    </div>

    {{-- Правильный ответ (всегда серая обводка) --}}
    <div class="rounded-xl mt-2">
      <div class="text-xs text-gray-500 mb-2">Правильный ответ</div>
      <div class="flex flex-wrap gap-2">
        @for($i=0; $i<$boxesLen; $i++)
          <div class="w-9 h-9 sm:w-10 sm:h-10 border-2 border-gray-300 rounded-lg flex items-center justify-center text-base sm:text-lg font-semibold bg-white select-none">
            {{ $up($corrChars[$i] ?? '') }}
          </div>
        @endfor
      </div>
    </div>

    {{-- Пояснение куратора (если есть) --}}
    {{-- <div class="rounded-xl p-3">
      <div class="text-xs text-gray-500 mb-1">Пояснение куратора</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($mentorNote) }}</div>
    </div> --}}
  </div>
@else
  {{-- РУЧНАЯ ПРОВЕРКА: оставляем карточки по ширине (без «квадратиков») --}}
  <div class="mt-4 grid grid-cols-1 md:grid-cols-1 gap-4">
    <div class="rounded-xl bg-blue-50 p-3 px-4">
      <div class="text-xs text-blue-500 mb-2">Ваш ответ</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($studentAns) }}</div>
    </div>
    
    @if($hasScore && !$skipped)
    <div style="background-color: #e2f4ef" class="rounded-xl p-3 px-4">
      <div style="color: #33a885" class="text-xs mb-2">Образцовый ответ</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($correctAns) }}</div>
    </div>
    @endif
    @if($hasScore && !$skipped)
    <div class="rounded-xl border border-gray-200 p-3 px-4">
      <div class="text-xs text-gray-500 mb-2">Пояснение куратора</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($mentorNote) }}</div>
    </div>
    @endif
    @if($hasScore && !$skipped)
    <div class="rounded-xl border border-gray-200 p-3">
      <div class="text-xs text-gray-500 mb-1">Обоснование баллов</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ trim($norm($mentorReason)) !== '' ? $norm($mentorReason) : '—' }}</div>
    </div>
    @endif
  </div>
@endif



      </div>
    </div>
  @empty
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600">
      Заданий нет.
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

@section('scripts')
  <style>
    /* Контейнер для отображения ответа «квадратиками» (read-only) */
    .ans-boxes{
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(32px,1fr));
      gap:10px;
      width:100%;
      max-width:520px;
    }
    .ans-box{
      aspect-ratio:1/1;
      min-width:32px;
      border:2px solid #e5e7eb;  /* нейтральная (для правильного ответа) */
      border-radius:9px;
      display:flex;align-items:center;justify-content:center;
      font-size:18px;font-weight:600;background:#fff;
      font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
    }
    /* Цвета рамок для ответа ученика по статусу */
    .ans-ok{ border-color:#10B981; }       /* зелёный */
    .ans-partial{ border-color:#F59E0B; }  /* оранжевый */
    .ans-fail{ border-color:#EF4444; }     /* красный */
    .ans-neutral{ border-color:#e5e7eb; }  /* серый (правильный ответ) */
  </style>
  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <script>
  (function () {
    function makePillGauge(canvas, percent, fromHex, toHex) {
      const ctx = canvas.getContext('2d');
      const SEGMENTS = 30;
      const filledCount = Math.round(SEGMENTS * Math.max(0, Math.min(100, percent)) / 100);
      const data = new Array(SEGMENTS).fill(1);
      const bg = 'rgba(107,114,128,0.18)';
      const colors = Array.from({length: SEGMENTS}, (_, i) => i < filledCount ? null : bg);

      const gradient = ctx.createLinearGradient(0, canvas.height, canvas.width, 0);
      gradient.addColorStop(0, fromHex);
      gradient.addColorStop(1, toHex);

      const colorizeFilled = {
        id: 'colorizeFilled',
        beforeDatasetsDraw(chart) {
          const meta = chart.getDatasetMeta(0);
          meta.data.forEach((arc, idx) => {
            if (idx < filledCount) {
              arc.options.backgroundColor = gradient;
              arc.options.segment = {borderRadius: 10};
              arc.options.borderWidth = 0;
              arc.options.hoverOffset = 0;
            }
          });
        }
      };

      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: new Array(SEGMENTS).fill(''),
          datasets: [{
            data,
            backgroundColor: colors,
            borderWidth: 0,
            spacing: 40,
            borderRadius: 10
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          rotation: -90,
          circumference: 180,
          animation: { duration: 600 },
          plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
          }
        },
        plugins: [colorizeFilled]
      });

      setTimeout(() => {
        const chart = Chart.getChart(canvas);
        if (!chart) return;
        const ds = chart.data.datasets[0];
        for (let i = 0; i < filledCount; i++) {
          ds.backgroundColor[i] = gradient;
        }
        chart.update('none');
      }, 0);
    }

    // Инициализация всех .pill-gauge
    document.querySelectorAll('canvas.pill-gauge').forEach(cv => {
      const pct  = Number(cv.dataset.percent || 0);
      const from = cv.dataset.from || '#7C3AED';
      const to   = cv.dataset.to   || '#C084FC';
      cv.style.display = 'block';
      cv.parentElement && (cv.parentElement.style.minHeight = '140px');
      makePillGauge(cv, pct, from, to);
    });
  })();
  </script>
@endsection
