{{-- «План на сегодня» в «Качалке» (App\Service\Trainers\DailyPlanService):
     2–3 тренажёра под норму ученика в минутах. Пока норма не выбрана —
     вопрос с вариантами нормы. Один и тот же блок на ПК (колонка справа) и
     на телефоне (над вкладками залов); раскрыт по умолчанию, свернуть —
     $open = false. Окно смены нормы — partials/norm-dialog, одно на страницу. --}}
<details class="gym-quests" @if($open ?? true) open @endif>
  <summary>
    <span>План на сегодня@if($plan && $plan['total'] && !$plan['complete'])<span class="gym-plan-min"> · ~{{ $plan['minutes'] }} мин</span>@endif</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
  </summary>

  @if(!$quests)
    <div class="gym-empty">Пока упражнений нет — тренажёры откроются вместе с курсом.</div>
  @elseif($plan === null)
    <div class="gym-norm-ask">
      <div class="gym-norm-q">Сколько минут в день готов тренироваться?</div>
      <div class="gym-norm-sub">Соберём посильный план на каждый день.</div>
      @include('student.trainers.partials.norm-form', ['current' => null])
    </div>
  @else
    @if($plan['light'])
      <div class="gym-plan-light">{{ $plan['light'] === 'lesson' ? 'Сегодня урок — план полегче' : 'Скоро сдавать домашку — план полегче' }}</div>
    @endif

    @if($plan['total'])
      <div class="gym-plan-progress">
        <div class="gym-plan-bar"><i style="width: {{ round(100 * $plan['done'] / $plan['total']) }}%"></i></div>
        <span>{{ $plan['done'] }} из {{ $plan['total'] }}</span>
      </div>

      <div class="gym-quest-list">
        @foreach($plan['items'] as $q)
          <a href="{{ $q['url'] }}" class="gym-quest {{ $q['done'] ? 'is-done' : '' }}">
            @if($q['done'])
              <span class="gym-quest-check" aria-label="Сделано">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
              </span>
            @else
              <span class="gym-quest-dot" style="background: {{ $badgeColor($q['situation']) }}"></span>
            @endif
            <span class="gym-quest-body">
              <span class="gym-quest-title">{{ $q['title'] }} <span>· {{ $q['hall_tab'] }}</span></span>
              <span class="gym-quest-note">{{ $q['done'] ? 'Сделано' : $q['quest'] }}</span>
            </span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#94A9B8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </a>
        @endforeach
      </div>

      @if($plan['complete'])
        <div class="gym-plan-done">План выполнен — отличная тренировка!</div>
      @endif
    @else
      <div class="gym-empty">На сегодня всё — отличная тренировка!</div>
    @endif

    <button type="button" class="gym-plan-norm" data-norm-open>Норма: {{ $plan['norm'] }} мин в день · изменить</button>
  @endif
</details>
