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
            <x-icon name="clipboard-check" class="w-4 h-4" />
        </span>
    @endif

    @if(!empty($lesson?->notes_link))
        <span class="w-8 h-8 rounded-full bg-white border border-gray-200 shadow-sm flex items-center justify-center text-gray-700" title="Конспект">
            <x-icon name="file-05" class="w-4 h-4" />
        </span>
    @endif
</div>
