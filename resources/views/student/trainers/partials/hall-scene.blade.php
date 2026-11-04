{{-- Сцена «Качалки»: по секции на зал (видна одна — активная), в зале —
     фон и снаряды в точках x/y (на телефоне — mx/my) из config/trainers.php. Одна и та же
     разметка на ПК (.gym-scene) и на телефоне (.gym-scene.gym-mini — там
     снаряды мельче, подписи читаемые). Ждёт $halls, $active, $icons,
     $badgeColor из index.blade.php. --}}
@foreach($halls as $hall)
  <section class="gym-hall" data-hall-panel="{{ $hall['key'] }}" aria-label="{{ $hall['title'] }}" style="--accent: {{ $hall['accent'] }}" @if($hall['key'] !== $active) hidden @endif>
    {{-- Фон обрезается своей рамкой, а не сценой: подсказки снарядов
         должны выходить за край сцены. --}}
    <div class="gym-clip">
      @if($hall['background'])
        <div class="gym-bg" style="--bg: url('{{ $hall['background'] }}'); --bg-phone: url('{{ $hall['background_phone'] ?? $hall['background'] }}'); {{ $hall['filter'] ? 'filter: '.$hall['filter'].';' : '' }}"></div>
      @else
        <div class="gym-bg is-empty"></div>
      @endif
    </div>

    @foreach($hall['stations'] as $s)
      {{-- У крайних снарядов подсказка прижимается к их краю, чтобы не
           уходить за экран. --}}
      @php
        $tipSide = $s['x'] < 22 ? 'tip-start' : ($s['x'] > 78 ? 'tip-end' : '');
      @endphp
      <div class="gym-station is-{{ $s['state'] }} {{ $tipSide }}" style="--x: {{ $s['x'] }}%; --y: {{ $s['y'] }}%; --mx: {{ $s['mx'] }}%; --my: {{ $s['my'] }}%; --s: {{ $s['scale'] }}; --z: {{ $s['size'] }}; --mz: {{ $s['msize'] }}; z-index: {{ (int) round($s['y']) }};">
        @if($s['state'] === 'open')
          <a href="{{ $s['url'] }}" class="gym-hit" aria-label="{{ $s['title'] }}: {{ $s['quest'] ?? $s['note'] }}">
        @else
          <button type="button" class="gym-hit" data-station-toggle aria-expanded="false">
        @endif
            <span class="gym-tag">
              @if($s['emoji'])<span class="gym-tag-emoji" aria-hidden="true">{{ $s['emoji'] }}</span>@endif{{ $s['title'] }}
              @if($s['badge'])
                <span class="gym-count" style="background: {{ $badgeColor($s['situation']) }}">{{ $s['badge'] }}</span>
              @elseif($s['state'] === 'open')
                <span class="gym-done" title="На сегодня всё">{!! $icons['check'] !!}</span>
              @endif
            </span>
            {{-- Снаряд в разработке занимает на полу столько же места, сколько
                 займёт готовый: так сразу видно, всем ли хватает места. Без
                 картинки — полупрозрачный силуэт со значком снаряда. --}}
            <span class="gym-object">
              @if($s['badge'])<span class="gym-ring"></span>@endif
              <span class="gym-floor"></span>
              @if($s['image'])
                <img src="{{ $s['image'] }}" alt="">
              @else
                <span class="gym-ph">@if($s['emoji'])<span class="gym-ph-emoji">{{ $s['emoji'] }}</span>@else{!! $icons['dumbbell'] !!}@endif</span>
              @endif
              @if($s['state'] === 'soon')<span class="gym-soon">скоро</span>@endif
            </span>
        @if($s['state'] === 'open')
          </a>
        @else
          </button>
        @endif
        <div class="gym-tip" role="tooltip">
          @if($s['state'] === 'soon')
            <span class="gym-tip-soon">В разработке</span>
          @endif
          <div class="gym-tip-title">{{ $s['title'] }}</div>
          @if($s['tagline'])<div class="gym-tip-tagline">{{ $s['tagline'] }}</div>@endif
          <div>
            @if($s['state'] === 'locked')
              Нет доступа. {{ $hall['locked_note'] }}
            @elseif($s['state'] === 'soon')
              {{ $s['about'] }}
            @else
              {{ $s['note'] }}
            @endif
          </div>
          @if($s['state'] === 'open')<div class="gym-tip-cta">{{ $s['cta'] }} →</div>@endif
        </div>
      </div>
    @endforeach

    @if($hall['locked'])
      <div class="gym-locked">
        <span class="gym-locked-icon">{!! $icons['lock'] !!}</span>
        <div class="gym-locked-title">{{ $hall['title'] }} закрыт</div>
        <div class="gym-locked-note">{{ $hall['locked_note'] }} Снаряды внутри уже ждут.</div>
      </div>
    @endif
  </section>
@endforeach
