{{-- resources/views/student/dates/partials/item-card.blade.php
     Полная карточка события или правления: годы, название, описание, другие
     названия, факты. Одна и та же в знакомстве и в справочнике. --}}
<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
  <span class="sans-medium text-2xl sm:text-3xl text-blue-700 tabular-nums">{{ $item->isReign() ? $item->yearsLabel() : $item->date_display }}</span>
  <span class="text-xs uppercase tracking-wide text-zinc-400">{{ $item->isReign() ? 'Правление' : 'Событие' }}</span>
</div>

<div class="mt-2">
  <div class="sans-medium text-xl sm:text-2xl text-zinc-900 break-words">{{ $item->title }}</div>
  @if($item->summary)
    <div class="text-sm sm:text-base text-zinc-600 mt-1">{{ $item->summary }}</div>
  @endif
  @if(!empty($item->other_names))
    <div class="text-sm text-zinc-500 mt-1.5">{{ $item->isReign() ? 'Другие имена' : 'Другие названия' }}: {{ implode(', ', $item->other_names) }}</div>
  @endif
</div>

<ul class="mt-4 space-y-2">
  @foreach($item->facts as $fact)
    <li class="flex gap-2.5 text-sm sm:text-base text-zinc-800">
      <span class="shrink-0 mt-2 w-1.5 h-1.5 rounded-full bg-blue-500"></span>
      <span>{{ $fact->text }}</span>
    </li>
  @endforeach
</ul>
