{{-- resources/views/student/submissions/show.blade.php --}}
@extends('layouts.main')

@section('title', $homework->title ?? 'Домашнее задание')
@section('back_url', route('student.courses.show', $submission->homework?->lesson?->courseSession?->course))

@section('content')
@php
  /** @var \App\Models\Submission $submission */
  $submission->loadMissing('homework.lesson.courseSession.course');

  $homework = $submission->homework;

  // Приводим задачи к коллекции объектов и сортируем по полю order
  // (с fallback на исходную позицию, если order не задан/совпадает —
  // иначе нумерация и порядок карточек ниже могли бы не совпадать с
  // реальным порядком задания).
  $tasksRaw = $homework->tasks ?? [];
  $tasksCol = collect($tasksRaw)
      ->map(fn($t) => is_array($t) ? (object)$t : $t)
      ->values()
      ->map(function ($t, $origIdx) {
          // origIdx хранит позицию ДО сортировки — по ней (а не по $i после
          // сортировки) строятся fallback-ключи t_auto_/t_manual_/t_{$i}
          // для поиска в $perTaskRes, когда у задания нет id.
          $t->_origIdx = $origIdx;
          return $t;
      })
      ->sortBy(fn($t) => [(int)($t->order ?? PHP_INT_MAX), $t->_origIdx])
      ->values();
    $storageUrl = function ($path) {
      if (!$path) return null;
      $isFull = \Illuminate\Support\Str::startsWith($path, ['http://','https://','/storage/','data:']);
      return $isFull ? $path : \Illuminate\Support\Facades\Storage::url($path);
  };

  

  // Типы, проверяемые вручную
  $manualTypes = \App\Models\HomeworkTask::MANUAL_TYPES;

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

  // Единая цветовая палитра статусов для плиток и чипсов-счётчиков —
  // классы apple-* из tailwind.config.js (см. /admin/design-system —
  // раздел "Цветовая палитра") вместо разномастных кастомных пастелей.
  $appleStatus = [
    'ok'      => ['bg' => 'bg-apple-green-50',  'text' => 'text-apple-green-700',  'border' => 'border-apple-green-500'],
    'partial' => ['bg' => 'bg-apple-orange-50', 'text' => 'text-apple-orange-700', 'border' => 'border-apple-orange-500'],
    'fail'    => ['bg' => 'bg-apple-red-50',    'text' => 'text-apple-red-700',    'border' => 'border-apple-red-500'],
    'wait'    => ['bg' => 'bg-zinc-100',        'text' => 'text-zinc-500',         'border' => 'border-zinc-300'],
  ];
@endphp

@php
  // $tasksCol/$manualTasks/$perTaskRes уже посчитаны в блоке выше (отсортированы по order) —
  // переиспользуем их, не пересобираем заново.

  // Есть ли хоть одно ручное задание, которое ещё не имеет результата или было пропущено
  $hasPendingManual = $manualTasks->contains(function($t) use ($perTaskRes) {
      $tid = $t->id ?? ("t_manual_{$t->_origIdx}");
      $row = $perTaskRes[$tid] ?? [];
      $hasScore = array_key_exists('score', $row);
      $skipped  = (bool)($row['skipped'] ?? false);
      return !$hasScore || $skipped;
  });

  $studentStatusLabel = $hasPendingManual ? 'Ожидает проверки' : 'Проверено';
@endphp

<div class="max-w-6xl mx-auto px-4 py-6">
@php
  // Уже нормализовано контроллером (Homework::normalizeAttemptsAllowed) —
  // единственный источник истины на 2 попытки по умолчанию, тот же, что и
  // в SubmissionController::create().
  $attempts_allowed = (int) $homework->attempts_allowed;
  $attemptNo   = (int)($submission->attempt_no ?? 1);
  $attemptsLeft = max(0, $attempts_allowed - $attemptNo);
