{{-- resources/views/student/terms/partials/step-a.blade.php
     Способ А: по признакам ввести название в квадратики. Правильного
     названия в странице нет — только число клеток ($cellCount) и буквы,
     открытые подсказкой ($revealed); проверяет сервер. После ответа
     $revealed — название целиком.

     Кнопки «Проверить» нет: ответ уходит сам, как только заполнена последняя
     клетка. Enter оставлен для коротких «других названий» (ВВП), которые
     клетки не заполняют. Ввод и анимации — в скрипте session.blade.php
     (ищет #term-a-form); обёртку карточки даёт region.blade.php.

     Неверный ответ показывается нейтральным серым, не красным — намеренно:
     красный на каждой ошибке демотивирует. --}}
@php
  $result = $current['result'];
  $answered = $result !== null;
  $hintButton = 'inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-800 hover:bg-zinc-50 transition';
@endphp

@include('student.terms.partials.photo', ['term' => $term, 'blurred' => !$answered])

<div class="sans-medium text-sm text-zinc-500 mt-4 mb-2">Что это за понятие?</div>
<ul class="space-y-2">
  @foreach($term->features as $feature)
    <li class="flex gap-2.5 text-sm sm:text-base text-zinc-800">
      <span class="shrink-0 mt-2 w-1.5 h-1.5 rounded-full bg-blue-500"></span>
      <span>{{ $feature->text }}</span>
    </li>
  @endforeach
</ul>

@if(!$answered)
  <form id="term-a-form" class="mt-6" method="POST" action="{{ route('student.terms.session.answer', $session) }}"
        hx-post="{{ route('student.terms.session.answer', $session) }}"
        hx-target="#terms-app" hx-swap="innerHTML">
    @csrf
    <input type="hidden" name="typed" id="term-a-typed" value="">

    {{-- Клетки — просто отображение; печатает ученик в прозрачное поле
         поверх них (так на телефоне тап по клеткам открывает клавиатуру).
         Поле — редактируемый блок, а не <input>: над клавиатурой Android
         Chrome показывает у <input> панель автозаполнения (пароли, карты,
         адреса), autocomplete="off" её не убирает. type="password" — хуже:
         Chrome предлагает пароли, а часть клавиатур переключается на
         латиницу. Текст читает скрипт в session.blade.php. --}}
    <div class="relative">
      <div id="term-a-cells" class="flex flex-wrap gap-1.5" data-locked="{{ count($revealed) }}">
        @for($i = 0; $i < $cellCount; $i++)
          <span class="term-cell w-8 h-10 sm:w-9 sm:h-11 rounded-lg border flex items-center justify-center sans-medium text-base sm:text-lg uppercase
                       {{ isset($revealed[$i]) ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-white border-zinc-300 text-zinc-900' }}">{{ $revealed[$i] ?? '' }}</span>
        @endfor
      </div>
      <div id="term-a-input" contenteditable="true" role="textbox" inputmode="text" enterkeyhint="done"
           autocapitalize="off" autocorrect="off" spellcheck="false" translate="no"
           aria-label="Название термина"
           class="absolute inset-0 w-full h-full opacity-0 cursor-text overflow-hidden whitespace-nowrap outline-none"
           style="font-size:16px; caret-color:transparent;"></div>
    </div>

    <div class="mt-5">
      @if($hintsLeft > 0)
        <button type="button" class="{{ $hintButton }}"
                hx-post="{{ route('student.terms.session.hint', $session) }}"
                hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
          <span class="w-5 h-5 rounded-md border border-zinc-400 flex items-center justify-center text-[11px] leading-none sans-medium" aria-hidden="true">А</span>
          Открыть букву
          <span class="text-zinc-400">{{ $hintsLeft }} из {{ $freeHints }}</span>
        </button>
      @else
        <button type="button" class="{{ $hintButton }}"
                hx-post="{{ route('student.terms.session.answer', $session) }}"
                hx-vals='{"reveal": 1}'
                hx-target="#terms-app" hx-swap="innerHTML" hx-disabled-elt="this">
          Показать ответ
        </button>
      @endif
    </div>
  </form>
@else
  {{-- Ответ показывают сами клетки, отдельной плашки «Верно / название»
       нет. Верно — зелёная волна и галочка, дальше экран уходит сам.
       Неверно — буквы ученика (data-typed) на глазах переворачиваются в
       правильные, клетки с промахом на секунду жёлтые (скрипт в
       session.blade.php); здесь уже итоговый вид, чтобы без скрипта экран
       был тем же. Реплику («Это «…»») говорит рыба. --}}
  <div id="term-a-result" data-correct="{{ $result['correct'] ? 1 : 0 }}" data-revealed="{{ $result['revealed'] ? 1 : 0 }}"
       class="mt-6 flex flex-wrap items-center gap-1.5">
    <span class="sr-only">{{ $result['correct'] ? 'Верно' : 'Правильный ответ' }}: {{ $term->title }}</span>
    @foreach($revealed as $i => $letter)
      <span class="term-cell w-8 h-10 sm:w-9 sm:h-11 rounded-lg border flex items-center justify-center sans-medium text-base sm:text-lg uppercase
                   {{ $result['correct'] ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-zinc-100 border-zinc-300 text-zinc-700' }}"
            data-typed="{{ $typedCells[$i] ?? '' }}" aria-hidden="true">{{ $letter }}</span>
    @endforeach
    @if($result['correct'])
      <x-icon id="term-a-check" name="check-circle" class="ml-1 w-7 h-7 text-emerald-500" aria-hidden="true" />
    @endif
  </div>

  @if($term->qualifier || !empty($term->other_names))
    <div class="term-chips mt-3 flex flex-wrap gap-1.5">
      @if($term->qualifier)
        <span class="rounded-full border border-zinc-200 bg-gray-50 px-2.5 py-0.5 text-xs text-zinc-600">{{ $term->qualifier }}</span>
      @endif
      @foreach($term->other_names ?? [] as $otherName)
        <span class="rounded-full border border-zinc-200 bg-gray-50 px-2.5 py-0.5 text-xs text-zinc-600">или {{ $otherName }}</span>
      @endforeach
    </div>
  @endif

  @if(!$result['correct'])
    @include('student.terms.partials.next-button', ['label' => 'Далее'])
  @else
    @include('student.terms.partials.next-bar')
  @endif
@endif
