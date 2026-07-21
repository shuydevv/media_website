{{-- resources/views/partials/student-dashboard-event-card.blade.php
     Общий дизайн для "ближайшего урока" и "ближайшей домашки" на дашборде
     ученика (student/dashboard.blade.php) — вынесено в partial, чтобы у
     обоих гарантированно был один и тот же разметочный код, а не просто
     похожие классы в двух местах.

     min-w-0 на каждом уровне flex-обёртки — иначе длинный заголовок без
     пробелов может распереть колонку грида шире контейнера: у flex/grid
     потомков по умолчанию min-width: auto, и он побеждает truncate
     (overflow: hidden), если хоть один родитель в цепочке его не сбросит.

     Ожидаемые переменные: $item (массив данных или null), $type, $color,
     $title, $subject, $dateLabel, $href (nullable), $emptyText. --}}
@php
    $bgMap = ['blue' => 'bg-blue-100', 'purple' => 'bg-purple-100', 'orange' => 'bg-orange-100', 'yellow' => 'bg-yellow-100', 'red' => 'bg-red-100'];
    $borderMap = ['blue' => 'border-blue-200', 'purple' => 'border-purple-200', 'orange' => 'border-orange-200', 'yellow' => 'border-yellow-200', 'red' => 'border-red-200'];
    $textMap = ['blue' => 'text-blue-700', 'purple' => 'text-purple-700', 'orange' => 'text-orange-700', 'yellow' => 'text-yellow-700', 'red' => 'text-red-700'];
    $bg = $bgMap[$color ?? 'blue'] ?? $bgMap['blue'];
    $border = $borderMap[$color ?? 'blue'] ?? $borderMap['blue'];
    $text = $textMap[$color ?? 'blue'] ?? $textMap['blue'];
    $boxClasses = "h-full min-w-0 flex flex-col justify-center gap-1 rounded-xl border {$bg} {$border} px-3 py-3";
@endphp
@if($item ?? null)
    @if($href)
        <a href="{{ $href }}" class="{{ $boxClasses }} hover:opacity-90 transition">
    @else
        <div class="{{ $boxClasses }}">
    @endif
            <div class="flex items-center justify-between gap-2 min-w-0 text-xs {{ $text }}">
                <span class="font-medium truncate">{{ $type }}</span>
                <span class="shrink-0 whitespace-nowrap">{{ $dateLabel }}</span>
            </div>
            <div class="text-sm font-medium text-gray-900 leading-snug truncate">{{ $title }}</div>
            <div class="text-xs text-gray-600 truncate">{{ $subject }}</div>
    @if($href)
        </a>
    @else
        </div>
    @endif
@else
    <div class="h-full min-w-0 flex items-center justify-center text-center text-gray-500 text-sm rounded-xl border border-dashed border-gray-200 px-3 py-3">
        {{ $emptyText }}
    </div>
@endif
