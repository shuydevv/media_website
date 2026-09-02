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
<div class="bg-white border rounded-2xl shadow-sm p-4 md:p-5" data-student-row data-user-id="{{ $student->id }}">

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="flex items-start gap-3 min-w-0">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    <span class="sans text-sm text-zinc-500 shrink-0 border border-zinc-200 rounded-md px-1.5 py-0.5">{{ $number }}</span>
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
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-zinc-200 text-xs text-zinc-600 sans">
                            <x-icon name="mail-01" class="w-3 h-3 shrink-0" />
                            {{ $student->email }}
                        </span>
                    @endif
                </div>
                @if($student->phone)
                    <div class="sans text-sm text-zinc-500 mt-1">{{ $student->phone }}</div>
                @endif
                <div class="sans text-xs text-zinc-400 mt-0.5">Регистрация: {{ optional($student->created_at)->translatedFormat('j F Y') }}</div>
            </div>
        </div>

        <div class="shrink-0">
            <select class="crm-stage-select rounded-lg px-2 py-1.5 sans text-sm border {{ $selectColorClasses[$status['color']] }}">
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
        @forelse($student->courses as $course)
            @php
                $pivot = $course->pivot;
                $isBilling = $pivot->billing_interval_days !== null;
                $rawUntil = $isBilling ? $pivot->next_payment_due_at : $pivot->expires_at;
                $until = $rawUntil ? \Illuminate\Support\Carbon::parse($rawUntil) : null;
                $isOverdue = $until && $until->isPast();
                $isSoon = $until && !$isOverdue && now()->diffInDays($until) <= 3;
                $dateColor = $isOverdue ? 'text-rose-600' : ($isSoon ? 'text-amber-600' : 'text-zinc-700');
                $lastPayment = $student->payments->firstWhere('course_id', $course->id);
            @endphp
            <div class="flex flex-wrap items-center gap-2 border border-zinc-200 rounded-lg px-3 py-2 text-sm"
                 data-course-row data-course-id="{{ $course->id }}">
                <span class="sans-medium text-zinc-800 min-w-0 truncate">{{ $course->title }}</span>
                <span class="text-zinc-300">·</span>

                <span data-field-group class="inline-flex items-center gap-1.5">
                    <span data-view class="inline-flex items-center gap-1">
                        <span class="sans {{ $dateColor }}">до {{ \App\Support\CrmDate::format($until) ?? '—' }}</span>
                        <button type="button" data-field-edit-toggle class="text-zinc-400 hover:text-zinc-700" title="Изменить дату">
                            <x-icon name="edit-02" class="w-3.5 h-3.5" />
                        </button>
                    </span>
                    <input type="date" data-edit hidden
                           class="crm-access-input border rounded-lg px-2 py-1 input-focus sans text-sm"
                           value="{{ $until?->format('Y-m-d') }}">
                    <input type="number" data-edit hidden min="0" step="1" placeholder="Сумма, ₽ (необязательно)"
                           class="crm-payment-amount border rounded-lg px-2 py-1 input-focus sans text-sm w-40">
                    <button type="button" data-edit hidden class="crm-access-save rounded-lg px-2 py-1 bg-zinc-900 text-white text-xs sans-medium hover:bg-zinc-800">
                        Сохранить
                    </button>
                    <button type="button" data-edit hidden class="crm-access-cancel text-xs text-zinc-400 hover:text-zinc-700 sans">
                        Отмена
                    </button>
                    <span class="crm-access-saved text-emerald-600 opacity-0 transition-opacity">✓</span>
                </span>

                <span class="text-zinc-300">·</span>
                <span class="crm-last-payment sans text-zinc-400 text-xs">
                    @if($lastPayment)
                        платёж: {{ number_format($lastPayment->amount_cents / 100, 0, ',', ' ') }} ₽ ({{ \App\Support\CrmDate::format($lastPayment->paid_at) }})
                    @else
                        платежей нет
                    @endif
                </span>
            </div>
        @empty
            <div class="sans text-sm text-zinc-400">Курсов нет</div>
        @endforelse
    </div>

    <div class="mt-3" data-field-group>
        <div data-view class="flex items-center gap-2">
            {{-- Есть заметка — сама показывается как инпут (readonly, пока не
                 нажали карандашик). Пустой заметки не бывает как инпута —
                 только обычный текст, чтобы не выглядело как пустое поле
                 ввода, которое почему-то нельзя нажать. --}}
            <textarea readonly rows="2"
                      class="crm-note-view flex-1 w-full border rounded-lg px-3 py-2 text-sm input-focus sans text-zinc-600"
                      {{ $student->crm_note ? '' : 'hidden' }}>{{ $student->crm_note }}</textarea>
            <div class="crm-note-empty flex-1 sans text-sm text-zinc-400" {{ $student->crm_note ? 'hidden' : '' }}>
                Заметок нет
            </div>
            <button type="button" data-field-edit-toggle class="shrink-0 text-zinc-400 hover:text-zinc-700" title="Изменить комментарий">
                <x-icon name="edit-02" class="w-3.5 h-3.5" />
            </button>
        </div>
        <textarea data-edit hidden rows="2"
                  class="crm-note-input w-full border rounded-lg px-3 py-2 text-sm input-focus sans"
                  placeholder="Заметка…">{{ $student->crm_note }}</textarea>
        <span class="crm-note-saved text-emerald-600 text-xs sans opacity-0 transition-opacity">Сохранено ✓</span>
    </div>
</div>
