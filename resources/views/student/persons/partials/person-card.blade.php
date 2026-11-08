{{-- Полная карточка личности: портрет, период, имя, кто это, чем известен,
     связи. Одна и та же в знакомстве и в справочнике. --}}
<div class="prs-intro">
  <div class="prs-intro-pic">
    @include('student.persons.partials.portrait', ['person' => $person, 'size' => 148])
    @include('student.persons.partials.credit', ['person' => $person])
  </div>
  <div class="prs-head-text">
    <span class="prs-era">{{ $eras[$person->era] ?? '' }}</span>
    <div class="prs-name mt-1">{{ $person->name }}</div>
    <div class="prs-desc">{{ $person->description }}</div>
    <div class="prs-assoc">
      <span class="prs-assoc-t">Чем известен</span>
      <span class="prs-assoc-v">{{ $person->association }}</span>
    </div>
    @if($person->related_text)
      <div class="prs-related"><span aria-hidden="true">🔗</span><span>{{ $person->related_text }}</span></div>
    @endif
  </div>
</div>
