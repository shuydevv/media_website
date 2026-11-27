{{-- Чат трансляции (public/js/live-chat.js). $config —
     App\Service\LiveChat\LiveChatService::clientConfig(). Стили и скрипт
     подключаются один раз на страницу, даже если панелей две. --}}
@once
  @push('meta')
    <link rel="stylesheet" href="{{ asset('css/live-chat.css') }}?v={{ filemtime(public_path('css/live-chat.css')) }}">
  @endpush
  @push('page-scripts')
    <script src="{{ asset('js/live-chat.js') }}?v={{ filemtime(public_path('js/live-chat.js')) }}" defer></script>
  @endpush
@endonce
<div class="lc-host"><div data-live-chat="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></div></div>
