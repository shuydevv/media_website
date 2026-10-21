{{-- resources/views/mentor/review/show.blade.php --}}
@extends('layouts.main')

@section('title', 'Проверка домашней работы — Школа Полтавского')

@section('content')

@php
  /** @var \App\Models\Submission $submission */
  $submission->loadMissing(['user','homework.course','homework.lesson.courseSession.course']);

  $user = auth()->user();
  $isAdmin  = (int) $user->role === \App\Models\User::ROLE_ADMIN;
  $isMentor = (int) $user->role === \App\Models\User::ROLE_MENTOR;

  $homework = $submission->homework;
  $student  = $submission->user;

  // tasks может быть JSON-строкой — превратим в массив
  $tasksRaw = $homework->tasks ?? [];
  if (is_string($tasksRaw)) {
      $decoded = json_decode($tasksRaw, true);
      $tasksRaw = is_array($decoded) ? $decoded : [];
  }

  // Нормализуем каждый элемент к объекту
  $tasksCol = collect($tasksRaw)->map(function ($t) {
      if (is_array($t))  return (object)$t;
      if (is_object($t)) return $t;
      if (is_string($t)) {
          $one = json_decode($t, true);
          return (object)($one ?: []);
      }
      return (object)[];
  });

  $manualTypes = \App\Models\HomeworkTask::MANUAL_TYPES;
  $autoTasks   = $tasksCol->filter(fn($t) => !in_array(($t->type ?? ''), $manualTypes, true))->values();
  $manualTasks = $tasksCol->filter(fn($t) =>  in_array(($t->type ?? ''), $manualTypes, true))->values();

  // Данные попытки
  $answers      = $submission->answers ?? [];
  $perTaskRes   = $submission->per_task_results ?? [];

  // Прошлая сданная попытка — показать рядом с ответом, что ученику
  // ставили раньше; и начатая, но не сданная новая — предупреждение.
  $prevAttempt  = $submission->previousFinished();
  $prevAnswers  = $prevAttempt?->answers ?? [];
  $prevPerTask  = $prevAttempt?->per_task_results ?? [];
  $newerRetry   = $submission->newerInProgress();
  $aiDrafts     = $submission->ai_drafts ?? [];
  $aiFrozenHash = $submission->ai_frozen_hash ?? [];
  $aiEnabled    = app(\App\Service\Homework\ClaudeGrader::class)->isEnabled();

  // Контекст работы: курс/урок, когда сдана и успела ли к дедлайну.
  $course      = $homework->course ?? $homework->lesson?->courseSession?->course;
  $lesson      = $homework->lesson;
  $submittedAt = $submission->submitted_at;
  $dueAt       = $homework->due_at;
  $lateHours   = ($submittedAt && $dueAt && $submittedAt->gt($dueAt)) ? (int) ceil($dueAt->diffInMinutes($submittedAt) / 60) : 0;
  $lateText    = $lateHours >= 24 ? 'на ' . intdiv($lateHours, 24) . ' дн.' : 'на ' . $lateHours . ' ч';

  // Карточка ученика: курсы, средний результат, слабые номера.
  $studentSummary = $student ? app(\App\Service\Homework\StudentReviewSummary::class)->for($student, $submission) : null;

  // Время на задание и подсказка — пишутся при решении (Student\SubmissionController, task_meta).
  $taskMeta = $submission->task_meta ?? [];
  $formatSeconds = fn (int $s) => $s < 60 ? 'меньше минуты' : (intdiv($s, 60) . ' мин');

  // Итог «сейчас»: автопроверка + баллы письменной части, выставленные на странице
  // (пересчитывается в JS по мере проверки, см. updateTotal()).
  $autoScoreNow = (int) ($submission->autocheck_score ?? 0);
  $totalMax     = $autoTasks->sum(fn ($t) => (int) ($t->max_score ?? 1)) + $manualTasks->sum(fn ($t) => (int) ($t->max_score ?? 3));

  $get = function($arr, $id, $key, $def=null) {
      return $arr[$id][$key] ?? $def;
  };

  // Таймер блокировки
  $lockUntil   = optional($submission->lock_expires_at);
  $now         = now();
  $lockSeconds = $lockUntil ? max(0, $lockUntil->diffInSeconds($now, false) * -1) : 0;

  // Когда задание считаем «закрытым» для кнопки Next
  $isTaskClosed = function($tid) use ($perTaskRes) {
      $skipped = (bool)($perTaskRes[$tid]['skipped'] ?? false);
      $hasAny  = array_key_exists($tid, $perTaskRes)
                 && (
                      array_key_exists('score',   $perTaskRes[$tid]) && $perTaskRes[$tid]['score'] !== null
                      || array_key_exists('reason',  $perTaskRes[$tid]) && $perTaskRes[$tid]['reason'] !== null
                      || array_key_exists('comment', $perTaskRes[$tid]) && $perTaskRes[$tid]['comment'] !== null
                    );
      return $skipped || $hasAny;
  };

  $allManualClosed = true;
  $manualTotal = $manualTasks->count();
  $manualClosedCount = 0;
  foreach ($manualTasks as $i => $t) {
      $tid = (string)($t->id ?? $t->task_id ?? "t_manual_{$i}");
      if ($isTaskClosed($tid)) {
          $manualClosedCount++;
      } else {
          $allManualClosed = false;
      }
  }
@endphp

