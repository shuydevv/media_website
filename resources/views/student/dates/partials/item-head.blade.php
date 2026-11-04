{{-- Шапка задания на факты: годы и название. Год здесь показываем — в
     квизе спрашиваем не его, а увидеть дату рядом с событием ещё раз полезно. --}}
<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
  <span class="sans-medium text-lg sm:text-xl text-blue-700 tabular-nums">{{ $item->yearsLabel() }}</span>
  <span class="text-xs uppercase tracking-wide text-zinc-400">{{ $item->isReign() ? 'Правление' : 'Событие' }}</span>
</div>
<div class="sans-medium text-xl sm:text-2xl text-zinc-900 break-words mt-1">{{ $item->title }}</div>
