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

  $manualScore = !is_null($submission->manual_score)
                ? (int)$submission->manual_score
                : (int)$manualTasks->sum(function($t) use ($getPerTask) {
                    $tid = $t->id ?? null;
                    return (int)$getPerTask($tid, 'score', 0);
                  });

  $totalMax   = $autoMax + $manualMax;
  $totalScore = (!is_null($submission->total_score))
                  ? (int)$submission->total_score
                  : ($autoScore + $manualScore);

  $pct = function(int $score, int $max) {
      return $max > 0 ? min(100, max(0, round($score * 100 / $max))) : 0;
  };
  $autoPct   = $pct($autoScore, $autoMax);
  $manualPct = $pct($manualScore, $manualMax);

  // Счётчики для живой сводки по ручной части (не влияет на текущие блоки сверху)
  $manualCheckedSum = 0;  // сумма баллов по уже проверенным ручным задачам
  $manualPendingCnt = 0;  // сколько ручных задач ещё ждут
  $manualPendingMax = 0;  // их суммарный максимум
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
        Статус: <span class="font-medium text-gray-700">{{ $submission->status ?? 'pending' }}</span>
      </div>
    </div>
    <div class="shrink-0">
      <a href="{{ route('student.courses.show', $submission->homework?->lesson?->courseSession?->course) }}"
         class="text-blue-600 hover:underline">
        ← Вернуться к курсу
      </a>
    </div>
  </div>

  {{-- Итог по работе --}}
  <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="text-gray-700">
        Итог по работе:
      </div>
      <div class="text-lg font-semibold">
        {{ $totalScore }} / {{ $totalMax }} баллов
      </div>
    </div>
  </div>

  {{-- Две колонки --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Левая: автопроверка --}}
    <div class="rounded-2xl  shadow bg-white p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Первая часть (автопроверка)</h2>
        {{-- <span class="text-gray-700 font-medium">{{ $autoScore }} / {{ $autoMax }}</span> --}}
      </div>

      <div class="flex items-center gap-6">
        {{-- ПОЛУКРУГ Chart.js: фиолетовый --}}
        <div class="relative w-[240px] h-[140px]">
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

<div class="rounded-2xl border-2 border-dashed border-purple-200 bg-white p-3 py-2 pb-3 shadow-sm relative overflow-hidden">
  <div class="flex items-center gap-2">
    <div class="flex-1">
      <div class="flex items-end gap-2">
        <span class="text-lg leading-none self-center relative top-1 mr-2">
            @if ($autoPct > 70)
                <img class="w-6" src="{{ asset('/img/noto_fire.svg') }}" alt="fire">
            @elseif ($autoPct > 50)
                <img class="w-6" src="{{ asset('/img/like.svg') }}" alt="like">
            @else
                <img class="w-6" src="{{ asset('/img/crying.svg') }}" alt="crying">
            @endif
        </span>
        <div class="mt-2 text-2xl font-extrabold leading-none
          text-violet-600
          bg-clip-text text-transparent">
          {{ $autoScore }} / {{ $autoMax }}
        </div>
        <div class="text-sm text-gray-500">баллов</div>
      </div>

      {{-- чипсы-статусы --}}
      <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
        <span style="background-color: #def5ee" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-green-700">
            <svg class="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M20 7L9 18l-5-5"/>
            </svg> Верно: {{ $autoStats['ok'] }}
        </span>
        <span style="background-color: #fdf4df" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-yellow-700">
          <svg class="w-4 h-4 text-yellow-600" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Частично: {{ $autoStats['partial'] }}
        </span>
        <span style="background-color: #ffe4e0" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-red-700">
          <svg class="w-3 h-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M18 6L6 18M6 6l12 12"/>
          </svg> Неверно: {{ $autoStats['fail'] }}
        </span>
      </div>
    </div>
  </div>
</div>

      </div>

