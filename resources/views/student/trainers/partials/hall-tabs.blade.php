{{-- Переключатель залов «Качалки» — общий сегментный контрол кабинета
     (x-ui.tabs / x-ui.tab). Переключает JS в index.blade.php по
     data-hall-tab, он же меняет классы активной вкладки.
     $labelKey — какое название зала показать: 'short' («История»;
     сейчас и на ПК, и на телефоне) или 'tab' («Историки»); $full — вкладки на всю ширину поровну (телефон). --}}
<x-ui.tabs role="tablist" aria-label="Залы" :class="!empty($full) ? 'gym-tabs-full' : ''">
  @foreach($halls as $hall)
    <x-ui.tab role="tab" :active="$hall['key'] === $active" data-hall-tab="{{ $hall['key'] }}" aria-selected="{{ $hall['key'] === $active ? 'true' : 'false' }}">
      @if($hall['locked'])
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
      @endif
      {{ $hall[$labelKey] }}
      @if($hall['pending'] > 0)
        <span class="min-w-5 h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs leading-5 text-center font-semibold">{{ $hall['pending'] }}</span>
      @endif
    </x-ui.tab>
  @endforeach
</x-ui.tabs>
