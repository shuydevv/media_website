{{-- Итог сессии тренажёра личностей — как student/dates/partials/step-result,
     те же крючки для общего скрипта (#term-result с data-corm — корм летит к
     маскоту, [data-result-dot] — кружки по одному). «Стоит повторить» —
     портреты с именами; отдельной строкой — счёт в задании «как в ЕГЭ». --}}
@php
  $isFree = $session->kind === \App\Models\PersonSession::KIND_FREE;
  $corm = (int) $session->corm_awarded;
  $termWord = ['личность', 'личности', 'личностей'];

  $next = null;
  if ($dueTomorrow > 0) {
      $next = 'Завтра ждут повторения: '.\App\Support\Text::plural($dueTomorrow, $termWord);
  } elseif ($nextReview) {
      $next = 'Следующее повторение — '.$nextReview['date']->translatedFormat('j F')
          .' · '.\App\Support\Text::plural($nextReview['count'], $termWord);
  }
@endphp

<div id="term-result" data-corm="{{ $isFree ? 0 : $corm }}"
     class="rounded-2xl border border-gray-200 bg-white card-surface p-5 sm:p-8 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-10">

  <div class="min-w-0 lg:flex lg:flex-col lg:justify-center">
    <div class="flex items-center gap-4">
      <div class="relative shrink-0 w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-blue-50 overflow-hidden">
        <img id="term-result-mascot" src="{{ $mascot['default'] }}"
             data-default-src="{{ $mascot['default'] }}" data-eating-src="{{ $mascot['eating'] }}" data-happy-src="{{ $mascot['correct'] }}"
             alt="" class="w-full h-full object-contain">
      </div>
      <div id="term-result-say" class="relative rounded-2xl px-4 py-2.5 text-sm sm:text-base sans-medium
                  {{ $resultSay['ok'] ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-100 text-zinc-800' }}">
        <span class="absolute -left-1.5 top-1/2 -translate-y-1/2 w-3 h-3 rotate-45 {{ $resultSay['ok'] ? 'bg-emerald-50' : 'bg-gray-100' }}" aria-hidden="true"></span>
        <span class="relative">{{ $resultSay['text'] }}</span>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap items-end gap-x-5 gap-y-3">
      <h2 class="sans-medium text-2xl sm:text-3xl text-zinc-900 leading-tight">
        {{ $okCount }} из {{ count($dots) }}
        <span class="block text-base sm:text-lg text-zinc-500 font-normal mt-0.5">с первой попытки</span>
      </h2>
      @unless($isFree)
        <div id="term-result-corm"
             class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 sans-medium text-base {{ $corm > 0 ? 'bg-amber-50 text-amber-800' : 'bg-gray-100 text-zinc-500' }}">
          @if($corm > 0)
            +{{ $corm }} <img src="{{ auth()->user()->fish_food_icon_url }}" alt="корм" class="w-5 h-5">
          @else
            без корма
          @endif
        </div>
      @endunless
    </div>

    <div class="mt-4 flex flex-wrap gap-2" aria-label="Результат по личностям">
      @foreach($dots as $dot)
        <span data-result-dot title="{{ $dot['title'] }} — {{ $dot['ok'] ? 'с первой попытки' : 'со второго раза' }}"
              class="w-4 h-4 rounded-full {{ $dot['ok'] ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
      @endforeach
    </div>

    @if($matchOf > 0)
      <div class="mt-4"><span class="prs-tag">🎓 Как в ЕГЭ: {{ $matchOk }} из {{ $matchOf }}</span></div>
    @endif

    @unless($isFree)
      <div class="text-xs text-zinc-400 mt-3">Всего корма: {{ $cormBalance }}</div>
    @endunless
  </div>

  <div class="min-w-0 mt-8 lg:mt-0 flex flex-col">
    @if($failedItems->isNotEmpty())
      <div class="sans-medium text-sm text-zinc-900">Стоит повторить</div>
      <div class="mt-3 space-y-2">
        @foreach($failedItems as $failed)
          <a href="{{ route('student.persons.show', $failed) }}"
             class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm transition px-3 py-2">
            @include('student.persons.partials.portrait', ['person' => $failed, 'size' => 40])
            <span class="min-w-0">
              <span class="block text-sm text-zinc-900 sans-medium leading-snug">{{ $failed->name }}</span>
              <span class="block text-xs text-zinc-500 leading-snug">{{ $failed->association }}</span>
            </span>
          </a>
        @endforeach
      </div>
    @endif

    <div class="{{ $failedItems->isNotEmpty() ? 'mt-8' : '' }} lg:mt-auto lg:pt-8">
      @if($next)
        <div class="flex items-center gap-2 text-sm text-zinc-600">
          <x-icon name="calendar" class="w-4 h-4 text-zinc-400 shrink-0" />
          {{ $next }}
        </div>
      @endif

      <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]
                  lg:static lg:border-0 lg:bg-transparent lg:p-0 lg:mt-4">
        <div class="max-w-3xl mx-auto lg:max-w-none flex flex-col gap-2 lg:flex-row lg:items-center">
          <x-ui.button href="{{ route('student.persons.index') }}" variant="game-main" block class="text-sm sm:text-base whitespace-nowrap lg:w-auto lg:px-8">К тренажёру</x-ui.button>
          @if($secondary)
            <form method="POST" action="{{ route('student.persons.start', 'free') }}" class="lg:inline">
              @csrf
              <button type="submit" class="w-full lg:w-auto px-4 py-2 text-sm sm:text-base text-blue-600 hover:underline whitespace-nowrap">{{ $secondary }}</button>
            </form>
          @endif
        </div>
      </div>
      <div class="h-28 lg:hidden" aria-hidden="true"></div>
    </div>
  </div>
</div>

@if($rankModal)
  @include('student.persons.partials.rank-modal', ['rank' => $rankModal])
@endif
