{{-- Окно «Приказ по флоту»: новое звание от Адмирала Клешни
     (App\Service\Persons\PersonRanks). На экране результата — после того как
     корм долетел до маскота; на главной — знакомство при первом входе.
     Показ, анимация и закрытие — partials/rank-script. $rank — данные из
     PersonTrainerController::rankModal(). --}}
<div id="prs-rank" class="prs-rank" role="dialog" aria-modal="true" aria-labelledby="prs-rank-title" hidden>
  <div class="prs-rank-card">
    <div class="prs-rank-head">{{ $rank['intro'] ? 'Прибытие на службу' : 'Приказ по флоту № '.$rank['n'] }}</div>

    <div class="prs-rank-pics">
      <img src="{{ $rank['image'] }}" alt="Адмирал Клешня" class="prs-rank-mascot">
      <div class="prs-rank-board">@include('student.persons.partials.rank-insignia', ['board' => $rank['board'], 'size' => 110])</div>
    </div>

    <div class="prs-rank-say">
      @if(!empty($rank['skipped']))<span class="prs-rank-skip">{{ config('persons.rank_skipped') }}</span> @endif{{ $rank['speech'] }}
    </div>

    <div class="prs-rank-label">{{ $rank['intro'] ? 'Твоё звание' : 'Присвоено звание' }}</div>
    <div id="prs-rank-title" class="prs-rank-title">{{ $rank['title'] }}</div>

    <div class="prs-rank-note">
      @if($rank['intro'])
        @if($rank['nextTitle'])Выучи {!! str_replace(' ', '&nbsp;', e(\App\Support\Text::plural($rank['left'], ['личность', 'личности', 'личностей']))) !!} — и&nbsp;ты {{ mb_strtolower($rank['nextTitle']) }}@endif
      @else
        {{-- Две строки по смыслу; число не отрывается от «ещё» (&nbsp;). --}}
        <span style="display:block">Личностей выучено: {{ $rank['inLine'] }}</span>
        @if($rank['nextTitle'] && $rank['left'])<span style="display:block">До звания «{{ $rank['nextTitle'] }}» ещё&nbsp;{{ $rank['left'] }}</span>@endif
      @endif
    </div>

    <x-ui.button type="button" variant="game-main" block id="prs-rank-ok" class="mt-5">{{ $rank['button'] }}</x-ui.button>
  </div>
</div>
