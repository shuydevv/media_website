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

    $tabs = [
        'todo'   => 'Нужно сделать',
        'review' => 'На проверке',
        'done'   => 'Сделано',
    ];
    $emptyText = [
        'todo'   => 'Всё сделано — новых домашек пока нет 🎉',
        'review' => 'Сейчас ничего не ждёт проверки куратора.',
        'done'   => 'Сданных домашек пока нет.',
    ];
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    <h1 class="text-xl md:text-2xl font-medium text-zinc-900 mb-4">Домашки</h1>

    <x-ui.tabs class="mb-6">
        @foreach($tabs as $key => $label)
            <x-ui.tab data-filter="{{ $key }}" :active="$activeTab === $key">
                {{ $label }}
                <span class="tabular-nums text-zinc-400">{{ $tabCounts[$key] }}</span>
            </x-ui.tab>
        @endforeach
    </x-ui.tabs>

    {{-- Пустая вкладка — своё сообщение, а не пустой экран. --}}
    @foreach($tabs as $key => $label)
        @if($tabCounts[$key] === 0)
            <div data-empty="{{ $key }}" class="{{ $activeTab === $key ? '' : 'hidden' }}">
                <x-ui.card class="text-zinc-500 text-center">{{ $emptyText[$key] }}</x-ui.card>
            </div>
        @endif
    @endforeach

    <div class="flex flex-col gap-3" id="hw-list">
        @foreach($rows as $row)
            @php
                $hw = $row['homework'];
                $status = $row['status'];

                // Метка статуса — только когда она что-то добавляет: на вкладках
                // «На проверке» и «Сделано» статус и так понятен.
                $badge = match ($status) {
                    'in_progress' => ['tone' => 'blue', 'label' => 'В процессе'],
                    'overdue'     => ['tone' => 'red',  'label' => 'Просрочено'],
                    default       => null,
                };

                // Срок: подпись только если он есть; близкий — оранжевым, просроченный — красным.
                $dueText = null;
                $dueClass = 'text-zinc-500';
                if ($hw->due_at) {
                    $days = now()->startOfDay()->diffInDays($hw->due_at->copy()->startOfDay(), false);
                    if ($status === 'overdue') {
                        // Именно $status, а не голое "$days < 0" — дедлайн мог
                        // пройти ещё ДО зачисления ученика на курс, тогда
                        // Homework::isOverdueFor() уже не считает её просроченной
                        // (см. HomeworkController::index()), и подпись не должна
                        // противоречить метке над ней.
                        $dueText = 'Срок прошёл ' . abs($days) . ' ' . ru_plural(abs($days), 'день', 'дня', 'дней') . ' назад';
                        $dueClass = 'text-apple-red-650';
                    } elseif (in_array($status, ['not_started', 'in_progress'], true) && $days >= 0) {
                        $dueText = 'Срок: ' . match (true) {
                            $days === 0 => 'сегодня',
                            $days === 1 => 'завтра',
                            default => 'через ' . $days . ' ' . ru_plural($days, 'день', 'дня', 'дней'),
                        };
                        if ($days <= 1) $dueClass = 'text-apple-orange-700';
                    }
                }

                $progressText = $status === 'in_progress' && $row['tasks_count'] > 0
                    ? 'Решено ' . $row['answered'] . ' из ' . $row['tasks_count']
                    : null;

                // «ДЗ — …» на странице «Домашки» лишнее.
                $title = preg_replace('/^\s*(ДЗ|Домашнее задание)\s*[—–-]\s*/u', '', (string) $hw->title) ?: $hw->title;

                // Балл сданной — маленьким кольцом, цвет как на странице результата.
                $showScore = $status === 'checked' && $row['score'] !== null && $row['max_score'] > 0;
                if ($showScore) {
                    $pct = (int) min(100, max(0, round($row['score'] * 100 / $row['max_score'])));
                    [$ringColor, $ringTrack] = $pct >= 80 ? ['text-apple-green-500', 'stroke-apple-green-100']
                        : ($pct >= 50 ? ['text-apple-orange-500', 'stroke-apple-orange-100']
                        : ['text-apple-red-450', 'stroke-apple-red-100']);
                    $ringLen = 2 * M_PI * 15;
                }

                $actionUrl = (in_array($status, ['pending_review', 'checked'], true) && $row['submission'] && Route::has('student.submissions.show'))
                    ? route('student.submissions.show', $row['submission'])
                    : (Route::has('student.submissions.create') ? route('student.submissions.create', $hw) : '#');
            @endphp

            <x-ui.card-link href="{{ $actionUrl }}"
               data-group="{{ $row['group'] }}"
               class="hw-card items-center {{ $row['group'] === $activeTab ? '' : 'hidden' }}">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        @if($badge)
                            <x-ui.badge :tone="$badge['tone']">{{ $badge['label'] }}</x-ui.badge>
                        @endif
                        @if($row['retry_in_progress'])
                            <x-ui.badge tone="blue">Начата попытка № {{ $row['retry_in_progress']->attempt_no }}</x-ui.badge>
                        @endif
                        <span class="text-xs text-zinc-400">{{ $row['course_title'] }}</span>
                    </div>
                    <div class="font-medium text-zinc-900 break-words">{{ $title }}</div>
                    @if($dueText || $progressText)
                        <div class="text-sm mt-1 flex flex-wrap gap-x-2">
                            @if($progressText)<span class="text-zinc-500">{{ $progressText }}</span>@endif
                            @if($dueText && $progressText)<span class="text-zinc-300">·</span>@endif
                            @if($dueText)<span class="{{ $dueClass }}">{{ $dueText }}</span>@endif
                        </div>
                    @endif
                </div>

                @if($showScore)
                    <div class="shrink-0 flex items-center gap-2" title="{{ $row['score'] }} из {{ $row['max_score'] }} {{ ru_plural($row['max_score'], 'балла', 'баллов', 'баллов') }}">
                        <span class="text-sm font-medium text-zinc-900 tabular-nums">{{ $row['score'] }}<span class="text-zinc-400">/{{ $row['max_score'] }}</span></span>
                        <svg viewBox="0 0 36 36" class="w-8 h-8 -rotate-90" aria-hidden="true">
                            <circle cx="18" cy="18" r="15" fill="none" stroke-width="5" class="{{ $ringTrack }}" />
                            <circle cx="18" cy="18" r="15" fill="none" stroke-width="5" stroke-linecap="round"
                                    stroke="currentColor" class="{{ $ringColor }}"
                                    stroke-dasharray="{{ round($ringLen * $pct / 100, 2) }} {{ round($ringLen, 2) }}" />
                        </svg>
                    </div>
                @endif
            </x-ui.card-link>
        @endforeach
    </div>
</div>

<script>
(function () {
    // Вкладки: классы активной/неактивной — те же, что в x-ui.tab. Выбранная
    // пишется в адрес (?tab=), чтобы «назад» со страницы домашки её не сбрасывал.
    var ACTIVE = ['bg-white', 'text-zinc-900', 'shadow-sm', 'font-medium'];
    var IDLE = ['text-zinc-500', 'hover:text-zinc-900'];
    var buttons = document.querySelectorAll('[data-filter]');

    function applyFilter(filter) {
        document.querySelectorAll('#hw-list .hw-card').forEach(function (card) {
            card.classList.toggle('hidden', card.dataset.group !== filter);
        });
        document.querySelectorAll('[data-empty]').forEach(function (el) {
            el.classList.toggle('hidden', el.dataset.empty !== filter);
        });
        buttons.forEach(function (btn) {
            var on = btn.dataset.filter === filter;
            btn.classList.remove.apply(btn.classList, on ? IDLE : ACTIVE);
            btn.classList.add.apply(btn.classList, on ? ACTIVE : IDLE);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        var url = new URL(window.location.href);
        url.searchParams.set('tab', filter);
        history.replaceState(null, '', url);
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () { applyFilter(btn.dataset.filter); });
    });
})();
</script>
@endsection
