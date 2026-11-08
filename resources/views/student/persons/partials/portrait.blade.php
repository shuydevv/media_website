{{-- Портрет личности. $size — сторона в px; $mystery — вместо портрета «?»
     (ввод фамилии до ответа: лицо было бы подсказкой). id с префиксом
     term-photo- и hx-preserve — как у фото терминов: пока на экране тот же
     портрет, htmx его не пересоздаёт и он не мигает при подмене шага.
     До 64 px — миниатюра (Person::thumbUrl), чтобы карта из 360 лиц была лёгкой. --}}
@php
  $size = $size ?? 96;
  $src = $size <= 64 ? $person->thumbUrl() : $person->portraitUrl();
@endphp
@if($mystery ?? false)
  <div class="prs-ph is-mystery" style="--s: {{ $size }}px" aria-hidden="true">?</div>
@elseif($src)
  <img id="term-photo-p{{ $person->id }}-{{ $size }}" hx-preserve="true" src="{{ $src }}" alt=""
       class="prs-portrait {{ $class ?? '' }}" style="--s: {{ $size }}px" loading="lazy" decoding="async">
@else
  <div class="prs-ph {{ $class ?? '' }}" style="--s: {{ $size }}px" aria-hidden="true">{{ $person->initials() }}</div>
@endif
