{{-- Кнопка «Далее» сессии тренажёра дат — как student/terms/partials/next-button. --}}
<form class="mt-5" method="POST" action="{{ route('student.dates.session.next', $session) }}"
      hx-post="{{ route('student.dates.session.next', $session) }}"
      hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="find button">
  @csrf
  <x-ui.button type="submit" variant="accent" class="relative text-sm sm:text-base" data-autofocus>
    <span class="btn-label">{{ $label }}</span>
    @include('student.terms.partials.spinner')
  </x-ui.button>
</form>
