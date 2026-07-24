{{-- resources/views/partials/student-dashboard-event-card.blade.php
     Общий дизайн для "ближайшего урока" и "ближайшей домашки" на дашборде
     ученика (student/dashboard.blade.php) — вынесено в partial, чтобы у
     обоих гарантированно был один и тот же разметочный код, а не просто
     похожие классы в двух местах.

     Цвета/радиус/иконка/пилюля предмета/размер заголовка — один в один с
     карточками занятий в расписании (тот же файл, блок #dashboard-cards-grid
     ниже): те же ключи $color (theory/practice/homework/mock/overdue/
     completed) — сюда приходит то же самое значение 'color' из $daysMap в
     DashboardController. Заголовок — text-base, как в расписании; если это
     не помещается в узкую card 2, ширину колонки нужно увеличивать в гриде
     (#dashboard-cards-grid), а не ужимать шрифт здесь.

     min-w-0 на каждом уровне flex-обёртки — иначе длинный заголовок без
     пробелов может распереть колонку грида шире контейнера: у flex/grid
     потомков по умолчанию min-width: auto, и он побеждает truncate
     (overflow: hidden), если хоть один родитель в цепочке его не сбросит.

     Ожидаемые переменные: $item (массив данных или null), $type, $color,
     $title, $subject, $dateLabel, $href (nullable), $emptyText. --}}
@php
    $bgMap = [
        'theory'    => 'bg-apple-blue-50',
        'practice'  => 'bg-apple-purple-50',
        'homework'  => 'bg-apple-orange-100',
        'mock'      => 'bg-apple-indigo-50',
        'overdue'   => 'bg-apple-red-50',
        'completed' => 'bg-apple-green-100',
    ];
    $borderMap = [
        'theory'    => 'border-apple-blue-200',
        'practice'  => 'border-apple-purple-200',
        'homework'  => 'border-apple-orange-300',
        'mock'      => 'border-apple-indigo-200',
        'overdue'   => 'border-apple-red-450',
        'completed' => 'border-apple-green-400',
    ];
    $textMap = [
        'theory'    => 'text-apple-blue-700',
        'practice'  => 'text-apple-purple-700',
        'homework'  => 'text-apple-orange-700',
        'mock'      => 'text-apple-indigo-700',
        'overdue'   => 'text-apple-red-650',
        'completed' => 'text-apple-green-700',
    ];
    $bg = $bgMap[$color ?? 'theory'] ?? $bgMap['theory'];
    $border = $borderMap[$color ?? 'theory'] ?? $borderMap['theory'];
    $text = $textMap[$color ?? 'theory'] ?? $textMap['theory'];
    // gap-2 (не gap-1) — тот же зазор между строками, что и space-y-2 у
    // карточек в расписании: это часть "точно такой же высоты", не только
    // размер шрифта.
    $boxClasses = "h-full min-w-0 flex flex-col justify-center gap-2 rounded-2xl border {$bg} {$border} px-3 py-3";
@endphp
@if($item ?? null)
    @if($href)
        <a href="{{ $href }}" class="{{ $boxClasses }} hover:opacity-90 transition">
    @else
        <div class="{{ $boxClasses }}">
    @endif
            <div class="flex items-center justify-between gap-2 min-w-0 text-xs {{ $text }}">
                <span class="flex items-center gap-1 min-w-0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 shrink-0"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 01-3.46 0"></path></svg>
                    <span class="font-medium truncate">{{ $type }}</span>
                </span>
                <span class="shrink-0 whitespace-nowrap">{{ $dateLabel }}</span>
            </div>
            <div class="text-base font-medium text-zinc-900 leading-snug truncate">{{ $title }}</div>
            <div class="min-w-0">
                <span class="inline-block bg-white text-zinc-700 text-xs px-2 pt-0.5 pb-1 rounded-full truncate max-w-full">{{ $subject }}</span>
            </div>
    @if($href)
        </a>
    @else
        </div>
    @endif
@else
    <div class="h-full min-w-0 flex items-center justify-center text-center text-zinc-500 text-sm rounded-2xl border border-dashed border-gray-200 px-3 py-3">
        {{ $emptyText }}
    </div>
@endif
