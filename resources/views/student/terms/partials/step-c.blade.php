{{-- resources/views/student/terms/partials/step-c.blade.php
     Задание «Лишний признак»: до трёх признаков термина и один чужой, найти
     нужно чужой. Чередуется с квизом (step-b) внутри сессии. Какой вариант
     лишний ($oddIndex) и от какого он понятия ($oddTerm), страница узнаёт
     только после ответа. Клиент отправляет номер варианта.

     Неверный выбор — жёлтый, не красный; лишний после ответа — зелёный.
     После верного ответа остальные варианты плавно гаснут (как в конце
     квиза) — экран уходит сам, и карточка не должна выглядеть недорешённой.
     Обёртку карточки даёт region.blade.php. --}}
@php
  $done = $current['done'];
  $reveal = $current['reveal'];
  $answered = $done || $reveal !== null;
@endphp

@include('student.terms.partials.photo', ['term' => $term])

<div class="mt-4">
  <div class="sans-medium text-xl sm:text-2xl text-zinc-900 break-words">{{ $term->title }}</div>
  @if($term->qualifier)
    <div class="text-sm text-zinc-500 mt-0.5">{{ $term->qualifier }}</div>
  @endif
</div>

<div class="sans-medium text-sm text-zinc-500 mt-4 mb-2">Какой признак лишний?</div>

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
              hx-post="{{ route('student.terms.session.choose', $session) }}"
              hx-vals='{"choice": {{ $i }}}'
              hx-target="#terms-app" hx-swap="innerHTML"
              hx-disabled-elt=".term-option">
        {{ $text }}
      </button>
    @endif
  @endforeach
</div>

{{-- Плашки с разбором нет: от какого понятия лишний признак, говорит рыба.
     После верного ответа экран уходит сам, после неверного ждёт «Далее». --}}
@if($reveal !== null)
  @include('student.terms.partials.next-button', ['label' => 'Далее'])
@elseif($done)
  @include('student.terms.partials.next-bar')
@endif
