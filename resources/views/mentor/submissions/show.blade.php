{{-- resources/views/mentor/review/show.blade.php --}}
@extends('layouts.main')

@section('content')

@php
  /** @var \App\Models\Submission $submission */
  $submission->loadMissing(['user','homework.lesson.courseSession.course']);

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

  $manualTypes = ['written','image_written','image_manual'];
  $autoTasks   = $tasksCol->filter(fn($t) => !in_array(($t->type ?? ''), $manualTypes, true))->values();
  $manualTasks = $tasksCol->filter(fn($t) =>  in_array(($t->type ?? ''), $manualTypes, true))->values();

  // Данные попытки
  $answers      = $submission->answers ?? [];
  $perTaskRes   = $submission->per_task_results ?? [];
  $aiDrafts     = $submission->ai_drafts ?? [];
  $aiFrozenHash = $submission->ai_frozen_hash ?? [];

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
  foreach ($manualTasks as $i => $t) {
      $tid = (string)($t->id ?? $t->task_id ?? "t_manual_{$i}");
      if (!$isTaskClosed($tid)) { $allManualClosed = false; break; }
  }
@endphp

<div class="max-w-5xl mx-auto px-4 py-6 space-y-6">

  {{-- Хедер --}}
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold mb-1">Проверка домашней работы</h1>
      <div class="text-sm text-gray-600">
        Ученик: <span class="font-medium text-gray-800">{{ $student?->name ?? ('ID '.$student?->id) }}</span>
        <span class="mx-2">•</span>
        Работа: <span class="font-medium text-gray-800">{{ $homework->title ?? 'Без названия' }}</span>
        <span class="mx-2">•</span>
        Попытка № {{ $submission->attempt_no ?? 1 }}
      </div>

      @foreach (['success'=>'green','error'=>'red','warning'=>'yellow','info'=>'blue'] as $k=>$clr)
        @if (session($k))
          <div class="mt-3 rounded-lg bg-{{ $clr }}-50 border border-{{ $clr }}-200 text-{{ $clr }}-800 px-3 py-2">
            {{ session($k) }}
          </div>
        @endif
      @endforeach
    </div>

    {{-- таймер лока в шапке --}}
    <div class="shrink-0">
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
      </div>
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

      // --- Критерии: всегда из таблицы tasks ---
      $taskIdForDb = $t->task_id ?? $t->id ?? null;
      $taskRow     = null;
      if ($taskIdForDb !== null && is_numeric($taskIdForDb)) {
          $taskRow = \App\Models\Task::select(['id','criteria','comment'])->find($taskIdForDb);
      }

      // сырой текст критериев из БД (может быть plain-text или JSON)
      $criteriaText = (string) optional($taskRow)->criteria;

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
          </div>
        </div>

        <div class="flex gap-6">
          {{-- AI-эксперт --}}
          @php
            $hasAi = ($aiRationaleFull && trim($aiRationaleFull) !== '') || ($aiCommentFull && trim($aiCommentFull) !== '') || is_numeric($aiScore);
          @endphp
          <div class="rounded-xl border {{ $hasAi ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-gray-200 bg-gray-50 text-gray-700' }} px-3 py-2 text-sm">
            <div class="font-medium mb-1">AI-эксперт</div>
            @if($hasAi)
              <div class="grid gap-1">
                <div class="ai-score-display">
                  Рекомендованные баллы: {{ is_numeric($aiScore) ? $aiScore : '—' }}
                </div>
              </div>
            @else
              <div class="text-sm">Пока без рекомендаций. Нажмите «Проверить заново (AI)» или заполните поля вручную.</div>
            @endif
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

      <div class="mb-4">
        <div class="text-xs text-gray-500 mb-1">Ответ ученика</div>
        <div class="whitespace-pre-wrap rounded-xl border border-gray-100 bg-gray-50 px-3 py-3 text-sm">
          {{ $studentAns !== '' ? $studentAns : '—' }}
        </div>
      </div>

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
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Комментарий ученику</label>
          <textarea name="comment" rows="5" class="smart-textarea w-full border rounded-xl px-3 py-2" placeholder="Понятный комментарий для ученика...">{{ $curComment }}</textarea>
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

        <div class="flex flex-wrap items-center gap-2 justify-start pt-1">
          {{-- Кнопки слева --}}
          <div class="flex flex-wrap items-center gap-2">
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

            <button
              type="submit"
              class="save-task-btn px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
              disabled
            >Сохранить</button>
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

  function cardSetState(card, state){
    if (card.dataset.skipped === '1') return;
    card.dataset.state = state;
    card.classList.remove('border-gray-200','border-blue-400','border-green-400');
    if (state === 'done')      card.classList.add('border-green-400');
    else if (state === 'editing') card.classList.add('border-blue-400');
    else                        card.classList.add('border-gray-200');
  }

  function markCardValidity(cardEl, isValid) {
    cardEl.classList.toggle('invalid', !isValid);
    cardEl.style.boxShadow = 'none';
  }

  perTaskForms.forEach((form) => {
    const card = form.closest('.review-card') || form.parentElement;
    const taskId = form.dataset.task || '';

    const scoreHidden  = form.querySelector(`#score-${CSS.escape(taskId)}`);
    const scoreVisible = document.querySelector(`[data-input-for="score-${CSS.escape(taskId)}"]`);
    const max          = scoreVisible ? parseInt(scoreVisible.dataset.max || '0', 10) : 0;

    const reasonEl  = form.querySelector('textarea[name="reason"]');
    const commentEl = form.querySelector('textarea[name="comment"]');

    const consentWrap = form.querySelector('.consent-wrap');
    const consentChk  = consentWrap ? consentWrap.querySelector('.consent-checkbox') : null;
    const saveBtn     = form.querySelector('.save-task-btn');

    const hasDb = form.dataset.hasDb === '1';
    if (hasDb) cardSetState(card, 'done');

    function syncScoreToHidden() {
      let v = scoreVisible && scoreVisible.value !== '' ? Number(scoreVisible.value) : NaN;
      if (Number.isNaN(v)) v = 0;
      if (v < 0) v = 0;
      if (v > max) v = max;
      if (scoreVisible) scoreVisible.value = String(v);
      if (scoreHidden)  scoreHidden.value  = String(v);
    }

    function updateSaveAvailability() {
      let v = scoreVisible && scoreVisible.value !== '' ? Number(scoreVisible.value) : NaN;
      if (Number.isNaN(v)) v = 0;
      const validScore = v >= 0 && v <= max;

      if (scoreHidden) scoreHidden.value = String(Math.floor(v));

      const reasonFilled  = reasonEl  && reasonEl.value.trim()  !== '';
      const commentFilled = commentEl && commentEl.value.trim() !== '';

      let consentOk = true;
      if (consentWrap && !consentWrap.classList.contains('hidden')) {
        consentOk = !!(consentChk && consentChk.checked);
      }

      const canSave = validScore && consentOk && reasonFilled && commentFilled;
      saveBtn.disabled = !canSave;

      if (!hasDb) {
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

    if (consentChk) consentChk.addEventListener('change', updateSaveAvailability);

    syncScoreToHidden();
    updateSaveAvailability();
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

        // обновляем «Рекомендованные баллы»
        const aiScoreDiv = card.querySelector('.ai-score-display');
        if (aiScoreDiv) {
          aiScoreDiv.textContent = 'Рекомендованные баллы: ' + (data.score ?? '—');
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
