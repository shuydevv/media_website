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
          // origIdx хранит позицию ДО сортировки — по ней строится
          // fallback-ключ t_{$i} для поиска в $perTaskRes, когда у задания нет id.
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

  // Куратор сохраняет оценки по заданиям сразу (автосейв), а 'checked' ставится
  // только по «Завершить проверку». Пока работа не завершена целиком, ученик не
  // должен видеть ни баллы, ни обоснование, ни комментарий по ручным заданиям —
  // иначе результат «протекает» по частям и может ещё поменяться. Прячем строки
  // результата у ручных заданий с непустым ответом (пустые ответы закрыты
  // нулём ещё при сдаче — см. Student\SubmissionController::finalize(), там
  // куратору нечего проверять) — всё ниже по шаблону читает уже $perTaskRes.
  if ($submission->status !== 'checked') {
      foreach ($manualTasks as $mt) {
          $mtid = $mt->id ?? ("t_{$mt->_origIdx}");
          $mAns = $answers[$mtid] ?? null;
          if (is_scalar($mAns) && trim((string)$mAns) !== '') {
              unset($perTaskRes[$mtid]);
          }
      }
  }

  // Одна строка на задание — статус, балл, номер. Из неё строятся и полоса
  // плиток в шапке, и счётчики, и фильтр разбора, чтобы все три места
  // гарантированно совпадали.
  //   ok / partial / fail — по баллу;
  //   wait — ручное задание без оценки куратора (или пропущенное им).
  $taskRows = $tasksCol->map(function ($t, $i) use ($perTaskRes, $manualTypes) {
      $tid      = $t->id ?? ("t_{$t->_origIdx}");
      $isManual = in_array($t->type ?? '', $manualTypes, true);
      $max      = (int)($t->max_score ?? 1);
      $row      = $perTaskRes[$tid] ?? [];
      $hasScore = array_key_exists('score', $row) && $row['score'] !== null;
      $skipped  = (bool)($row['skipped'] ?? false);

      if ($isManual && (!$hasScore || $skipped)) {
          $status = 'wait';
          $score  = null;
      } else {
          $score  = (int)($row['score'] ?? 0);
          $status = $score >= $max ? 'ok' : ($score > 0 ? 'partial' : 'fail');
      }

      return (object)[
          'task'     => $t,
          'tid'      => $tid,
          'no'       => $t->order ?? ($i + 1),
          'isManual' => $isManual,
          'max'      => $max,
          'score'    => $score,
          'status'   => $status,
      ];
  });

  $counts = ['ok' => 0, 'partial' => 0, 'fail' => 0, 'wait' => 0];
  foreach ($taskRows as $r) $counts[$r->status]++;
  $errorCount = $counts['partial'] + $counts['fail'];

  $hasPendingManual   = $counts['wait'] > 0;
  $studentStatusLabel = $hasPendingManual ? 'Ожидает проверки' : 'Проверено';

  $maxOf = fn($coll) => (int) $coll->sum(fn($t) => (int)($t->max_score ?? 1));
  $autoMax   = max(0, $maxOf($autoTasks));
  $manualMax = max(0, $maxOf($manualTasks));

  $autoScore = !is_null($submission->autocheck_score)
      ? (int)$submission->autocheck_score
      : (int)$taskRows->where('isManual', false)->sum('score');

  $manualScore = ($submission->status === 'checked' && !is_null($submission->manual_score))
      ? (int)$submission->manual_score
      : (int)$taskRows->where('isManual', true)->sum(fn($r) => $r->score ?? 0);

  $totalMax   = $autoMax + $manualMax;
  $totalScore = ($submission->status === 'checked' && !is_null($submission->total_score))
      ? (int)$submission->total_score
      : ($autoScore + $manualScore);
  $totalPct = $totalMax > 0 ? (int) min(100, max(0, round($totalScore * 100 / $totalMax))) : 0;

  // «1 балла / 2 баллов / 5 баллов» — родительный падеж после «из».
  $plural = function (int $n, array $forms) {
      $n = abs($n) % 100;
      $n1 = $n % 10;
      if ($n > 10 && $n < 20) return $forms[2];
      if ($n1 > 1 && $n1 < 5) return $forms[1];
      if ($n1 === 1) return $forms[0];
      return $forms[2];
  };

  // Палитра статусов — те же тона, что у x-ui.badge (см. /admin/design-system).
  $tone = [
    'ok'      => ['badge' => 'green',  'tile' => 'bg-apple-green-100 text-apple-green-800',   'dot' => 'bg-apple-green-500'],
    'partial' => ['badge' => 'orange', 'tile' => 'bg-apple-orange-100 text-apple-orange-800', 'dot' => 'bg-apple-orange-500'],
    'fail'    => ['badge' => 'red',    'tile' => 'bg-apple-red-50 text-apple-red-650',        'dot' => 'bg-apple-red-450'],
    'wait'    => ['badge' => 'gray',   'tile' => 'bg-zinc-100 text-zinc-500',                 'dot' => 'bg-zinc-300'],
  ];
  $legend = [
    'ok'      => 'Верно',
    'partial' => 'Частично',
    'fail'    => 'Неверно',
    'wait'    => 'Ждут проверки',
  ];

  // Фильтр разбора: по умолчанию — только ошибки, если они есть и это не
  // все задания подряд (тогда фильтр ничего не отсекает и не нужен).
  $showFilter    = $errorCount > 0 && $errorCount < $taskRows->count();
  $defaultFilter = $showFilter ? 'errors' : 'all';

  // Уже нормализовано контроллером (Homework::normalizeAttemptsAllowed) —
  // единственный источник истины на 2 попытки по умолчанию, тот же, что и
  // в SubmissionController::create().
  $attempts_allowed = (int) $homework->attempts_allowed;
  $attemptNo   = (int)($submission->attempt_no ?? 1);
  // По числу реально сданных попыток, а не по номеру открытой: со страницы
  // первой попытки при уже сданной второй кнопка не должна обещать ещё одну.
  $attemptsLeft = max(0, $attempts_allowed - max($attemptNo, $attempts->count()));
  $redoCount = $redoCount ?? 0;
  $retryInProgress = $retryInProgress ?? null;

  // Утилита нормализации многострочного текста
  $norm = function ($s) {
      if ($s === null || $s === '') return '—';
      $s = (string)$s;
      $s = preg_replace('/^\xEF\xBB\xBF/u', '', $s);
      $s = str_replace(["\r\n","\r"], "\n", $s);
      $s = str_replace("\xC2\xA0", ' ', $s);
      return trim($s) === '' ? '—' : $s;
  };