<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

  {{-- Хедер --}}
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold mb-1">Проверка домашней работы</h1>
      <div class="text-sm text-gray-600">
        Ученик: <span class="font-medium text-gray-800">{{ $student?->full_name ?? ('ID '.$submission->user_id) }}</span>
        <span class="mx-2">•</span>
        Работа: <span class="font-medium text-gray-800">{{ $homework->title ?? 'Без названия' }}</span>
        <span class="mx-2">•</span>
        Попытка № {{ $submission->attempt_no ?? 1 }}
      </div>
      <div class="mt-1 text-sm text-gray-600">
        @if($course)
          Курс: <span class="text-gray-800">{{ $course->title }}</span>
        @endif
        @if($lesson)
          <span class="mx-2">•</span>Урок: <span class="text-gray-800">{{ $lesson->title }}</span>
        @endif
      </div>
      <div class="mt-1 text-sm text-gray-600">
        Сдано: <span class="text-gray-800">{{ $submittedAt?->format('d.m.Y H:i') ?? '—' }}</span>
        @if($dueAt)
          <span class="mx-2">•</span>Дедлайн: <span class="text-gray-800">{{ $dueAt->format('d.m.Y H:i') }}</span>
          @if($lateHours > 0)
            <span class="ml-2 inline-flex rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs text-red-700">с опозданием {{ $lateText }}</span>
          @elseif($submittedAt)
            <span class="ml-2 inline-flex rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs text-green-700">вовремя</span>
          @endif
        @endif
      </div>

      {{-- Мини-карточка ученика --}}
      @if($studentSummary)
        <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            @if($studentSummary['courses'])
              <span>Курсы: <span class="text-gray-900">{{ implode(', ', $studentSummary['courses']) }}</span></span>
            @endif
            <span>Проверено работ: <span class="text-gray-900">{{ $studentSummary['checked'] }}</span></span>
            @if($studentSummary['recent_percent'] !== null)
              <span>
                Средний результат за последние {{ $studentSummary['recent_count'] }}:
                <span class="font-semibold {{ $studentSummary['recent_percent'] < 50 ? 'text-red-700' : ($studentSummary['recent_percent'] < 75 ? 'text-amber-700' : 'text-green-700') }}">{{ $studentSummary['recent_percent'] }}%</span>
              </span>
            @endif
            @if($isAdmin)
              <a href="{{ route('admin.user.show', $student) }}" class="underline text-gray-600 hover:text-gray-900">Профиль ученика</a>
            @endif
          </div>
          @if($studentSummary['numbers'])
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
              <span class="text-xs text-gray-500">По номерам (письменная часть):</span>
              @foreach($studentSummary['numbers'] as $n)
                <span class="rounded-full border px-2 py-0.5 text-xs {{ $n['weak'] ? 'border-red-200 bg-red-50 text-red-700 font-medium' : 'border-gray-200 bg-white text-gray-600' }}"
                      title="Набрано {{ $n['score'] }} из {{ $n['max'] }} за {{ $n['count'] }} отв.">
                  №{{ $n['number'] }}: {{ $n['percent'] }}%
                </span>
              @endforeach
            </div>
            @if(collect($studentSummary['numbers'])->contains('weak', true))
              <div class="mt-1 text-xs text-red-700">Красным — слабые места (меньше 60% баллов хотя бы за 2 ответа): на них стоит обратить внимание в комментарии.</div>
            @endif
          @endif
        </div>
      @endif

      @if($newerRetry)
        <div class="mt-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm">
          Ученик сейчас перерешивает эту работу (попытка № {{ $newerRetry->attempt_no }}, начата {{ $newerRetry->created_at?->format('d.m H:i') }}, ещё не отправлена).
          Если он её отправит, проверять эту попытку будет не нужно — итог считается по последней.
        </div>
      @endif

      @if($submission->isSuperseded())
        <div class="mt-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm">
          Ученик уже сдал более новую попытку по этой работе — эту проверять не нужно, итог считается по последней.
        </div>
      @endif

      @foreach (['success'=>'green','error'=>'red','warning'=>'yellow','info'=>'blue'] as $k=>$clr)
        @if (session($k))
          <div class="mt-3 rounded-lg bg-{{ $clr }}-50 border border-{{ $clr }}-200 text-{{ $clr }}-800 px-3 py-2">
            {{ session($k) }}
          </div>
        @endif
      @endforeach
    </div>

    {{-- таймер лока в шапке --}}
    <div class="shrink-0 flex flex-col items-end gap-2">
      <div class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium">
        Проверено: <span id="progress-count">{{ $manualClosedCount }}</span> из {{ $manualTotal }}
      </div>
      <div class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium"
           title="Автопроверка ({{ $autoScoreNow }}) + баллы, выставленные за письменную часть">
        Итог сейчас: <span id="total-now" data-auto="{{ $autoScoreNow }}">—</span> из {{ $totalMax }}
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm">
        <div class="text-gray-600">Лок до:</div>
        <div class="font-semibold">
          <span id="lock-until-text">
            {{ optional($submission->lock_expires_at)->format('H:i') ?? '—' }}
          </span>
        </div>
        @if(($submission->lock_expires_at && now()->lt($submission->lock_expires_at)))
          <div class="text-xs text-gray-500 mt-1">
            Осталось: <span id="lock-countdown">—:—</span>
          </div>
        @endif
        @if($submission->locked_by && $submission->locked_by !== $user->id && now()->lt($submission->lock_expires_at))
          <div class="text-xs text-amber-700 mt-1">
            Занято: {{ $submission->lockedByUser?->name ?? ('ID '.$submission->locked_by) }}
          </div>
        @endif
      </div>

      {{-- Удаление попытки целиком — только админ, не куратор. --}}
      @if($isAdmin)
        <form action="{{ route('mentor.submissions.destroy', $submission) }}" method="POST"
              onsubmit="return confirm('Удалить эту попытку сдачи безвозвратно? Ответы, оценки и комментарии куратора будут потеряны.');">
          @csrf
          @method('DELETE')
          <button type="submit" class="text-sm text-red-600 hover:underline">Удалить попытку</button>
        </form>
      @endif
    </div>
  </div>

  {{-- Автопроверка (если есть) --}}
  <details class="rounded-2xl border border-gray-200 bg-white p-4">
    <summary class="cursor-pointer select-none text-sm text-gray-700">
      Показать первую часть (автопроверка)
    </summary>
    <div class="mt-3 grid gap-3">
      @forelse($autoTasks as $i => $t)
        @php
          $tid     = $t->id ?? ("t_auto_$i");
          $max     = (int)($t->max_score ?? 1);
          $score   = (int)($perTaskRes[$tid]['score'] ?? 0);
          $ans     = (string)($answers[$tid] ?? '');
          $correct = (string)($t->answer ?? '');
        @endphp
        <div class="rounded-xl border border-gray-200 bg-white p-3">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">№ {{ $t->order ?? ($i+1) }}</div>
            <div class="text-sm text-gray-700">
              Баллы: <span class="font-semibold">{{ $score }} / {{ $max }}</span>
            </div>
          </div>
          <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
              <div class="text-gray-500">Ответ ученика</div>
              <div class="font-mono">{{ $ans !== '' ? e($ans) : '—' }}</div>
            </div>
            <div>
              <div class="text-gray-500">Правильный ответ</div>
              <div class="font-mono">{{ $correct !== '' ? e($correct) : '—' }}</div>
            </div>
          </div>
        </div>
      @empty
        <div class="text-sm text-gray-500">Автопроверяемых заданий нет.</div>
      @endforelse
    </div>
  </details>

  {{-- Письменная часть --}}
  @forelse($manualTasks as $i => $t)
    @php
      $tid         = $t->id ?? ("t_manual_$i");
      $maxScore    = (int)($t->max_score ?? 3);
      $studentAns  = (string)($answers[$tid] ?? '');

      // История прошлых попыток студента по этому номеру задания
      $taskHistory = app(\App\Service\Homework\StudentTaskHistory::class)
          ->forTaskNumber((int)$student->id, $t->number ?? null, (int)$submission->id);

      // Критерии: общие для (категория, номер) задания, см. TaskCriteria
      $taskIdForDb = $t->task_id ?? $t->id ?? null;
      $taskRow     = null;
      if ($taskIdForDb !== null && is_numeric($taskIdForDb)) {
          $taskRow = \App\Models\Task::select(['id','category_id','number','criteria_override'])->find($taskIdForDb);
      }

      // сырой текст критериев из БД (может быть plain-text или JSON)
      $criteriaText = (string) optional($taskRow)->resolved_criteria;

      // пробуем как JSON (мог быть сохранён как структура)
      $decoded = json_decode($criteriaText, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
          // на случай «задвоенных» экранирований в БД
          $criteriaText = preg_replace('/\\\\{2,}/', '\\', trim($criteriaText, "\" \t\n\r\0\x0B"));
          $decoded = json_decode($criteriaText, true);
      }
      if (is_array($decoded)) {
          $lines = [];
          if (function_exists('array_is_list') ? array_is_list($decoded) : array_keys($decoded) === range(0, count($decoded)-1)) {
              foreach ($decoded as $v) {
                  $lines[] = is_array($v) ? implode(' ', $v) : (string) $v;
              }
          } else {
              foreach ($decoded as $k => $v) {
                  $prefix  = is_string($k) && $k !== '' ? ($k . ': ') : '';
                  $lines[] = $prefix . (is_array($v) ? implode(' ', $v) : (string) $v);
              }
          }
          $criteriaText = implode("\n", array_filter(array_map('trim', $lines)));
      }
      $criteriaLines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\r|\n/", (string) $criteriaText))));

      // Результаты/драфты
      $dbScore     = $get($perTaskRes, $tid, 'score', null);
      $dbReason    = $get($perTaskRes, $tid, 'reason', null);
      $dbComment   = $get($perTaskRes, $tid, 'comment', null);
      $isSkipped   = (bool)($perTaskRes[$tid]['skipped'] ?? false);

      $aiDraft     = $aiDrafts[$tid] ?? [];
      $aiScore     = $aiDraft['score'] ?? null;
      $aiRationaleFull = $aiDraft['rationale'] ?? $aiDraft['explanation'] ?? '';
      $aiCommentFull   = $aiDraft['comment']   ?? $aiDraft['recommendation'] ?? '';

      $curScore    = is_numeric($dbScore) ? $dbScore : (is_numeric($aiScore) ? $aiScore : null);
      $curReason   = ($dbReason !== null) ? $dbReason : $aiRationaleFull;
      $curComment  = ($dbComment !== null) ? $dbComment : $aiCommentFull;

      $frozenHash  = $aiFrozenHash[$tid] ?? null;
      $currentHash = sha1(json_encode([
          'score'      => $aiScore,
          'rationale'  => $aiRationaleFull,
          'comment'    => $aiCommentFull,
      ], JSON_UNESCAPED_UNICODE));
      $needConsent = $frozenHash && $frozenHash === $currentHash;

      $initialState = ($isSkipped || ($dbScore !== null || $dbReason !== null || $dbComment !== null)) ? 'done' : 'idle';
    @endphp

    <div
      class="review-card relative rounded-2xl bg-white p-5 transition-shadow border-4
            @if($isSkipped) border-red-100
            @elseif($initialState==='done') border-green-100
            @else border-gray-200 @endif"
      data-state="{{ $initialState }}"
      data-skipped="{{ $isSkipped ? '1' : '0' }}"
    >

      <div class="flex items-start justify-between gap-4 mb-3">
        <div>
          <div class="text-xs text-gray-500">Задание (ручная проверка)</div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold">№ {{ $t->order ?? ($i+1) }}</h2>
            @if($isSkipped)
              <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                Пропущено
              </span>
            @endif
            @if(!empty($perTaskRes[$tid]['carried']))
              {{-- «Перерешать ошибки»: ученик не решал это задание заново, ответ
                   (и оценка, если она уже была) перенесены из прошлой попытки. --}}
              <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-600">
                Из прошлой попытки
              </span>
            @endif
          </div>
        </div>

        <div class="flex gap-6">
          {{-- AI-эксперт --}}
          @php
            $hasAi = ($aiRationaleFull && trim($aiRationaleFull) !== '') || ($aiCommentFull && trim($aiCommentFull) !== '') || is_numeric($aiScore);
          @endphp
          <div class="ai-panel min-w-0 flex-1 rounded-xl border {{ $hasAi ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-gray-200 bg-gray-50 text-gray-700' }} px-3 py-2 text-sm">
            <div class="font-medium mb-1">AI-эксперт</div>
            <div class="ai-empty text-sm {{ $hasAi ? 'hidden' : '' }}">
              @if(($aiDraft['status'] ?? null) === 'pending')
                ИИ-проверка в очереди — черновик появится автоматически. Можно не ждать и проверить вручную.
              @elseif(($aiDraft['status'] ?? null) === 'exported')
                Выгружено на ИИ-проверку в Claude ({{ $aiDraft['exported_at'] ?? '' }}) — черновик появится после загрузки результата. Можно не ждать и проверить вручную.
              @elseif(($aiDraft['status'] ?? null) === 'error')
                ИИ-проверка не удалась: {{ $aiDraft['error'] ?? '' }} Нажмите «AI-проверка», чтобы попробовать ещё раз.
              @else
                Пока без рекомендаций. Нажмите «AI-проверка» или заполните поля вручную.
              @endif
            </div>
            <div class="ai-score-display {{ $hasAi ? '' : 'hidden' }}">
              Рекомендованные баллы: {{ is_numeric($aiScore) ? $aiScore : '—' }}
            </div>
            {{-- Флаг «модель не уверена» и разбор по элементам видит только куратор. --}}
            <div class="ai-review mt-1 rounded-lg bg-amber-100 px-2 py-1 text-amber-900 {{ !empty($aiDraft['needs_review']) ? '' : 'hidden' }}">
              Проверьте сами: <span class="ai-review-reason">{{ $aiDraft['review_reason'] ?? '' }}</span>
            </div>
            <details class="ai-elements mt-1 {{ !empty($aiDraft['elements']) ? '' : 'hidden' }}">
              <summary class="cursor-pointer select-none">Разбор по элементам</summary>
              <ul class="ai-elements-list mt-1 grid gap-1">
                @foreach(($aiDraft['elements'] ?? []) as $el)
                  <li>{{ !empty($el['counted']) ? '✓' : '✗' }} <b>{{ $el['element'] ?? '' }}</b> — {{ $el['reason'] ?? '' }}</li>
                @endforeach
              </ul>
            </details>
          </div>

          <div class="w-28">
            <label class="block text-xs text-gray-500 mb-1">Баллы (макс. {{ $maxScore }})</label>
            <input
              type="number" min="0" step="1" max="{{ $maxScore }}"
              class="score-input w-full border rounded-lg px-2 py-1.5 text-center font-semibold"
              name="score-visible-{{ $tid }}"
              data-input-for="score-{{ $tid }}"
              data-max="{{ $maxScore }}"
              value="{{ is_numeric($curScore) ? $curScore : '' }}"
            >
          </div>
        </div>
      </div>

      @if(trim((string)($t->question_text ?? '')) !== '')
      <div class="mb-4">
        <div class="text-xs text-gray-500 mb-1">Текст задания</div>
        <div class="whitespace-pre-wrap rounded-xl border border-blue-100 bg-blue-50 px-3 py-3 text-sm">
          {{ $t->question_text }}
        </div>
        @if(trim((string)($t->passage_text ?? '')) !== '')
          <details class="mt-2 rounded-xl border border-gray-200 p-3 bg-gray-50">
            <summary class="text-sm text-gray-700 cursor-pointer select-none">Показать текст к заданию</summary>
            <div class="mt-2 whitespace-pre-wrap text-sm text-gray-800">{{ $t->passage_text }}</div>
          </details>
        @endif
      </div>
      @endif

      @php
        $meta      = $taskMeta[(string) $tid] ?? [];
        $exemplar  = trim((string) ($t->answer ?? ''));
        $explain   = trim((string) ($t->explanation ?? ''));
        $hintText  = trim((string) ($t->hint ?? ''));
      @endphp
      <div class="mb-4">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
          <div class="text-xs text-gray-500">Ответ ученика</div>
          @if(isset($meta['seconds']) || !empty($meta['hint']))
            <div class="flex flex-wrap gap-1.5 text-xs">
              @if(isset($meta['seconds']))
                <span class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-gray-600" title="Сколько времени задание было открыто у ученика">⏱ {{ $formatSeconds((int) $meta['seconds']) }}</span>
              @endif
              @if(!empty($meta['hint']))
                <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-800">💡 подсказка открыта</span>
              @endif
            </div>
          @endif
        </div>
        <div class="whitespace-pre-wrap rounded-xl border border-gray-100 bg-gray-50 px-3 py-3 text-sm">
          {{ $studentAns !== '' ? $studentAns : '—' }}
        </div>
      </div>

      @if($exemplar !== '' || $explain !== '' || $hintText !== '')
        <details class="mb-4 rounded-xl border border-green-100 bg-green-50 px-3 py-2 text-sm" open>
          <summary class="cursor-pointer select-none text-green-800">Образцовый ответ</summary>
          <div class="mt-2 space-y-2 text-gray-800">
            @if($exemplar !== '')
              <div class="whitespace-pre-wrap">{{ $exemplar }}</div>
            @endif
            @if($explain !== '')
              <div>
                <div class="text-xs text-gray-500 mb-1">Пояснение</div>
                <div class="whitespace-pre-wrap">{{ $explain }}</div>
              </div>
            @endif
            @if($hintText !== '')
              <div>
                <div class="text-xs text-gray-500 mb-1">Подсказка, которую видел ученик</div>
                <div class="whitespace-pre-wrap">{{ $hintText }}</div>
              </div>
            @endif
          </div>
        </details>
      @endif

      @php
        $prevRow = $prevPerTask[$tid] ?? null;
        $prevAns = (string) ($prevAnswers[$tid] ?? '');
        $showPrev = $prevAttempt && empty($perTaskRes[$tid]['carried']) && ($prevAns !== '' || is_array($prevRow));
      @endphp
      @if($showPrev)
        <details class="mb-4 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
          <summary class="cursor-pointer select-none text-gray-700">
            Прошлая попытка № {{ $prevAttempt->attempt_no ?? '—' }}:
            @if(is_array($prevRow) && array_key_exists('score', $prevRow) && empty($prevRow['skipped']))
              <b>{{ (int) $prevRow['score'] }} / {{ $t->max_score ?? ($prevRow['max'] ?? '—') }}</b>
              @if($prevAttempt->status !== 'checked')
                <span class="text-gray-400">(проверка не была завершена)</span>
              @endif
            @else
              <span class="text-gray-400">без оценки</span>
            @endif
          </summary>
          <div class="mt-2 space-y-2">
            <div>
              <div class="text-xs text-gray-500 mb-1">Ответ тогда</div>
              <div class="whitespace-pre-wrap rounded-lg bg-gray-50 px-3 py-2">{{ $prevAns !== '' ? $prevAns : '—' }}</div>
            </div>
            @if(filled($prevRow['reason'] ?? null))
              <div>
                <div class="text-xs text-gray-500 mb-1">Обоснование</div>
                <div class="whitespace-pre-wrap">{{ $prevRow['reason'] }}</div>
              </div>
            @endif
            @if(filled($prevRow['comment'] ?? null))
              <div>
                <div class="text-xs text-gray-500 mb-1">Комментарий ученику</div>
                <div class="whitespace-pre-wrap">{{ $prevRow['comment'] }}</div>
              </div>
            @endif
          </div>
        </details>
      @endif

      <form
        action="{{ route('mentor.review.task.save', [$submission, $tid]) }}"
        method="post"
        class="per-task-form space-y-4"
        data-task="{{ $tid }}"
        data-has-db="{{ ($dbScore !== null || $dbReason !== null || $dbComment !== null) ? '1' : '0' }}"
        data-submission="{{ $submission->id }}"
      >
        @csrf
        <input type="hidden" name="score" id="score-{{ $tid }}" value="{{ is_numeric($curScore) ? $curScore : '' }}">

        <div>
          <label class="block text-sm font-medium mb-1">Обоснование баллов</label>
          <textarea name="reason" rows="4" class="smart-textarea w-full border rounded-xl px-3 py-2" placeholder="По критериям экзамена...">{{ $curReason }}</textarea>
          <div class="text-[11px] text-gray-400 mt-1">Опишите, почему выставлены именно такие баллы.</div>
          @if(!empty($quickPhrases['reason']))
            <div class="flex flex-wrap gap-1.5 mt-2">
              @foreach($quickPhrases['reason'] as $phrase)
                <button type="button" class="quick-phrase-btn text-xs px-2 py-1 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-100" data-target="reason" data-phrase="{{ e($phrase) }}">{{ \Illuminate\Support\Str::limit($phrase, 28) }}</button>
              @endforeach
            </div>
          @endif
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Комментарий ученику</label>
          <textarea name="comment" rows="5" class="smart-textarea w-full border rounded-xl px-3 py-2" placeholder="Понятный комментарий для ученика...">{{ $curComment }}</textarea>
          @if(!empty($quickPhrases['comment']))
            <div class="flex flex-wrap gap-1.5 mt-2">
              @foreach($quickPhrases['comment'] as $phrase)
                <button type="button" class="quick-phrase-btn text-xs px-2 py-1 rounded-full border border-gray-200 text-gray-600 hover:bg-gray-100" data-target="comment" data-phrase="{{ e($phrase) }}">{{ \Illuminate\Support\Str::limit($phrase, 28) }}</button>
              @endforeach
            </div>
          @endif
        </div>

        {{-- Спойлер с критериями (из tasks.criteria) --}}
        <details class="rounded-xl border border-gray-200 p-3 bg-gray-50">
          <summary class="text-sm text-gray-700 cursor-pointer select-none">
            Показать критерии для этого задания
          </summary>
          <div class="mt-2 text-sm text-gray-800">
            @if(!empty($criteriaLines))
              <ul class="list-disc pl-5 space-y-1">
                @foreach($criteriaLines as $line)
                  <li class="whitespace-pre-wrap">{{ e($line) }}</li>
                @endforeach
              </ul>
            @else
              <div class="text-gray-500">Критерии для этого задания не указаны.</div>
            @endif
          </div>
        </details>

        @if(!empty($taskHistory))
        <details class="rounded-xl border border-gray-200 p-3 bg-gray-50">
          <summary class="text-sm text-gray-700 cursor-pointer select-none">
            Прошлые попытки студента по этому номеру задания
          </summary>
          <div class="mt-2 flex flex-wrap gap-2">
            @foreach($taskHistory as $h)
              <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700" title="{{ $h['date'] }}">
                {{ $h['score'] }} / {{ $h['max'] }} <span class="text-gray-400 ml-1">({{ $h['date'] }})</span>
              </span>
            @endforeach
          </div>
        </details>
        @endif

        <div class="flex flex-wrap items-center gap-2 justify-start pt-1">
          {{-- Кнопки слева --}}
          <div class="flex flex-wrap items-center gap-2">
            @if($aiEnabled)
            <button
              formaction="{{ route('mentor.review.task.regen', [$submission, $tid]) }}"
              class="px-3 py-2 rounded-lg border-2 border-purple-200 text-purple-700 hover:bg-purple-50"
            >
              <svg class="w-5 h-5 text-purple-500 inline-block align-[-2px]" viewBox="0 0 72 72" fill="currentColor" aria-hidden="true">
                <path d="M26 12 Q31 26, 46 36 Q31 46, 26 60 Q21 46, 6 36 Q21 26, 26 12Z"/>
                <path d="M50 6 Q53 12, 60 18 Q53 24, 50 30 Q47 24, 40 18 Q47 12, 50 6Z"/>
                <path d="M56 38 Q59 43, 64 48 Q59 53, 56 58 Q53 53, 48 48 Q53 43, 56 38Z"/>
              </svg>
              AI-проверка
            </button>
            @endif