@endphp

  {{-- Заголовок (левая половина на ПК) + итог/действие (правая половина, по правому краю) --}}
  <div class="mb-6 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
    <div class="min-w-0 sm:w-1/2">
      <h1 class="sans-medium text-xl md:text-2xl text-zinc-900">
        {{ $homework->title ?? 'Домашнее задание' }}
      </h1>
      <div class="sans text-sm text-zinc-500 mt-1">
        Попытка № {{ $submission->attempt_no ?? 1 }} ·
        Статус: <span class="sans-medium text-zinc-700">{{ $studentStatusLabel }}</span>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-stretch sm:justify-end gap-2 shrink-0 sm:w-1/2">
      <div class="rounded-xl sm:h-full border border-zinc-200 bg-zinc-50 px-4 py-2.5 flex items-center justify-center sm:justify-start">
        <span class="sans-medium text-base sm:text-sm text-zinc-900 whitespace-nowrap">{{ $totalScore }} / {{ $totalMax }} баллов</span>
      </div>

      @if($attemptsLeft > 0)
        <x-ui.button href="{{ route('student.submissions.create', $homework) }}?retry=1" variant="accent" class="w-full sm:w-auto justify-center !bg-apple-blue-500 hover:!bg-apple-blue-600 sm:!px-4 sm:!py-2 sm:!text-sm">
          Перерешать работу
        </x-ui.button>
        {{-- <div class="sans text-xs text-zinc-400 text-right">
          У тебя есть еще одна попытка
        </div> --}}
      @else
        <x-ui.button type="button" disabled class="w-full sm:w-auto justify-center sm:!px-4 sm:!py-3 sm:!text-sm">
          Перерешать работу
        </x-ui.button>
      @endif
    </div>
  </div>

  {{-- Две колонки --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Левая: автопроверка --}}
    <x-ui.card>
      <div class="flex items-center justify-between mb-4">
        <h2 class="sans-medium text-lg md:text-xl text-zinc-900">Первая часть</h2>
      </div>

@php
  // Подсчёт статусов по автозаданиям
  $autoStats = ['ok'=>0,'partial'=>0,'fail'=>0];
  foreach ($autoTasks as $t) {
      $tid   = $t->id ?? ("t_auto_{$t->_origIdx}");
      $max   = (int)($t->max_score ?? 1);
      $score = (int)($perTaskRes[$tid]['score'] ?? 0);
      if ($score === $max)      $autoStats['ok']++;
      elseif ($score > 0)       $autoStats['partial']++;
      else                      $autoStats['fail']++;
  }
