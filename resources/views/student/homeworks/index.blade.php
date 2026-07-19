{{-- resources/views/student/homeworks/index.blade.php
     Список всех домашек студента по всем курсам сразу (без пробников —
     у тех своя страница). --}}
@extends('layouts.main')

@php
    // При @extends дочерний шаблон выполняется РАНЬШЕ родительского layout
    // (сначала собираются секции, потом рендерится layout вокруг них) —
    // поэтому нельзя полагаться на ru_plural() из partials/billing-banner,
    // она ещё не определена на этот момент. Дублируем локально, как и в
    // dashboard.blade.php/billing-banner.blade.php.
    if (!function_exists('ru_plural')) {
        function ru_plural($n, $one, $few, $many) {
            $n = abs($n);
            $mod10 = $n % 10;
            $mod100 = $n % 100;
            if ($mod10 === 1 && $mod100 !== 11) return $one;
            if (in_array($mod10, [2, 3, 4]) && !in_array($mod100, [12, 13, 14])) return $few;
            return $many;
        }
    }
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    <h1 class="text-2xl font-semibold mb-6">Домашки</h1>

    <div class="flex flex-wrap gap-2 mb-6" id="hw-filters">
        <button type="button" data-filter="todo" class="hw-filter-btn px-3 py-1.5 rounded-full text-sm border">Нужно сделать</button>
        <button type="button" data-filter="review" class="hw-filter-btn px-3 py-1.5 rounded-full text-sm border">На проверке</button>
        <button type="button" data-filter="done" class="hw-filter-btn px-3 py-1.5 rounded-full text-sm border">Сделано</button>
    </div>

    @if($rows->isEmpty())
        <div class="p-6 rounded-2xl border bg-white text-gray-600 text-center">
            Домашек в очереди нет 🎉
        </div>
    @else
        <div class="flex flex-col gap-3" id="hw-list">
            @foreach($rows as $row)
                @php
                    $hw = $row['homework'];
                    $status = $row['status'];

                    $badgeMap = [
                        'not_started'    => ['label' => 'Не начато', 'class' => 'bg-gray-100 text-gray-700'],
                        'in_progress'    => ['label' => 'В процессе', 'class' => 'bg-blue-50 text-blue-700'],
                        'overdue'        => ['label' => 'Просрочено', 'class' => 'bg-rose-50 text-rose-700'],
                        'pending_review' => ['label' => 'На проверке', 'class' => 'bg-amber-50 text-amber-700'],
                        'checked'        => ['label' => 'Проверено', 'class' => 'bg-emerald-50 text-emerald-700'],
                    ];
                    $badge = $badgeMap[$status];

                    $filterGroup = match ($status) {
                        'not_started', 'in_progress', 'overdue' => 'todo',
                        'pending_review' => 'review',
                        'checked' => 'done',
                    };

                    $dueText = null;
                    if ($hw->due_at) {
                        $days = now()->startOfDay()->diffInDays($hw->due_at->copy()->startOfDay(), false);
                        if ($days < 0) {
                            $dueText = 'просрочено ' . abs($days) . ' ' . ru_plural(abs($days), 'день', 'дня', 'дней') . ' назад';
                        } elseif ($days === 0) {
                            $dueText = 'сегодня';
                        } elseif ($days === 1) {
                            $dueText = 'завтра';
                        } else {
                            $dueText = 'через ' . $days . ' ' . ru_plural($days, 'день', 'дня', 'дней');
                        }
                    }

                    $actionLabel = match ($status) {
                        'not_started' => 'Начать',
                        'in_progress' => 'Продолжить',
                        'overdue' => 'Сдать',
                        'pending_review' => 'Посмотреть',
                        'checked' => 'Результат',
                    };

                    $actionUrl = (in_array($status, ['pending_review', 'checked'], true) && $row['submission'] && Route::has('student.submissions.show'))
                        ? route('student.submissions.show', $row['submission'])
                        : (Route::has('student.submissions.create') ? route('student.submissions.create', $hw) : '#');
                @endphp

                <a href="{{ $actionUrl }}"
                   data-group="{{ $filterGroup }}"
                   class="hw-card flex items-center justify-between gap-4 rounded-2xl border bg-white p-4 hover:border-amber-300 hover:shadow-sm transition">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            <span class="text-xs text-gray-400">{{ $row['course_title'] }}</span>
                        </div>
                        <div class="font-medium text-gray-900 truncate">{{ $hw->title }}</div>
                        <div class="text-sm text-gray-500 mt-0.5">
                            @if($dueText)
                                Срок: {{ $dueText }}
                            @else
                                Без срока
                            @endif
                            @if($status === 'checked' && $row['score'] !== null)
                                &middot; {{ $row['score'] }}/{{ $row['max_score'] }} баллов
                            @endif
                        </div>
                    </div>
                    <span class="shrink-0 text-sm font-medium text-amber-700 whitespace-nowrap">{{ $actionLabel }} →</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

<style>
    .hw-filter-btn { border-color: #e5e7eb; color: #6b7280; background: #fff; }
    .hw-filter-btn.is-active { background: #18181b; color: #fff; border-color: #18181b; }
</style>

<script>
(function () {
    var buttons = document.querySelectorAll('.hw-filter-btn');
    var cards = document.querySelectorAll('#hw-list .hw-card');
    if (!buttons.length) return;

    function applyFilter(filter) {
        cards.forEach(function (card) {
            card.style.display = card.dataset.group === filter ? '' : 'none';
        });
        buttons.forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.filter === filter);
        });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () { applyFilter(btn.dataset.filter); });
    });

    applyFilter('todo');
})();
</script>
@endsection
