{{-- Подпись к портрету: автор и лицензия (CC BY / BY-SA требуют указать). --}}
@php $meta = $person->image_meta ?? []; @endphp
@if($person->image_path && (!empty($meta['author']) || !empty($meta['license'])))
  <div class="prs-credit">
    @if(!empty($meta['source']))<a href="{{ $meta['source'] }}" target="_blank" rel="noopener nofollow">Портрет</a>@else Портрет @endif:
    {{ \Illuminate\Support\Str::limit(trim(($meta['author'] ?? '').(!empty($meta['author']) && !empty($meta['license']) ? ', ' : '').($meta['license'] ?? '')), 80) }}
  </div>
@endif
