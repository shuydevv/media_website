{{-- resources/views/student/dates/partials/step-a.blade.php
     Способ А тренажёра дат: по названию и фактам ввести год. У диапазона —
     год начала и год окончания подряд: клетки «_ _ _ _ — _ _ _ _», тире
     ($splitAt) — не клетка, его не вводят. Правильного года в странице нет:
     только число клеток и цифры, открытые подсказкой; проверяет сервер.

     Разметка и id — как у student/terms/partials/step-a: ввод, отправку на
     последней клетке и анимации делает общий скрипт сессии
     (student/terms/partials/session-script). Отличие — цифровая клавиатура
     (inputmode="numeric"). Неверный ответ — серый, не красный. --}}
@php
  $result = $current['result'];
  $answered = $result !== null;
  $hintButton = 'inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-800 hover:bg-zinc-50 transition';
  $question = match (true) {
      $item->isReign() && $item->year_end === null => 'С какого года правит?',
      $item->isReign() => 'Годы правления?',
      $item->isRange() => 'Годы начала и окончания?',
      default => 'В каком году?',
  };
  // У диапазона восемь клеток и тире: на телефоне шириной 360px клетки
  // обычного размера в карточку не влезают — для диапазона они уже.
  $cellClass = 'term-cell rounded-lg border flex items-center justify-center sans-medium tabular-nums '
      .($splitAt !== null ? 'w-7 h-10 text-base sm:w-10 sm:h-12 sm:text-xl' : 'w-9 h-11 text-lg sm:w-10 sm:h-12 sm:text-xl');
  $dash = '<span class="w-3 sm:w-5 text-center text-zinc-400 sans-medium text-lg" aria-hidden="true">—</span>';
@endphp

<div class="text-xs uppercase tracking-wide text-zinc-400">{{ $item->isReign() ? 'Правление' : 'Событие' }}</div>
<div class="sans-medium text-xl sm:text-2xl text-zinc-900 break-words mt-1">{{ $item->title }}</div>
@if($item->summary)
  <div class="text-sm sm:text-base text-zinc-600 mt-1">{{ $item->summary }}</div>
@endif

<ul class="mt-4 space-y-2">
  @foreach($item->facts as $fact)
    <li class="flex gap-2.5 text-sm sm:text-base text-zinc-800">
      <span class="shrink-0 mt-2 w-1.5 h-1.5 rounded-full bg-blue-500"></span>
      <span>{{ $fact->text }}</span>
    </li>
  @endforeach
</ul>

<div class="sans-medium text-sm text-zinc-500 mt-5 mb-2">{{ $question }}</div>

@if(!$answered)
  <form id="term-a-form" method="POST" action="{{ route('student.dates.session.answer', $session) }}"
        hx-post="{{ route('student.dates.session.answer', $session) }}"
        hx-target="#terms-app" hx-swap="innerHTML">
    @csrf
    <input type="hidden" name="typed" id="term-a-typed" value="">

    {{-- Поле — редактируемый блок поверх клеток, как у терминов (почему не
         <input> — см. student/terms/partials/step-a). --}}
    <div class="relative inline-block">
      <div id="term-a-cells" class="flex items-center gap-1 sm:gap-1.5" data-locked="{{ count($revealed) }}">
        @for($i = 0; $i < $cellCount; $i++)
          @if($splitAt !== null && $i === $splitAt)
            {!! $dash !!}
          @endif
          <span class="{{ $cellClass }} {{ isset($revealed[$i]) ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-zinc-300 text-zinc-900' }}">{{ $revealed[$i] ?? '' }}</span>
        @endfor
      </div>
      <div id="term-a-input" contenteditable="true" role="textbox" inputmode="numeric" enterkeyhint="done"
           autocapitalize="off" autocorrect="off" spellcheck="false" translate="no"
           aria-label="Год"
           class="absolute inset-0 w-full h-full opacity-0 cursor-text overflow-hidden whitespace-nowrap outline-none"
           style="font-size:16px; caret-color:transparent;"></div>
    </div>

    <div class="mt-5">
      @if($hintsLeft > 0)
        <button type="button" class="{{ $hintButton }}"
                hx-post="{{ route('student.dates.session.hint', $session) }}"
                hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
          <span class="w-5 h-5 rounded-md border border-zinc-400 flex items-center justify-center text-[11px] leading-none sans-medium" aria-hidden="true">1</span>
          Открыть цифру
          <span class="text-zinc-400">{{ $hintsLeft }} из {{ $freeHints }}</span>
        </button>
      @else
        <button type="button" class="{{ $hintButton }}"
                hx-post="{{ route('student.dates.session.answer', $session) }}"
                hx-vals='{"reveal": 1}'
                hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
          Показать ответ
        </button>
      @endif
    </div>
  </form>
@else
  {{-- Ответ показывают сами клетки: верно — зелёная волна, неверно —
       цифры ученика (data-typed) переворачиваются в правильные. --}}
  <div id="term-a-result" data-correct="{{ $result['correct'] ? 1 : 0 }}" data-revealed="{{ $result['revealed'] ? 1 : 0 }}"
       class="flex flex-wrap items-center gap-1 sm:gap-1.5">
    <span class="sr-only">{{ $result['correct'] ? 'Верно' : 'Правильный ответ' }}: {{ $item->yearsLabel() }}</span>
    @foreach($revealed as $i => $digit)
      @if($splitAt !== null && $i === $splitAt)
        {!! $dash !!}
      @endif
      <span class="{{ $cellClass }} {{ $result['correct'] ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-zinc-100 border-zinc-300 text-zinc-700' }}"
            data-typed="{{ $typedCells[$i] ?? '' }}" aria-hidden="true">{{ $digit }}</span>
    @endforeach
    @if($result['correct'])
      <x-icon id="term-a-check" name="check-circle" class="ml-1 w-7 h-7 text-emerald-500" aria-hidden="true" />
    @endif
  </div>

  @if($item->date_display !== $item->yearsLabel())
    <div class="term-chips mt-3 flex flex-wrap gap-1.5">
      <span class="rounded-full border border-zinc-200 bg-gray-50 px-2.5 py-0.5 text-xs text-zinc-600">{{ $item->date_display }}</span>
    </div>
  @endif

  @if(!$result['correct'])
    @include('student.dates.partials.next-button', ['label' => 'Далее'])
  @else
    @include('student.terms.partials.next-bar')
  @endif
@endif