{{-- Пропустить / Вернуть --}}
  @if(!$isSkipped)
    <button type="submit"
            formaction="{{ route('mentor.review.task.skip', [$submission, $tid]) }}"
            formmethod="POST"
            onclick="return confirm('Пропустить задание? Оно уйдёт администратору.');"
            class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-amber-700 border-amber-300">
      Пропустить задание
    </button>
  @else
    <button type="submit"
            formaction="{{ route('mentor.review.task.unskip', [$submission, $tid]) }}"
            formmethod="POST"
            class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-emerald-700 border-emerald-300">
      Вернуть на проверку
    </button>
  @endif

            {{-- Кнопки «Сохранить» нет: правки уходят на сервер автоматически
                 (см. autosave в скрипте ниже), тут только статус. --}}
            <span class="save-status text-xs text-gray-500"></span>
          </div>

          {{-- Галочка согласия справа --}}
          <div class="ml-auto">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 consent-wrap {{ $needConsent ? '' : 'hidden' }}">
              <input type="checkbox" class="consent-checkbox rounded" data-task="{{ $tid }}">
              Я согласен с AI-экспертом
            </label>
          </div>
        </div>
      </form>

      {{-- Оверлей загрузки AI --}}
      <div class="ai-loading-overlay hidden absolute inset-0 bg-white bg-opacity-70 flex items-center justify-center rounded-2xl z-20">
        <svg class="animate-spin h-8 w-8 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
      </div>
    </div>
  @empty
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-gray-600">
      Письменных заданий нет.
    </div>
  @endforelse

  {{-- Итоговые действия --}}
  <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
    <form action="{{ route('mentor.review.finish', $submission) }}" method="post" id="finish-form">
      @csrf
      <button type="submit"
        class="finish-btn px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
        @if(!$allManualClosed) disabled title="Завершение доступно, когда все задания проверены или отправлены в отказ" @endif
      >Завершить проверку</button>
    </form>
    <form action="{{ route('mentor.review.finish_next', $submission) }}" method="post" id="finish-next-form">
      @csrf
      <button type="submit"
        class="finish-next-btn px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
        @if(!$allManualClosed) disabled title="Доступно, когда все задания проверены или отправлены в отказ" @endif
      >Завершить и взять следующую</button>
    </form>
  </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const perTaskForms = document.querySelectorAll('.per-task-form');

  // Незасейвленные задания — предупреждаем при уходе со страницы (A4) и
  // держим счётчик прогресса (A2) в синхронизации с реальным состоянием
  // карточек, а не только с тем, что было при загрузке страницы.
  const dirtyTasks = new Set();
  window.addEventListener('beforeunload', (ev) => {
    if (dirtyTasks.size === 0) return;
    ev.preventDefault();
    ev.returnValue = '';
  });

  const progressCountEl = document.getElementById('progress-count');
  function isCardClosed(card) {
    return card.dataset.skipped === '1' || card.dataset.state === 'done';
  }
  // «Завершить»/«Завершить и взять следующую» раньше блокировались только
  // сервером при загрузке страницы ($allManualClosed), а автосохранение идёт
  // через fetch без перезагрузки — кнопки так и оставались disabled, работа
  // висела у куратора вечно. Поэтому доступность пересчитываем здесь, на
  // каждом изменении состояния карточек.
  const finishBtns = document.querySelectorAll('.finish-btn, .finish-next-btn');
  // «Итог сейчас»: автопроверка + баллы, введённые в карточках письменной части.
  const totalNowEl = document.getElementById('total-now');
  function updateTotal() {
    if (!totalNowEl) return;
    let sum = parseInt(totalNowEl.dataset.auto || '0', 10);
    document.querySelectorAll('.score-input').forEach((inp) => {
      const v = parseInt(inp.value, 10);
      if (!Number.isNaN(v)) sum += v;
    });
    totalNowEl.textContent = String(sum);
  }
  // На любой ввод: AI-черновик ставит балл программно и шлёт input на форму, а не на поле.
  document.addEventListener('input', updateTotal);
  updateTotal();

  function updateProgressCount() {
    updateTotal();
    const cards = document.querySelectorAll('.review-card');
    let closed = 0;
    cards.forEach((c) => { if (isCardClosed(c)) closed++; });
    if (progressCountEl) progressCountEl.textContent = String(closed);

    const allClosed = closed === cards.length;
    finishBtns.forEach((b) => {
      b.disabled = !allClosed;
      if (allClosed) b.removeAttribute('title');
      else b.title = 'Завершение доступно, когда все задания проверены или отправлены в отказ';
    });
  }

  function cardSetState(card, state){
    if (card.dataset.skipped === '1') return;
    card.dataset.state = state;
    card.classList.remove('border-gray-200','border-blue-400','border-green-400');
    if (state === 'done')      card.classList.add('border-green-400');
    else if (state === 'editing') card.classList.add('border-blue-400');
    else                        card.classList.add('border-gray-200');
    updateProgressCount();
  }

  function markCardValidity(cardEl, isValid) {
    cardEl.classList.toggle('invalid', !isValid);
    cardEl.style.boxShadow = 'none';
  }

  perTaskForms.forEach((form) => {
    const card = form.closest('.review-card') || form.parentElement;
    const taskId = form.dataset.task || '';
    const submissionId = form.dataset.submission || '';
    const draftKey = `mentor_review_draft:${submissionId}:${taskId}`;

    const scoreHidden  = form.querySelector(`#score-${CSS.escape(taskId)}`);
    const scoreVisible = document.querySelector(`[data-input-for="score-${CSS.escape(taskId)}"]`);
    const max          = scoreVisible ? parseInt(scoreVisible.dataset.max || '0', 10) : 0;

    const reasonEl  = form.querySelector('textarea[name="reason"]');
    const commentEl = form.querySelector('textarea[name="comment"]');

    const consentWrap = form.querySelector('.consent-wrap');
    const consentChk  = consentWrap ? consentWrap.querySelector('.consent-checkbox') : null;
    const statusEl    = form.querySelector('.save-status');

    // Можно ли сейчас отправлять карточку на сервер (валидный балл + согласие
    // с AI, если оно требуется) — пересчитывается в updateSaveAvailability().
    let canSave = false;

    if (form.dataset.hasDb === '1') cardSetState(card, 'done');

    // Черновик в localStorage (B6) — подстраховка от истёкшего лока
    // (ниже все поля формы блокируются разом) и случайного reload.
    // Восстанавливаем только в пустые поля, чтобы не перетереть то, что
    // реально уже сохранено в БД или пришло от AI.
    function readDraft() {
      try {
        const raw = localStorage.getItem(draftKey);
        return raw ? JSON.parse(raw) : null;
      } catch (e) { return null; }
    }
    function writeDraft() {
      try {
        localStorage.setItem(draftKey, JSON.stringify({
          score:   scoreVisible ? scoreVisible.value : '',
          reason:  reasonEl ? reasonEl.value : '',
          comment: commentEl ? commentEl.value : '',
        }));
      } catch (e) { /* приватный режим и т.п. — черновик просто не сохранится */ }
    }
    function clearDraft() {
      try { localStorage.removeItem(draftKey); } catch (e) {}
    }

    let autosaveTimer = null;
    let editVersion = 0;               // растёт на каждую правку — см. runSave()
    let saveChain = Promise.resolve(); // сохранения идут строго по очереди

    // Отправка карточки на сервер. Сохранения сериализуем: иначе два запроса
    // подряд (правка во время предыдущего fetch) могли бы прийти на сервер
    // в обратном порядке, и старое значение затёрло бы новое. Значения формы
    // читаем в момент реальной отправки, а не постановки в очередь.
    function doSave() {
      saveChain = saveChain.then(runSave);
      return saveChain;
    }

    async function runSave() {
      if (!canSave) return;
      const versionAtSend = editVersion;
      if (statusEl) statusEl.textContent = 'Сохраняем…';
      try {
        const resp = await fetch(form.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
          },
          body: new FormData(form),
        });
        const data = await resp.json().catch(() => null);
        if (!resp.ok || !data || !data.ok) throw new Error('save failed');

        form.dataset.hasDb = '1';
        cardSetState(card, 'done');
        // Если пока шёл запрос куратор успел что-то ещё поправить — карточка
        // остаётся «несохранённой» до следующего автосейва.
        if (versionAtSend === editVersion) {
          dirtyTasks.delete(taskId);
          clearDraft();
          if (statusEl) statusEl.textContent = 'Сохранено';
        }
      } catch (e) {
        console.error(e);
        if (statusEl) statusEl.textContent = 'Ошибка сохранения, попробуйте ещё раз';
      }
    }

    // Ctrl/Cmd+Enter — сохранить карточку немедленно, не дожидаясь автосейва.
    form.__mentorReviewSave = () => {
      if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
      return doSave();
    };

    // Перед «Завершить»: досохранить только реально изменённую карточку
    // (отложенная правка либо неудачный прошлый сейв) и дождаться идущего
    // запроса. Нетронутые карточки НЕ пересылаем: saveTask() снимает флаг
    // skipped, и «Пропущено» превратилось бы в обычную карточку.
    form.__mentorReviewFlush = () => {
      if (autosaveTimer || dirtyTasks.has(taskId)) {
        if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
        return doSave();
      }
      return saveChain;
    };

    form.addEventListener('submit', (ev) => {
      // Обычной отправкой формы остались только «Пропустить»/«Вернуть» — они
      // используют formaction и должны навигировать как обычно. Неявную
      // отправку (без нажатой кнопки, напр. Enter в поле) глушим: иначе она
      // молча сработала бы как первая submit-кнопка формы.
      if (!ev.submitter) ev.preventDefault();
    });

    // Пустой балл НЕ превращаем в 0: без кнопки «Сохранить» карточка
    // считается проверенной только когда куратор явно ввёл балл (в том числе
    // 0), иначе автосейв тихо ставил бы ноль, стоило лишь начать писать
    // комментарий.
    function syncScoreToHidden() {
      const raw = scoreVisible ? scoreVisible.value : '';
      if (raw === '') {
        if (scoreHidden) scoreHidden.value = '';
        return;
      }
      let v = Number(raw);
      if (Number.isNaN(v)) v = 0;
      if (v < 0) v = 0;
      if (v > max) v = max;
      if (scoreVisible) scoreVisible.value = String(v);
      if (scoreHidden)  scoreHidden.value  = String(v);
    }

    function updateSaveAvailability() {
      const raw = scoreVisible ? scoreVisible.value : '';
      const v = raw !== '' ? Number(raw) : NaN;
      const validScore = !Number.isNaN(v) && v >= 0 && v <= max;

      let consentOk = true;
      if (consentWrap && !consentWrap.classList.contains('hidden')) {
        consentOk = !!(consentChk && consentChk.checked);
      }

      // Обоснование баллов / комментарий ученику — необязательны (см.
      // TaskContentRules и SubmissionReviewController::saveTask, оба поля
      // nullable на сервере): куратор может сохранить оценку, не заполняя
      // их, если это осмысленно (например, работа оценена без комментария).
      canSave = validScore && consentOk;

      if (form.dataset.hasDb !== '1') {
        if (canSave) cardSetState(card, 'editing');
        else         cardSetState(card, 'idle');
      }

      markCardValidity(card, canSave);
    }

    function onAnyFieldEdited() {
      if (consentWrap && !consentWrap.classList.contains('hidden')) {
        consentWrap.classList.add('hidden');
        if (consentChk) consentChk.checked = false;
      }
      syncScoreToHidden();
      updateSaveAvailability();

      editVersion++;
      dirtyTasks.add(taskId);
      writeDraft();
      if (statusEl) {
        statusEl.textContent = canSave ? '' : 'Укажите баллы — без них задание не сохранится';
      }

      // Автосохранение (B5) — через ~1.5с простоя после последней правки,
      // только когда карточка валидна для сохранения (см. updateSaveAvailability()).
      if (autosaveTimer) clearTimeout(autosaveTimer);
      autosaveTimer = setTimeout(() => {
        autosaveTimer = null;
        if (canSave) doSave();
      }, 1500);
    }

    if (scoreVisible) {
      scoreVisible.addEventListener('input', () => {
        let raw = scoreVisible.value.replace(/[^\d]/g, '');
        if (raw !== '') {
          let n = parseInt(raw, 10);
          if (n > max) n = max;
          if (n < 0)   n = 0;
          scoreVisible.value = String(n);
        }
        onAnyFieldEdited();
      });
    }

    [reasonEl, commentEl].forEach(el => {
      if (!el) return;
      const resize = () => { el.style.height = 'auto'; el.style.height = (el.scrollHeight) + 'px'; };
      resize();
      el.addEventListener('input', resize);
      el.addEventListener('input', onAnyFieldEdited);
      el.addEventListener('change', onAnyFieldEdited);
    });

    // Согласие с AI не проходит через onAnyFieldEdited() (тот, наоборот,
    // сбрасывает галочку), поэтому ставим в очередь сохранение отдельно.
    if (consentChk) consentChk.addEventListener('change', () => {
      updateSaveAvailability();
      if (canSave) {
        editVersion++;
        dirtyTasks.add(taskId);
        form.__mentorReviewSave();
      }
    });

    syncScoreToHidden();
    updateSaveAvailability();

    // Восстановление черновика — только в пустые поля, чтобы не перетереть
    // то, что реально уже сохранено в БД или пришло от AI. Раз кнопки
    // «Сохранить» больше нет, восстановленное сразу уходит в автосейв (через
    // onAnyFieldEdited), а не ждёт, пока куратор что-нибудь допечатает.
    const draft = readDraft();
    if (draft) {
      let restored = false;
      if (scoreVisible && scoreVisible.value === '' && draft.score)          { scoreVisible.value = draft.score; restored = true; }
      if (reasonEl && reasonEl.value.trim() === '' && draft.reason)          { reasonEl.value = draft.reason; restored = true; }
      if (commentEl && commentEl.value.trim() === '' && draft.comment)       { commentEl.value = draft.comment; restored = true; }
      if (restored) {
        [reasonEl, commentEl].forEach((el) => { if (el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; } });
        onAnyFieldEdited();
        if (statusEl && canSave) statusEl.textContent = 'Восстановлен черновик';
      }
    }
  });

  updateProgressCount();

  // «Завершить» / «Завершить и взять следующую»: перед отправкой дожидаемся
  // автосохранения всех карточек (debounce 1.5с — последняя правка могла ещё
  // не уйти на сервер, и статус 'checked' закрыл бы работу без неё).
  ['finish-form', 'finish-next-form'].forEach((id) => {
    const f = document.getElementById(id);
    if (!f) return;
    f.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      finishBtns.forEach((b) => { b.disabled = true; });

      await Promise.all(Array.from(perTaskForms).map((pf) => pf.__mentorReviewFlush && pf.__mentorReviewFlush()));

      if (dirtyTasks.size > 0) {
        alert('Не все изменения сохранились. Проверьте, что во всех заданиях указаны баллы и нет ошибок сохранения, и попробуйте ещё раз.');
        updateProgressCount();
        return;
      }
      f.submit(); // программный submit() не вызывает событие повторно
    });
  });

  // Быстрые фразы (C7) — вставляют текст в соответствующее textarea той же
  // карточки и дёргают 'input' на самом textarea (а не на форме — обработчики
  // навешаны точечно на элементы, всплытие с формы их не достанет).
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.quick-phrase-btn');
    if (!btn) return;
    const form = btn.closest('.per-task-form');
    const el = form ? form.querySelector(`textarea[name="${btn.dataset.target}"]`) : null;
    if (!el) return;
    const phrase = btn.dataset.phrase || '';
    el.value = el.value.trim() !== '' ? (el.value.replace(/\s+$/, '') + ' ' + phrase) : phrase;
    el.dispatchEvent(new Event('input', { bubbles: true }));
  });

  // Горячие клавиши (C9): Ctrl/Cmd+Enter — сохранить текущую карточку сразу, не дожидаясь автосейва;
  // Alt+вверх/вниз — перейти к следующей/предыдущей непроверенной карточке.
  document.addEventListener('keydown', (ev) => {
    if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') {
      const form = document.activeElement && document.activeElement.closest('.per-task-form');
      if (form && typeof form.__mentorReviewSave === 'function') {
        ev.preventDefault();
        form.__mentorReviewSave();
      }
      return;
    }

    if (ev.altKey && (ev.key === 'ArrowDown' || ev.key === 'ArrowUp')) {
      const cards = Array.from(document.querySelectorAll('.review-card'));
      if (!cards.length) return;
      ev.preventDefault();

      const current = document.activeElement ? document.activeElement.closest('.review-card') : null;
      const idx = current ? cards.indexOf(current) : -1;
      const dir = ev.key === 'ArrowDown' ? 1 : -1;

      let next = null;
      for (let step = 1; step <= cards.length; step++) {
        const pos = ((idx + dir * step) % cards.length + cards.length) % cards.length;
        if (!isCardClosed(cards[pos])) { next = cards[pos]; break; }
      }
      if (!next) next = cards[((idx + dir) % cards.length + cards.length) % cards.length];

      next.scrollIntoView({ behavior: 'smooth', block: 'center' });
      const focusable = next.querySelector('textarea, input:not([type="hidden"])');
      if (focusable) focusable.focus();
    }
  });



  @if($lockSeconds > 0)
  (function(){
    let left = {{ $lockSeconds }};
    const out = document.getElementById('lock-countdown');
    function tick(){
      if (!out) return;
      const m = Math.floor(left/60);
      const s = left%60;
      out.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
      if (left<=0) {
        document.querySelectorAll('button, input, textarea, select').forEach(n => n.disabled = true);
        out.closest('.text-xs')?.classList.add('text-red-700');
        return;
      }
      left--;
      setTimeout(tick, 1000);
    }
    tick();
  })();
  @endif
});

