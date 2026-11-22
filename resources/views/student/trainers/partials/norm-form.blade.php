{{-- Варианты нормы тренажёров на день (config('daily_plan.norms')).
     Ничего не выбрано заранее; $current — нынешняя норма: плитка потемнее
     с галочкой в углу, нажать её нельзя. --}}
<form method="POST" action="{{ route('student.trainers.norm') }}" class="gym-norm-options">
  @csrf
  <input type="hidden" name="hall" value="{{ $active }}">
  @foreach($norms as $m)
    @if($current === $m)
      <button type="button" class="gym-norm-option is-current" disabled aria-label="{{ $m }} мин — текущая норма">
        <span class="gym-norm-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
        </span>
        <b>{{ $m }}</b> мин
      </button>
    @else
      <button type="submit" name="minutes" value="{{ $m }}" class="gym-norm-option">
        <b>{{ $m }}</b> мин
      </button>
    @endif
  @endforeach
</form>
