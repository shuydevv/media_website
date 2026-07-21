{{-- resources/views/student/partials/lesson-image-badges.blade.php
     Два значка поверх обложки урока: домашка (цвет = статус, см.
     CourseController::homeworkBadgeColor — gray/red/blue/green, либо не
     выводится вовсе, если домашки нет) и конспект (выводится только если у
     урока есть notes_link, без вариантов состояния — просто есть/нет). Белая
     подложка с обводкой, как договорились. --}}
@php
    $homeworkColorClass = [
        'gray'  => 'text-gray-400',
        'red'   => 'text-red-500',
        'blue'  => 'text-blue-500',
        'green' => 'text-green-500',
    ][$homeworkColor ?? null] ?? null;
@endphp
<div class="absolute top-3 right-3 flex items-center gap-2">
    @if($homeworkColorClass)
        <span class="w-8 h-8 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center {{ $homeworkColorClass }}" title="Домашка">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                <path d="M9 3v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V3"></path>
                <path d="M9 12l2 2 4-4"></path>
            </svg>
        </span>
    @endif

    @if(!empty($lesson?->notes_link))
        <span class="w-8 h-8 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center text-gray-700" title="Конспект">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H18a1 1 0 0 1 1 1v17a1 1 0 0 1-1 1H6.5A2.5 2.5 0 0 1 4 18.5v-14Z"></path>
                <path d="M8 7h8M8 11h8M8 15h5"></path>
            </svg>
        </span>
    @endif
</div>
