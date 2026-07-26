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
                        'not_started'    => ['label' => 'Не начат',   'class' => 'bg-white/20 text-white'],
                        'in_progress'    => ['label' => 'В процессе', 'class' => 'bg-white/25 text-white'],
                        'pending_review' => ['label' => 'На проверке','class' => 'bg-amber-400/90 text-amber-950'],
                        'checked'        => ['label' => 'Проверено',  'class' => 'bg-emerald-400/90 text-emerald-950'],
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

                    $pct = $row['scaledScore'] ?? 0;
                @endphp

                <a href="{{ $actionUrl }}" class="mock-card">
                    <div class="mock-card-head">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-[11px] uppercase tracking-wider text-white/70 mb-1">Пробник</div>
                                <div class="text-3xl font-semibold text-white leading-none">№{{ $row['mockNumber'] ?? '—' }}</div>
                            </div>
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-medium whitespace-nowrap {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        </div>
                        <div class="mt-3 text-sm text-white/85 truncate">{{ $row['courseTitle'] }}</div>
                    </div>

                    <div class="mock-card-body">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="stat-tile">
                                <div class="stat-ring" style="--pct: {{ $pct }}">
                                    <div class="stat-ring-inner">
                                        {{ $row['primaryScore'] !== null ? $row['primaryScore'] : '—' }}{{ $row['primaryMax'] ? '/'.$row['primaryMax'] : '' }}
                                    </div>
                                </div>
                                <div class="stat-label">Первичный балл</div>
                            </div>
                            <div class="stat-tile">
                                <div class="stat-ring" style="--pct: {{ $pct }}; --ring-color: #10b981; --ring-bg: #d1fae5;">
                                    <div class="stat-ring-inner">{{ $row['scaledScore'] !== null ? $row['scaledScore'] : '—' }}</div>
                                </div>
                                <div class="stat-label">Баллы (из 100)</div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <div class="text-xs text-zinc-500">
                                @if($row['submittedAt'])
                                    Сдан {{ $row['submittedAt']->format('d.m.Y') }}
                                @else
                                    Ещё не сдавался
                                @endif
                            </div>
                            <span class="mock-cta">{{ $actionLabel }} →</span>
                        </div>
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
        position: relative;
        display: block;
        border-radius: 1.25rem;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 20px rgba(0,0,0,.06);
        transition: transform .22s ease, box-shadow .22s ease;
        isolation: isolate;
    }
    .mock-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 10px rgba(0,0,0,.06), 0 18px 34px rgba(76,29,149,.16);
    }
    /* Диагональный световой блик по ховеру — чисто CSS, без лишних зависимостей. */
    .mock-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(115deg, transparent 40%, rgba(255,255,255,.35) 50%, transparent 60%);
        background-size: 250% 250%;
        background-position: 130% 130%;
        transition: background-position .7s ease;
        pointer-events: none;
        z-index: 5;
    }
    .mock-card:hover::after {
        background-position: -30% -30%;
    }

    .mock-card-head {
        position: relative;
        padding: 1.25rem 1.25rem 1.1rem;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #d946ef 100%);
    }

    .mock-card-body {
        padding: 1.1rem 1.25rem 1.25rem;
    }

    .stat-tile {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .5rem;
        text-align: center;
    }
    .stat-ring {
        --pct: 0;
        --ring-color: #7c3aed;
        --ring-bg: #ede9fe;
        width: 72px;
        height: 72px;
        border-radius: 9999px;
        background: conic-gradient(var(--ring-color) calc(var(--pct) * 1%), var(--ring-bg) 0);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-ring-inner {
        width: 54px;
        height: 54px;
        border-radius: 9999px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: .8rem;
        color: #3f3f46;
    }
    .stat-label {
        font-size: 11px;
        color: #71717a;
    }

    .mock-cta {
        font-size: .8rem;
        font-weight: 500;
        color: #7c3aed;
        white-space: nowrap;
    }
</style>
@endsection