@endphp

      {{-- Кольцо + чипсы-статусы под ним — в одной левой колонке; баллы за
           задания — в правой колонке (без обтекания, обычный flex-row). --}}
      <div class="flex flex-col md:flex-row items-center md:items-start gap-4 md:gap-6">
        <div class="flex flex-col items-center shrink-0">
          <div class="relative w-[160px] h-[160px] md:w-[180px] md:h-[180px]">
            <canvas
              class="result-ring"
              id="gauge-auto"
              width="320" height="320"
              data-percent="{{ $autoPct }}"
              data-color="#AF52DE"
              data-track="#F1E1F9"
            ></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <div class="sans-medium text-2xl text-zinc-900 leading-none">
                {{ $autoScore }}<span class="text-base font-normal text-zinc-400">/{{ $autoMax }}</span>
              </div>
              <div class="sans text-xs text-zinc-400 mt-1.5">баллов</div>
            </div>
          </div>

          {{-- Чипсы-статусы — под кольцом. На ПК — один столбик (друг под
               другом), все чипсы одной ширины: inline-grid с одной колонкой
               растягивает каждый элемент (justify-items: stretch по
               умолчанию) до ширины самого широкого — колонка автоматически
               сайзится по нему, т.к. контейнер inline-grid (shrink-to-fit).
               На мобиле — обычный flex-wrap, ширина по содержимому. --}}
          <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs md:inline-grid md:grid-cols-1 md:gap-1.5">
            <span class="{{ $appleStatus['ok']['bg'] }} {{ $appleStatus['ok']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="check-circle" class="w-3 h-3 shrink-0" /> Верно: {{ $autoStats['ok'] }}
            </span>
            <span class="{{ $appleStatus['partial']['bg'] }} {{ $appleStatus['partial']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="alert-circle" class="w-4 h-4 shrink-0" /> Частично: {{ $autoStats['partial'] }}
            </span>
            <span class="{{ $appleStatus['fail']['bg'] }} {{ $appleStatus['fail']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="x-circle" class="w-3 h-3 shrink-0" /> Неверно: {{ $autoStats['fail'] }}
            </span>
          </div>
        </div>

        <div class="flex-1 w-full">
          <div class="sans text-sm font-medium text-zinc-600 mb-2">Баллы за задания:</div>
          <div class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-4 lg:grid-cols-5 gap-2">
            @forelse($autoTasks as $i => $t)
              @php
                $tid   = $t->id ?? ("t_auto_{$t->_origIdx}");
                $max   = (int)($t->max_score ?? 1);
                $score = (int)($perTaskRes[$tid]['score'] ?? 0);

                if ($score === $max)      $status = 'ok';
                elseif ($score > 0)       $status = 'partial';
                else                      $status = 'fail';
                $tileColor = $appleStatus[$status];
              @endphp

              <div class="{{ $tileColor['bg'] }} flex flex-col items-center justify-center gap-0.5 rounded-lg aspect-square p-1.5">
                <div class="text-[10px] font-medium text-zinc-500">
                  № {{ $t->order ?? ($i+1) }}
                </div>

                {{-- Без обёрточного круга: check-circle/alert-circle/x-circle
                     из Untitled UI уже сами нарисованы как круг с символом —
                     border rounded-full поверх них рисовал второй, лишний круг. --}}
                <div class="{{ $tileColor['text'] }}">
                  @if($status === 'ok')
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                  @elseif($status === 'partial')
                    <x-icon name="alert-circle" class="w-3.5 h-3.5" />
                  @else
                    <x-icon name="x-circle" class="w-3.5 h-3.5" />
                  @endif
                </div>

                <span class="{{ $tileColor['text'] }} text-sm font-medium">
                  {{ $score }}/{{ $max }}
                </span>
              </div>
            @empty
              <div class="text-sm text-zinc-500">Автопроверяемых заданий нет.</div>
            @endforelse
          </div>
        </div>
      </div>
    </x-ui.card>

    {{-- Правая: ручная проверка --}}
    <x-ui.card>
      <div class="flex items-center justify-between mb-4">
        <h2 class="sans-medium text-lg md:text-xl text-zinc-900">Вторая часть</h2>
      </div>

