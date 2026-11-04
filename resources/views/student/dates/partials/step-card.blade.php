{{-- resources/views/student/dates/partials/step-card.blade.php
     Знакомство с новым событием или правлением. «Запомнил» — через пару
     шагов придёт вопрос про год; «Ещё не запомнил» — карточка вернётся. --}}
@include('student.dates.partials.item-card', ['item' => $item])

<div class="mt-6 grid gap-2 sm:grid-cols-2">
  @foreach([['1', 'accent', 'Запомнил'], ['0', 'outline', 'Ещё не запомнил']] as [$value, $variant, $label])
    <form method="POST" action="{{ route('student.dates.session.card', $session) }}"
          hx-post="{{ route('student.dates.session.card', $session) }}"
          hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="find button">
      @csrf
      <input type="hidden" name="remembered" value="{{ $value }}">
      <x-ui.button type="submit" :variant="$variant" block class="relative text-sm sm:text-base">
        <span class="btn-label">{{ $label }}</span>
        @include('student.terms.partials.spinner')
      </x-ui.button>
    </form>
  @endforeach
</div>
<div class="text-xs text-zinc-500 mt-3">«Ещё не запомнил» покажет эту карточку ещё раз чуть позже.</div>
