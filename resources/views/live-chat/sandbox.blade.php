@extends('layouts.main')

@section('title', 'Песочница чата трансляций')

@section('content')
<style>
  .sb-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 14px; color: #3f3f46; }
  .sb-bar a, .sb-bar button { padding: 6px 12px; border-radius: 10px; background: #fff; border: 1px solid #e4e4e7; color: #3f3f46; font: inherit; cursor: pointer; text-decoration: none; }
  .sb-bar .is-on { background: #dbeafe; border-color: #bfdbfe; color: #1e3a8a; font-weight: 500; }
  .sb-live { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; border-radius: 12px;
             background: radial-gradient(circle at 30% 30%, #1e3a8a, #0f172a 70%); color: #e0e7ff; overflow: hidden; }
  .sb-live-top { position: absolute; top: 14px; left: 14px; right: 14px; display: flex; justify-content: space-between; font-size: 14px; }
  .sb-dot { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; background: #dc2626; color: #fff; font-weight: 600; }
  .sb-dot::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #fff; animation: sb-blink 1.2s infinite; }
  @keyframes sb-blink { 50% { opacity: .2; } }
  .sb-viewers { padding: 4px 10px; border-radius: 8px; background: rgba(255,255,255,.12); }
  .sb-title { font-size: clamp(18px, 2.4vw, 28px); font-weight: 600; text-align: center; padding: 0 20px; }
  .sb-sub { font-size: 14px; opacity: .7; text-align: center; padding: 0 20px; }
</style>

<div class="max-w-6xl mx-auto md:px-6 px-4 pt-6">
  <h1 class="sans-medium text-xl md:text-3xl mt-4 mb-3 text-zinc-900">Песочница чата трансляций</h1>
  <p class="text-sm md:text-base text-zinc-600 mb-4 max-w-3xl">
    «Ученики» пишут по сценарию урока и реагируют на ваши сообщения. Всё происходит только в этом
    браузере: в базу ничего не пишется, настоящие ученики ничего не видят. Обновите страницу —
    урок начнётся заново.
  </p>
  <div class="sb-bar mb-4">
    <span>Смотреть как:</span>
    <a href="{{ request()->fullUrlWithQuery(['as' => null]) }}" class="{{ $asStudent ? '' : 'is-on' }}">преподаватель</a>
    <a href="{{ request()->fullUrlWithQuery(['as' => 'student']) }}" class="{{ $asStudent ? 'is-on' : '' }}">ученик</a>
    <span class="ml-2">Скорость:</span>
    <button type="button" data-speed="1" class="is-on">×1</button>
    <button type="button" data-speed="2">×2</button>
    <button type="button" data-speed="4">×4</button>
    <a href="{{ request()->fullUrl() }}" class="ml-2">Начать заново</a>
  </div>
</div>

<div class="lc-layout px-3 md:px-4 mb-10">
  <div>
    <div class="border-4 rounded-2xl border-blue-200" style="position: relative; padding-top: 56.25%; width: 100%">
      @if ($video)
        <iframe src="https://kinescope.io/embed/{{ $video }}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media;" frameborder="0" allowfullscreen
                style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe>
      @else
        <div class="sb-live">
          <div class="sb-live-top"><span class="sb-dot">ЭФИР <span id="sb-timer">00:00</span></span>@unless ($asStudent)<span class="sb-viewers" title="Видит только команда">👁 <span id="sb-viewers">47</span></span>@endunless</div>
          <div class="sb-title">Типы обществ. Задание 6</div>
          <div class="sb-sub">Здесь будет видео трансляции. Чтобы подставить запись — добавьте к адресу ?video=код из Kinescope</div>
        </div>
      @endif
    </div>
  </div>
  <div>@include('live-chat.panel', ['config' => $config])</div>
</div>

{{-- До live-chat.js: подменяет сервер чата (fetch на /__sandbox/…) и запускает ботов. --}}
<script>window.LC_SANDBOX = @json($sandbox);</script>
<script src="{{ asset('js/live-chat-sandbox.js') }}?v={{ filemtime(public_path('js/live-chat-sandbox.js')) }}"></script>
<script>
  (function () {
    const t0 = Date.now();
    const timer = document.getElementById('sb-timer');
    const viewers = document.getElementById('sb-viewers');
    setInterval(() => {
      const s = Math.floor((Date.now() - t0) / 1000 * (window.LC_SANDBOX_SPEED || 1)) + 754;
      if (timer) timer.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
      if (viewers && Math.random() < .15) viewers.textContent = 44 + Math.floor(Math.random() * 9);
    }, 1000);
    document.querySelectorAll('[data-speed]').forEach((b) => b.addEventListener('click', () => {
      window.LC_SANDBOX_SPEED = Number(b.dataset.speed);
      document.querySelectorAll('[data-speed]').forEach((x) => x.classList.toggle('is-on', x === b));
    }));
  })();
</script>
@endsection
