{{-- resources/views/student/terms/partials/step-result.blade.php
     Итог сессии. Три уровня сверху вниз:
       1) итог — рыба с репликой, «N из M с первой попытки», кружки по
          терминам (зелёный — сразу верно, жёлтый — со второго раза), корм;
       2) детали — термины, которые стоит перечитать, карточками с фото;
       3) что дальше — ближайшее повторение и кнопки.
     На широком экране (lg) — две колонки: итог слева, детали и «что
     дальше» справа. На телефоне — одна колонка, кнопки закреплены внизу
     экрана (на месте полоски прогресса; меню платформы здесь скрыто).

     Крючки для скрипта в session.blade.php: #term-result (data-corm) —
     пицца летит к рыбе и та ест, за 3 корма конфетти; [data-result-dot] —
     кружки появляются по одному. У свободной тренировки корма нет. --}}
@php
  $isFree = $session->kind === \App\Models\TermSession::KIND_FREE;
  $corm = (int) $session->corm_awarded;
  $termWord = ['термин', 'термина', 'терминов'];

  $next = null;
  if ($dueTomorrow > 0) {
      $next = 'Завтра ждут повторения: '.\App\Support\Text::plural($dueTomorrow, $termWord);
  } elseif ($nextReview) {
      $next = 'Следующее повторение — '.$nextReview['date']->translatedFormat('j F')
          .' · '.\App\Support\Text::plural($nextReview['count'], $termWord);
  }
@endphp

<div id="term-result" data-corm="{{ $isFree ? 0 : $corm }}"
     class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-8 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-10">

  {{-- 1. Итог (на широком экране — по центру своей колонки по высоте) --}}
  <div class="min-w-0 lg:flex lg:flex-col lg:justify-center">
    <div class="flex items-center gap-4">
      <div class="relative shrink-0 w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-blue-50 overflow-hidden">
        <img id="term-result-mascot" src="{{ $mascot['default'] }}"
             data-default-src="{{ $mascot['default'] }}" data-eating-src="{{ $mascot['eating'] }}" data-happy-src="{{ $mascot['correct'] }}"
             alt="" class="w-full h-full object-contain">
      </div>
      {{-- Реплика рыбы — облачком с хвостиком в её сторону, мягкой заливкой
           без обводки (в рамке она читалась как кнопка). --}}
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
            +{{ $corm }} <img src="{{ asset('img/pizza.svg') }}" alt="корм" class="w-5 h-5">
          @else
            без корма
          @endif
        </div>
      @endunless
    </div>

    <div class="mt-4 flex flex-wrap gap-2" aria-label="Результат по терминам">
      @foreach($dots as $dot)
        <span data-result-dot title="{{ $dot['title'] }} — {{ $dot['ok'] ? 'с первой попытки' : 'со второго раза' }}"
              class="w-4 h-4 rounded-full {{ $dot['ok'] ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
      @endforeach
    </div>

    @unless($isFree)
      <div class="text-xs text-zinc-400 mt-3">Всего корма: {{ $cormBalance }}</div>
    @endunless
  </div>

  {{-- 2–3. Детали и что дальше --}}
  <div class="min-w-0 mt-8 lg:mt-0 flex flex-col">
    @if($failedTerms->isNotEmpty())
      <div class="sans-medium text-sm text-zinc-900">Стоит перечитать</div>
      {{-- Подпись — только там, где это правда: свободная тренировка
           список «Стоит перечитать» на главной не меняет. --}}
      @unless($isFree)
        <div class="text-xs text-zinc-500 mt-0.5">Останутся в этом списке на главной, пока не ответишь верно на повторении.</div>
      @endunless

      {{-- Телефон — лента карточек, листается пальцем; широкий экран — список. --}}
      <div class="mt-3 -mx-5 px-5 sm:-mx-8 sm:px-8 lg:mx-0 lg:px-0 flex gap-3 overflow-x-auto snap-x lg:flex-col lg:overflow-visible">
        @foreach($failedTerms as $failed)
          <a href="{{ route('student.terms.show', $failed) }}"
             class="snap-start shrink-0 w-40 lg:w-auto rounded-xl border border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm transition overflow-hidden
                    lg:flex lg:items-center lg:gap-3">
            @if($failed->image_path)
              <img src="{{ asset('storage/'.$failed->image_path) }}" alt="" loading="lazy"
                   class="w-full h-24 lg:w-20 lg:h-14 object-cover bg-gray-100 shrink-0">
            @else
              <div class="w-full h-24 lg:w-20 lg:h-14 bg-gray-100 shrink-0"></div>
            @endif
            <div class="px-3 py-2 lg:px-0 lg:pr-3 min-w-0">
              <div class="text-sm text-zinc-900 leading-snug line-clamp-2">{{ $failed->title }}</div>
              @if($failed->qualifier)
                <div class="text-xs text-zinc-500 truncate">{{ $failed->qualifier }}</div>
              @endif
            </div>
          </a>
        @endforeach
      </div>
    @endif

    <div class="{{ $failedTerms->isNotEmpty() ? 'mt-8' : '' }} lg:mt-auto lg:pt-8">
      @if($next)
        <div class="flex items-center gap-2 text-sm text-zinc-600">
          <x-icon name="calendar" class="w-4 h-4 text-zinc-400 shrink-0" />
          {{ $next }}
        </div>
      @endif

      {{-- Кнопки. На телефоне — закреплены внизу экрана, поэтому ниже место под них. --}}
      <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 backdrop-blur px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]
                  lg:static lg:border-0 lg:bg-transparent lg:backdrop-blur-none lg:p-0 lg:mt-4">
        <div class="max-w-3xl mx-auto lg:max-w-none flex flex-col gap-2 lg:flex-row lg:items-center">
          <x-ui.button href="{{ route('student.terms.index') }}" variant="accent" block class="text-sm sm:text-base whitespace-nowrap lg:w-auto lg:px-8">К тренажёру</x-ui.button>
          @if($secondary)
            <form method="POST" action="{{ route('student.terms.start', 'free') }}" class="lg:inline">
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
