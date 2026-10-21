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

<div class="max-w-6xl mx-auto px-4 py-6 space-y-4">

  {{-- Шапка: кто, что, когда сдано + прогресс, итог и лок --}}
  <div class="rounded-2xl border border-gray-200 bg-white p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-400">Проверка домашней работы</div>
        <h1 class="mt-0.5 text-xl font-semibold text-gray-900">{{ $student?->full_name ?? ('ID '.$submission->user_id) }}</h1>
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-gray-600">
          <span class="font-medium text-gray-800">{{ $homework->title ?? 'Без названия' }}</span>
          <span class="text-gray-300">•</span><span>попытка № {{ $submission->attempt_no ?? 1 }}</span>
          @if($course)<span class="text-gray-300">•</span><span>Курс: {{ $course->title }}</span>@endif
          @if($lesson)<span class="text-gray-300">•</span><span>Урок: {{ $lesson->title }}</span>@endif
        </div>
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-gray-600">
          <span>Сдано: <span class="text-gray-800">{{ $submittedAt?->format('d.m.Y H:i') ?? '—' }}</span></span>
          @if($dueAt)
            <span class="text-gray-300">•</span>
            <span>Дедлайн: <span class="text-gray-800">{{ $dueAt->format('d.m.Y H:i') }}</span></span>
            @if($lateHours > 0)
              <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">с опозданием {{ $lateText }}</span>
            @elseif($submittedAt)
              <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">вовремя</span>
            @endif
          @endif
        </div>
      </div>

      <div class="flex flex-wrap items-stretch gap-2 text-sm">
        <div class="rounded-xl bg-gray-50 px-3 py-2">
          <div class="text-xs text-gray-500">Проверено</div>
          <div class="font-semibold text-gray-900"><span id="progress-count">{{ $manualClosedCount }}</span> из {{ $manualTotal }}</div>
        </div>
        <div class="rounded-xl bg-gray-50 px-3 py-2" title="Автопроверка ({{ $autoScoreNow }}) + баллы, выставленные за письменную часть">
          <div class="text-xs text-gray-500">Итог сейчас</div>
          <div class="font-semibold text-gray-900"><span id="total-now" data-auto="{{ $autoScoreNow }}">—</span> из {{ $totalMax }}</div>
        </div>
        <div class="rounded-xl bg-gray-50 px-3 py-2">
          <div class="text-xs text-gray-500">Лок до <span id="lock-until-text">{{ optional($submission->lock_expires_at)->format('H:i') ?? '—' }}</span></div>
          @if(($submission->lock_expires_at && now()->lt($submission->lock_expires_at)))
            <div class="text-xs font-semibold text-gray-900">осталось <span id="lock-countdown">—:—</span></div>
          @endif
          @if($submission->locked_by && $submission->locked_by !== $user->id && now()->lt($submission->lock_expires_at))
            <div class="text-xs text-amber-700">Занято: {{ $submission->lockedByUser?->name ?? ('ID '.$submission->locked_by) }}</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Ученик одной строкой: курсы, сколько проверено, средний результат, слабые номера --}}
    @if($studentSummary)
      @php $weak = collect($studentSummary['numbers'])->where('weak', true); @endphp
      <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-gray-100 pt-3 text-xs">
        @foreach($studentSummary['courses'] as $courseTitle)
          <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">{{ $courseTitle }}</span>
        @endforeach
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">проверено работ: {{ $studentSummary['checked'] }}</span>
        @if($studentSummary['recent_percent'] !== null)
          @php $rp = $studentSummary['recent_percent']; @endphp
          <span class="rounded-full px-2 py-0.5 font-medium {{ $rp < 50 ? 'bg-red-50 text-red-700' : ($rp < 75 ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700') }}"
                title="Средний результат за последние {{ $studentSummary['recent_count'] }} проверенных работ">
            в среднем {{ $rp }}%
          </span>
        @endif
        @if($weak->isNotEmpty())
          <span class="ml-1 text-gray-500">слабые места:</span>
          @foreach($weak as $n)
            <span class="rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700"
                  title="Набрано {{ $n['score'] }} из {{ $n['max'] }} за {{ $n['count'] }} отв. — стоит обратить внимание в комментарии">№{{ $n['number'] }}: {{ $n['percent'] }}%</span>
          @endforeach
        @endif
        @if(count($studentSummary['numbers']) > $weak->count())
          <details class="relative">
            <summary class="cursor-pointer select-none rounded-full px-2 py-0.5 text-gray-500 hover:bg-gray-100">все номера</summary>
            <div class="absolute left-0 z-10 mt-1 flex w-72 flex-wrap gap-1 rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
              @foreach($studentSummary['numbers'] as $n)
                <span class="rounded-full px-2 py-0.5 {{ $n['weak'] ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-700' }}"
                      title="Набрано {{ $n['score'] }} из {{ $n['max'] }} за {{ $n['count'] }} отв.">№{{ $n['number'] }}: {{ $n['percent'] }}%</span>
              @endforeach
            </div>
          </details>
        @endif
        @if($isAdmin)
          <span class="ml-auto flex items-center gap-3">
            <a href="{{ route('admin.user.show', $student) }}" class="text-gray-500 underline hover:text-gray-800">Профиль ученика</a>
            {{-- Удаление попытки целиком — только админ, не куратор. --}}
            <form action="{{ route('mentor.submissions.destroy', $submission) }}" method="POST"
                  onsubmit="return confirm('Удалить эту попытку сдачи безвозвратно? Ответы, оценки и комментарии куратора будут потеряны.');">
              @csrf
              @method('DELETE')
              <button type="submit" class="text-red-600 hover:underline">Удалить попытку</button>
            </form>
          </span>
        @endif
      </div>
    @endif

    @if($newerRetry)
      <div class="mt-3 rounded-lg bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
        Ученик сейчас перерешивает эту работу (попытка № {{ $newerRetry->attempt_no }}, начата {{ $newerRetry->created_at?->format('d.m H:i') }}, ещё не отправлена).
        Если он её отправит, проверять эту попытку будет не нужно — итог считается по последней.
      </div>
    @endif
    @if($submission->isSuperseded())
      <div class="mt-3 rounded-lg bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
        Ученик уже сдал более новую попытку по этой работе — эту проверять не нужно, итог считается по последней.
      </div>
    @endif
    @foreach (['success'=>'green','error'=>'red','warning'=>'yellow','info'=>'blue'] as $k=>$clr)
      @if (session($k))
        <div class="mt-3 rounded-lg bg-{{ $clr }}-50 px-3 py-2 text-sm text-{{ $clr }}-800">{{ session($k) }}</div>
      @endif
    @endforeach
  </div>

  {{-- Первая часть (автопроверка) — таблицей, ошибки красным --}}
  @php
    $autoRows = $autoTasks->values()->map(function ($t, $i) use ($perTaskRes, $answers) {
        $tid = $t->id ?? ("t_auto_$i");
        return [
            'no'      => $t->number ?? $t->order ?? ($i + 1),
            'max'     => (int) ($t->max_score ?? 1),
            'score'   => (int) ($perTaskRes[$tid]['score'] ?? 0),
            'answer'  => (string) ($answers[$tid] ?? ''),
            'correct' => (string) ($t->answer ?? ''),
        ];
    });
    $autoWrong = $autoRows->filter(fn ($r) => $r['score'] < $r['max'])->count();
  @endphp
  <details class="rounded-2xl border border-gray-200 bg-white">
    <summary class="cursor-pointer select-none px-4 py-3 text-sm text-gray-700">
      Первая часть (автопроверка):
      <span class="font-semibold text-gray-900">{{ $autoRows->sum('score') }} из {{ $autoRows->sum('max') }}</span>
      @if($autoWrong)<span class="ml-1 text-red-700">· с ошибками: {{ $autoWrong }}</span>@endif
    </summary>
    @if($autoRows->isNotEmpty())
      <div class="overflow-x-auto border-t border-gray-100 px-4 pb-3">
        <table class="mt-2 w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500">
              <th class="py-1 pr-3 font-normal">№</th>
              <th class="py-1 pr-3 font-normal">Ответ ученика</th>
              <th class="py-1 pr-3 font-normal">Правильный</th>
              <th class="py-1 text-right font-normal">Балл</th>
            </tr>
          </thead>
          <tbody>
            @foreach($autoRows as $r)
              @php $ok = $r['score'] >= $r['max']; @endphp
              <tr class="border-t border-gray-50 {{ $ok ? '' : 'text-red-700' }}">
                <td class="py-1 pr-3">{{ $r['no'] }}</td>
                <td class="py-1 pr-3 font-mono">{{ $r['answer'] !== '' ? $r['answer'] : '—' }}</td>
                <td class="py-1 pr-3 font-mono text-gray-600">{{ $r['correct'] !== '' ? $r['correct'] : '—' }}</td>
                <td class="py-1 text-right font-medium">{{ $r['score'] }}/{{ $r['max'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="border-t border-gray-100 px-4 py-3 text-sm text-gray-500">Автопроверяемых заданий нет.</div>
    @endif
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

      // Новое оформление карточки
      $hasAi     = ($aiRationaleFull && trim($aiRationaleFull) !== '') || ($aiCommentFull && trim($aiCommentFull) !== '') || is_numeric($aiScore);
      $meta      = $taskMeta[(string) $tid] ?? [];
      $exemplar  = trim((string) ($t->answer ?? ''));
      $explain   = trim((string) ($t->explanation ?? ''));
      $hintText  = trim((string) ($t->hint ?? ''));
      $passage   = trim((string) ($t->passage_text ?? ''));
      $prevRow   = $prevPerTask[$tid] ?? null;
      $prevAns   = (string) ($prevAnswers[$tid] ?? '');
      $showPrev  = $prevAttempt && empty($perTaskRes[$tid]['carried']) && ($prevAns !== '' || is_array($prevRow));
      $prevScore = (is_array($prevRow) && array_key_exists('score', $prevRow) && empty($prevRow['skipped'])) ? (int) $prevRow['score'] : null;
      // Баллы кнопками 0…max; при большом максимуме — обычное поле.
      $scoreButtons = $maxScore <= 6;
      // Уже проверенные карточки при открытии страницы свёрнуты в одну строку.
      $collapsed = $initialState === 'done';
      $stripe = $isSkipped ? 'border-l-red-300' : ($initialState === 'done' ? 'border-l-green-400' : 'border-l-gray-200');
      $tabCls = 'card-tab rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50';
      // Статус словами в заголовке (дальше обновляет JS, см. renderStatus()).
      [$statusText, $statusCls] = match (true) {
          $initialState === 'done' => ['Сохранено', 'bg-green-50 text-green-700'],
          $hasAi                   => ['Черновик ИИ — не принят', 'bg-indigo-50 text-indigo-700'],
          default                  => ['Не проверено', 'bg-gray-100 text-gray-600'],
      };
    @endphp

    <div
      class="review-card relative overflow-hidden rounded-2xl border border-gray-200 border-l-4 {{ $stripe }} bg-white transition-shadow"
      data-state="{{ $initialState }}"
      data-skipped="{{ $isSkipped ? '1' : '0' }}"
      data-collapsed="{{ $collapsed ? '1' : '0' }}"
      data-ai-draft="{{ $hasAi && $initialState !== 'done' ? '1' : '0' }}"
      data-dirty="0"
    >
      {{-- Заголовок: номер ЕГЭ, статус; клик сворачивает/разворачивает --}}
      <button type="button" class="card-toggle flex w-full items-center gap-2 px-4 py-3 text-left hover:bg-gray-50">
        <span class="text-base font-semibold text-gray-900">№{{ $t->number ?? ($t->order ?? ($i+1)) }}</span>
        <span class="text-xs text-gray-500">макс. {{ $maxScore }}</span>
        <span class="card-status rounded-full px-2 py-0.5 text-xs font-medium {{ $statusCls }} {{ $isSkipped ? 'hidden' : '' }}">{{ $statusText }}</span>
        @if($isSkipped)
          <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Пропущено</span>
        @endif
        @if(!empty($perTaskRes[$tid]['carried']))
          {{-- «Перерешать ошибки»: ученик не решал это задание заново, ответ
               (и оценка, если она уже была) перенесены из прошлой попытки. --}}
          <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Из прошлой попытки</span>
        @endif
        <span class="card-summary min-w-0 flex-1 truncate text-sm text-gray-500 {{ $collapsed ? '' : 'hidden' }}">
          @if(is_numeric($dbScore))<b class="text-gray-800">{{ $dbScore }}/{{ $maxScore }}</b>@endif
          {{ \Illuminate\Support\Str::limit((string) $dbReason, 110) }}
        </span>
        <span class="ml-auto flex shrink-0 items-center gap-1.5 text-xs">
          @if(isset($meta['seconds']))
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600" title="Сколько времени задание было открыто у ученика">⏱ {{ $formatSeconds((int) $meta['seconds']) }}</span>
          @endif
          @if(!empty($meta['hint']))
            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-800">💡 подсказка открыта</span>
          @endif
          <svg class="card-chevron h-4 w-4 text-gray-400 transition-transform {{ $collapsed ? '' : 'rotate-180' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </span>
      </button>

      <div class="card-body grid gap-5 border-t border-gray-100 p-4 lg:grid-cols-2 {{ $collapsed ? 'hidden' : '' }}">
        {{-- Слева — то, что читаем --}}
        <div class="min-w-0 space-y-3">
          @if(trim((string)($t->question_text ?? '')) !== '')
            <div class="whitespace-pre-wrap text-sm text-gray-600">{{ $t->question_text }}</div>
          @endif

          <div>
            <div class="mb-1 text-sm font-medium text-gray-700">Ответ ученика</div>
            <div class="whitespace-pre-wrap rounded-xl bg-gray-50 px-3 py-3 text-[15px] leading-relaxed text-gray-900">{{ $studentAns !== '' ? $studentAns : '—' }}</div>
          </div>

          {{-- Справка вкладками: открыта максимум одна --}}
          <div class="flex flex-wrap gap-1.5">
            @if($passage !== '')<button type="button" class="{{ $tabCls }}" data-tab="text">Текст</button>@endif
            @if($exemplar !== '' || $explain !== '' || $hintText !== '')<button type="button" class="{{ $tabCls }}" data-tab="exemplar">Образец</button>@endif
            <button type="button" class="{{ $tabCls }}" data-tab="criteria">Критерии</button>
            @if($showPrev)
              <button type="button" class="{{ $tabCls }}" data-tab="prev">Прошлая попытка{{ $prevScore !== null ? ' · '.$prevScore.'/'.$maxScore : '' }}</button>
            @endif
            @if(!empty($taskHistory))<button type="button" class="{{ $tabCls }}" data-tab="history">История</button>@endif
          </div>

          @if($passage !== '')
            <div class="card-tab-panel hidden max-h-96 overflow-y-auto whitespace-pre-wrap rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-800" data-tab="text">{{ $passage }}</div>
          @endif
          @if($exemplar !== '' || $explain !== '' || $hintText !== '')
            <div class="card-tab-panel hidden space-y-2 rounded-xl bg-green-50 px-3 py-2 text-sm text-gray-800" data-tab="exemplar">
              @if($exemplar !== '')<div class="whitespace-pre-wrap">{{ $exemplar }}</div>@endif
              @if($explain !== '')
                <div><div class="mb-0.5 text-xs text-gray-500">Пояснение</div><div class="whitespace-pre-wrap">{{ $explain }}</div></div>
              @endif
              @if($hintText !== '')
                <div><div class="mb-0.5 text-xs text-gray-500">Подсказка, которую видел ученик</div><div class="whitespace-pre-wrap">{{ $hintText }}</div></div>
              @endif
            </div>
          @endif
          <div class="card-tab-panel hidden rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-800" data-tab="criteria">
            @if(!empty($criteriaLines))
              <ul class="list-disc space-y-1 pl-5">
                @foreach($criteriaLines as $line)
                  <li class="whitespace-pre-wrap">{{ $line }}</li>
                @endforeach
              </ul>
            @else
              <div class="text-gray-500">Критерии для этого задания не указаны.</div>
            @endif
          </div>
          @if($showPrev)
            <div class="card-tab-panel hidden space-y-2 rounded-xl bg-gray-50 px-3 py-2 text-sm" data-tab="prev">
              <div class="text-xs text-gray-500">
                Прошлая попытка № {{ $prevAttempt->attempt_no ?? '—' }}:
                @if($prevScore !== null)
                  <b class="text-gray-800">{{ $prevScore }} / {{ $t->max_score ?? ($prevRow['max'] ?? '—') }}</b>
                  @if($prevAttempt->status !== 'checked')<span class="text-gray-400">(проверка не была завершена)</span>@endif
                @else
                  без оценки
                @endif
              </div>
              <div class="whitespace-pre-wrap">{{ $prevAns !== '' ? $prevAns : '—' }}</div>
              @if(filled($prevRow['reason'] ?? null))
                <div><div class="mb-0.5 text-xs text-gray-500">Обоснование</div><div class="whitespace-pre-wrap">{{ $prevRow['reason'] }}</div></div>
              @endif
              @if(filled($prevRow['comment'] ?? null))
                <div><div class="mb-0.5 text-xs text-gray-500">Комментарий ученику</div><div class="whitespace-pre-wrap">{{ $prevRow['comment'] }}</div></div>
              @endif
            </div>
          @endif
          @if(!empty($taskHistory))
            <div class="card-tab-panel hidden rounded-xl bg-gray-50 px-3 py-2 text-sm" data-tab="history">
              <div class="mb-1 text-xs text-gray-500">Прошлые попытки ученика по этому номеру</div>
              <div class="flex flex-wrap gap-1.5">
                @foreach($taskHistory as $h)
                  <span class="rounded-full bg-white px-2 py-0.5 text-xs text-gray-700">{{ $h['score'] }} / {{ $h['max'] }} <span class="text-gray-400">· {{ $h['date'] }}</span></span>
                @endforeach
              </div>
            </div>
          @endif
        </div>

        {{-- Справа — то, что заполняем --}}
        <div class="min-w-0 space-y-3">
          {{-- ИИ одной строкой --}}
          <div class="ai-panel rounded-xl border {{ $hasAi ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-gray-200 bg-gray-50 text-gray-700' }} px-3 py-2 text-sm">
            <div class="flex flex-wrap items-center gap-2">
              <span class="ai-score-display font-medium {{ $hasAi ? '' : 'hidden' }}">ИИ: {{ is_numeric($aiScore) ? $aiScore : '—' }} из {{ $maxScore }}</span>
              <span class="ai-empty {{ $hasAi ? 'hidden' : '' }}">
                @if(($aiDraft['status'] ?? null) === 'pending')
                  ИИ-проверка в очереди — можно не ждать.
                @elseif(($aiDraft['status'] ?? null) === 'exported')
                  Выгружено на ИИ-проверку в Claude ({{ $aiDraft['exported_at'] ?? '' }}) — можно не ждать.
                @elseif(($aiDraft['status'] ?? null) === 'error')
                  ИИ-проверка не удалась: {{ $aiDraft['error'] ?? '' }}
                @else
                  ИИ-черновика нет.
                @endif
              </span>
              {{-- Флаг «модель не уверена» и разбор по элементам видит только куратор. --}}
              <span class="ai-review rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900 {{ !empty($aiDraft['needs_review']) ? '' : 'hidden' }}"
                    title="{{ $aiDraft['review_reason'] ?? '' }}">⚠ проверьте сами</span>
              <button type="button"
                      class="ai-accept ml-auto rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 {{ $hasAi && $initialState !== 'done' ? '' : 'hidden' }}"
                      data-score="{{ is_numeric($aiScore) ? $aiScore : '' }}"
                      data-reason="{{ $aiRationaleFull }}"
                      data-comment="{{ $aiCommentFull }}"
                      title="Сохранить балл, обоснование и комментарий ИИ как оценку куратора">Принять оценку ИИ</button>
            </div>
            <div class="ai-review-reason mt-1 text-xs text-amber-900 {{ !empty($aiDraft['review_reason']) && !empty($aiDraft['needs_review']) ? '' : 'hidden' }}">{{ $aiDraft['review_reason'] ?? '' }}</div>
            {{-- Разбор нужен куратору, когда ИИ не уверен (раскрыт сразу); иначе — тихая ссылка. --}}
            @php $aiNeedsReview = !empty($aiDraft['needs_review']); @endphp
            <details class="ai-elements mt-1 {{ !empty($aiDraft['elements']) ? '' : 'hidden' }}" @if($aiNeedsReview) open @endif>
              <summary class="ai-elements-summary cursor-pointer select-none text-xs {{ $aiNeedsReview ? '' : 'text-gray-500' }}">{{ $aiNeedsReview ? 'Разбор по элементам' : 'почему такой балл' }}</summary>
              <ul class="ai-elements-list mt-1 grid gap-1 text-xs">
                @foreach(($aiDraft['elements'] ?? []) as $el)
                  <li>{{ !empty($el['counted']) ? '✓' : '✗' }} <b>{{ $el['element'] ?? '' }}</b> — {{ $el['reason'] ?? '' }}</li>
                @endforeach
              </ul>
            </details>
          </div>

          {{-- Баллы --}}
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-700">Баллы</span>
            @if($scoreButtons)
              <div class="inline-flex overflow-hidden rounded-lg border border-gray-200">
                @for($v = 0; $v <= $maxScore; $v++)
                  <button type="button" class="score-btn min-w-[2.25rem] border-l border-gray-200 px-2 py-1.5 text-sm font-semibold text-gray-700 first:border-l-0 hover:bg-gray-50" data-value="{{ $v }}">{{ $v }}</button>
                @endfor
              </div>
            @endif
            <input
              type="number" min="0" step="1" max="{{ $maxScore }}"
              class="score-input {{ $scoreButtons ? 'sr-only' : 'w-20 rounded-lg border px-2 py-1.5 text-center font-semibold' }}"
              name="score-visible-{{ $tid }}"
              data-input-for="score-{{ $tid }}"
              data-max="{{ $maxScore }}"
              value="{{ is_numeric($curScore) ? $curScore : '' }}"
            >
          </div>

          <form
            action="{{ route('mentor.review.task.save', [$submission, $tid]) }}"
            method="post"
            class="per-task-form space-y-3"
            data-task="{{ $tid }}"
            data-has-db="{{ ($dbScore !== null || $dbReason !== null || $dbComment !== null) ? '1' : '0' }}"
            data-submission="{{ $submission->id }}"
          >
            @csrf
            <input type="hidden" name="score" id="score-{{ $tid }}" value="{{ is_numeric($curScore) ? $curScore : '' }}">

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Обоснование баллов</label>
              <textarea name="reason" rows="2" class="smart-textarea w-full resize-none overflow-hidden rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-300 focus:ring-indigo-200" placeholder="По критериям экзамена: почему именно такие баллы">{{ $curReason }}</textarea>
              @if(!empty($quickPhrases['reason']))
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                  @foreach($quickPhrases['reason'] as $phrase)
                    <button type="button" class="quick-phrase-btn rounded-full border border-gray-200 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100" data-target="reason" data-phrase="{{ $phrase }}">{{ \Illuminate\Support\Str::limit($phrase, 28) }}</button>
                  @endforeach
                </div>
              @endif
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Комментарий ученику</label>
              <textarea name="comment" rows="3" class="smart-textarea w-full resize-none overflow-hidden rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-300 focus:ring-indigo-200" placeholder="Понятный комментарий для ученика">{{ $curComment }}</textarea>
              @if(!empty($quickPhrases['comment']))
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                  @foreach($quickPhrases['comment'] as $phrase)
                    <button type="button" class="quick-phrase-btn rounded-full border border-gray-200 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-100" data-target="comment" data-phrase="{{ $phrase }}">{{ \Illuminate\Support\Str::limit($phrase, 28) }}</button>
                  @endforeach
                </div>
              @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
              @if($aiEnabled)
                <button formaction="{{ route('mentor.review.task.regen', [$submission, $tid]) }}"
                        class="inline-flex items-center gap-1 rounded-lg border border-purple-200 px-2.5 py-1 text-xs text-purple-700 hover:bg-purple-50">
                  <svg class="h-4 w-4 text-purple-500" viewBox="0 0 72 72" fill="currentColor" aria-hidden="true">
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
                        class="text-xs text-gray-500 underline decoration-dotted hover:text-amber-700">
                  Пропустить задание
                </button>
              @else
                <button type="submit"
                        formaction="{{ route('mentor.review.task.unskip', [$submission, $tid]) }}"
                        formmethod="POST"
                        class="rounded-lg border border-emerald-200 px-2.5 py-1 text-xs text-emerald-700 hover:bg-emerald-50">
                  Вернуть на проверку
                </button>
              @endif

              {{-- Кнопки «Сохранить» нет: правки уходят на сервер автоматически
                   (см. autosave в скрипте ниже), тут только статус. --}}
              <span class="save-status text-xs text-gray-500"></span>

              {{-- Галочка согласия справа --}}
              <label class="consent-wrap ml-auto inline-flex items-center gap-2 text-xs text-gray-700 {{ $needConsent && !($hasAi && $initialState !== 'done') ? '' : 'hidden' }}">
                <input type="checkbox" class="consent-checkbox rounded" data-task="{{ $tid }}">
                Я согласен с AI-экспертом
              </label>
            </div>
          </form>
        </div>
      </div>

      {{-- Оверлей загрузки AI --}}
      <div class="ai-loading-overlay hidden absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white bg-opacity-70">
        <svg class="h-8 w-8 animate-spin text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
      </div>
    </div>
  @empty
    <div class="rounded-2xl border border-gray-200 bg-white p-4 text-gray-600">Письменных заданий нет.</div>
  @endforelse

  {{-- Итоговые действия --}}
  <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
    <form action="{{ route('mentor.review.finish', $submission) }}" method="post" id="finish-form">
      @csrf
      <button type="submit"
        class="finish-btn rounded-lg bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700 disabled:opacity-50"
        @if(!$allManualClosed) disabled title="Завершение доступно, когда все задания проверены или отправлены в отказ" @endif
      >Завершить проверку</button>
    </form>
    <form action="{{ route('mentor.review.finish_next', $submission) }}" method="post" id="finish-next-form">
      @csrf
      <button type="submit"
        class="finish-next-btn rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
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

  // Высота полей под текст. Считаем не только при загрузке: веб-шрифт
  // приходит позже и переносит текст иначе (комментарий обрезался с
  // прокруткой), а у свёрнутых карточек высоты нет вовсе — их пропускаем
  // и досчитываем при разворачивании.
  function autoSize(el) {
    if (!el || el.offsetParent === null) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }
  function autoSizeAll() { document.querySelectorAll('.smart-textarea').forEach(autoSize); }
  window.addEventListener('load', autoSizeAll);
  window.addEventListener('resize', autoSizeAll);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(autoSizeAll);

  // Статус карточки словами в заголовке — чтобы было видно, оценка это,
  // нетронутый черновик ИИ или несохранённая правка.
  const STATUS = {
    saved:   ['Сохранено',                 'bg-green-50 text-green-700'],
    dirty:   ['Не сохранено',              'bg-amber-50 text-amber-800'],
    aiDraft: ['Черновик ИИ — не принят',   'bg-indigo-50 text-indigo-700'],
    todo:    ['Не проверено',              'bg-gray-100 text-gray-600'],
  };
  function renderStatus(card) {
    const chip = card.querySelector('.card-status');
    if (!chip) return;
    let key = 'todo';
    if (card.dataset.dirty === '1')          key = 'dirty';
    else if (card.dataset.state === 'done')  key = 'saved';
    else if (card.dataset.aiDraft === '1')   key = 'aiDraft';
    const hide = card.dataset.skipped === '1';
    chip.className = 'card-status rounded-full px-2 py-0.5 text-xs font-medium ' + STATUS[key][1] + (hide ? ' hidden' : '');
    chip.textContent = STATUS[key][0];
  }

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
  document.addEventListener('input', () => {
    updateTotal();
    document.querySelectorAll('.review-card').forEach(paintScoreButtons);
  });
  updateTotal();

  // Баллы кнопками 0…max: источник правды — поле .score-input (на нём висят
  // автосейв и валидация), кнопки только ставят в него значение.
  function paintScoreButtons(card) {
    const input = card.querySelector('.score-input');
    card.querySelectorAll('.score-btn').forEach((b) => {
      const on = input && input.value !== '' && b.dataset.value === input.value;
      b.classList.toggle('bg-indigo-600', on);
      b.classList.toggle('text-white', on);
      b.classList.toggle('hover:bg-gray-50', !on);
    });
  }
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.score-btn');
    if (!btn) return;
    const input = btn.closest('.review-card')?.querySelector('.score-input');
    if (!input || input.disabled) return;
    input.value = btn.dataset.value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  document.querySelectorAll('.review-card').forEach(paintScoreButtons);

  // Вкладки справки (Текст / Образец / Критерии / …): открыта максимум одна, повторный клик закрывает.
  document.addEventListener('click', (ev) => {
    const tab = ev.target.closest('.card-tab');
    if (!tab) return;
    const card = tab.closest('.review-card');
    const panel = card.querySelector(`.card-tab-panel[data-tab="${tab.dataset.tab}"]`);
    const wasOpen = panel && !panel.classList.contains('hidden');
    card.querySelectorAll('.card-tab-panel').forEach((p) => p.classList.add('hidden'));
    // У активной вкладки свой hover: общий hover:bg-gray-50 давал белый текст на светлом фоне.
    const ACTIVE = ['bg-gray-900', 'text-white', 'border-gray-900', 'hover:bg-gray-700'];
    card.querySelectorAll('.card-tab').forEach((t) => {
      t.classList.remove(...ACTIVE);
      t.classList.add('hover:bg-gray-50');
    });
    if (panel && !wasOpen) {
      panel.classList.remove('hidden');
      tab.classList.remove('hover:bg-gray-50');
      tab.classList.add(...ACTIVE);
    }
  });

  // Свернуть/развернуть карточку (проверенные при загрузке страницы свёрнуты).
  function setCollapsed(card, collapsed) {
    card.dataset.collapsed = collapsed ? '1' : '0';
    card.querySelector('.card-body')?.classList.toggle('hidden', collapsed);
    card.querySelector('.card-summary')?.classList.toggle('hidden', !collapsed);
    card.querySelector('.card-chevron')?.classList.toggle('rotate-180', !collapsed);
    if (!collapsed) card.querySelectorAll('.smart-textarea').forEach(autoSize);
  }
  document.addEventListener('click', (ev) => {
    const head = ev.target.closest('.card-toggle');
    if (!head) return;
    const card = head.closest('.review-card');
    setCollapsed(card, card.dataset.collapsed !== '1');
  });

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
    // Статус — цветной полоской слева (border-l-4 у карточки).
    card.classList.remove('border-l-gray-200','border-l-blue-400','border-l-green-400');
    if (state === 'done')      card.classList.add('border-l-green-400');
    else if (state === 'editing') card.classList.add('border-l-blue-400');
    else                        card.classList.add('border-l-gray-200');
    renderStatus(card);
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
          card.dataset.dirty = '0';
          clearDraft();
          if (statusEl) statusEl.textContent = '';
        }
        renderStatus(card);
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

      // «Заполняется» — только когда куратор сам что-то поменял: подставленный
      // при загрузке черновик ИИ ещё не оценка (раньше карточка с черновиком
      // сразу выглядела как начатая проверка).
      if (form.dataset.hasDb !== '1') {
        if (canSave && dirtyTasks.has(taskId)) cardSetState(card, 'editing');
        else                                   cardSetState(card, 'idle');
      }

      markCardValidity(card, canSave);
    }

    function onAnyFieldEdited() {
      if (consentWrap && !consentWrap.classList.contains('hidden')) {
        consentWrap.classList.add('hidden');
        if (consentChk) consentChk.checked = false;
      }
      // Любая правка — это уже оценка куратора, а не нетронутый черновик ИИ:
      // кнопка «Принять» больше не нужна, дальше работает автосохранение.
      editVersion++;
      dirtyTasks.add(taskId);
      card.dataset.dirty = '1';
      card.dataset.aiDraft = '0';
      card.querySelector('.ai-accept')?.classList.add('hidden');

      syncScoreToHidden();
      updateSaveAvailability();
      renderStatus(card);
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
      const resize = () => autoSize(el);
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

    // «Принять» в панели ИИ: подставить черновик ИИ и сразу сохранить.
    // Нажатие кнопки — и есть согласие с ИИ, поэтому идём через
    // onAnyFieldEdited() (он снимает требование галочки).
    const acceptBtn = card.querySelector('.ai-accept');
    if (acceptBtn) acceptBtn.addEventListener('click', () => {
      if (scoreVisible) scoreVisible.value = acceptBtn.dataset.score || '';
      if (reasonEl)  reasonEl.value  = acceptBtn.dataset.reason || '';
      if (commentEl) commentEl.value = acceptBtn.dataset.comment || '';
      [reasonEl, commentEl].forEach(autoSize);
      onAnyFieldEdited();
      paintScoreButtons(card);
      if (canSave) {
        form.__mentorReviewSave();
        acceptBtn.classList.add('hidden');
      } else if (statusEl) {
        statusEl.textContent = 'У черновика ИИ нет балла — укажите баллы вручную';
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
        [reasonEl, commentEl].forEach(autoSize);
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
          scoreDiv.textContent = 'ИИ: ' + (data.score ?? '—') + ' из ' + (scoreInput ? scoreInput.dataset.max : '—');
          scoreDiv.classList.remove('hidden');

          panel.querySelector('.ai-review').classList.toggle('hidden', !data.needs_review);
          const reasonLine = panel.querySelector('.ai-review-reason');
          reasonLine.textContent = data.review_reason || '';
          reasonLine.classList.toggle('hidden', !(data.needs_review && data.review_reason));

          // «Принять» — с данными свежего черновика
          const acceptBtn = panel.querySelector('.ai-accept');
          if (acceptBtn) {
            acceptBtn.dataset.score   = data.score ?? '';
            acceptBtn.dataset.reason  = data.rationale ?? '';
            acceptBtn.dataset.comment = data.comment ?? '';
            acceptBtn.classList.remove('hidden');
          }

          const list = panel.querySelector('.ai-elements-list');
          list.replaceChildren(...(data.elements || []).map((el) => {
            const li = document.createElement('li');
            const b = document.createElement('b');
            b.textContent = el.element || '';
            li.append((el.counted ? '✓ ' : '✗ '), b, ' — ' + (el.reason || ''));
            return li;
          }));
          const details = panel.querySelector('.ai-elements');
          details.classList.toggle('hidden', !(data.elements || []).length);
          details.open = !!data.needs_review;
          const summary = details.querySelector('.ai-elements-summary');
          summary.textContent = data.needs_review ? 'Разбор по элементам' : 'почему такой балл';
          summary.classList.toggle('text-gray-500', !data.needs_review);
        }

        // Свежий черновик ИИ ещё не оценка: согласие с ним — кнопка «Принять»
        // (раньше для этого была отдельная галочка).
        card.dataset.aiDraft = '1';
        card.dataset.dirty = '0';
        [reasonEl, commentEl].forEach((el) => { if (el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; } });
        if (form.dataset.hasDb !== '1') {
          card.dataset.state = 'idle';
        }
        const chip = card.querySelector('.card-status');
        if (chip) {
          chip.className = 'card-status rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700';
          chip.textContent = 'Черновик ИИ — не принят';
        }

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