@php
  $manualTotals = ['ok'=>0,'partial'=>0,'fail'=>0,'pending'=>0];

  foreach ($manualTasks as $t) {
      $tid = $t->id ?? ("t_manual_{$t->_origIdx}");
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

      {{-- Кольцо + чипсы-статусы под ним — в одной левой колонке; баллы за
           задания — в правой колонке (без обтекания). --}}
      <div class="flex flex-col md:flex-row items-center md:items-start gap-4 md:gap-6">
        <div class="flex flex-col items-center shrink-0">
          <div class="relative w-[160px] h-[160px] md:w-[180px] md:h-[180px]">
            <canvas
              class="result-ring"
              id="gauge-manual"
              width="320" height="320"
              data-percent="{{ $manualPct }}"
              data-color="#34C759"
              data-track="#E3F8E8"
            ></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
              <div class="sans-medium text-2xl text-zinc-900 leading-none">
                {{ $manualScore }}<span class="text-base font-normal text-zinc-400">/{{ $manualMax }}</span>
              </div>
              <div class="sans text-xs text-zinc-400 mt-1.5">баллов</div>
            </div>
          </div>

          {{-- Чипсы-статусы (без отдельного «админ») — под кольцом. На ПК —
               один столбик, все чипсы одной ширины (см. комментарий у
               Первой части); на мобиле — flex-wrap по содержимому. --}}
          <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs md:inline-grid md:grid-cols-1 md:gap-1.5">
            <span class="{{ $appleStatus['ok']['bg'] }} {{ $appleStatus['ok']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="check-circle" class="w-3 h-3 shrink-0" /> Верно: {{ $manualTotals['ok'] }}
            </span>
            <span class="{{ $appleStatus['partial']['bg'] }} {{ $appleStatus['partial']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="alert-circle" class="w-4 h-4 shrink-0" /> Частично: {{ $manualTotals['partial'] }}
            </span>
            <span class="{{ $appleStatus['fail']['bg'] }} {{ $appleStatus['fail']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="x-circle" class="w-3 h-3 shrink-0" /> Неверно: {{ $manualTotals['fail'] }}
            </span>
            <span class="{{ $appleStatus['wait']['bg'] }} {{ $appleStatus['wait']['text'] }} inline-flex items-center justify-center gap-1 px-2 py-1 rounded-full md:w-full">
              <x-icon name="clock-stopwatch" class="w-3.5 h-3.5 shrink-0" /> Ожидает: {{ $manualTotals['pending'] }}
            </span>
          </div>
        </div>

        <div class="flex-1 w-full">
          <div class="sans text-sm font-medium text-zinc-600 mb-2">Баллы за задания:</div>
          <div class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-4 lg:grid-cols-5 gap-2">
            @forelse($manualTasks as $i => $t)
              @php
                $tid   = $t->id ?? ("t_manual_{$t->_origIdx}");
                $max   = (int)($t->max_score ?? 1);

                $row     = $perTaskRes[$tid] ?? [];
                $score   = $row['score'] ?? null;             // может быть 0
                $skipped = (bool)($row['skipped'] ?? false);

                $hasResult = array_key_exists('score', $row); // «сохранено» (даже 0)
                $isChecked = $hasResult && !$skipped;

                // Плитка: по требованию — «пропущено» и «не начато» = одинаково серые
                $tileStatus = 'wait';
                $tileScoreText = '?';

                if ($isChecked) {
                    $scoreInt = (int)$score;
                    if ($scoreInt === $max) {
                        $tileStatus = 'ok';
                    } elseif ($scoreInt > 0) {
                        $tileStatus = 'partial';
                    } else {
                        $tileStatus = 'fail';
                    }
                    $tileScoreText = "{$scoreInt}/{$max}";
                }
                $tileColor = $appleStatus[$tileStatus];
              @endphp

              <div class="{{ $tileColor['bg'] }} flex flex-col items-center justify-center gap-0.5 rounded-lg aspect-square p-1.5">
                <div class="text-[10px] font-medium text-zinc-500">
                  № {{ $t->order ?? ($i+1) }}
                </div>

                {{-- Без обёрточного круга: эти иконки Untitled UI уже сами
                     нарисованы как круг с символом — border rounded-full
                     поверх них рисовал второй, лишний круг. --}}
                <div class="{{ $tileColor['text'] }}">
                  @if($tileStatus === 'ok')
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                  @elseif($tileStatus === 'partial')
                    <x-icon name="alert-circle" class="w-3.5 h-3.5" />
                  @elseif($tileStatus === 'fail')
                    <x-icon name="x-circle" class="w-3.5 h-3.5" />
                  @else {{-- wait (универсальный для «не начато» и «пропущено») --}}
                    <x-icon name="clock-stopwatch" class="w-3.5 h-3.5" />
                  @endif
                </div>

                <span class="{{ $tileColor['text'] }} text-sm font-medium">{{ $tileScoreText }}</span>
              </div>
            @empty
              <div class="text-sm text-zinc-500">Заданий для ручной проверки нет.</div>
            @endforelse
          </div>
        </div>
      </div>

      {{-- Сводка по ручной части --}}
      @if($manualPendingCnt > 0)
      <div class="mt-4 p-3 rounded-lg bg-apple-orange-50 border border-apple-orange-100">
        <div class="text-sm">
          {{-- <div>
            <strong>Проверено:</strong> {{ $manualCheckedSum }}
            @if($manualMax>0) / {{ $manualMax }} @endif
          </div> --}}
            <div class="text-apple-orange-700">
              Некоторые задания ещё на проверке. Итоговый результат обновится, когда задания будут проверены до конца.
            </div>
        </div>
      </div>
      @endif
    </x-ui.card>
  </div>


  {{-- ===== Детализация по каждому заданию (под карточками результатов) ===== --}}
<div class="mt-8">
  <h3 class="sans-medium text-lg md:text-2xl text-zinc-900 mb-4">Разбор заданий</h3>

  @forelse($tasksCol as $i => $t)
    @php
      $tid          = $t->id ?? ("t_{$t->_origIdx}");
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

      // Статус бейджа. 'short' — компактная версия для мобиле (просто
      // дробь без слова-статуса, статус и так виден по цвету), 'name' —
      // полная версия для ПК.
      $badge = [
        'bg'    => 'bg-zinc-100',
        'text'  => 'text-zinc-700',
        'name'  => 'Ожидает проверки',
        'short' => 'Ожидает проверки',
      ];
      if ($skipped || !$hasScore) {
        $badge = ['bg'=>'bg-zinc-100','text'=>'text-zinc-700','name'=>'Ожидает проверки','short'=>'Ожидает проверки'];
      } else {
        if ($score >= $max) {
          $badge = ['bg'=>'bg-apple-green-50','text'=>'text-apple-green-700','name'=>"Верно: {$score} / {$max}",'short'=>"{$score}/{$max}"];
        } elseif ($score > 0) {
          $badge = ['bg'=>'bg-apple-orange-50','text'=>'text-apple-orange-700','name'=>"Частично верно: {$score} / {$max}",'short'=>"{$score}/{$max}"];
        } else {
          $badge = ['bg'=>'bg-apple-red-50','text'=>'text-apple-red-700','name'=>"Неверно: 0 / {$max}",'short'=>"0/{$max}"];
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

    <x-ui.card class="mb-4">
      <div class="flex items-center justify-between gap-2 mb-4 sm:mb-5">
        <div class="flex items-center gap-3 sm:gap-4 min-w-0">
          <span class="inline-block shrink-0 whitespace-nowrap px-2 py-0.5 text-xs rounded-full bg-zinc-100 border border-zinc-200 text-zinc-700">
            №{{ $titleNo }} в ЕГЭ
          </span>
          <span class="sans-medium text-lg text-zinc-900 truncate">
            Задание №{{ $titleNo }}
          </span>
        </div>
        <span class="inline-flex items-center shrink-0 whitespace-nowrap px-2.5 py-1 rounded-full text-xs font-medium {{ $badge['bg'] }} {{ $badge['text'] }}">
          <span class="sm:hidden">{{ $badge['short'] }}</span>
          <span class="hidden sm:inline">{{ $badge['name'] }}</span>
        </span>
      </div>

      {{-- Текст/медиа задания (если есть). Картиночные типы всегда попадают
           сюда, даже без текста и без реальной картинки — тогда ниже
           покажется заглушка вместо картинки, а не пустой блок. --}}
      @if($questionText || $passageText || $mediaUrl || in_array($type, ['image_auto','image_manual']))
        <div class="mb-6 space-y-3">
          @if($passageText)
            <div class="mb-6 p-3 rounded-lg bg-gray-50 border text-sm sm:text-base whitespace-pre-wrap">{{ $norm($passageText) }}</div>
          @endif
          @if($questionText)
            <div class="sans text-sm sm:text-base text-zinc-700 whitespace-pre-wrap">{{ $norm($questionText) }}</div>
          @endif
          {{-- Тот же принцип, что и в самом визарде (task-prompt.blade.php):
               если типу положена картинка, но её не загрузили — заглушка
               вместо пустоты. --}}
          @if(in_array($type, ['image_auto','image_manual']))
            <div>
              @if($mediaUrl)
                <img src="{{ $mediaUrl }}" alt="" class="w-full max-h-[320px] object-contain rounded-lg border">
              @else
                <div class="w-full h-40 rounded-lg border border-gray-200 bg-gray-100 flex items-center justify-center text-gray-300">
                  <x-icon name="image-01" class="w-10 h-10" />
                </div>
              @endif
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
              <th class="border border-gray-200 px-3 py-2 sm:py-3 text-left text-xs sm:text-sm font-medium text-zinc-700">{{ $c }}</th>
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
                <div class="sans text-sm sm:text-[15px] text-zinc-700 whitespace-pre-wrap">
                  {{ (string)$cell }}
                </div>
              </td>
            @endforeach
          </tr>
        @empty
          <tr>
            <td class="px-3 py-3 text-xs sm:text-sm text-zinc-500">Таблица не задана</td>
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

@php
  // Подсветка вариантов цветом: вариант, входящий в правильный ответ, —
  // зелёный (даже если ученик его не выбрал — так видно, что пропущено);
  // вариант, который ученик выбрал, но который неверен, — красный.
  // Работает только для автопроверяемых типов — там $studentAns/$correctAns
  // это последовательности цифр-номеров вариантов (как для «квадратиков»
  // ниже); у ручных заданий "options" — просто справочный список, не трогаем.
  $isManualForOptions = in_array($curType, \App\Models\HomeworkTask::MANUAL_TYPES, true);
  $optionsCorrectSet = $isManualForOptions ? [] : (preg_split('//u', (string)$correctAns, -1, PREG_SPLIT_NO_EMPTY) ?: []);
  $optionsStudentSet = $isManualForOptions ? [] : (preg_split('//u', (string)$studentAns, -1, PREG_SPLIT_NO_EMPTY) ?: []);
@endphp

@if(!empty($options))
  <div class="mt-3 sm:mt-4 text-zinc-900 text-sm sm:text-base flex flex-col flex-wrap gap-2 sm:gap-3 items-start">
    @foreach($options as $optIdx => $opt)
      @php
        $optNum = (string)($optIdx + 1);
        $optIsCorrect  = in_array($optNum, $optionsCorrectSet, true);
        $optIsSelected = in_array($optNum, $optionsStudentSet, true);

        $optClasses = 'border-gray-200 bg-gray-50';
        if ($optIsCorrect) {
          $optClasses = 'border-apple-green-500 bg-apple-green-50 text-apple-green-700';
        } elseif ($optIsSelected) {
          $optClasses = 'border-apple-red-500 bg-apple-red-50 text-apple-red-700';
        }
      @endphp
      <div class="px-2 sm:px-3 py-0.5 sm:py-1 rounded-lg border {{ $optClasses }}">{{ $opt }}</div>
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

    // Правильный ответ для matching хранится в том же $t->answer, что и у
    // остальных авто-типов: строка цифр, ПОЗИЦИОННО — i-й символ = номер
    // элемента правой колонки, верный для i-го элемента левой (см.
    // AutoGrader::scoreOne() — сравнение идёт посимвольно по позиции).
    // Отдельного поля "правильное соответствие" в matches нет.
    $matchingCorrectDigits = preg_split('//u', (string)$correctAns, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  @endphp

  <div class="grid md:grid-cols-2 gap-4 sm:gap-6 mt-3 sm:mt-4 mb-4">
    <div class="rounded-xl border bg-white">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-zinc-700">{{ $cur->left_title ?? 'Левая колонка' }}</div>
      <div class="divide-y">
        @forelse($left as $iL => $val)
          @php
            $matchingCorrectNum = $matchingCorrectDigits[$iL] ?? null;
          @endphp
          <div class="relative px-3 py-2 sm:py-3 text-sm sm:text-base">
            @if($matchingCorrectNum !== null && $matchingCorrectNum !== '')
              {{-- Кружок с номером верного варианта из правой колонки —
                   абсолютно позиционирован и вынесен левее границы блока,
                   чтобы сразу бросался в глаза как отдельная пометка,
                   а не часть текста строки. --}}
              <div class="absolute -left-8 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-apple-green-500 text-white text-xs font-medium flex items-center justify-center shadow-sm">
                {{ $matchingCorrectNum }}
              </div>
            @endif
            <span class="text-zinc-500 mr-2">{{ $letters[$iL] ?? ($iL+1) }}.</span> {{ $val }}
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-zinc-500">Нет элементов</div>
        @endforelse
      </div>
    </div>

    <div class="rounded-xl border bg-white">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-zinc-700">{{ $cur->right_title ?? 'Правая колонка' }}</div>
      <div class="divide-y">
        @forelse($right as $iR => $val)
          <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-zinc-500 mr-2">{{ $iR+1 }}.</span> {{ $val }}
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-zinc-500">Нет элементов</div>
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
  $isManual  = in_array($curType, \App\Models\HomeworkTask::MANUAL_TYPES, true);

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
    'ok'      => 'border-apple-green-500',
    'partial' => 'border-apple-orange-500',
    'fail'    => 'border-apple-red-500',
    'pending' => 'border-zinc-300',
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
  {{-- АВТОПРОВЕРКА: выводим ответ ученика и правильный ответ «квадратиками».
       flex-wrap вместо grid-cols-1 md:grid-cols-2: если оба блока помещаются
       в одну строку по контенту (короткие ответы) — стоят рядом на любой
       ширине; если нет — переносятся, а не залипают в 1 колонку до md. --}}
  <div class="mt-5 flex flex-wrap gap-4">
    {{-- Ваш ответ (цветная обводка по статусу) --}}
    <div class="rounded-xl mt-2 flex-1 min-w-[160px]">
      <div class="sans text-xs text-zinc-500 mb-2">Ваш ответ</div>
      <div class="flex flex-wrap gap-2">
        @for($i=0; $i<$boxesLen; $i++)
          <div class="w-9 h-9 sm:w-10 sm:h-10 border-2 {{ $borderClass }} rounded-lg flex items-center justify-center text-base sm:text-lg font-medium select-none">
            {{ $up($stuChars[$i] ?? '') }}
          </div>
        @endfor
      </div>
    </div>

    {{-- Правильный ответ (всегда серая обводка) --}}
    <div class="rounded-xl mt-2 flex-1 min-w-[160px]">
      <div class="sans text-xs text-zinc-500 mb-2">Правильный ответ</div>
      <div class="flex flex-wrap gap-2">
        @for($i=0; $i<$boxesLen; $i++)
          <div class="w-9 h-9 sm:w-10 sm:h-10 border-2 border-zinc-300 rounded-lg flex items-center justify-center text-base sm:text-lg font-medium bg-white select-none">
            {{ $up($corrChars[$i] ?? '') }}
          </div>
        @endfor
      </div>
    </div>

  </div>

  {{-- Объяснение задания — заполняется админом в конструкторе (поле
       "Объяснение задания", только у авто-проверяемых типов), разбирает
       каждый пункт ответа: почему верно/неверно, для соотнесения — почему
       пара именно такая. Показываем только если заполнено — пустой блок
       с одним "—" ничего ученику не даёт. --}}
  @if(!empty($t->explanation))
    <div class="mt-4 rounded-xl bg-zinc-50 border border-zinc-200 p-3 px-4">
      <div class="text-xs text-zinc-500 mb-2">Объяснение</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($t->explanation) }}</div>
    </div>
  @endif
@else
  {{-- РУЧНАЯ ПРОВЕРКА: оставляем карточки по ширине (без «квадратиков») --}}
  <div class="mt-4 grid grid-cols-1 md:grid-cols-1 gap-4">
    <div class="rounded-xl bg-apple-blue-50 p-3 px-4">
      <div class="sans text-xs text-apple-blue-600 mb-2">Ваш ответ</div>
      <div class="sans text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($studentAns) }}</div>
    </div>

    {{-- Эти три блока показываются, только если куратор реально что-то
         заполнил — пустой "—" ученику ничего не даёт, лучше не показывать
         блок вовсе, чем пустую подпись. --}}
    {{-- ВРЕМЕННО скрыто по просьбе — блок "Образцовый ответ" для ручных
         заданий. Чтобы вернуть, раскомментировать код ниже.
    @if($hasScore && !$skipped && trim((string)$correctAns) !== '')
    <div class="rounded-xl bg-apple-green-50 p-3 px-4">
      <div class="sans text-xs text-apple-green-700 mb-2">Образцовый ответ</div>
      <div class="sans text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($correctAns) }}</div>
    </div>
    @endif
    --}}
    @if($hasScore && !$skipped && trim((string)$mentorNote) !== '')
    <div class="rounded-xl border border-gray-200 p-3 px-4">
      <div class="text-xs text-zinc-500 mb-2">Пояснение куратора</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($mentorNote) }}</div>
    </div>
    @endif
    @if($hasScore && !$skipped && trim((string)$mentorReason) !== '')
    <div class="rounded-xl border border-gray-200 p-3">
      <div class="text-xs text-zinc-500 mb-1">Обоснование баллов</div>
      <div class="text-sm whitespace-pre-wrap break-words">{{ $norm($mentorReason) }}</div>
    </div>
    @endif
  </div>
