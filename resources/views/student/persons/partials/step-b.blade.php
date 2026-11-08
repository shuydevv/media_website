{{-- «Чем известен?»: только портрет и имя (описание часто намекает на
     ответ — решение автора от 07.11.2026), выбрать ассоциацию из четырёх.
     Клиент отправляет номер варианта; какой верный, страница узнаёт только
     после ответа. Тогда под каждым вариантом подписано, чей он: ошибка тоже
     учит. Разметка вариантов — как в квизе дат (общий скрипт анимирует
     .term-option, [data-pulse], .term-option-faded). --}}
@php $done = $current['done']; @endphp

<div class="prs-head">
  @include('student.persons.partials.portrait', ['person' => $person, 'size' => 88])
  <div class="prs-head-text">
    <span class="prs-era">{{ $eras[$person->era] ?? '' }}</span>
    <div class="prs-name mt-1">{{ $person->name }}</div>
  </div>
</div>

<div class="prs-q">Чем известен?</div>

<div class="term-options space-y-2">
  @foreach($options as $i => $option)
    @if($answered)
      <div @if($i === $correctIndex) data-pulse @endif
           class="w-full text-left rounded-xl border px-4 py-3 text-sm sm:text-base
                  {{ $i === $correctIndex ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : ($i === $chosenIndex ? 'bg-amber-50 border-amber-300 text-amber-800' : ($done ? 'term-option-faded bg-white border-gray-200 text-zinc-300' : 'bg-white border-gray-200 text-zinc-500')) }}">
        {{ $option?->association }}
        @unless($done && $i !== $correctIndex)
          <span class="prs-owner {{ $i === $correctIndex ? 'is-right' : '' }}">{{ $option?->name }}</span>
        @endunless
      </div>
    @else
      <button type="button"
              class="term-option w-full text-left rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm sm:text-base text-zinc-900 hover:bg-zinc-50 active:bg-zinc-100 transition"
              hx-post="{{ route('student.persons.session.choose', $session) }}"
              hx-vals='{"choice": {{ $i }}}'
              hx-target="#terms-app" hx-swap="innerHTML"
              hx-disabled-elt=".term-option">
        {{ $option?->association }}
      </button>
    @endif
  @endforeach
</div>

@if($current['reveal'] !== null)
  @include('student.persons.partials.next-button', ['label' => 'Далее'])
@elseif($done)
  @include('student.terms.partials.next-bar')
@endif

@unless($done)
  @include('student.persons.partials.report')
@endunless
