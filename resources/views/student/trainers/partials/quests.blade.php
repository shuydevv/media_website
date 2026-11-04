{{-- «Упражнения на сегодня» в «Качалке» — открытые ученику тренажёры всех
     залов, срочные первыми (App\Service\Trainers\TrainerGym). Один и тот же
     блок на ПК (колонка справа) и на телефоне (над вкладками залов);
     раскрыт по умолчанию, свернуть — $open = false. --}}
<details class="gym-quests" @if($open ?? true) open @endif>
  <summary>
    Упражнения на сегодня
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
  </summary>
  @if($quests)
    <div class="gym-quest-list">
      @foreach($quests as $q)
        @php
          $done = $q['situation'] === \App\Service\Trainers\Trainer::DONE;
          $dot = $done ? '#15803D' : $badgeColor($q['situation']);
        @endphp
        <a href="{{ $q['url'] }}" class="gym-quest {{ $done ? 'is-done' : '' }}">
          <span class="gym-quest-dot" style="background: {{ $dot }}"></span>
          <span class="gym-quest-body">
            <span class="gym-quest-title">{{ $q['title'] }} <span>· {{ $q['hall_tab'] }}</span></span>
            <span class="gym-quest-note">{{ $q['quest'] }}</span>
          </span>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#94A9B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </a>
      @endforeach
    </div>
  @else
    <div class="gym-empty">Пока упражнений нет — тренажёры откроются вместе с курсом.</div>
  @endif
</details>
