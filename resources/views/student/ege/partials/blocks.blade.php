{{-- Блоки. Часть 1 ($blocksInstead): либо номера, либо блоки — выбран
     блок, номера берутся все (решение автора 07.11.2026, заменило «номер +
     блок»). Часть 2: номер один, блок — уточнение, пусто — все блоки. --}}
@if($blocksInstead ?? false)
  <div class="eg-or">{{ $texts['or_blocks'] }}</div>
@else
  <div class="eg-sub">{{ $texts['pick_blocks'] }}</div>
@endif
<div class="eg-chips">
  @foreach($blocks as $key => $title)
    <label class="eg-chip">
      <input type="checkbox" name="blocks[]" value="{{ $key }}">
      <span aria-hidden="true">{{ $blockEmoji[$key] ?? '' }}</span>{{ $title }}
    </label>
  @endforeach
</div>

