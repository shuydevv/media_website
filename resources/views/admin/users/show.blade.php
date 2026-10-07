@extends('admin.layouts.main')

@section('content')
@php
    $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?: 'Пользователь #'.$user->id);
    $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-black/5 p-5';
    $label = 'text-xs text-zinc-500 uppercase tracking-wide';
@endphp

<div class="flex items-start justify-between gap-3 flex-wrap mb-6">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold text-zinc-900">{{ $fullName }}</h1>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
            @if($user->name)
                <a href="https://t.me/{{ ltrim($user->name, '@') }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline">{{ $user->name }}</a>
            @endif
            @if($user->email)
                <span>{{ $user->email }}</span>
            @endif
            <span>на платформе с {{ $user->created_at?->format('d.m.Y') ?: '—' }}</span>
            @unless($user->isStudent())
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700">{{ \App\Models\User::getRoles()[$user->role] ?? 'Роль '.$user->role }}</span>
            @endunless
        </div>
    </div>

    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('admin.user.edit', $user->id) }}"
           class="px-3 py-2 text-sm bg-zinc-900 text-white rounded-lg hover:bg-zinc-800">Изменить</a>

        @if($user->isStudent())
            <form method="POST" action="{{ route('admin.user.impersonate', $user->id) }}">
                @csrf
                <button class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Войти как ученик
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.user.delete', $user->id) }}"
              onsubmit="return confirm('Точно пометить пользователя как удалённого?');">
            @csrf
            @method('DELETE')
            <button class="px-3 py-2 text-sm border border-rose-200 text-rose-700 rounded-lg hover:bg-rose-50">
                Удалить
            </button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg bg-rose-50 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        @foreach($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
@endif

@unless($user->isStudent())
    <div class="{{ $card }} text-sm text-zinc-600">
        Отчёт о прогрессе показывается только для учеников.
    </div>
@else
    @php
        $activity = $report['activity'];
        $toneClasses = [
            'good' => 'bg-emerald-500',
            'warn' => 'bg-amber-500',
            'bad' => 'bg-rose-500',
            'info' => 'bg-zinc-400',
        ];
        $toneLabels = ['good' => 'хорошо', 'warn' => 'внимание', 'bad' => 'проблема', 'info' => 'к сведению'];
    @endphp

    {{-- ── Коротко: о чём сказать в голосовом ─────────────────────────── --}}
    <div class="{{ $card }} mb-6">
        <h2 class="text-lg font-medium mb-3">Коротко</h2>
        <ul class="space-y-2 text-sm text-zinc-800">
            @foreach($report['headline'] as $line)
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 {{ $toneClasses[$line['tone']] }}" title="{{ $toneLabels[$line['tone']] }}"></span>
                    <span><span class="sr-only">{{ $toneLabels[$line['tone']] }}: </span>{{ $line['text'] }}</span>
                </li>
            @endforeach
        </ul>

        <div class="mt-4 pt-4 border-t border-zinc-100 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="{{ $label }}">Последняя активность</div>
                <div class="text-zinc-900 mt-0.5">
                    @if($activity['lastSeen'])
                        {{ $activity['lastSeenExact'] ? $activity['lastSeen']->format('d.m.Y H:i') : $activity['lastSeen']->format('d.m.Y') }}
                    @else
                        —
                    @endif
                </div>
            </div>
            <div>
                <div class="{{ $label }}">Активных дней за 14</div>
                <div class="text-zinc-900 mt-0.5">{{ $activity['activeDays14'] }}</div>
            </div>
            <div>
                <div class="{{ $label }}">Практика за 30 дней</div>
                <div class="text-zinc-900 mt-0.5">
                    {{ $report['practice']['recent'] }} реш.
                    @if($report['practice']['recentOkPercent'] !== null)
                        <span class="text-zinc-500">· верно {{ $report['practice']['recentOkPercent'] }}%</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="{{ $label }}">Последняя обратная связь</div>
                <div class="text-zinc-900 mt-0.5">
                    @if($report['daysSinceFeedback'] === null)
                        не было
                    @elseif($report['daysSinceFeedback'] === 0)
                        сегодня
                    @else
                        {{ $report['daysSinceFeedback'] }} дн. назад
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Журнал обратной связи ──────────────────────────────────────── --}}
    <div class="{{ $card }} mb-6">
        <h2 class="text-lg font-medium mb-3">Обратная связь ученику</h2>

        <form method="POST" action="{{ route('admin.user.feedback.store', $user) }}" class="flex flex-wrap items-start gap-2 mb-4">
            @csrf
            <select name="kind" class="border rounded-lg px-2 py-2 text-sm">
                @foreach(\App\Models\StudentFeedback::KINDS as $kind => $kindLabel)
                    <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ $kindLabel }}</option>
                @endforeach
            </select>
            <textarea name="note" rows="2" maxlength="5000" placeholder="О чём сказали — пригодится в следующий раз"
                      class="flex-1 min-w-[220px] border rounded-lg px-3 py-2 text-sm">{{ old('note') }}</textarea>
            <button class="px-3 py-2 bg-zinc-900 text-white rounded-lg text-sm hover:bg-zinc-800">Записать</button>
        </form>

        @forelse($report['feedback'] as $item)
            <div class="flex items-start gap-3 py-2 border-t border-zinc-100 text-sm">
                <div class="w-24 shrink-0 text-zinc-500">{{ $item->created_at->format('d.m.Y') }}</div>
                <div class="flex-1 min-w-0">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-zinc-100 text-zinc-700">{{ $item->kindLabel() }}</span>
                    @if($item->author)
                        <span class="text-xs text-zinc-500 ml-1">{{ trim(($item->author->first_name ?? '').' '.($item->author->last_name ?? '')) ?: $item->author->name }}</span>
                    @endif
                    @if($item->note)
                        <div class="text-zinc-800 mt-1 whitespace-pre-line">{{ $item->note }}</div>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.user.feedback.destroy', [$user, $item]) }}"
                      onsubmit="return confirm('Удалить запись из журнала?');">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs text-zinc-400 hover:text-rose-600" title="Удалить запись">удалить</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-zinc-500">Записей пока нет.</p>
        @endforelse

        @if($user->crm_note)
            <div class="mt-4 pt-4 border-t border-zinc-100">
                <div class="{{ $label }}">Заметка из CRM</div>
                <div class="text-sm text-zinc-700 mt-1 whitespace-pre-line">{{ $user->crm_note }}</div>
            </div>
        @endif
    </div>

    {{-- ── Активность по дням ─────────────────────────────────────────── --}}
    @php
        // Один оттенок от светлого к тёмному — чем темнее, тем больше времени.
        $levelClasses = [0 => 'bg-zinc-100', 1 => 'bg-emerald-200', 2 => 'bg-emerald-400', 3 => 'bg-emerald-600'];
        $leading = $activity['days'][0]['date']->dayOfWeekIso - 1;
    @endphp
    <div class="{{ $card }} mb-6">
        <div class="flex items-baseline justify-between gap-3 flex-wrap mb-3">
            <h2 class="text-lg font-medium">Активность за {{ \App\Service\StudentReport::ACTIVITY_DAYS }} дней</h2>
            <div class="text-sm text-zinc-500">
                за 7 дней: {{ $activity['activeDays7'] }} дн.@if($activity['minutes7'] > 0), около {{ $activity['minutes7'] }} мин@endif
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1 max-w-sm">
            @foreach(['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'] as $weekday)
                <div class="text-[11px] text-zinc-400 text-center">{{ $weekday }}</div>
            @endforeach
            @for($i = 0; $i < $leading; $i++)
                <div></div>
            @endfor
            @foreach($activity['days'] as $day)
                @php
                    $tip = $day['date']->format('d.m').': '.match (true) {
                        $day['minutes'] !== null => 'около '.$day['minutes'].' мин на платформе',
                        $day['level'] > 0 => 'решал задания (время не считалось)',
                        default => 'не заходил',
                    };
                @endphp
                <div class="h-8 rounded-md flex items-center justify-center text-[11px] {{ $levelClasses[$day['level']] }} {{ $day['level'] >= 2 ? 'text-white' : 'text-zinc-600' }} {{ $day['date']->isToday() ? 'ring-2 ring-zinc-900' : '' }}"
                     title="{{ $tip }}">{{ $day['date']->day }}</div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500">
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded {{ $levelClasses[0] }}"></span>не заходил</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded {{ $levelClasses[1] }}"></span>до 20 мин</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded {{ $levelClasses[2] }}"></span>20–60 мин</span>
            <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded {{ $levelClasses[3] }}"></span>больше часа</span>
        </div>
        @unless($activity['hasTracked'])
            <p class="mt-2 text-xs text-zinc-500">Точное время на платформе начало считаться недавно — пока календарь построен по дням, когда ученик решал домашки и задания из банка.</p>
        @endunless
    </div>

    {{-- ── По курсам ──────────────────────────────────────────────────── --}}
    @forelse($report['courses'] as $c)
        @php
            $s = $c['summary'];
            $pivot = $c['pivot'];
            $statusLabels = ['active' => 'активен', 'completed' => 'завершён', 'suspended' => 'заморожен', 'dropped' => 'отчислен'];
            $stateBadges = [
                'checked' => ['Проверено', 'bg-emerald-50 text-emerald-700'],
                'pending' => ['На проверке', 'bg-blue-50 text-blue-700'],
                'in_progress' => ['Начал, не сдал', 'bg-amber-50 text-amber-700'],
                'overdue' => ['Не сдано, срок вышел', 'bg-rose-50 text-rose-700'],
                'not_started' => ['Не начато', 'bg-zinc-100 text-zinc-600'],
            ];
            $minutes = fn (?int $seconds) => $seconds === null ? null : max(1, (int) round($seconds / 60));
        @endphp
        <div class="{{ $card }} mb-6">
            <div class="flex items-baseline justify-between gap-3 flex-wrap mb-4">
                <h2 class="text-lg font-medium">{{ $c['course']->title }}</h2>
                <div class="text-sm text-zinc-500">
                    {{ $statusLabels[$pivot->status] ?? $pivot->status }}
                    @if($c['enrolledAt']) · на курсе с {{ $c['enrolledAt']->format('d.m.Y') }} @endif
                </div>
            </div>

            {{-- Цель --}}
            <form method="POST" action="{{ route('admin.user.goal.update', [$user, $c['course']]) }}" class="flex flex-wrap items-end gap-3 mb-5 text-sm">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block {{ $label }} mb-1">Цель, баллов ЕГЭ</label>
                    <input type="number" name="target_score" min="0" max="100" value="{{ $pivot->target_score }}" placeholder="—" class="border rounded-lg px-2 py-1.5 w-24">
                </div>
                <div>
                    <label class="block {{ $label }} mb-1">Входной результат</label>
                    <input type="number" name="entry_score" min="0" max="100" value="{{ $pivot->entry_score }}" placeholder="—" class="border rounded-lg px-2 py-1.5 w-24">
                </div>
                <button class="px-3 py-1.5 border rounded-lg text-sm hover:bg-zinc-50">Сохранить</button>
            </form>

            {{-- Сводка по домашкам --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div>
                    <div class="{{ $label }}">Сдано домашек</div>
                    <div class="text-2xl text-zinc-900 mt-0.5">{{ $s['done'] }}<span class="text-base text-zinc-400"> / {{ $s['total'] }}</span></div>
                    @if($s['inProgress'] > 0)
                        <div class="text-xs text-zinc-500">начато и брошено: {{ $s['inProgress'] }}</div>
                    @endif
                </div>
                <div>
                    <div class="{{ $label }}">Средний результат</div>
                    <div class="text-2xl text-zinc-900 mt-0.5">{{ $s['avgPercent'] !== null ? $s['avgPercent'].'%' : '—' }}</div>
                    @if($s['recentPercent'] !== null && $s['previousPercent'] !== null)
                        <div class="text-xs text-zinc-500">последние 3: {{ $s['recentPercent'] }}%, до них: {{ $s['previousPercent'] }}%</div>
                    @endif
                </div>
                <div>
                    <div class="{{ $label }}">С опозданием</div>
                    <div class="text-2xl text-zinc-900 mt-0.5">{{ $s['late'] }}</div>
                    @if($s['lastMinute'] > 0)
                        <div class="text-xs text-zinc-500">в последние 12 ч до срока: {{ $s['lastMinute'] }}</div>
                    @endif
                </div>
                <div>
                    <div class="{{ $label }}">Верно с первой проверки</div>
                    <div class="text-2xl text-zinc-900 mt-0.5">{{ $s['firstTryPercent'] !== null ? $s['firstTryPercent'].'%' : '—' }}</div>
                    @if($s['firstTryPercent'] === null)
                        <div class="text-xs text-zinc-500">данные копятся с новых работ</div>
                    @endif
                </div>
            </div>

            {{-- Слабые места по номерам --}}
            @if($c['numbers']->isNotEmpty())
                <h3 class="text-sm font-medium text-zinc-900 mb-2">По номерам заданий ЕГЭ</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 mb-5">
                    @foreach($c['numbers'] as $n)
                        @php $weak = $n['percent'] !== null && $n['percent'] < \App\Service\StudentReport::WEAK_PERCENT && $n['count'] >= 2; @endphp
                        <div class="flex items-center gap-2 text-sm"
                             title="№{{ $n['number'] }}: {{ $n['score'] }} из {{ $n['max'] }} баллов за {{ $n['count'] }} заданий{{ $n['wrong'] > 0 ? ', неверных проверок: '.$n['wrong'] : '' }}">
                            <span class="w-9 shrink-0 text-zinc-700">№{{ $n['number'] }}</span>
                            <span class="flex-1 h-2 rounded-full bg-zinc-100 overflow-hidden">
                                <span class="block h-full rounded-full bg-blue-500" style="width: {{ $n['percent'] ?? 0 }}%"></span>
                            </span>
                            <span class="w-10 shrink-0 text-right text-zinc-900">{{ $n['percent'] ?? '—' }}%</span>
                            <span class="w-20 shrink-0 text-xs {{ $weak ? 'text-rose-700' : 'text-zinc-400' }}">
                                {{ $weak ? 'слабое место' : $n['count'].' зад.' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- По темам --}}
            @if($c['topics']->isNotEmpty())
                <h3 class="text-sm font-medium text-zinc-900 mb-2">По темам</h3>
                <div class="space-y-1.5 mb-5">
                    @foreach($c['topics'] as $t)
                        <div class="flex items-center gap-2 text-sm" title="{{ $t['score'] }} из {{ $t['max'] }} баллов за {{ $t['count'] }} заданий">
                            <span class="w-48 shrink-0 truncate text-zinc-700">{{ $t['title'] }}</span>
                            <span class="flex-1 h-2 rounded-full bg-zinc-100 overflow-hidden">
                                <span class="block h-full rounded-full bg-blue-500" style="width: {{ $t['percent'] ?? 0 }}%"></span>
                            </span>
                            <span class="w-10 shrink-0 text-right text-zinc-900">{{ $t['percent'] ?? '—' }}%</span>
                            <span class="w-14 shrink-0 text-xs text-zinc-400">{{ $t['count'] }} зад.</span>
                        </div>
                    @endforeach
                </div>
            @elseif($c['numbers']->isNotEmpty())
                <p class="text-xs text-zinc-500 mb-5">Разбивки по темам пока нет: у заданий в банке не указана тема (поле «Тема» в редакторе задания).</p>
            @endif

            {{-- Домашки и пробники --}}
            <h3 class="text-sm font-medium text-zinc-900 mb-2">Домашки и пробники</h3>
            @if($c['rows']->isEmpty())
                <p class="text-sm text-zinc-500 mb-5">Доступных ученику работ пока нет.</p>
            @else
                <div class="overflow-x-auto mb-5">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-zinc-500">
                                <th class="py-1.5 pr-3 font-normal">Работа</th>
                                <th class="py-1.5 pr-3 font-normal">Статус</th>
                                <th class="py-1.5 pr-3 font-normal text-right">Результат</th>
                                <th class="py-1.5 font-normal">Как решал</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($c['rows'] as $r)
                                @php [$badgeText, $badgeClass] = $stateBadges[$r['state']]; @endphp
                                <tr class="border-t border-zinc-100 align-top">
                                    <td class="py-2 pr-3">
                                        @if($r['final'])
                                            <a href="{{ route('mentor.review.show', $r['final']) }}" class="text-zinc-900 hover:underline">{{ $r['homework']->title }}</a>
                                        @else
                                            <span class="text-zinc-900">{{ $r['homework']->title }}</span>
                                        @endif
                                        <div class="text-xs text-zinc-500">
                                            {{ $r['isMock'] ? 'пробник' : 'домашка' }}
                                            @if($r['homework']->due_at) · срок {{ $r['homework']->due_at->format('d.m') }} @endif
                                        </div>
                                    </td>
                                    <td class="py-2 pr-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs whitespace-nowrap {{ $badgeClass }}">{{ $badgeText }}</span>
                                        @if($r['submittedAt'])
                                            <div class="text-xs text-zinc-500 mt-1">
                                                {{ $r['submittedAtExact'] ? 'сдал' : 'начал' }} {{ $r['submittedAt']->format('d.m H:i') }}
                                                @if($r['late']) · <span class="text-rose-700">после срока</span> @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3 text-right whitespace-nowrap">
                                        @if($r['final'])
                                            <div class="text-zinc-900">{{ $r['percent'] !== null ? $r['percent'].'%' : '—' }}</div>
                                            <div class="text-xs text-zinc-500">{{ $r['score'] }} из {{ $r['max'] }}@if($r['state'] === 'pending'), не итог@endif</div>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-xs text-zinc-600">
                                        @if($r['final'])
                                            <div>попытка {{ $r['attempts'] }} из {{ $r['attemptsAllowed'] }}@if($r['firstPercent'] !== null), первая — {{ $r['firstPercent'] }}%@endif</div>
                                            @if($r['seconds'])
                                                <div>время: {{ $minutes($r['seconds']) }} мин</div>
                                            @endif
                                            @if($r['firstTryTotal'])
                                                <div>с первой проверки: {{ $r['firstTryOk'] }} из {{ $r['firstTryTotal'] }}</div>
                                            @endif
                                            @if($r['hints'])
                                                <div>подсказок открыто: {{ $r['hints'] }}</div>
                                            @endif
                                            @if($r['blank'] > 0)
                                                <div>без ответа: {{ $r['blank'] }}</div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Уроки --}}
            @php
                $lessonBadges = [
                    'watched' => ['Посмотрел', 'bg-emerald-50 text-emerald-700'],
                    'partial' => ['Частично', 'bg-amber-50 text-amber-700'],
                    'missed' => ['Не открывал', 'bg-rose-50 text-rose-700'],
                    'no_data' => ['Нет данных', 'bg-zinc-100 text-zinc-500'],
                    'no_video' => ['Без видео', 'bg-zinc-100 text-zinc-500'],
                ];
                $lessons = $c['lessons'];
            @endphp
            <div class="flex items-baseline justify-between gap-3 flex-wrap mb-2">
                <h3 class="text-sm font-medium text-zinc-900">Уроки</h3>
                @if($lessons['tracked'] > 0)
                    <div class="text-xs text-zinc-500">посмотрел {{ $lessons['watched'] }} из {{ $lessons['tracked'] }} (от {{ \App\Service\StudentReport::WATCHED_PERCENT }}% урока)</div>
                @endif
            </div>
            @if($lessons['rows']->isEmpty())
                <p class="text-sm text-zinc-500 mb-5">Прошедших уроков пока нет.</p>
            @else
                <div class="overflow-x-auto mb-5">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($lessons['rows'] as $index => $l)
                                @php [$badgeText, $badgeClass] = $lessonBadges[$l['state']]; @endphp
                                <tr class="border-t border-zinc-100 align-top" @if($index >= 10) data-lesson-extra hidden @endif>
                                    <td class="py-2 pr-3 w-14 text-zinc-500 whitespace-nowrap">{{ $l['date']->format('d.m') }}</td>
                                    <td class="py-2 pr-3 text-zinc-900">{{ $l['lesson']->title ?: 'Урок без названия' }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs whitespace-nowrap {{ $badgeClass }}">{{ $badgeText }}</span>
                                    </td>
                                    <td class="py-2 text-xs text-zinc-600">
                                        @if($l['liveMinutes'] !== null)
                                            <div>эфир: {{ $l['liveMinutes'] }} мин</div>
                                        @endif
                                        @if($l['recordingMinutes'] !== null)
                                            <div>запись: {{ $l['recordingPercent'] !== null ? $l['recordingPercent'].'%' : $l['recordingMinutes'].' мин' }}</div>
                                        @endif
                                        @if($l['shortMinutes'] !== null)
                                            <div>«сок»: {{ $l['shortPercent'] !== null ? $l['shortPercent'].'%' : $l['shortMinutes'].' мин' }}</div>
                                        @endif
                                        @if($l['hasNotes'] && $l['state'] !== 'no_data')
                                            <div>конспект: {{ $l['notesOpened'] ? 'открывал' : 'не открывал' }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($lessons['rows']->count() > 10)
                        <button type="button" class="mt-2 text-sm text-blue-700 hover:underline" data-lesson-more>
                            Показать все уроки ({{ $lessons['rows']->count() }})
                        </button>
                    @endif
                </div>
            @endif

            {{-- Комментарии куратора --}}
            @if($c['comments']->isNotEmpty())
                <h3 class="text-sm font-medium text-zinc-900 mb-2">Последние комментарии куратора</h3>
                <div class="space-y-3">
                    @foreach($c['comments'] as $comment)
                        <div class="text-sm border-l-2 border-zinc-200 pl-3">
                            <div class="text-xs text-zinc-500">
                                {{ $comment['homework']->title }}@if($comment['number'] !== '') · №{{ $comment['number'] }}@endif
                                · {{ $comment['score'] }} из {{ $comment['max'] }}
                                @if($comment['at']) · {{ $comment['at']->format('d.m.Y') }} @endif
                                @if($comment['reviewer'])
                                    · {{ trim(($comment['reviewer']->first_name ?? '').' '.($comment['reviewer']->last_name ?? '')) ?: $comment['reviewer']->name }}
                                @endif
                            </div>
                            <div class="text-zinc-800 mt-0.5 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($comment['comment'], 400) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="{{ $card }} mb-6 text-sm text-zinc-500">Ученик не записан ни на один курс.</div>
    @endforelse

    {{-- ── Вне курсов ─────────────────────────────────────────────────── --}}
    @php
        $fish = app(\App\Service\FishFoodService::class);
        $fishLevel = $fish->levelFor((int) $user->fish_total_fed);
        $practice = $report['practice'];
    @endphp
    <div class="{{ $card }}">
        <h2 class="text-lg font-medium mb-3">Самостоятельная работа</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="{{ $label }}">Задач из банка всего</div>
                <div class="text-zinc-900 mt-0.5">{{ $practice['total'] }}</div>
            </div>
            <div>
                <div class="{{ $label }}">За 30 дней</div>
                <div class="text-zinc-900 mt-0.5">{{ $practice['recent'] }} реш. по {{ $practice['recentTasks'] }} зад.</div>
            </div>
            <div>
                <div class="{{ $label }}">Последнее решение</div>
                <div class="text-zinc-900 mt-0.5">{{ $practice['lastAt'] ? \Illuminate\Support\Carbon::parse($practice['lastAt'])->format('d.m.Y') : '—' }}</div>
            </div>
            <div>
                <div class="{{ $label }}">Рыба</div>
                <div class="text-zinc-900 mt-0.5">{{ $user->fish_name ?: $fish->levelName($fishLevel) }}, уровень {{ $fishLevel }}</div>
                <div class="text-xs text-zinc-500">скормлено {{ (int) $user->fish_total_fed }}, в запасе {{ (int) $user->fish_corm_balance }}</div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        document.querySelectorAll('[data-lesson-more]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('div').querySelectorAll('[data-lesson-extra]').forEach(function (row) { row.hidden = false; });
                button.remove();
            });
        });
    })();
    </script>
@endunless

<div class="mt-8 flex gap-4 text-sm">
    <a href="{{ route('admin.crm.index') }}" class="text-zinc-600 hover:text-zinc-900 underline">← CRM (оплаты и доступ)</a>
    <a href="{{ route('admin.user.index') }}" class="text-zinc-600 hover:text-zinc-900 underline">Все пользователи</a>
</div>
@endsection
