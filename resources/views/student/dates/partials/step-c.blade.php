{{-- resources/views/student/dates/partials/step-c.blade.php
     «Лишний факт»: три факта элемента и один чужой (того же вида — событие
     к событию, правление к правлению), найти чужой. Какой лишний и откуда он,
     страница узнаёт только после ответа; говорит об этом рыба. --}}
@php
  $done = $current['done'];
  $reveal = $current['reveal'];
  $answered = $done || $reveal !== null;
@endphp

@include('student.dates.partials.item-head', ['item' => $item])

<div class="sans-medium text-sm text-zinc-500 mt-4 mb-2">Какой факт лишний?</div>

<div class="space-y-2">
  @foreach($options as $i => $text)
    @if($answered)
      <div @if($i === $oddIndex) data-pulse @endif
           class="w-full text-left rounded-xl border px-4 py-3 text-sm sm:text-base
                  {{ $i === $oddIndex ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : ($reveal !== null && $i === $reveal['choice'] ? 'bg-amber-50 border-amber-300 text-amber-800' : ($done ? 'term-option-faded bg-white border-gray-200 text-zinc-300' : 'bg-white border-gray-200 text-zinc-700')) }}">
        {{ $text }}
      </div>
    @else
      <button type="button"
              class="term-option w-full text-left rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm sm:text-base text-zinc-900 hover:bg-zinc-50 active:bg-zinc-100 transition"
              hx-post="{{ route('student.dates.session.choose', $session) }}"
              hx-vals='{"choice": {{ $i }}}'
              hx-target="#terms-app" hx-swap="innerHTML"
              hx-disabled-elt=".term-option">
        {{ $text }}
      </button>
    @endif
  @endforeach
</div>

@if($reveal !== null)
  @include('student.dates.partials.next-button', ['label' => 'Далее'])
@elseif($done)
  @include('student.terms.partials.next-bar')
@endif
