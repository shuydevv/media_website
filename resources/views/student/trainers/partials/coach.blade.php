{{-- Карточка тренера «Качалки» — одна на все залы и никогда не исчезает:
     при смене зала меняется только реплика (App\Service\Trainers\TrainerGym
     — speech/lead). Если есть куда звать, последнее слово реплики
     («Продолжим?») — синяя ссылка в самый срочный тренажёр зала. Одна и та
     же на ПК (колонка справа) и на телефоне (над вкладками). --}}
<div class="gym-coach-card">
  <span class="gym-coach-pic"><img src="{{ asset('img/trainers/coach.webp') }}" alt="Тренер"></span>
  <div class="gym-coach-bubble">
    <div class="gym-coach-label">Тренер</div>
    @foreach($halls as $hall)
      <div class="gym-coach-text" data-hall-panel="{{ $hall['key'] }}" @if($hall['key'] !== $active) hidden @endif>
        @if($hall['lead'] && preg_match('/^(.*\s)?(\S+?)([?!.…]*)$/us', $hall['speech'], $m))
          {{ $m[1] }}<a href="{{ $hall['lead'] }}" class="gym-coach-link">{{ $m[2] }}</a>{{ $m[3] }}
        @else
          {{ $hall['speech'] }}
        @endif
      </div>
    @endforeach
  </div>
</div>
