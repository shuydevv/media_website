@php
    $isActive = $reminder->isActive();
@endphp
<div class="flex items-center gap-2 border rounded-lg px-3 py-2 text-sm {{ $isActive ? 'border-amber-400 bg-amber-100' : 'border-amber-200 bg-amber-50' }}"
     data-reminder-body-row data-reminder-id="{{ $reminder->id }}">
    <x-icon name="bell-01" class="w-4 h-4 text-amber-500 shrink-0" />
    <span class="flex-1 min-w-0 truncate sans text-zinc-700" data-reminder-body-note>{{ $reminder->note }}</span>
    <span class="sans text-xs text-zinc-500 shrink-0" data-reminder-body-date>{{ \App\Support\CrmDate::format($reminder->due_at) }}</span>
</div>