@endphp

<div class="max-w-6xl mx-auto px-4 py-6">

  @if($errors->has('attempts'))
    <div class="mb-4 rounded-xl border border-apple-orange-200 bg-apple-orange-50 text-apple-orange-800 px-4 py-3 text-sm">
      {{ $errors->first('attempts') }}
    </div>
  @endif

  @if($retryInProgress)
    <div class="mb-4 rounded-xl border border-apple-blue-200 bg-apple-blue-50 px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="text-sm text-apple-blue-800">
        Попытка № {{ $retryInProgress->attempt_no }} начата, но ещё не отправлена. Пока её не отправишь, в зачёте остаётся эта.
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <x-ui.button href="{{ route('student.submissions.create', $homework) }}" variant="accent" size="sm" class="!bg-apple-blue-500 hover:!bg-apple-blue-600 !py-2">
          Продолжить
        </x-ui.button>
        @if($retryInProgress->canBeCancelled())
          @include('student.submissions.partials.cancel-attempt', ['submission' => $retryInProgress, 'isMock' => ($homework->type ?? null) === 'mock'])
        @endif
      </div>
    </div>
  @endif

  {{-- ===== Заголовок страницы: название, статус, попытки ===== --}}
  <div class="mb-4">
    <h1 class="text-xl md:text-2xl font-medium text-zinc-900">
      {{ $homework->title ?? 'Домашнее задание' }}
    </h1>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <x-ui.badge :tone="$hasPendingManual ? 'orange' : 'green'">
          @if($hasPendingManual)
            <x-icon name="clock-stopwatch" class="w-3.5 h-3.5" />
          @else
            <x-icon name="check-circle" class="w-3.5 h-3.5" />
          @endif
          {{ $studentStatusLabel }}
        </x-ui.badge>
        @if($attempts->count() <= 1)
          <span class="text-sm text-zinc-500">Попытка № {{ $attemptNo }}</span>
        @endif
      </div>

      {{-- Переключатель между сданными попытками — сравнить, что изменилось. --}}
      @if($attempts->count() > 1)
        <div class="flex items-center gap-2 min-w-0">
          <span class="text-sm text-zinc-500 shrink-0">Попытки:</span>
          <x-ui.tabs>
            @foreach($attempts->sortBy('id') as $att)
              <x-ui.tab :href="route('student.submissions.show', $att)" :active="$att->id === $submission->id">
                № {{ $att->attempt_no ?? $loop->iteration }}
                <span class="text-zinc-400">· {{ ($awaitingReview[$att->id] ?? false) ? 'на проверке' : (int) $att->total_score . ' б.' }}</span>
              </x-ui.tab>
            @endforeach
          </x-ui.tabs>
        </div>
      @endif
    </div>
  </div>

  {{-- ===== Итог: кольцо с баллом, вердикт, действие, карта заданий ===== --}}
  @php
    // Цвет кольца — оценка результата. Пока письменная часть не проверена,
    // балл неполный: нейтральный синий, а не «красный» за непроверенное.
    $ringColor = $hasPendingManual ? 'text-apple-blue-500'
      : ($totalPct >= 80 ? 'text-apple-green-500' : ($totalPct >= 50 ? 'text-apple-orange-500' : 'text-apple-red-450'));
    $ringLen = 2 * M_PI * 42;

    $tasksTotal = $taskRows->count();
    $allCorrect = !$hasPendingManual && $errorCount === 0;
    if ($hasPendingManual) {
        $verdict = 'Ждём проверку куратора';
    } elseif ($allCorrect) {
        $verdict = 'Без ошибок';
    } else {
        $verdict = $errorCount . ' ' . $plural($errorCount, ['задание', 'задания', 'заданий']) . ' с ошибками';
    }
  @endphp
  <x-ui.card class="mb-8 !p-5 sm:!p-6">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-5">
      <div class="relative w-24 h-24 sm:w-28 sm:h-28 shrink-0">
        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90" aria-hidden="true">
          <circle cx="50" cy="50" r="42" fill="none" stroke-width="10" class="stroke-zinc-100" />
          <circle cx="50" cy="50" r="42" fill="none" stroke-width="10" stroke-linecap="round"
                  stroke="currentColor" class="{{ $ringColor }}"
                  stroke-dasharray="{{ round($ringLen * $totalPct / 100, 2) }} {{ round($ringLen, 2) }}" />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-2xl sm:text-3xl font-medium text-zinc-900 leading-none tabular-nums">{{ $totalScore }}</span>
          <span class="mt-1 text-xs text-zinc-500 tabular-nums">из {{ $totalMax }}</span>
        </div>
      </div>

      <div class="flex-1 min-w-[10rem]">
        <div class="text-lg sm:text-xl font-medium text-zinc-900">{{ $verdict }}</div>
        @if($allCorrect)
          <div class="mt-1 text-sm text-zinc-500">
            Верно {{ $tasksTotal }} из {{ $tasksTotal }} {{ $plural($tasksTotal, ['задания', 'заданий', 'заданий']) }}
          </div>
        @else
          <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600">
            @foreach($legend as $st => $label)
              @if($counts[$st] > 0)
                <span class="inline-flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full {{ $tone[$st]['dot'] }}"></span>
                  {{ $label }}: <span class="font-medium text-zinc-900 tabular-nums">{{ $counts[$st] }}</span>
                </span>
              @endif
            @endforeach
          </div>
        @endif
      </div>

      <div class="flex flex-col gap-2 w-full md:w-auto md:items-end shrink-0">
        @if($retryInProgress)
          {{-- Продолжить/отменить — в плашке над результатом. --}}
        @elseif($attemptsLeft > 0)
          @if($redoCount > 0)
            <x-ui.button href="{{ route('student.submissions.create', $homework) }}?retry=1&mode=mistakes" variant="accent" size="sm" class="!bg-apple-blue-500 hover:!bg-apple-blue-600" title="Верно решённые задания перенесутся в новую попытку">
              Перерешать ошибки ({{ $redoCount }})
            </x-ui.button>
            <a href="{{ route('student.submissions.create', $homework) }}?retry=1" class="text-sm text-zinc-500 hover:text-zinc-900 text-center md:text-right py-1">
              Перерешать всю работу
            </a>
          @else
            {{-- Без ошибок перерешивать незачем — кнопка не должна звать первой. --}}
            <x-ui.button href="{{ route('student.submissions.create', $homework) }}?retry=1" :variant="$allCorrect ? 'secondary' : 'accent'" size="sm" class="{{ $allCorrect ? '' : '!bg-apple-blue-500 hover:!bg-apple-blue-600' }}">
              Перерешать работу
            </x-ui.button>
          @endif
          <div class="text-xs text-zinc-400 text-center md:text-right">
            {{ $attemptsLeft === 1 ? 'Осталась 1 попытка' : 'Осталось попыток: ' . $attemptsLeft }}
          </div>
        @else
          <div class="text-sm text-zinc-400 text-center md:text-right">Попытки закончились</div>
        @endif
      </div>
    </div>

    @if($hasPendingManual)
      <div class="mt-5 flex items-start gap-2 rounded-xl bg-apple-orange-50 px-4 py-3 text-sm text-apple-orange-800">
        <x-icon name="clock-stopwatch" class="w-4 h-4 mt-0.5 shrink-0" />
        <span>Куратор ещё не проверил письменные задания ({{ $counts['wait'] }}). Итоговый балл обновится после проверки.</span>
      </div>
    @endif

    {{-- Карта заданий: цвет — статус, клик ведёт к разбору задания (балл —
         во всплывающей подсказке). Тестовую и письменную части подписываем,
         только когда в работе есть обе. --}}
    @if($taskRows->isNotEmpty())
      @php
        $groups = $taskRows->groupBy(fn($r) => $r->isManual ? 'manual' : 'auto');
        $groupTitles = ['auto' => 'Тестовая часть', 'manual' => 'Письменная часть'];
      @endphp
      <div class="mt-6 pt-5 border-t border-zinc-100 space-y-4">
        @foreach(['auto', 'manual'] as $g)
          @continue(!isset($groups[$g]))
          <div>
            @if($groups->count() > 1)
              <div class="text-xs text-zinc-500 mb-2">{{ $groupTitles[$g] }}</div>
            @endif
            <div class="flex flex-wrap gap-2">
              @foreach($groups[$g] as $r)
                <a href="#task-{{ $r->tid }}" data-task-link
                   title="Задание {{ $r->no }}: {{ $r->status === 'wait' ? 'ждёт проверки' : $r->score . ' из ' . $r->max }}"
                   class="{{ $tone[$r->status]['tile'] }} w-10 h-10 rounded-xl flex items-center justify-center text-sm font-medium tabular-nums hover:ring-2 hover:ring-zinc-300 transition">
                  {{ $r->no }}
                </a>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </x-ui.card>


  {{-- ===== Разбор заданий ===== --}}
  <div id="task-review">
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <h2 class="text-lg md:text-xl font-medium text-zinc-900">Разбор заданий</h2>
      @if($showFilter)
        <x-ui.tabs>
          <x-ui.tab data-review-filter="errors" :active="$defaultFilter === 'errors'">С ошибками · {{ $errorCount }}</x-ui.tab>
          <x-ui.tab data-review-filter="all" :active="$defaultFilter === 'all'">Все · {{ $taskRows->count() }}</x-ui.tab>
        </x-ui.tabs>
      @endif
    </div>

  @forelse($taskRows as $r)
    @php
      $t            = $r->task;
      $tid          = $r->tid;
      $type         = (string)($t->type ?? 'unknown');
      $max          = $r->max;
      $status       = $r->status;
      $isManual     = $r->isManual;
      $isError      = in_array($status, ['partial', 'fail'], true);

      $row      = $perTaskRes[$tid] ?? [];
      $hasScore = array_key_exists('score', $row) && $row['score'] !== null;
      $skipped  = (bool)($row['skipped'] ?? false);

      // Ответы
      $studentAns = $row['answer']  ?? ($submission->answers[$tid] ?? null);
      $correctAns = $row['correct'] ?? ($t->answer ?? null);

      // Пояснение куратора (может храниться в reason/comment)
      $mentorNote   = $row['comment'] ?? null;
      $mentorReason = $row['reason']  ?? '';

      // Медиа (для image_* типов)
      $mediaUrl = $storageUrl($t->image_path ?? null);

      $badgeText = match ($status) {
        'ok'      => "Верно · {$r->score}/{$max}",
        'partial' => "Частично · {$r->score}/{$max}",
        'fail'    => "Неверно · {$r->score}/{$max}",
        default   => 'Ожидает проверки',
      };
      $badgeShort = $status === 'wait' ? 'Ожидает проверки' : "{$r->score}/{$max}";

      $questionText = $t->question_text ?? null;
      $passageText  = $t->passage_text  ?? null;
    @endphp

    {{-- Верно решённые свёрнуты: ученику на этой странице нужны прежде всего ошибки. --}}
    <div id="task-{{ $tid }}" data-task-card data-status="{{ $status }}"
         class="scroll-mt-6 mb-3 {{ $defaultFilter === 'errors' && !$isError ? 'hidden' : '' }}">
      <details class="group rounded-2xl border border-gray-200 bg-white" @if($status !== 'ok') open @endif>
        <summary class="flex items-center justify-between gap-3 cursor-pointer list-none p-4 sm:px-5 [&::-webkit-details-marker]:hidden">
          <span class="flex items-center gap-2 min-w-0">
            <x-icon name="chevron-right" class="w-4 h-4 text-zinc-400 shrink-0 transition-transform group-open:rotate-90" />
            <span class="text-base font-medium text-zinc-900">Задание {{ $r->no }}</span>
          </span>
          <x-ui.badge :tone="$tone[$status]['badge']" class="shrink-0">
            <span class="sm:hidden">{{ $badgeShort }}</span>
            <span class="hidden sm:inline">{{ $badgeText }}</span>
          </x-ui.badge>
        </summary>

        <div class="px-4 pb-4 sm:px-5 sm:pb-5">

      {{-- Текст/медиа задания (если есть). Картиночные типы всегда попадают
           сюда, даже без текста и без реальной картинки — тогда ниже
           покажется заглушка вместо картинки, а не пустой блок. --}}
      @if($questionText || $passageText || $mediaUrl || in_array($type, ['image_auto','image_manual']))
        <div class="mb-5 space-y-3">
          @if($passageText)
            <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-200 text-sm sm:text-base text-zinc-700 whitespace-pre-wrap">{{ $norm($passageText) }}</div>
          @endif
          @if($questionText)
            <div class="text-sm sm:text-base text-zinc-700 whitespace-pre-wrap">{{ $norm($questionText) }}</div>
          @endif
          {{-- Тот же принцип, что и в самом визарде (task-prompt.blade.php):
               если типу положена картинка, но её не загрузили — заглушка
               вместо пустоты. --}}
          @if(in_array($type, ['image_auto','image_manual']))
            @if($mediaUrl)
              <img src="{{ $mediaUrl }}" alt="" class="w-full max-h-[320px] object-contain rounded-xl border border-zinc-200">
            @else
              <div class="w-full h-40 rounded-xl border border-zinc-200 bg-zinc-100 flex items-center justify-center text-zinc-300">
                <x-icon name="image-01" class="w-10 h-10" />
              </div>
            @endif
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
  @endphp

  <div class="overflow-auto rounded-xl border border-zinc-200 mb-5">
    <table class="min-w-full border-collapse">
      @if(!empty($cols2))
        <thead class="bg-zinc-50">
          <tr>
            @foreach($cols2 as $c)
              <th class="border border-zinc-200 px-3 py-2 sm:py-3 text-left text-xs sm:text-sm font-medium text-zinc-700">{{ $c }}</th>
            @endforeach
          </tr>
        </thead>
      @endif
      <tbody>
        @forelse($rows2 as $tRow)
          <tr>
            @foreach((array)$tRow as $cell)
              <td class="px-3 py-2 sm:py-3 align-top border border-zinc-200">
                <div class="text-sm sm:text-base text-zinc-700 whitespace-pre-wrap">{{ (string)$cell }}</div>
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
  // источники вариантов — как на странице create
  $rawOptions = $t->options ?? $t->image_auto_options ?? null;
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

  // Подсветка вариантов цветом: вариант, входящий в правильный ответ, —
  // зелёный (даже если ученик его не выбрал — так видно, что пропущено);
  // вариант, который ученик выбрал, но который неверен, — красный.
  // Работает только для автопроверяемых типов — там $studentAns/$correctAns
  // это последовательности цифр-номеров вариантов (как для «квадратиков»
  // ниже); у ручных заданий "options" — просто справочный список, не трогаем.
  $optionsCorrectSet = $isManual ? [] : (preg_split('//u', (string)$correctAns, -1, PREG_SPLIT_NO_EMPTY) ?: []);
  $optionsStudentSet = $isManual ? [] : (preg_split('//u', (string)$studentAns, -1, PREG_SPLIT_NO_EMPTY) ?: []);
