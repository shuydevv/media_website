{{-- Кнопка перехода к следующему шагу сессии («Далее»). data-autofocus —
     скрипт в session.blade.php ставит на неё фокус после подмены шага, чтобы
     на клавиатуре хватало Enter (атрибут autofocus в подменённом htmx-ом
     фрагменте браузер игнорирует). --}}
<form class="mt-5" method="POST" action="{{ route('student.terms.session.next', $session) }}"
      hx-post="{{ route('student.terms.session.next', $session) }}"
      hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="find button">
  @csrf
  <x-ui.button type="submit" variant="accent" class="relative text-sm sm:text-base" data-autofocus>
    <span class="btn-label">{{ $label }}</span>
    @include('student.terms.partials.spinner')
  </x-ui.button>
</form>
