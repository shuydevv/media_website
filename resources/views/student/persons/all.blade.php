{{-- resources/views/student/persons/all.blade.php
     «Все личности» — справочник карточками, по образцу превью базы
     (data_personalities/preview.html): портрет, период, имя, чем известен,
     кто это, связи. Разделы — уровни, внутри — по периодам и алфавиту.
     Поиск по имени и фильтр по периоду — на странице, без запросов.
     Портреты — копии 256 px (Person::cardUrl), грузятся лениво. --}}
@extends('layouts.main')

@section('title', 'Все личности — Школа Полтавского')

@section('back_url', route('student.persons.index'))

@section('content')
@include('student.persons.partials.styles')
<style>
  .pra { max-width: 72rem; margin: 0 auto; padding: 24px 16px 120px; }
  .pra-h1 { font-size: 26px; font-weight: 600; color: #0F1E29; margin: 0; }
  .pra-sub { margin-top: 6px; font-size: 14px; color: #657B8B; }

  /* Поиск и периоды — липкая полоска сверху. */
  .pra-bar { position: sticky; top: 0; z-index: 10; margin: 16px -16px 0; padding: 10px 16px; background: rgba(246, 250, 253, .92); -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px); }
  .pra-search { width: 100%; max-width: 22rem; height: 40px; padding: 0 14px; border: 0; border-radius: 12px; background: #fff; box-shadow: 0 1px 2px rgba(11, 102, 162, .05), 0 4px 16px rgba(11, 102, 162, .06); font-size: 15px; color: #0F1E29; }
  .pra-search:focus { outline: 2px solid #93C5FD; }
  .pra-chips { display: flex; gap: 6px; margin-top: 10px; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
  .pra-chips::-webkit-scrollbar { display: none; }
  .pra-chips { padding: 2px 2px 6px; margin-left: -2px; margin-right: -2px; }
  .pra-chip { flex: none; padding: 7px 14px; border: 0; border-radius: 99px; background: #fff; box-shadow: 0 1px 2px rgba(11, 102, 162, .06), 0 2px 8px rgba(11, 102, 162, .06); font-size: 13px; font-weight: 500; color: #485C6A; cursor: pointer; white-space: nowrap; transition: background-color .2s, color .2s; }
  @media (hover: hover) { .pra-chip:hover { background: #F3F7FA; } }
  .pra-chip.is-on { background: #DBEAFE; color: #1D4ED8; box-shadow: none; }

  .pra-level { margin-top: 28px; }
  .pra-level h2 { display: flex; align-items: baseline; gap: 8px; margin: 0 0 12px; font-size: 19px; font-weight: 500; color: #0F1E29; }
  .pra-level h2 span { font-size: 14px; font-weight: 500; color: #94A9B8; }
  .pra-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
  @media (min-width: 640px) { .pra-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; } }

  .pra-card { display: flex; flex-direction: column; padding: 8px 8px 12px; border-radius: 18px; background: #fff; box-shadow: 0 1px 2px rgba(11, 102, 162, .05), 0 4px 16px rgba(11, 102, 162, .06); }
  .pra-pic { position: relative; }
  .pra-card .prs-portrait, .pra-card .prs-ph { width: 100%; height: auto; aspect-ratio: 1 / 1; font-size: 40px; box-shadow: none; }
  .pra-done { position: absolute; left: 8px; top: 8px; padding: 2px 9px; border-radius: 99px; background: rgba(255, 255, 255, .92); font-size: 12px; font-weight: 500; color: #047857; }
  .pra-body { padding: 10px 4px 0; }
  .pra-era { font-size: 12px; font-weight: 500; color: #94A9B8; }
  .pra-name { margin-top: 2px; font-size: 15px; line-height: 1.25; font-weight: 500; color: #0F1E29; }
  .pra-assoc { margin-top: 4px; font-size: 13px; line-height: 1.35; font-weight: 500; color: #1D4ED8; }
  .pra-desc { margin-top: 8px; font-size: 13px; line-height: 1.4; color: #485C6A; }
  .pra-rel { margin-top: 6px; font-size: 12px; line-height: 1.4; color: #657B8B; }
  .pra-card[hidden], .pra-level[hidden], .pra-empty[hidden] { display: none; }
  .pra-empty { margin-top: 32px; text-align: center; font-size: 15px; color: #657B8B; }
  @media (max-width: 639px) { .pra-name { font-size: 14px; } .pra-desc, .pra-rel { display: none; } }
</style>

<div class="pra" id="pra">
  <x-ui.page-header title="Все личности" />
  <div class="pra-sub">{{ $total }} личностей из заданий ЕГЭ: кто это и чем известен. Изученные отмечены.</div>

  <div class="pra-bar">
    <input type="search" class="pra-search" id="pra-search" placeholder="Найти по имени или событию" autocomplete="off" aria-label="Поиск">
    <div class="pra-chips" role="group" aria-label="Период">
      <button type="button" class="pra-chip is-on" data-era="">Все периоды</button>
      @foreach($usedEras as $era)
        <button type="button" class="pra-chip" data-era="{{ $era }}">{{ $eras[$era] ?? 'Период '.$era }}</button>
      @endforeach
    </div>
  </div>

  @foreach(\App\Models\Person::LEVELS as $level => $label)
    @php $people = $levels->get($level, collect()); @endphp
    @continue($people->isEmpty())
    <section class="pra-level" data-level>
      <h2>Уровень {{ $level }} · {{ $label }} <span>{{ $people->count() }}</span></h2>
      <div class="pra-grid">
        @foreach($people as $person)
          <article class="pra-card" data-era="{{ $person->era }}"
                   data-text="{{ mb_strtolower(str_replace('ё', 'е', $person->name.' '.$person->association.' '.$person->description)) }}">
            <div class="pra-pic">
              @if($person->cardUrl())
                <img src="{{ $person->cardUrl() }}" alt="" class="prs-portrait" loading="lazy" decoding="async">
              @else
                <div class="prs-ph" aria-hidden="true">{{ $person->initials() }}</div>
              @endif
              @if($progress->has($person->id))
                <span class="pra-done">✓ Изучено</span>
              @endif
            </div>
            <div class="pra-body">
              <div class="pra-era">{{ $eras[$person->era] ?? '' }}</div>
              <div class="pra-name">{{ $person->name }}</div>
              <div class="pra-assoc">{{ $person->association }}</div>
              <div class="pra-desc">{{ $person->description }}</div>
              @if($person->related_text)
                <div class="pra-rel">🔗 {{ $person->related_text }}</div>
              @endif
            </div>
          </article>
        @endforeach
      </div>
    </section>
  @endforeach
  <div class="pra-empty" id="pra-empty" hidden>Никого не нашли. Попробуй другое слово.</div>
</div>

<script>
(function () {
  var root = document.getElementById('pra');
  var search = document.getElementById('pra-search');
  var chips = root.querySelectorAll('.pra-chip');
  var cards = root.querySelectorAll('.pra-card');
  var sections = root.querySelectorAll('[data-level]');
  var empty = document.getElementById('pra-empty');
  var era = '';

  function apply() {
    var q = search.value.trim().toLowerCase().replace(/ё/g, 'е');
    var shown = 0;
    cards.forEach(function (c) {
      var ok = (!era || c.dataset.era === era) && (!q || c.dataset.text.indexOf(q) >= 0);
      c.hidden = !ok;
      if (ok) shown++;
    });
    sections.forEach(function (s) {
      var count = s.querySelectorAll('.pra-card:not([hidden])').length;
      s.hidden = count === 0;
      s.querySelector('h2 span').textContent = count;
    });
    empty.hidden = shown > 0;
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      era = chip.dataset.era;
      chips.forEach(function (c) { c.classList.toggle('is-on', c === chip); });
      apply();
    });
  });
  search.addEventListener('input', apply);
})();
</script>
@endsection