@endif



      </div>
    </x-ui.card>
  @empty
    <x-ui.card class="text-sm text-zinc-600">
      Заданий нет.
    </x-ui.card>
  @endforelse
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
    // Сплошное кольцо в стиле Apple Activity — та же техника, что и на
    // /student/mocks (makeSectionRings), но одно кольцо на карточку вместо
    // двух вложенных: тут каждая часть (авто/ручная) уже на своей карточке.
    // Второй датасет — невидимый (прозрачный), нужен только чтобы Chart.js
    // делил полосу кольца между двумя датасетами так же, как на /mocks
    // (там 2 реальных датасета делят её пополам) — иначе один датасет при
    // том же cutout занял бы вдвое более широкую полосу.
    function makeResultRing(canvas, percent, color, track) {
      new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          datasets: [
            {
              data: [percent, 100 - percent],
              backgroundColor: [color, track],
              borderColor: '#fff',
              borderWidth: 5,
              borderRadius: 12,
              weight: 1,
            },
            {
              data: [100],
              backgroundColor: ['transparent'],
              borderColor: 'transparent',
              borderWidth: 0,
              weight: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          rotation: -90,
          circumference: 360,
          animation: { duration: 600 },
          events: [],
          plugins: {
            legend: { display: false },
            tooltip: { enabled: false },
          },
        },
      });
    }

    // Инициализация всех .result-ring
    document.querySelectorAll('canvas.result-ring').forEach(cv => {
      const pct   = Number(cv.dataset.percent || 0);
      const color = cv.dataset.color || '#AF52DE';
      const track = cv.dataset.track || '#F1E1F9';
      makeResultRing(cv, pct, color, track);
    });
  })();
  </script>

  @if(session('just_submitted'))
    {{-- Разовое конфетти сразу после отправки всей домашки (флаг ставит SubmissionController::finishSubmit) --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      // GSAP подключён с defer — ждём DOMContentLoaded, чтобы он гарантированно успел
      // выполниться (иначе этот inline-скрипт, который парсится раньше, видит window.gsap
      // ещё не определённым и молча ничего не делает).
      if (typeof window.gsap === 'undefined') return;

      const colors = ['#7C3AED', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#EC4899'];
      const count = 60;
      const container = document.createElement('div');
      container.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:70;overflow:hidden;';
      document.body.appendChild(container);

      for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        const size = 6 + Math.random() * 6;
        const color = colors[Math.floor(Math.random() * colors.length)];
        el.style.cssText = 'position:absolute; left:50%; top:18%; width:' + size + 'px; height:' + (size * 0.4) + 'px; background:' + color + '; border-radius:2px;';
        container.appendChild(el);

        const dx = (Math.random() - 0.5) * window.innerWidth * 0.8;
        const dy = window.innerHeight * (0.5 + Math.random() * 0.5);
        const rotation = (Math.random() - 0.5) * 720;

        gsap.timeline({ onComplete: () => el.remove() })
          .to(el, {
            x: dx * 0.4,
            y: -80 - Math.random() * 80,
            rotation: rotation * 0.3,
            duration: .35 + Math.random() * .15,
            ease: 'power2.out',
          })
          .to(el, {
            x: dx,
            y: dy,
            rotation: rotation,
            opacity: 0,
            duration: 1.1 + Math.random() * .5,
            ease: 'power1.in',
          });
      }

      setTimeout(function () { container.remove(); }, 2500);
    });
    </script>
  @endif
@endsection
