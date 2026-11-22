{{-- Варианты нормы тренажёров на день (config('daily_plan.norms')).
     Ничего не выбрано заранее; $current — нынешняя норма, подписана «сейчас». --}}
<form method="POST" action="{{ route('student.trainers.norm') }}" class="gym-norm-options">
  @csrf
  <input type="hidden" name="hall" value="{{ $active }}">
  @foreach($norms as $m)
    <button type="submit" name="minutes" value="{{ $m }}" class="gym-norm-option">
      <b>{{ $m }}</b> мин
      @if($current === $m)<small>сейчас</small>@endif
    </button>
  @endforeach
</form>
