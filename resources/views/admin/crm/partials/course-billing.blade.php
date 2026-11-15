{{--
    Блок оплаты в строке курса: последний платёж, «отменить», галочки
    «Напомнил»/«Оплатил». Перерисовывается целиком из JSON-ответов CRM
    (поле billing_html), поэтому все обработчики — делегированные (layout).
    $s — App\Support\CrmCourseState::for($student, $course).
--}}
@php
    $lastReal = $s['lastRealPayment'];
@endphp
<span data-course-billing class="inline-flex flex-wrap items-center gap-x-3 gap-y-1.5"
      data-default-amount="{{ $s['defaultAmountRub'] }}"
      data-last-hint="{{ $lastReal ? number_format($lastReal->amount_cents / 100, 0, ',', ' ').' ₽ — '.\App\Support\CrmDate::format($lastReal->paid_at) : '' }}"
      data-interval="{{ $s['intervalDays'] }}"
      data-baseline="{{ $s['baselineDate']?->format('Y-m-d') }}">
    <span class="crm-last-payment sans text-zinc-400 text-xs">
        @if($s['lastPayment'])
            платёж: {{ number_format($s['lastPayment']->amount_cents / 100, 0, ',', ' ') }} ₽ ({{ \App\Support\CrmDate::format($s['lastPayment']->paid_at) }})
        @else
            платежей нет
        @endif
    </span>
    @if($s['undoPayment'])
        <button type="button" data-payment-undo="{{ $s['undoPayment']->id }}"
                class="sans text-xs text-zinc-400 underline decoration-dotted hover:text-rose-600"
                title="Удалить этот платёж и вернуть прежнюю дату">
            отменить
        </button>
    @endif

    @if($s['needsAction'] || $s['remindedAt'])
        <label class="crm-mark inline-flex items-center gap-1.5 cursor-pointer sans text-xs text-zinc-600 min-h-8">
            <input type="checkbox" data-mark-reminded class="crm-mark-box" {{ $s['remindedAt'] ? 'checked' : '' }}>
            Напомнил
            @if($s['remindedAt'])
                <span class="text-zinc-400">{{ \App\Support\CrmDate::format($s['remindedAt']) }}</span>
            @endif
        </label>
    @endif
    @if($s['needsAction'])
        <label class="crm-mark inline-flex items-center gap-1.5 cursor-pointer sans text-xs text-zinc-600 min-h-8">
            <input type="checkbox" data-mark-paid class="crm-mark-box">
            Оплатил
        </label>
    @endif
</span>
