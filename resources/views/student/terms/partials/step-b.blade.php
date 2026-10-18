{{-- resources/views/student/terms/partials/step-b.blade.php
     Способ Б: квиз по признакам. Слоты идут в порядке базы; для активного —
     четыре варианта. Клиент отправляет номер варианта, не id признака.

     Неверный клик останавливает квиз по термину: показываем верный вариант
     ($correctIndex приходит только в этот момент) и по «Далее» идём к
     следующему термину — оставшиеся признаки не спрашиваем, термин вернётся
     позже целиком. Выбранный неверный вариант — жёлтый, не красный.

     data-just-filled — слот, заполненный последним кликом: скрипт в
     session.blade.php «перелетает» в него выбранным вариантом, а рамка
     активного слота (data-active-slot) плавно съезжает с него на следующий. Обёртку
     карточки даёт region.blade.php. --}}
@php
  $slot = $current['slot'];
  $done = $current['done'];
  $reveal = $current['reveal'];
  $justFilled = $flash === 'slot' ? $slot - 1 : ($done && $flash === 'correct' ? count($features) - 1 : null);
@endphp

@include('student.terms.partials.photo', ['term' => $term])

<div class="mt-4">
  <div class="sans-medium text-xl sm:text-2xl text-zinc-900 break-words">{{ $term->title }}</div>
  @if($term->qualifier)
    <div class="text-sm text-zinc-500 mt-0.5">{{ $term->qualifier }}</div>
  @endif
</div>

<ol class="mt-4 space-y-2">
  @foreach($features as $i => $feature)
    @php
      $filled = $done || $i < $slot;
      $active = !$done && $i === $slot;
    @endphp
    <li @if($i === $justFilled) data-just-filled @endif @if($active) data-active-slot @endif
        class="flex gap-2.5 items-start rounded-xl border px-3 py-2.5 text-sm sm:text-base
               {{ $filled ? 'border-emerald-100 bg-emerald-50 text-zinc-800' : ($active ? 'border-blue-300 bg-blue-50 text-blue-900' : 'border-dashed border-gray-200 bg-gray-50 text-zinc-300') }}">
      {{-- Заполненный слот — зелёная плашка с иконкой, а не белая карточка
           с рамкой: иначе он выглядел так же, как варианты ответа под ним. --}}
      @if($filled)
        <x-icon name="check-circle" class="shrink-0 w-5 h-5 mt-0.5 text-emerald-600" />
      @else
        <span class="shrink-0 w-5 text-center sans-medium">{{ $i + 1 }}</span>
      @endif
      <span class="min-w-0">
        @if($filled)
          {{ $feature->text }}
        @elseif($active)
          {{ $reveal !== null ? $feature->text : 'Какой признак подходит?' }}
        @else
          &nbsp;
        @endif
      </span>
    </li>
  @endforeach
</ol>

@if($done)
  {{-- Квиз пройден: варианты последнего вопроса остаются на месте, но
       гаснут — ничего не исчезает и карточка не пустеет, пока не улетит сама
       (см. #term-auto-next в region.blade.php). На месте выбранного —
       пунктирная рамка: видно, что он улетел отсюда в слот. Текст в ней
       невидимый, но занимает место, чтобы рамка была той же высоты. --}}
  <div class="term-options-final mt-5 space-y-2" aria-hidden="true">
    @foreach($options as $i => $text)
      @if($i === $chosenIndex)
        <div class="w-full rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm sm:text-base"><span class="invisible">{{ $text }}</span></div>
      @else
        <div class="term-option-faded w-full text-left rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm sm:text-base text-zinc-300">{{ $text }}</div>
      @endif
    @endforeach
  </div>
  @include('student.terms.partials.next-bar')
@else
  {{-- .term-options — крючок для скрипта: когда квиз пройден и вариантов
       в разметке уже нет, он плавно растворяет их снимок. --}}
  <div class="term-options mt-5 space-y-2">
    @foreach($options as $i => $text)
      @if($reveal !== null)
        <div @if($i === $correctIndex) data-pulse @endif
             class="w-full text-left rounded-xl border px-4 py-3 text-sm sm:text-base
                    {{ $i === $correctIndex ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : ($i === $reveal['choice'] ? 'bg-amber-50 border-amber-300 text-amber-800' : 'bg-white border-gray-200 text-zinc-400') }}">
          {{ $text }}
        </div>
      @else
        <button type="button"
                class="term-option w-full text-left rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm sm:text-base text-zinc-900 hover:bg-zinc-50 active:bg-zinc-100 transition"
                hx-post="{{ route('student.terms.session.choose', $session) }}"
                hx-vals='{"choice": {{ $i }}}'
                hx-target="#terms-app" hx-swap="innerHTML"
                hx-disabled-elt=".term-option">
          {{ $text }}
        </button>
      @endif
    @endforeach
  </div>

  @if($reveal !== null)
    @include('student.terms.partials.next-button', ['label' => 'Далее'])
  @endif

  {{-- Репорт привязан к текущему слоту: сервер сам берёт из состояния
       сессии верный признак и показанные дистракторы. --}}
  <details class="mt-5 text-right">
    <summary class="inline-block cursor-pointer text-xs text-zinc-400 hover:text-zinc-600 list-none">Сообщить об ошибке</summary>
    <form class="mt-2 text-left" method="POST" action="{{ route('student.terms.session.report', $session) }}"
          hx-post="{{ route('student.terms.session.report', $session) }}" hx-swap="none"
          hx-on::after-request="if (event.detail.successful) this.closest('details').removeAttribute('open')">
      @csrf
      <textarea name="comment" rows="2" maxlength="500" placeholder="Что не так? Можно не писать — вопрос уже прикреплён."
                class="w-full border border-zinc-300 rounded-xl px-3 py-2 text-sm"></textarea>
      <x-ui.button type="submit" variant="outline" size="sm" class="mt-2 text-sm">Отправить</x-ui.button>
    </form>
  </details>
@endif