@endphp

@if(!empty($options))
  <div class="mb-5 flex flex-col items-start gap-2 text-sm sm:text-base text-zinc-900">
    @foreach($options as $optIdx => $opt)
      @php
        $optNum = (string)($optIdx + 1);
        $optIsCorrect  = in_array($optNum, $optionsCorrectSet, true);
        $optIsSelected = in_array($optNum, $optionsStudentSet, true);

        $optClasses = 'border-zinc-200 bg-zinc-50';
        if ($optIsCorrect) {
          $optClasses = 'border-apple-green-500 bg-apple-green-50 text-apple-green-800';
        } elseif ($optIsSelected) {
          $optClasses = 'border-apple-red-500 bg-apple-red-50 text-apple-red-650';
        }
      @endphp
      <div class="px-3 py-1 rounded-lg border {{ $optClasses }}">{{ $opt }}</div>
    @endforeach
  </div>
@endif

@if($type === 'matching')
  @php
    $left  = [];
    $right = [];
    // как на странице create
    if (!empty($t->matches['left'])) {
      $left = is_array($t->matches['left'])
        ? $t->matches['left']
        : preg_split("/\r\n|\r|\n/", (string)$t->matches['left']);
    }
    if (!empty($t->matches['right'])) {
      $right = is_array($t->matches['right'])
        ? $t->matches['right']
        : preg_split("/\r\n|\r|\n/", (string)$t->matches['right']);
    }
    $letters = ['А','Б','В','Г','Д','Е','Ж','З','И','К','Л','М'];

    // Правильный ответ для matching хранится в том же $t->answer, что и у
    // остальных авто-типов: строка цифр, ПОЗИЦИОННО — i-й символ = номер
    // элемента правой колонки, верный для i-го элемента левой (см.
    // AutoGrader::scoreOne() — сравнение идёт посимвольно по позиции).
    // Отдельного поля "правильное соответствие" в matches нет.
    $matchingCorrectDigits = preg_split('//u', (string)$correctAns, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  @endphp

  <div class="grid md:grid-cols-2 gap-4 mb-5">
    <div class="rounded-xl border border-zinc-200">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-zinc-700 border-b border-zinc-200">{{ $t->left_title ?? 'Левая колонка' }}</div>
      <div class="divide-y divide-zinc-200">
        @forelse($left as $iL => $val)
          @php $matchingCorrectNum = $matchingCorrectDigits[$iL] ?? null; @endphp
          <div class="flex items-start gap-2 px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-zinc-500 shrink-0">{{ $letters[$iL] ?? ($iL+1) }}.</span>
            <span class="flex-1">{{ $val }}</span>
            {{-- Номер верного элемента из правой колонки — справа в строке. --}}
            @if($matchingCorrectNum !== null && $matchingCorrectNum !== '')
              <span class="shrink-0 w-6 h-6 rounded-full bg-apple-green-100 text-apple-green-800 text-xs font-medium flex items-center justify-center" title="Верное соответствие">
                {{ $matchingCorrectNum }}
              </span>
            @endif
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-zinc-500">Нет элементов</div>
        @endforelse
      </div>
    </div>

    <div class="rounded-xl border border-zinc-200">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-zinc-700 border-b border-zinc-200">{{ $t->right_title ?? 'Правая колонка' }}</div>
      <div class="divide-y divide-zinc-200">
        @forelse($right as $iR => $val)
          <div class="flex items-start gap-2 px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-zinc-500 shrink-0">{{ $iR+1 }}.</span>
            <span class="flex-1">{{ $val }}</span>
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-zinc-500">Нет элементов</div>
        @endforelse
      </div>
    </div>
  </div>
@endif

@if(!$isManual)
  @php
    // «Квадратики» ответа. Каждая клетка ответа ученика красится по тому же
    // правилу, что и у AutoGrader::scoreAuto(): при важном порядке — сверка
    // по позиции, без порядка — есть ли такая цифра в правильном ответе
    // (с учётом повторов). Ответ словом (image_auto с буквами) сверяется
    // целиком — красим все клетки по итоговому статусу.
    $toChars = function ($s) {
      $s = mb_strtolower(preg_replace('/\s+/u', '', (string)($s ?? '')));
      return preg_split('//u', strtr($s, ['ё' => 'е']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    };
    $stuChars  = $toChars($studentAns);
    $corrChars = $toChars($correctAns);
    $orderMatters = in_array($type, ['matching', 'table'], true) || (bool)($t->order_matters ?? false);
    $wholeWord = $type === 'image_auto' && preg_match('/[a-zа-я]/u', implode('', $stuChars));

    $pool = array_count_values($corrChars);
    $cellOk = [];
    foreach ($stuChars as $i => $ch) {
      if ($wholeWord) {
        $cellOk[$i] = $status === 'ok';
      } elseif ($orderMatters) {
        $cellOk[$i] = ($corrChars[$i] ?? null) === $ch;
      } else {
        $cellOk[$i] = ($pool[$ch] ?? 0) > 0;
        if ($cellOk[$i]) $pool[$ch]--;
      }
    }
    $boxClass = 'w-10 h-10 rounded-lg border-2 flex items-center justify-center text-lg font-medium select-none tabular-nums';
  @endphp

  <div class="flex flex-wrap gap-x-8 gap-y-4">
    <div>
      <div class="text-xs text-zinc-500 mb-2">Твой ответ</div>
      @if($stuChars === [])
        <div class="h-10 flex items-center text-sm text-zinc-400">Нет ответа</div>
      @else
        <div class="flex flex-wrap gap-2">
          @foreach($stuChars as $i => $ch)
            <div class="{{ $boxClass }} {{ $cellOk[$i] ? 'border-apple-green-500 bg-apple-green-50 text-apple-green-800' : 'border-apple-red-450 bg-apple-red-50 text-apple-red-650' }}">
              {{ mb_strtoupper($ch) }}
            </div>
          @endforeach
        </div>
      @endif
    </div>

    {{-- Правильный ответ — только когда ответ ученика неверен: при верном
         оба ряда совпадают и второй ничего не добавляет. --}}
    @if($status !== 'ok' && $corrChars !== [])
      <div>
        <div class="text-xs text-zinc-500 mb-2">Правильный ответ</div>
        <div class="flex flex-wrap gap-2">
          @foreach($corrChars as $ch)
            <div class="{{ $boxClass }} border-zinc-300 bg-white text-zinc-900">{{ mb_strtoupper($ch) }}</div>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  {{-- Объяснение задания — заполняется админом в конструкторе (поле
       "Объяснение задания", только у авто-проверяемых типов), разбирает
       каждый пункт ответа. Показываем только если заполнено. --}}
  @if(!empty($t->explanation))
    <div class="mt-5 rounded-xl bg-zinc-50 border border-zinc-200 px-4 py-3">
      <div class="text-xs text-zinc-500 mb-2">Объяснение</div>
      <div class="text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($t->explanation) }}</div>
    </div>
  @endif
@else
  <div class="space-y-3">
    <div class="rounded-xl bg-apple-blue-50 px-4 py-3">
      <div class="text-xs text-apple-blue-700 mb-2">Твой ответ</div>
      <div class="text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($studentAns) }}</div>
    </div>

    {{-- Эти три блока показываются, только если куратор реально что-то
         заполнил — пустой "—" ученику ничего не даёт. --}}
    @if($hasScore && !$skipped && trim((string)$mentorReason) !== '')
      <div class="rounded-xl border border-zinc-200 px-4 py-3">
        <div class="text-xs text-zinc-500 mb-2">Обоснование баллов</div>
        <div class="text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($mentorReason) }}</div>
      </div>
    @endif
    @if($hasScore && !$skipped && trim((string)$mentorNote) !== '')
      <div class="rounded-xl border border-zinc-200 px-4 py-3">
        <div class="text-xs text-zinc-500 mb-2">Пояснение куратора</div>
        <div class="text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($mentorNote) }}</div>
      </div>
    @endif
    {{-- Образцовый ответ — последним, после обоснования баллов и пояснения
         куратора: ученик сначала читает разбор своей работы, потом сверяется
         с эталоном. Только после проверки (до неё эталон был бы подсказкой). --}}
    @if($hasScore && !$skipped && trim((string)$correctAns) !== '')
      <div class="rounded-xl bg-apple-green-50 px-4 py-3">
        <div class="text-xs text-apple-green-800 mb-2">Образцовый ответ</div>
        <div class="text-sm text-zinc-700 whitespace-pre-wrap break-words">{{ $norm($correctAns) }}</div>
      </div>
    @endif
  </div>
@endif

        </div>
      </details>
    </div>
  @empty
    <x-ui.card class="text-sm text-zinc-600">
      Заданий нет.
    </x-ui.card>
  @endforelse
  </div>

</div>
@endsection

@section('scripts')
  <script>
  (function () {
    // Фильтр разбора «С ошибками / Все» и переход к заданию по плитке.
    // Классы активной/неактивной вкладки — те же, что в x-ui.tab.
    var ACTIVE = ['bg-white', 'text-zinc-900', 'shadow-sm', 'font-medium'];
    var IDLE = ['text-zinc-500', 'hover:text-zinc-900'];

    function applyFilter(filter) {
      document.querySelectorAll('[data-task-card]').forEach(function (card) {
        var isError = card.dataset.status === 'fail' || card.dataset.status === 'partial';
        card.classList.toggle('hidden', filter === 'errors' && !isError);
      });
      document.querySelectorAll('[data-review-filter]').forEach(function (btn) {
        var on = btn.dataset.reviewFilter === filter;
        btn.classList.remove.apply(btn.classList, on ? IDLE : ACTIVE);
        btn.classList.add.apply(btn.classList, on ? ACTIVE : IDLE);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }

    document.querySelectorAll('[data-review-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () { applyFilter(btn.dataset.reviewFilter); });
    });

    // Плитка может вести к заданию, скрытому фильтром или свёрнутому, —
    // сначала показываем и раскрываем, потом прокручиваем.
    document.querySelectorAll('[data-task-link]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        var hash = link.getAttribute('href');
        var card = document.getElementById(hash.slice(1));
        if (!card) return;
        e.preventDefault();
        if (card.classList.contains('hidden')) applyFilter('all');
        var details = card.querySelector('details');
        if (details) details.open = true;
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', hash);
      });
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