// Таймер остатка времени (резервный)
(function(){
  var secondsLeft = {{ max(0, optional($submission->lock_expires_at)->diffInSeconds(now(), false) * -1) ?? 0 }};
  var out = document.getElementById('lock-countdown');
  if (!out) return;

  function tick(){
    if (secondsLeft <= 0) {
      out.textContent = '00:00';
      document.querySelectorAll('button, input, textarea, select').forEach(function(n){ n.disabled = true; });
      out.closest('.text-xs')?.classList.add('text-red-700');
      return;
    }
    var m = Math.floor(secondsLeft/60);
    var s = secondsLeft%60;
    out.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    secondsLeft -= 1;
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Один обработчик на regen с оверлеем и обновлением "Рекомендованные баллы"
  document.querySelectorAll('.per-task-form').forEach((form) => {
    form.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('button[formaction]');
      if (!btn) return;

      const url = btn.getAttribute('formaction') || '';
      if (!url.includes('/mentor/review/') || !url.endsWith('/regen')) return;

      ev.preventDefault();
      btn.disabled = true;

      const card = form.closest('.review-card');
      const overlay = card.querySelector('.ai-loading-overlay');
      if (overlay) overlay.classList.remove('hidden');

      try {
        const resp = await fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
          },
          body: new FormData()
        });

        const data = await resp.json();
        if (!data.ok) {
          alert(data.message || 'Не удалось получить черновик от AI.');
          return;
        }

        // обновляем поля
        const scoreInput  = card.querySelector(`[data-input-for="score-${CSS.escape(data.taskId)}"]`);
        const hiddenScore = form.querySelector(`#score-${CSS.escape(data.taskId)}`);
        const reasonEl    = form.querySelector('textarea[name="reason"]');
        const commentEl   = form.querySelector('textarea[name="comment"]');

        if (scoreInput)  scoreInput.value  = String(data.score ?? '');
        if (hiddenScore) hiddenScore.value = String(data.score ?? '');
        if (reasonEl)    reasonEl.value    = data.rationale ?? '';
        if (commentEl)   commentEl.value   = data.comment ?? '';

        // обновляем панель «AI-эксперт»
        const panel = card.querySelector('.ai-panel');
        if (panel) {
          panel.classList.remove('border-gray-200', 'bg-gray-50', 'text-gray-700');
          panel.classList.add('border-indigo-200', 'bg-indigo-50', 'text-indigo-800');
          panel.querySelector('.ai-empty')?.classList.add('hidden');

          const scoreDiv = panel.querySelector('.ai-score-display');
          scoreDiv.textContent = 'Рекомендованные баллы: ' + (data.score ?? '—');
          scoreDiv.classList.remove('hidden');

          panel.querySelector('.ai-review').classList.toggle('hidden', !data.needs_review);
          panel.querySelector('.ai-review-reason').textContent = data.review_reason || '';

          const list = panel.querySelector('.ai-elements-list');
          list.replaceChildren(...(data.elements || []).map((el) => {
            const li = document.createElement('li');
            const b = document.createElement('b');
            b.textContent = el.element || '';
            li.append((el.counted ? '✓ ' : '✗ '), b, ' — ' + (el.reason || ''));
            return li;
          }));
          panel.querySelector('.ai-elements').classList.toggle('hidden', !(data.elements || []).length);
        }

        // показать галочку согласия
        const consentWrap = form.querySelector('.consent-wrap');
        const consentChk  = consentWrap ? consentWrap.querySelector('.consent-checkbox') : null;
        if (consentWrap) consentWrap.classList.remove('hidden');
        if (consentChk)  consentChk.checked = false;

        // триггеры локальной валидации
        form.dispatchEvent(new Event('input', { bubbles: true }));
        form.dispatchEvent(new Event('change', { bubbles: true }));

      } catch (e) {
        console.error(e);
        alert('Сбой при обращении к AI-сервису.');
      } finally {
        if (overlay) overlay.classList.add('hidden');
        btn.disabled = false;
      }
    });
  });
});
</script>
@endsection
