{{-- Погон звания Адмирала Клешни, SVG без картинок. По мотивам погон ВМФ:
     чёрный погон, золото. $board — рисунок из config('persons.ranks.*.board'):
     ribbon — ленточка бескозырки (юнга), f — «Ф» (матрос), f1/f2 — «Ф» и
     лычки, michman — две звёздочки, lt — просвет и две малые звезды,
     cap1/cap2/cap3 — два просвета и 1/2/3 большие звезды. $size — высота в px. --}}
@php
  $board = (string) $board;
  $size = (int) ($size ?? 90);
  $gold = '#E8B931';
  $navy = '#1F2A44';
  $c = 26; // центр по ширине (viewBox 52×90)
  $star = function (float $cx, float $cy, float $r) {
      $pts = [];
      for ($i = 0; $i < 10; $i++) {
          $rad = $i % 2 ? $r * 0.42 : $r;
          $a = -M_PI / 2 + $i * M_PI / 5;
          $pts[] = round($cx + $rad * cos($a), 2).','.round($cy + $rad * sin($a), 2);
      }
      return implode(' ', $pts);
  };
@endphp
<svg viewBox="0 0 52 90" height="{{ $size }}" width="{{ round($size * 52 / 90) }}" aria-hidden="true" class="prs-insignia" style="display:block">
  @if($board === 'ribbon')
    {{-- Ленточка бескозырки: околыш и два хвоста «ласточкой». --}}
    <rect x="3" y="14" width="46" height="13" rx="2" fill="{{ $navy }}"/>
    <path d="M7 20.5h38" stroke="{{ $gold }}" stroke-width="1.4" stroke-dasharray="3 2"/>
    <path d="M13 27 L8 84 L14.5 77 L19.5 85 L24 27 Z" fill="{{ $navy }}"/>
    <path d="M28 27 L32.5 85 L37.5 77 L44 84 L39 27 Z" fill="{{ $navy }}"/>
    <path d="M11 62 L22.5 62 M29.5 62 L41 62" stroke="{{ $gold }}" stroke-width="1.8"/>
  @else
    <path d="M5 87 L5 15 L26 3 L47 15 L47 87 Z" fill="{{ $navy }}" stroke="{{ $navy }}" stroke-width="3" stroke-linejoin="round"/>
    <circle cx="{{ $c }}" cy="13" r="3.4" fill="{{ $gold }}"/>

    @if(in_array($board, ['f', 'f1', 'f2'], true))
      <text x="{{ $c }}" y="80" text-anchor="middle" font-family="sans-serif" font-size="15" font-weight="600" fill="{{ $gold }}">Ф</text>
    @endif
    @if($board === 'f1')
      <rect x="7" y="48" width="38" height="4.5" fill="{{ $gold }}"/>
    @elseif($board === 'f2')
      <rect x="7" y="42" width="38" height="4.5" fill="{{ $gold }}"/>
      <rect x="7" y="51" width="38" height="4.5" fill="{{ $gold }}"/>
    @elseif($board === 'michman')
      <polygon points="{{ $star($c, 42, 5.5) }}" fill="{{ $gold }}"/>
      <polygon points="{{ $star($c, 59, 5.5) }}" fill="{{ $gold }}"/>
    @elseif($board === 'lt')
      <rect x="24.5" y="20" width="3" height="68" fill="{{ $gold }}"/>
      <polygon points="{{ $star(15, 62, 5) }}" fill="{{ $gold }}"/>
      <polygon points="{{ $star(37, 62, 5) }}" fill="{{ $gold }}"/>
    @elseif(in_array($board, ['cap1', 'cap2', 'cap3'], true))
      <rect x="16" y="20" width="3" height="68" fill="{{ $gold }}"/>
      <rect x="33" y="20" width="3" height="68" fill="{{ $gold }}"/>
      @if($board === 'cap1')
        <polygon points="{{ $star($c, 60, 8) }}" fill="{{ $gold }}"/>
      @else
        <polygon points="{{ $star(17.5, 62, 7.5) }}" fill="{{ $gold }}" stroke="{{ $navy }}" stroke-width="0.8"/>
        <polygon points="{{ $star(34.5, 62, 7.5) }}" fill="{{ $gold }}" stroke="{{ $navy }}" stroke-width="0.8"/>
        @if($board === 'cap3')
          <polygon points="{{ $star($c, 43, 7.5) }}" fill="{{ $gold }}"/>
        @endif
      @endif
    @endif
  @endif
</svg>
