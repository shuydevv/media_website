{{-- resources/views/student/terms/partials/term-card.blade.php
     Полная карточка термина: фото, название, уточнение, другие названия,
     признаки. Одна и та же в знакомстве с новым термином и в справочнике. --}}
@include('student.terms.partials.photo', ['term' => $term])

<div class="mt-4">
  <div class="sans-medium text-xl sm:text-2xl text-zinc-900 break-words">{{ $term->title }}</div>
  @if($term->qualifier)
    <div class="text-sm text-zinc-500 mt-0.5">{{ $term->qualifier }}</div>
  @endif
  @if(!empty($term->other_names))
    <div class="text-sm text-zinc-500 mt-1.5">Другие названия: {{ implode(', ', $term->other_names) }}</div>
  @endif
</div>

<ul class="mt-4 space-y-2">
  @foreach($term->features as $feature)
    <li class="flex gap-2.5 text-sm sm:text-base text-zinc-800">
      <span class="shrink-0 mt-2 w-1.5 h-1.5 rounded-full bg-blue-500"></span>
      <span>{{ $feature->text }}</span>
    </li>
  @endforeach
</ul>
