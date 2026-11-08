{{-- «Кто это?»: по описанию и ассоциации выбрать личность из четырёх
     (портрет и имя). «Подсказка» показывает связи (related) — она
     бесплатная, как открытая цифра в датах. Ответ страница узнаёт только
     после выбора; кто был выбран по ошибке, говорит маскот. --}}
@php
  $done = $current['done'];
@endphp

<div class="prs-clue">
  <span class="prs-era">{{ $eras[$person->era] ?? '' }}</span>
  <div class="prs-clue-desc mt-1">{{ $person->description }}</div>
  <div class="prs-assoc">
    <span class="prs-assoc-t">Кто это?</span>
    <span class="prs-assoc-v">{{ $person->association }}</span>
  </div>
</div>

<div class="mt-4"></div>

<div class="prs-tiles">
  @foreach($options as $i => $option)
    @if($answered)
      <div @if($i === $correctIndex) data-pulse @endif
           class="prs-tile {{ $i === $correctIndex ? 'is-right' : ($i === $chosenIndex ? 'is-chosen' : 'is-faded') }}">
        @include('student.persons.partials.portrait', ['person' => $option, 'size' => 96])
        <span>{{ $option->name }}</span>
      </div>
    @else
      <button type="button" class="prs-tile term-option"
              hx-post="{{ route('student.persons.session.choose', $session) }}"
              hx-vals='{"choice": {{ $i }}}'
              hx-target="#terms-app" hx-swap="innerHTML"
              hx-disabled-elt=".term-option">
        @include('student.persons.partials.portrait', ['person' => $option, 'size' => 96])
        <span>{{ $option->name }}</span>
      </button>
    @endif
  @endforeach
</div>

@if($current['reveal'] !== null)
  @include('student.persons.partials.next-button', ['label' => 'Далее'])
@elseif($done)
  @include('student.terms.partials.next-bar')
@endif

{{-- Низ карточки: маленькая кнопка подсказки и «Сообщить об ошибке» в
     одну строку. Открытая подсказка встаёт на место кнопки. --}}
@if(!$done && ($hasHint || $hintText))
  <div class="prs-foot">
    @if($hintText)
      <div class="prs-hint"><span aria-hidden="true">🔗</span><span>{{ $hintText }}</span></div>
    @elseif(!$answered)
      <button type="button" class="prs-hint-btn"
              hx-post="{{ route('student.persons.session.hint', $session) }}"
              hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
        <span aria-hidden="true">🔗</span> Подсказка
      </button>
    @endif
    @include('student.persons.partials.report', ['inline' => true])
  </div>
@elseif($hintText)
  <div class="prs-hint mt-4"><span aria-hidden="true">🔗</span><span>{{ $hintText }}</span></div>
@elseif(!$done)
  @include('student.persons.partials.report')
@endif
