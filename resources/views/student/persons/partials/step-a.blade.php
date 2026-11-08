{{-- Ввод фамилии: по описанию и ассоциации вспомнить, кто это, и ввести
     фамилию в клетки. Приходит только в повторении с шага
     config('persons.type_from_step') — сначала узнаём, потом вспоминаем.
     Портрет до ответа скрыт («?»): лицо было бы подсказкой. Правильной
     фамилии в странице нет — только число клеток и открытые буквы.

     Разметка и id — как у student/terms/partials/step-a: ввод, отправку на
     последней клетке и анимации делает общий скрипт сессии. --}}
@php
  $result = $current['result'];
  $answered = $result !== null;
  $hintButton = 'inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-800 hover:bg-zinc-50 transition';
@endphp

<div class="prs-type-head">
  @include('student.persons.partials.portrait', ['person' => $person, 'size' => 72, 'mystery' => !$answered])
  <div class="prs-head-text">
    <span class="prs-era">{{ $eras[$person->era] ?? '' }}</span>
    @if($answered)
      <div class="prs-name mt-1">{{ $person->name }}</div>
    @endif
    <div class="prs-desc {{ $answered ? '' : 'mt-2' }}">{{ $person->description }}</div>
  </div>
</div>
<div class="prs-assoc">
  <span class="prs-assoc-t">Чем известен</span>
  <span class="prs-assoc-v">{{ $person->association }}</span>
</div>

<div class="prs-q">Кто это? Фамилия</div>

@if(!$answered)
  <form id="term-a-form" method="POST" action="{{ route('student.persons.session.answer', $session) }}"
        hx-post="{{ route('student.persons.session.answer', $session) }}"
        hx-target="#terms-app" hx-swap="innerHTML">
    @csrf
    <input type="hidden" name="typed" id="term-a-typed" value="">

    <div class="relative">
      <div id="term-a-cells" class="flex flex-wrap gap-1.5" data-locked="{{ count($revealed) }}">
        @for($i = 0; $i < $cellCount; $i++)
          <span class="term-cell w-8 h-10 sm:w-9 sm:h-11 rounded-lg border flex items-center justify-center sans-medium text-base sm:text-lg uppercase
                       {{ isset($revealed[$i]) ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-zinc-300 text-zinc-900' }}">{{ $revealed[$i] ?? '' }}</span>
        @endfor
      </div>
      <div id="term-a-input" contenteditable="true" role="textbox" inputmode="text" enterkeyhint="done"
           autocapitalize="off" autocorrect="off" spellcheck="false" translate="no"
           aria-label="Фамилия"
           class="absolute inset-0 w-full h-full opacity-0 cursor-text overflow-hidden whitespace-nowrap outline-none"
           style="font-size:16px; caret-color:transparent;"></div>
    </div>

    <div class="mt-5">
      @if($hintsLeft > 0)
        <button type="button" class="{{ $hintButton }}"
                hx-post="{{ route('student.persons.session.hint', $session) }}"
                hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
          <span class="w-5 h-5 rounded-md border border-zinc-400 flex items-center justify-center text-[11px] leading-none sans-medium" aria-hidden="true">А</span>
          Открыть букву
          <span class="text-zinc-400">{{ $hintsLeft }} из {{ $freeHints }}</span>
        </button>
      @else
        <button type="button" class="{{ $hintButton }}"
                hx-post="{{ route('student.persons.session.answer', $session) }}"
                hx-vals='{"reveal": 1}'
                hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
          Показать ответ
        </button>
      @endif
    </div>
  </form>
@else
  {{-- Ответ показывают сами клетки: верно — зелёная волна, неверно —
       буквы ученика (data-typed) переворачиваются в правильные. --}}
  <div id="term-a-result" data-correct="{{ $result['correct'] ? 1 : 0 }}" data-revealed="{{ $result['revealed'] ? 1 : 0 }}"
       class="flex flex-wrap items-center gap-1.5">
    <span class="sr-only">{{ $result['correct'] ? 'Верно' : 'Правильный ответ' }}: {{ $person->answer }}</span>
    @foreach($revealed as $i => $letter)
      <span class="term-cell w-8 h-10 sm:w-9 sm:h-11 rounded-lg border flex items-center justify-center sans-medium text-base sm:text-lg uppercase
                   {{ $result['correct'] ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-zinc-100 border-zinc-300 text-zinc-700' }}"
            data-typed="{{ $typedCells[$i] ?? '' }}" aria-hidden="true">{{ $letter }}</span>
    @endforeach
    @if($result['correct'])
      <x-icon id="term-a-check" name="check-circle" class="ml-1 w-7 h-7 text-emerald-500" aria-hidden="true" />
    @endif
  </div>

  @if(!$result['correct'])
    @include('student.persons.partials.next-button', ['label' => 'Далее'])
  @else
    @include('student.terms.partials.next-bar')
  @endif
@endif
