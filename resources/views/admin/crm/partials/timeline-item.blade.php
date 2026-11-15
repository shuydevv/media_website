{{-- Строка ленты «История» в карточке CRM. $item — из App\Support\CrmTimeline. --}}
<li class="flex gap-2 sans text-sm">
    <span class="shrink-0 w-16 text-zinc-400 text-xs leading-5">{{ \App\Support\CrmDate::format($item['at']) ? \Illuminate\Support\Str::beforeLast(\App\Support\CrmDate::format($item['at']), ' ') : '' }}</span>
    <span class="{{ $item['tone'] === 'money' ? 'text-emerald-700' : ($item['tone'] === 'bad' ? 'text-rose-600' : 'text-zinc-600') }} min-w-0 break-words">{{ $item['text'] }}</span>
</li>
