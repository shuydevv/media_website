{{-- resources/views/student/mocks/index.blade.php
     Список пробников студента — отдельная страница от обычных домашек
     (см. HomeworkController::index(), там type=mock намеренно исключён).
     Один пробник = одна карточка (не по попытке), сетка в 2 колонки. --}}
@extends('layouts.main')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    <h1 class="sans-medium text-2xl md:text-3xl mb-6 text-zinc-900">Пробники</h1>

    @if($rows->isEmpty())
        <x-ui.card class="text-zinc-600 text-center">
            Пробников пока нет.
        </x-ui.card>
    @else
        <div class="mock-grid">
            @foreach($rows as $row)
                @php
                    $hw = $row['homework'];
                    $status = $row['status'];

                    $badgeMap = [
                        'not_started'    => ['label' => 'Не выполнен',   'class' => 'bg-gray-100 text-gray-700'],
                        'in_progress'    => ['label' => 'В процессе', 'class' => 'bg-blue-50 text-blue-700'],
                        'pending_review' => ['label' => 'На проверке','class' => 'bg-amber-50 text-amber-700'],
                        'checked'        => ['label' => 'Проверено',  'class' => 'bg-emerald-50 text-emerald-700'],
                    ];
                    $badge = $badgeMap[$status];

                    $actionMap = [
                        'not_started'    => 'Начать пробник',
                        'in_progress'    => 'Продолжить',
                        'pending_review' => 'Посмотреть',
                        'checked'        => 'Результат',
                    ];
                    $actionLabel = $actionMap[$status];

                    $actionUrl = (in_array($status, ['pending_review', 'checked'], true) && $row['submission'] && Route::has('student.submissions.show'))
                        ? route('student.submissions.show', $row['submission'])
                        : (Route::has('student.submissions.create') ? route('student.submissions.create', $hw) : '#');

                @endphp

                <a href="{{ $actionUrl }}" class="mock-card">
                    <div class="flex items-start justify-between gap-2 mb-5">
                        <div class="min-w-0">
                            <div class="text-xs text-zinc-400 truncate">{{ $row['courseTitle'] }}</div>
                            <div class="text-base font-semibold text-zinc-900 mt-0.5">Пробник №{{ $row['mockNumber'] ?? '—' }}</div>
                        </div>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-medium whitespace-nowrap shrink-0 {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    </div>

                    <div class="flex flex-col items-center gap-5 py-3">
                        <div class="mock-chart-wrap">
                            <canvas
                                class="mock-chart"
                                width="320" height="320"
                                data-auto-pct="{{ $row['autoPct'] }}"
                                data-manual-pct="{{ $row['manualPct'] }}"
                            ></canvas>
                            <div class="mock-chart-center">
                                <div class="mock-chart-score">{{ $row['scaledScore'] }}<span class="mock-chart-score-max">/100</span></div>
                                <div class="mock-chart-score-label">баллов</div>
                            </div>
                        </div>
                        <div class="mock-legend">
                            <div class="mock-legend-row">
                                <span class="mock-legend-dot" style="background:#007AFF"></span>
                                <span class="mock-legend-label">Первая часть</span>
                                <span class="mock-legend-value">{{ $row['autoScaled'] }}</span>
                            </div>
                            <div class="mock-legend-row">
                                <span class="mock-legend-dot" style="background:#AF52DE"></span>
                                <span class="mock-legend-label">Вторая часть</span>
                                <span class="mock-legend-value">{{ $row['manualScaled'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-zinc-100 flex items-center justify-between gap-2">
                        <div class="text-xs text-zinc-500">
                            @if($row['submittedAt'])
                                {{ $row['submittedAt']->translatedFormat('j F Y') }}
                            @else
                                Дата появится после выполнения
                            @endif
                        </div>
                        <span class="mock-cta">{{ $actionLabel }} →</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>

<style>
    .mock-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    @media (min-width: 768px) {
        .mock-grid { grid-template-columns: 1fr 1fr; }
    }

    .mock-card {
        display: block;
        border-radius: 1rem;
        background: #fff;
        border: 1px solid #e4e4e7;
        padding: 1.25rem;
    }

    .mock-chart-wrap {
        position: relative;
        width: 210px;
        height: 210px;
        max-width: 100%;
    }
    /* Диаграмма чисто декоративная: не реагирует на курсор (см. events: []
       в инициализации Chart.js ниже) — pointer-events отключаем и на
       уровне canvas тоже, на случай наведения между сегментами. */
    .mock-chart {
        pointer-events: none;
    }
    .mock-chart-center {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        line-height: 1;
    }
    .mock-chart-score {
        font-size: 2.1rem;
        font-weight: 700;
        color: #18181b;
    }
    .mock-chart-score-max {
        font-size: 1rem;
        font-weight: 500;
        color: #a1a1aa;
    }
    .mock-chart-score-label {
        font-size: .75rem;
        color: #a1a1aa;
        margin-top: 6px;
    }

    .mock-legend {
        width: 100%;
        max-width: 220px;
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .mock-legend-row {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .8rem;
        color: #52525b;
    }
    .mock-legend-dot {
        width: 9px;
        height: 9px;
        border-radius: 9999px;
        flex-shrink: 0;
    }
    .mock-legend-label {
        flex: 1;
        min-width: 0;
    }
    .mock-legend-value {
        font-weight: 600;
        color: #18181b;
    }

    .mock-cta {
        font-size: .875rem;
        font-weight: 500;
        color: #2927BE;
        white-space: nowrap;
    }
</style>

{{-- Chart.js — те же CDN и версия, что и в student/submissions/show.blade.php --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Два вложенных кольца в стиле Apple Activity: сплошная скруглённая дуга
    // на светлом треке, без подписей внутри canvas — итоговый балл наложен
    // поверх обычным HTML (.mock-chart-center). ВАЖНО: заливка кольца — это
    // % от СОБСТВЕННОГО максимума части (autoPct/manualPct), а не доля части
    // в общем стобалльном счёте (то число, что в легенде под диаграммой) —
    // иначе даже полностью верно решённая часть никогда не заполнила бы
    // кольцо целиком. borderColor = фон карточки: белая обводка "прокусывает"
    // заливку, создавая зазор между кольцами и воздух вокруг скруглённых
    // концов дуги, а не просто два кольца впритык.
    function makeSectionRings(canvas, autoPct, manualPct) {
        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                datasets: [
                    {
                        data: [autoPct, 100 - autoPct],
                        backgroundColor: ['#007AFF', '#F0F7FF'],
                        borderColor: '#fff',
                        borderWidth: 5,
                        borderRadius: 12,
                        weight: 1,
                    },
                    {
                        data: [manualPct, 100 - manualPct],
                        backgroundColor: ['#AF52DE', '#F9F2FD'],
                        borderColor: '#fff',
                        borderWidth: 5,
                        borderRadius: 12,
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
                // events: [] — полностью выключает обработку мыши: сегменты
                // не подсвечиваются и не сдвигаются при наведении, диаграмма
                // чисто декоративная (кликабельна только карточка-ссылка).
                events: [],
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                },
            },
        });
    }

    document.querySelectorAll('canvas.mock-chart').forEach(function (cv) {
        makeSectionRings(cv, Number(cv.dataset.autoPct || 0), Number(cv.dataset.manualPct || 0));
    });
})();
</script>
@endsection
