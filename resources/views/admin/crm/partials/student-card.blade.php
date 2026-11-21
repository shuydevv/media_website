@php
    $status = $student->crmStatus();
    $selectColorClasses = [
        'gray'    => 'bg-gray-100 text-gray-700 border-gray-300',
        'blue'    => 'bg-blue-50 text-blue-700 border-blue-300',
        'amber'   => 'bg-amber-50 text-amber-700 border-amber-300',
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-300',
        'rose'    => 'bg-rose-50 text-rose-700 border-rose-300',
    ];
@endphp
<div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5" data-student-row data-user-id="{{ $student->id }}">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3 min-w-0">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    <a href="{{ route('admin.user.show', $student) }}" class="sans-medium text-base text-zinc-900 hover:underline">
                        {{ trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: '—' }}
                    </a>
                    @if($student->name)
                        <a href="https://t.me/{{ ltrim($student->name, '@') }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-zinc-200 text-xs text-zinc-600 hover:border-zinc-300 hover:text-zinc-900 sans"
                           title="Написать в Telegram">
                            <x-icon name="send-01" class="w-3 h-3 shrink-0" />
                            {{ $student->name }}
                        </a>
                    @endif
                    @if($student->email)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-zinc-200 text-xs text-zinc-600 sans max-w-full break-all">
                            <x-icon name="mail-01" class="w-3 h-3 shrink-0" />
                            {{ $student->email }}
                        </span>
                    @endif
                </div>
                @if($student->phone)
                    <div class="sans text-sm text-zinc-500 mt-1">{{ $student->phone }}</div>
                @endif
                @php
                    $lastActive = $student->crmLastActiveDate;
                    $daysAway = $lastActive ? (int) $lastActive->diffInDays(now()->startOfDay()) : null;
                    $activityText = $lastActive === null ? 'на платформу не заходил'
                        : ($daysAway === 0 ? 'заходил сегодня' : ($daysAway === 1 ? 'заходил вчера' : 'заходил '.$daysAway.' дн. назад'));
                @endphp
                <div class="sans text-xs text-zinc-400 mt-0.5 flex flex-wrap items-center gap-x-2">
                    @if($student->crmIsInactive())
                        <x-ui.badge tone="orange" title="Есть доступ, но не заходил {{ \App\Models\User::CRM_INACTIVE_DAYS }}+ дней — риск, что не продлит">{{ \Illuminate\Support\Str::ucfirst($activityText) }}</x-ui.badge>
                    @else
                        <span>{{ \Illuminate\Support\Str::ucfirst($activityText) }}</span>
                    @endif
                    <span class="text-zinc-300">·</span>
                    <span>Регистрация: {{ optional($student->created_at)->translatedFormat('j F Y') }}</span>
                    @if($student->crm_stage === 'lost' && $student->crm_lost_reason)
                        <span class="text-zinc-300">·</span>
                        <span class="text-rose-600">Причина: {{ \App\Models\User::CRM_LOST_REASONS[$student->crm_lost_reason] ?? $student->crm_lost_reason }}</span>
                    @endif
                    <span class="text-zinc-300">·</span>
                    {{-- Свой/тестовый аккаунт: доступ остаётся, но в статусы,
                         счётчики и доход не идёт (Crm\ExcludedController). --}}
                    <form method="POST" action="{{ route('admin.crm.excluded.update', $student) }}" class="inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="excluded" value="{{ $student->crm_excluded ? 0 : 1 }}">
                        <button type="submit" class="py-2 -my-2 text-zinc-400 hover:text-zinc-800 underline decoration-dotted underline-offset-2"
                                title="{{ $student->crm_excluded ? 'Вернуть в статусы, счётчики и доход' : 'Свой или тестовый аккаунт: доступ сохранится, но в статусы, счётчики и доход не пойдёт' }}">
                            {{ $student->crm_excluded ? 'Снова учитывать' : 'Не учитывать' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-2 relative" data-reminder-widget>
            <button type="button" data-reminder-toggle class="relative p-2.5 -m-2.5 text-amber-500 hover:text-amber-600" title="Напоминания" aria-label="Напоминания">
                <x-icon name="bell-01" class="w-5 h-5" />
                <span class="absolute top-1.5 right-1.5 bg-white rounded-full leading-none">
                    <x-icon name="plus" class="w-3 h-3" />
                </span>
            </button>

            <div data-reminder-popover hidden
                 class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100vw-2rem)] max-w-sm max-h-[80vh] overflow-y-auto sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:translate-x-0 sm:translate-y-0 sm:w-72 sm:max-h-none sm:overflow-visible bg-white border border-zinc-200 rounded-xl shadow-lg p-3 z-30 text-left">
                <div class="sans text-xs text-zinc-400 uppercase tracking-wide mb-2">Напоминания</div>
                <div class="space-y-1.5 mb-2 empty:mb-0" data-reminder-popover-list>
                    @foreach($student->crmReminders as $reminder)
                        @include('admin.crm.partials.reminder-row', ['reminder' => $reminder])
                    @endforeach
                </div>
                <div class="flex flex-col gap-1.5 border-t border-zinc-100 pt-2">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach([1 => 'Завтра', 3 => 'Через 3 дня', 7 => 'Через неделю'] as $days => $label)
                            <button type="button" data-reminder-preset="{{ now()->addDays($days)->toDateString() }}"
                                    class="px-2.5 min-h-9 md:min-h-7 rounded-full border border-zinc-200 text-xs sans text-zinc-600 hover:border-zinc-400 hover:text-zinc-900">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <input type="date" data-reminder-new-date
                           class="border rounded-lg px-2 py-1.5 input-focus sans text-base md:text-sm">
                    <input type="text" data-reminder-new-note placeholder="Что сделать…" maxlength="500"
                           class="border rounded-lg px-2 py-1.5 input-focus sans text-base md:text-sm">
                    <button type="button" data-reminder-add
                            class="rounded-lg px-3 min-h-10 md:min-h-8 bg-zinc-800 text-white text-sm md:text-xs sans-medium hover:bg-zinc-900">
                        Добавить
                    </button>
                </div>
            </div>

            <select class="crm-stage-select rounded-lg px-2 min-h-10 md:min-h-9 sans text-sm border {{ $selectColorClasses[$status['color']] }}">
                @foreach(\App\Models\User::crmStatusOptionsFor($status['key']) as $key => $opt)
                    @php $optValue = $key === 'new' ? '' : $key; @endphp
                    <option value="{{ $optValue }}"
                            {{ !$opt['selectable'] ? 'disabled' : '' }}
                            {{ $status['key'] === $key ? 'selected' : '' }}>
                        {{ $opt['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-3 space-y-2">
        {{-- suspended тоже показываем: enrollments:expire переводит просроченную
             запись в suspended, но это всё ещё "его" курс — админ должен видеть
             его и просто переставить дату (AccessController вернёт status в
             active), а не записывать ученика на курс заново. --}}
        @forelse($student->courses->whereIn('pivot.status', ['active', 'suspended']) as $course)
            @php
                $s = \App\Support\CrmCourseState::for($student, $course);
                $isSuspended = $s['isSuspended'];
                $until = $s['until'];
                $dateColor = $s['dateColor'];
            @endphp
            <div class="flex flex-wrap items-center gap-2 border border-zinc-200 rounded-lg px-3 py-2 text-sm"
                 data-course-row data-course-id="{{ $course->id }}" data-course-title="{{ $course->title }}">
                <span class="sans-medium text-zinc-800 min-w-0 truncate">{{ $course->title }}</span>
                @if($isSuspended)
                    <x-ui.badge tone="red" data-course-suspended title="Доступ приостановлен — оплата просрочена. Поставьте новую дату, чтобы вернуть доступ.">Просрочен</x-ui.badge>
                @endif
                <span class="text-zinc-300">·</span>

                <span data-field-group class="inline-flex flex-wrap items-center gap-1.5">
                    <span data-view class="inline-flex items-center gap-1">
                        <span class="sans {{ $dateColor }}">до {{ \App\Support\CrmDate::format($until) ?? '—' }}</span>
                        <button type="button" data-field-edit-toggle class="p-2.5 -m-2 text-zinc-400 hover:text-zinc-700" title="Изменить дату" aria-label="Изменить дату">
                            <x-icon name="edit-02" class="w-3.5 h-3.5" />
                        </button>
                    </span>
                    <input type="date" data-edit hidden
                           class="crm-access-input border rounded-lg px-2 py-1.5 input-focus sans text-base md:text-sm"
                           value="{{ $until?->format('Y-m-d') }}">
                    <input type="number" data-edit hidden min="0" step="1" placeholder="Сумма, ₽ (необязательно)"
                           class="crm-payment-amount border rounded-lg px-2 py-1.5 input-focus sans text-base md:text-sm w-full sm:w-52">
                    <button type="button" data-edit hidden class="crm-access-save rounded-lg px-3 min-h-10 md:min-h-8 bg-zinc-800 text-white text-sm md:text-xs sans-medium hover:bg-zinc-900">
                        Сохранить
                    </button>
                    <button type="button" data-edit hidden class="crm-access-cancel px-2 min-h-10 md:min-h-8 text-sm md:text-xs text-zinc-500 hover:text-zinc-800 sans">
                        Отмена
                    </button>
                    <span class="crm-access-saved text-emerald-600 opacity-0 transition-opacity">✓</span>
                </span>

                <span class="text-zinc-300">·</span>
                @include('admin.crm.partials.course-billing', ['s' => $s])

                {{-- Сам снял ученика с курса (ушёл, перешёл на другой) —
                     RemoveCourseController, курс уходит из сводки и карточки. --}}
                <button type="button" data-course-remove
                        class="ml-auto px-2 min-h-10 md:min-h-8 text-sm md:text-xs text-zinc-400 hover:text-rose-600 sans"
                        title="Убрать курс: ученик больше на нём не учится">
                    Убрать курс
                </button>
            </div>
        @empty
            <div class="sans text-sm text-zinc-400">Курсов нет</div>
        @endforelse
    </div>

    <div class="mt-3 space-y-1.5" data-reminder-body-list {{ $student->crmReminders->isEmpty() ? 'hidden' : '' }}>
        @foreach($student->crmReminders as $reminder)
            @include('admin.crm.partials.reminder-body-row', ['reminder' => $reminder])
        @endforeach
    </div>

    {{-- История: платежи + события CRM (App\Support\CrmTimeline), новые
         сверху; видны последние 3, остальное — по «Вся история». --}}
    @php $timeline = \App\Support\CrmTimeline::for($student); @endphp
    @if($timeline)
        <details class="mt-3 group" data-crm-history>
            <summary class="list-none cursor-pointer select-none">
                <div class="sans text-xs text-zinc-400 uppercase tracking-wide mb-1">История</div>
                <ul class="space-y-0.5">
                    @foreach(array_slice($timeline, 0, 3) as $item)
                        @include('admin.crm.partials.timeline-item', ['item' => $item])
                    @endforeach
                </ul>
                @if(count($timeline) > 3)
                    <span class="sans text-xs text-zinc-500 underline decoration-dotted underline-offset-2 group-open:hidden inline-block mt-1 min-h-8">Вся история ({{ count($timeline) }})</span>
                @endif
            </summary>
            <ul class="space-y-0.5">
                @foreach(array_slice($timeline, 3) as $item)
                    @include('admin.crm.partials.timeline-item', ['item' => $item])
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Заметка — сразу обычное поле: пишешь и уходишь, сохраняется само
         по blur (без карандаша). --}}
    <div class="mt-3" data-note-group>
        <textarea rows="2"
                  class="crm-note-input w-full border rounded-lg px-3 py-2 text-base md:text-sm input-focus sans text-zinc-700"
                  placeholder="Заметка… (сохранится сама)">{{ $student->crm_note }}</textarea>
        <span class="crm-note-saved text-emerald-600 text-xs sans opacity-0 transition-opacity">Сохранено ✓</span>
    </div>
</div>
