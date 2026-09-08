@php
    $isActive = $reminder->isActive();
@endphp
<div class="text-sm border rounded-lg px-2 py-1.5 {{ $isActive ? 'border-amber-400 bg-amber-100' : 'border-amber-200 bg-amber-50' }}"
     data-reminder-row data-reminder-id="{{ $reminder->id }}">
    <div data-view class="flex items-center gap-1.5">
        <span class="text-zinc-500 text-xs shrink-0" data-reminder-date-label>{{ \App\Support\CrmDate::format($reminder->due_at) }}</span>
        <span class="flex-1 min-w-0 truncate sans text-zinc-700" data-reminder-note-label>{{ $reminder->note }}</span>
        <button type="button" data-reminder-edit-toggle class="text-zinc-400 hover:text-zinc-700 shrink-0" title="Изменить">
            <x-icon name="edit-02" class="w-3.5 h-3.5" />
        </button>
        <button type="button" data-reminder-delete class="text-zinc-400 hover:text-rose-600 shrink-0" title="Удалить">
            <x-icon name="x-close" class="w-3.5 h-3.5" />
        </button>
    </div>
    <div data-edit hidden class="flex flex-col gap-1.5 mt-1.5">
        <input type="date" data-reminder-edit-date class="border rounded-lg px-2 py-1 input-focus sans text-xs" value="{{ $reminder->due_at->format('Y-m-d') }}">
        <input type="text" data-reminder-edit-note maxlength="500" class="border rounded-lg px-2 py-1 input-focus sans text-xs" value="{{ $reminder->note }}">
        <div class="flex items-center gap-2">
            <button type="button" data-reminder-save class="rounded-lg px-2 py-1 bg-zinc-900 text-white text-xs sans-medium hover:bg-zinc-800">Сохранить</button>
            <button type="button" data-reminder-cancel class="text-xs text-zinc-400 hover:text-zinc-700 sans">Отмена</button>
        </div>
    </div>
</div>
