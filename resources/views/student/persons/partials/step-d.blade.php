{{-- «Соответствие как в ЕГЭ»: четыре события (ассоциации) и шесть
     личностей, две лишние. Вид — как у заданий на соответствие на станции
     «Задания» (student/ege/round), но без кнопок с цифрами у строк
     (решение автора от 08.11.2026): ответ вводится с клавиатуры в клеточки
     бланка, как на экзамене. Цифры по буквам собирает скрипт
     partials/match-script; проверяет сервер, каждая буква — отдельно.
     На расписание и корм задание не влияет: это тренировка формата. --}}
@php
  $answered = $result !== null;
  $optionCount = count($options);
  $matchedIds = $answered ? array_map('intval', $current['rows']) : [];
@endphp

<div id="prs-match" data-answered="{{ $answered ? 1 : 0 }}" data-rows="{{ count($rows) }}" data-options="{{ $optionCount }}">
  <span class="prs-tag">🎓 Как в ЕГЭ</span>
  <div class="prs-task">Установите соответствие между событиями и их участниками: к каждому событию подберите участника.</div>

  <div class="prs-match">
    <div>
      <div class="prs-col-title">События</div>
      <div class="prs-left">
        @foreach($rows as $i => $row)
          <div class="prs-lrow {{ $answered ? ($row['ok'] ? 'r-ok' : 'r-bad') : '' }}" @if($answered && $row['ok']) data-pulse @endif>
            <div class="prs-lrow-text"><b>{{ $row['letter'] }})</b>{{ $row['text'] }}</div>
            @if($answered && !$row['ok'])
              <div class="prs-lrow-fix">Верно: {{ $row['right'] }} — {{ $row['rightName'] }}</div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
    <div>
      <div class="prs-col-title">Участники</div>
      <ol class="prs-right">
        @foreach($options as $j => $option)
          <li class="{{ $answered && !in_array((int) $option->id, $matchedIds, true) ? 'is-extra' : '' }}">
            <span>{{ $j + 1 }})</span><span>{{ $option->name }}</span>
          </li>
        @endforeach
      </ol>
    </div>
  </div>

  {{-- Ответ — клеточки бланка под буквами. До ответа их заполняет скрипт
       (клики по цифрам или ввод с клавиатуры), после — сервер. --}}
  @if(!$answered)
    <form id="prs-match-form" class="prs-answer" method="POST" action="{{ route('student.persons.session.match', $session) }}"
          hx-post="{{ route('student.persons.session.match', $session) }}"
          hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="find button">
      @csrf
      <input type="hidden" name="answer" id="prs-match-answer" value="">
      <div class="pin-field">
        <div class="pin-boxes">
          @foreach($rows as $row)
            <div class="prs-cell"><span class="prs-cell-l">{{ $row['letter'] }}</span><div class="pin-box" data-box></div></div>
          @endforeach
        </div>
        <input id="prs-match-input" class="prs-pin-input" type="text" inputmode="numeric" autocomplete="off"
               maxlength="{{ count($rows) }}" aria-label="Ответ: цифры по буквам">
      </div>
      <x-ui.button type="submit" variant="game-main" class="prs-check relative text-sm sm:text-base" disabled>
        <span class="btn-label">Проверить</span>
        @include('student.terms.partials.spinner')
      </x-ui.button>
    </form>
  @else
    <div class="prs-answer" aria-label="Твой ответ">
      <div class="pin-field">
        <div class="pin-boxes">
          @foreach($rows as $row)
            <div class="prs-cell">
              <span class="prs-cell-l">{{ $row['letter'] }}</span>
              <div class="pin-box filled {{ $row['ok'] ? 'is-ok' : 'is-bad' }}">{{ $row['chosen'] ?: '–' }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    @if(in_array(false, $result['ok'], true))
      @include('student.persons.partials.next-button', ['label' => 'Далее'])
    @else
      @include('student.terms.partials.next-bar')
    @endif
  @endif

  @unless($answered)
    @include('student.persons.partials.report')
  @endunless
</div>
