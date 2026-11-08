{{-- Знакомство с новой личностью. «Запомнил» — через пару шагов придёт
     «Чем известен?»; «Ещё не запомнил» — карточка вернётся. --}}
@include('student.persons.partials.person-card', ['person' => $person])

<div class="mt-6 grid gap-2 sm:grid-cols-2">
  @foreach([['1', 'game-main', 'Запомнил'], ['0', 'game', 'Ещё не запомнил']] as [$value, $variant, $label])
    <form method="POST" action="{{ route('student.persons.session.card', $session) }}"
          hx-post="{{ route('student.persons.session.card', $session) }}"
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
