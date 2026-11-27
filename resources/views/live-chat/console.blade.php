<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Пульт эфира{{ $lesson ? ' — ' . $lesson->title : '' }}</title>
  <link rel="stylesheet" href="{{ asset('css/live-chat.css') }}?v={{ filemtime(public_path('css/live-chat.css')) }}">
  <style>
    html, body { margin: 0; height: 100%; font-family: Golos, system-ui, -apple-system, 'Segoe UI', sans-serif; background: #fff; }
    .cs { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }
    .cs-bar { display: flex; align-items: center; gap: 10px; padding: 8px 12px; font-size: 13px; color: #52525b; border-bottom: 1px solid #e4e4e7; }
    .cs-title { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #18181b; font-weight: 500; }
    .cs-sig summary { cursor: pointer; color: #2563eb; list-style: none; }
    .cs-sig[open] summary { margin-bottom: 6px; }
    .cs-sig form { display: flex; gap: 6px; flex-wrap: wrap; position: absolute; right: 12px; z-index: 20; background: #fff; padding: 10px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,.16); }
    .cs-sig input { padding: 6px 8px; border: 1px solid #e4e4e7; border-radius: 8px; font: inherit; }
    .cs-sig button { padding: 6px 12px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font: inherit; cursor: pointer; }
    .cs-main { flex: 1; min-height: 0; }
    .cs-main .lc-host { height: 100%; min-height: 0; }
    .cs-main .lc { height: 100%; min-height: 0; }
    .cs-none { padding: 40px 20px; text-align: center; color: #71717a; }
  </style>
</head>
<body>
<div class="cs">
  <div class="cs-bar">
    <div class="cs-title">
      @if ($lesson)
        {{ $lesson->title }}@if ($lesson->courseSession) · {{ \Carbon\Carbon::parse($lesson->courseSession->date)->format('d.m') }} {{ substr((string) $lesson->courseSession->start_time, 0, 5) }}@endif
        @if ($auto) <span style="color:#a1a1aa">· выбран автоматически</span>@endif
      @else
        Пульт эфира
      @endif
    </div>
    <details class="cs-sig">
      <summary>Подпись: {{ auth()->user()->chat_name ?: auth()->user()->full_name }} · {{ auth()->user()->chat_badge ?: (auth()->user()->isAdmin() ? 'Команда' : 'Куратор') }} ✎</summary>
      <form method="POST" action="{{ route('live-chat.signature') }}">
        @csrf
        <input name="chat_name" maxlength="64" required placeholder="Имя в чате" value="{{ auth()->user()->chat_name ?: auth()->user()->full_name }}">
        <input name="chat_badge" maxlength="32" required placeholder="Плашка" value="{{ auth()->user()->chat_badge ?: (auth()->user()->isAdmin() ? 'Команда' : 'Куратор') }}">
        <button type="submit">Сохранить</button>
      </form>
    </details>
  </div>
  <div class="cs-main">
    @if ($config)
      <div class="lc-host"><div data-live-chat="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></div></div>
    @else
      <div class="cs-none">Ближайших уроков с трансляцией нет</div>
    @endif
  </div>
</div>
@if ($config)
  <script src="{{ asset('js/live-chat.js') }}?v={{ filemtime(public_path('js/live-chat.js')) }}"></script>
@endif
@if ($auto)
  {{-- Постоянный адрес в OBS: раз в 5 минут, пока чат не открыт, проверяем, не начался ли следующий урок. --}}
  <script>
    setInterval(function () {
      if (!document.querySelector('.lc-pill.is-open, .lc-pill.is-break')) location.reload();
    }, 300000);
  </script>
@endif
</body>
</html>
