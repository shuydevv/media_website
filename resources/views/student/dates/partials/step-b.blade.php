{{-- resources/views/student/dates/partials/step-b.blade.php
     Квиз по фактам — как student/terms/partials/step-b (там подробные
     комментарии к разметке и анимациям): слоты фактов по порядку, к
     активному — четыре варианта. Клиент отправляет номер варианта. --}}
@php
  $slot = $current['slot'];
  $done = $current['done'];
  $reveal = $current['reveal'];
  $justFilled = $flash === 'slot' ? $slot - 1 : ($done && $flash === 'correct' ? count($facts) - 1 : null);
@endphp

@include('student.dates.partials.item-head', ['item' => $item])

<ol class="mt-4 space-y-2">
  @foreach($facts as $i => $fact)
    @php
      $filled = $done || $i < $slot;
      $active = !$done && $i === $slot;
    @endphp
    <li @if($i === $justFilled) data-just-filled @endif @if($active) data-active-slot @endif
        class="flex gap-2.5 items-start rounded-xl border px-3 py-2.5 text-sm sm:text-base
               {{ $filled ? 'border-emerald-100 bg-emerald-50 text-zinc-800' : ($active ? 'border-blue-300 bg-blue-50 text-blue-900' : 'border-dashed border-gray-200 bg-gray-50 text-zinc-300') }}">
      @if($filled)
        <x-icon name="check-circle" class="shrink-0 w-5 h-5 mt-0.5 text-emerald-600" />
      @else
        <span class="shrink-0 w-5 text-center sans-medium">{{ $i + 1 }}</span>
      @endif
      <span class="min-w-0">
        @if($filled)
          {{ $fact->text }}
        @elseif($active)
          {{ $reveal !== null ? $fact->text : 'Какой факт подходит?' }}
        @else
          &nbsp;
        @endif
      </span>
    </li>
  @endforeach
</ol>

@if($done)
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
                hx-post="{{ route('student.dates.session.choose', $session) }}"
                hx-vals='{"choice": {{ $i }}}'
                hx-target="#terms-app" hx-swap="innerHTML"
                hx-disabled-elt=".term-option">
          {{ $text }}
        </button>
      @endif
    @endforeach
  </div>

  @if($reveal !== null)
    @include('student.dates.partials.next-button', ['label' => 'Далее'])
  @endif

  <details class="mt-5 text-right">
    <summary class="inline-block cursor-pointer text-xs text-zinc-400 hover:text-zinc-600 list-none">Сообщить об ошибке</summary>
    <form class="mt-2 text-left" method="POST" action="{{ route('student.dates.session.report', $session) }}"
          hx-post="{{ route('student.dates.session.report', $session) }}" hx-swap="none"
          hx-on::after-request="if (event.detail.successful) this.closest('details').removeAttribute('open')">
      @csrf
      <textarea name="comment" rows="2" maxlength="500" placeholder="Что не так? Можно не писать — вопрос уже прикреплён."
                class="w-full border border-zinc-300 rounded-xl px-3 py-2 text-sm"></textarea>
      <x-ui.button type="submit" variant="outline" size="sm" class="mt-2 text-sm">Отправить</x-ui.button>
    </form>
  </details>
@endif