{{-- Список авто-заданий --}}
<div class="mt-5">
  <div class="text-sm font-medium text-gray-600 mb-4">Баллы за задания:</div>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
    @forelse($autoTasks as $i => $t)
      @php
        $tid   = $t->id ?? ("t_auto_$i");
        $max   = (int)($t->max_score ?? 1);
        $score = (int)($perTaskRes[$tid]['score'] ?? 0);

        if ($score === $max) {
            $status = 'ok';
        } elseif ($score > 0) {
            $status = 'partial';
        } else {
            $status = 'fail';
        }
      @endphp

      <div style="background-color: #{{ $status === 'ok' ? 'DEF5EE' : ($status === 'partial' ? 'FDF4DF' : 'FFE4E0') }}" class="flex flex-col items-center justify-between rounded-xl 
                  aspect-square p-2"> 
        {{-- Номер задания --}}
        <div class="text-xs font-medium text-gray-500">
          № {{ $t->order ?? ($i+1) }}
        </div>

        {{-- Статус + Баллы --}}
        <div class="flex-1 flex flex-col items-center justify-center gap-1">
          {{-- Иконка --}}
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

          {{-- Баллы --}}
          <span class="text-lg font-bold 
                       {{ $status === 'ok' ? 'text-green-600' : ($status === 'partial' ? 'text-yellow-600' : 'text-red-600') }}">
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
        {{-- <span class="text-gray-700 font-medium">{{ $manualScore }} / {{ $manualMax }}</span> --}}
      </div>

      <div class="flex items-center gap-4">
        {{-- ПОЛУКРУГ Chart.js: зелёный --}}
        <div class="relative w-[240px] h-[140px]">
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

        {{-- Карточка баллов --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="text-sm text-gray-600">Начислено за ручную часть</div>
          <div class="mt-1 text-4xl font-extrabold leading-none">{{ $manualScore }}</div>
          <div class="text-sm text-gray-500">из {{ $manualMax }} баллов</div>

          <div class="mt-4 h-2 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-400" style="width: {{ $manualPct }}%"></div>
          </div>
        </div>
      </div>

      {{-- Список ручных заданий (без правильных ответов) --}}
      <div class="mt-5 space-y-3">
        @forelse($manualTasks as $i => $t)
          @php
            $tid     = $t->id ?? ("t_manual_$i");
            $max     = (int)($t->max_score ?? 1);
            $row     = $perTaskRes[$tid] ?? [];
            $score   = (int)($row['score'] ?? 0);
            $skipped = (bool)($row['skipped'] ?? false);

            // «Проверено» только если реально сохранён результат по задаче и она не была пропущена.
            // НЕ наследуем checked со всей работы.
            $isChecked = ($score > 0) && !$skipped;

            // Ответ ученика (ранее в твоём шаблоне $ans мог быть не определён)
            $ans   = (string)($answers[$tid] ?? '');

            // Счётчики для сводки по ручной части
            if ($isChecked) {
              $manualCheckedSum += $score;
            } else {
              $manualPendingCnt += 1;
              $manualPendingMax += $max;
            }
          @endphp

          <div class="rounded-xl border border-gray-200 p-3">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-gray-100 text-gray-700 text-xs font-semibold">{{ $t->order ?? ($i+1) }}</span>
                <div class="text-sm font-medium">Задание (ручная проверка)</div>
              </div>

              {{-- Правый край: либо баллы, либо статусы ожидания --}}
              @if($isChecked)
                <div class="text-sm font-semibold text-gray-700">{{ $score }} / {{ $max }}</div>
              @elseif($skipped && $submission->status === 'pending')
                <span class="inline-block text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-800">
                  На проверке админом
                </span>
              @else
                <span class="inline-block text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">
                  Ожидает проверки
                </span>
              @endif
            </div>

            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <div>
                <div class="text-gray-500">Ваш ответ</div>
                @if(in_array($t->type, ['image_written','image_manual']))
                  <div class="font-mono text-base">{{ $ans !== '' ? e($ans) : '—' }}</div>
                @else
                  <div class="whitespace-pre-wrap text-base">{{ $ans !== '' ? e($ans) : '—' }}</div>
                @endif
              </div>
              <div>
                <div class="text-gray-500">Статус проверки</div>
                <div class="inline-flex items-center gap-2 text-sm">
                  @if($isChecked)
                    <svg class="w-4 h-4 text-green-600" viewBox="0 0 24 24" fill="none"><path d="M20 7L9 18l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-gray-700">Проверено куратором</span>
                  @elseif($skipped && $submission->status === 'pending')
                    <svg class="w-4 h-4 text-yellow-600" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-gray-700">На проверке админом</span>
                  @else
                    <svg class="w-4 h-4 text-yellow-600" viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-gray-700">Ожидает проверки</span>
                  @endif
                </div>
              </div>
            </div>
          </div>
        @empty
          <div class="text-sm text-gray-500">Заданий для ручной проверки нет.</div>
        @endforelse
      </div>

      {{-- Сводка по ручной части (живой прогресс для пользователя) --}}
      <div class="mt-4 p-3 rounded-lg bg-gray-50 border">
        <div class="text-sm">
          <div>
            <strong>Проверено:</strong> {{ $manualCheckedSum }}
            @if($manualMax>0) / {{ $manualMax }} @endif
          </div>
          @if($manualPendingCnt > 0)
            <div class="text-gray-600 mt-1">
              Ещё на проверке: {{ $manualPendingCnt }} {{ \Illuminate\Support\Str::plural('задание', $manualPendingCnt, 'задание|задания|заданий') }},
              максимум {{ $manualPendingMax }} балл{{ $manualPendingMax % 10 == 1 && $manualPendingMax % 100 != 11 ? '' : 'ов' }}.
              Итог обновится после проверки админом.
            </div>
          @endif
        </div>
      </div>
    </div>
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
  {{-- Chart.js --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <script>
  (function () {
    // Создаём полукруговую «pill»-диаграмму
    function makePillGauge(canvas, percent, fromHex, toHex) {
      const ctx = canvas.getContext('2d');

      // Сколько «пилюль» по дуге
      const SEGMENTS = 30; // 28–36 выглядит хорошо
      const filledCount = Math.round(SEGMENTS * Math.max(0, Math.min(100, percent)) / 100);

      // Данные — равные сегменты
      const data = new Array(SEGMENTS).fill(1);

      // Цвет фона (светлая дорожка)
      const bg = 'rgba(107,114,128,0.18)'; // #6B7280 с прозрачностью
      const colors = Array.from({length: SEGMENTS}, (_, i) => i < filledCount ? null : bg);

      // Градиент заливки заполненной части
      const gradient = ctx.createLinearGradient(0, canvas.height, canvas.width, 0);
      gradient.addColorStop(0, fromHex);
      gradient.addColorStop(1, toHex);

      const colorizeFilled = {
        id: 'colorizeFilled',
        beforeDatasetsDraw(chart) {
          const meta = chart.getDatasetMeta(0);
          const mid = (filledCount - 1) / 2;
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
