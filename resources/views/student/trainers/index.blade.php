{{-- resources/views/student/trainers/index.blade.php
     «Качалка» — локация, как в игре: залы историков и обществоведов
     (переключаются вкладками), в зале — снаряды. Открытые снаряды —
     тренажёры ученика (клик — в тренажёр), остальные — «в разработке».
     Залы, снаряды и их расстановка — config/trainers.php, данные —
     App\Service\Trainers\TrainerGym.

     ПК (от 768px): слева сцена зала, поверх неё вверху — вкладки залов
     (слева) и корм (справа); справа колонка — карточка тренера и
     «Упражнения на сегодня».
     Телефон: карточка тренера, раскрытые «Упражнения на сегодня», вкладки
     на всю ширину, мини-локация зала (та же сцена, своя раскладка mx/my,
     снаряды мельче; подсказка снаряда — плашкой под локацией, а не поверх).

     Части: partials/coach (тренер), partials/hall-tabs (вкладки),
     partials/hall-scene (сцена), partials/quests (упражнения на сегодня).
     Картинки снарядов и фоны залов пока заглушки — пути кладутся в
     config/trainers.php ('image', 'background'). Стили — здесь же, обычным
     CSS (.gym-*), чтобы не пересобирать Tailwind. --}}
@extends('layouts.main')

@section('title', 'Качалка — Школа Полтавского')
@section('back_url', route('student.dashboard'))

@php
  $badgeColor = fn (?string $situation) => match ($situation) {
      \App\Service\Trainers\Trainer::OVERDUE => '#C2410C',
      \App\Service\Trainers\Trainer::UNFINISHED => '#B45309',
      default => '#2563EB',
  };
@endphp

@section('content')
<style>
  /* Ширина и поля — как у дашборда (max-w-6xl mx-auto px-4 py-6). */
  .gym { --gym-ink: #1D2E3A; --gym-muted: #485C6A; color: var(--gym-ink); max-width: 72rem; margin: 0 auto; padding: 24px 16px; box-sizing: border-box; }
  .gym [hidden] { display: none !important; }

  .gym-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
  .gym-title { margin: 0; font-size: 26px; font-weight: 600; line-height: 1.15; }
  .gym-chips { display: flex; gap: 8px; flex-wrap: wrap; }
  .gym-chip { display: inline-flex; align-items: center; gap: 8px; background: #fff; border-radius: 999px; padding: 6px 14px 6px 8px; font-size: 14px; box-shadow: 0 2px 10px rgba(37,99,235,.08); }
  .gym-chip img { width: 22px; height: 22px; }
  /* Полоса поверх сцены (ПК): вкладки залов и корм. Сама полоса клики
     пропускает — под ней снаряды; выше замка закрытого зала. */
  .gym-aside-title { margin: 0; padding-top: 14px; min-height: 40px; display: flex; align-items: center; }
  .gym-scene-bar { position: absolute; top: 14px; left: 14px; right: 14px; z-index: 130; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; pointer-events: none; }
  .gym-scene-bar > * { pointer-events: auto; box-shadow: 0 4px 14px rgba(15,30,41,.18); }
  .gym-count { min-width: 24px; height: 24px; padding: 0 7px; box-sizing: border-box; border-radius: 999px; color: #fff; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
  .gym-done { width: 22px; height: 22px; border-radius: 50%; background: #15803D; color: #fff; display: inline-flex; align-items: center; justify-content: center; }
  .gym-done svg { width: 13px; height: 13px; }

  /* Вкладки на всю ширину (телефон): x-ui.tabs внутри — inline-flex. */
  .gym-tabs-full > div { display: flex; width: 100%; box-sizing: border-box; }
  .gym-tabs-full [data-hall-tab] { flex: 1 1 0; justify-content: center; min-height: 44px; }

  /* Упражнения на сегодня */
  .gym-quests { background: #fff; border-radius: 20px; box-shadow: 0 4px 18px rgba(37,99,235,.10); }
  .gym-quests > summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 16px; min-height: 44px; box-sizing: border-box; font-size: 16px; font-weight: 600; }
  .gym-quests > summary::-webkit-details-marker { display: none; }
  .gym-quests > summary svg { width: 18px; height: 18px; color: #94A9B8; transition: transform .15s; }
  .gym-quests[open] > summary svg { transform: rotate(180deg); }
  .gym-quest-list { padding: 0 8px 8px; display: flex; flex-direction: column; gap: 2px; }
  .gym-quest { display: flex; align-items: center; gap: 12px; padding: 9px 8px; border-radius: 12px; text-decoration: none; color: inherit; }
  .gym-quest:hover { background: #F1F6F9; }
  .gym-quest-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .gym-quest-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
  .gym-quest-title { font-size: 15px; font-weight: 600; }
  .gym-quest-title span { font-weight: 400; color: #657B8B; }
  .gym-quest-note { font-size: 13px; color: var(--gym-muted); }
  .gym-quest.is-done .gym-quest-title { color: var(--gym-muted); }
  .gym-empty { padding: 4px 16px 16px; font-size: 14px; color: var(--gym-muted); }

  /* Карточка тренера */
  .gym-coach-card { background: #fff; border-radius: 20px; padding: 12px 14px 12px 8px; box-shadow: 0 4px 18px rgba(37,99,235,.10); display: flex; gap: 6px; align-items: center; }
  .gym-coach-pic { width: 64px; height: 64px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
  /* Тренер качалки — img/trainers/coach.webp (обрезан по фигуре, без полей). */
  .gym-coach-pic img { width: 64px; height: 64px; object-fit: contain; animation: gym-bob 3.2s ease-in-out infinite; }
  .gym-coach-bubble { flex: 1; min-width: 0; background: #EAF3FB; border-radius: 14px; padding: 10px 12px; font-size: 14px; line-height: 1.45; }
  .gym-coach-label { font-size: 11px; font-weight: 600; color: #485C6A; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 1px; }
  .gym-coach-link { color: #1D4ED8; font-weight: 600; text-decoration: none; }
  .gym-coach-link:hover { color: #1E3A8A; text-decoration: underline; }

  /* Сцена зала. isolation — слои снарядов (z-index) живут только внутри
     сцены и не перекрывают меню сайта. Без overflow: hidden — подсказки
     снарядов выходят за край; фон режет .gym-clip. */
  .gym-scene { position: relative; isolation: isolate; border-radius: 28px; background: #1D2E3A; box-shadow: 0 12px 36px rgba(15,30,41,.16); }
  .gym-clip { position: absolute; inset: 0; overflow: hidden; border-radius: inherit; }
  .gym-hall { position: absolute; inset: 0; border-radius: inherit; }
  /* Фон увеличен ради пола (решение автора) и привязан к низу и ширине
     сцены: пол всегда одного размера, а высота сцены решает, сколько видно
     стены. Сцена вытянута в высоту (ПК 7:5, телефон 10:11), чтобы стена
     не срезалась над постерами. Стык стены и пола — около 52% высоты. */
  .gym-bg { position: absolute; inset: -12px; background-image: var(--bg); background-size: 114% auto; background-position: 50% 100%; background-repeat: no-repeat; }
  .gym-bg.is-empty { background: #CBD8E1; }
  /* Снаряд: (x, y) — точка на полу под центром снаряда. */
  .gym-station { position: absolute; left: var(--x); top: var(--y); transform: translate(-50%, -100%) scale(var(--s, 1)); transform-origin: 50% 100%; width: calc(120px * var(--z, 1)); }
  .gym-station:hover, .gym-station:focus-within, .gym-station.tip-open { z-index: 110 !important; }
  .gym-hit { display: flex; flex-direction: column; align-items: center; gap: 8px; width: 100%; background: none; border: 0; padding: 0; font: inherit; color: inherit; text-decoration: none; cursor: pointer; }
  .gym-hit:focus-visible { outline: 3px solid #fff; outline-offset: 6px; border-radius: 18px; }
  .gym-tag { position: relative; display: inline-flex; align-items: center; gap: 8px; background: #fff; border-radius: 12px; padding: 3px 8px; box-shadow: 0 4px 12px rgba(15,30,41,.25); font-size: 12px; font-weight: 600; white-space: nowrap; border-bottom: 3px solid var(--accent); }
  .gym-tag-emoji { font-size: 13px; line-height: 1; margin-right: -2px; }
  .is-soon .gym-tag, .is-locked .gym-tag { border-bottom-color: #94A9B8; color: #485C6A; }
  .gym-tag .gym-count { min-width: 20px; height: 20px; padding: 0 6px; font-size: 11px; }
  .gym-tag .gym-done { width: 18px; height: 18px; }
  .gym-tag .gym-count { animation: gym-bob 1.6s ease-in-out infinite; }
  .gym-object { position: relative; width: calc(120px * var(--z, 1)); height: calc(120px * var(--z, 1)); transition: transform .18s ease; }
  /* Мягкая тень отделяет снаряд от фона. Белую обводку автор убрал. */
  .gym-object img { width: 100%; height: 100%; object-fit: contain; object-position: 50% 100%; filter: drop-shadow(0 8px 12px rgba(15,30,41,.35)); }
  .is-soon .gym-object img, .is-locked .gym-object img { filter: grayscale(1) opacity(.75); }
  .gym-hit:hover .gym-object, .gym-hit:focus-visible .gym-object, .gym-station.tip-open .gym-object { transform: translateY(-6px); }
  .gym-floor { position: absolute; left: -7%; right: -7%; bottom: -10%; height: 20%; border-radius: 50%; background: rgba(15,30,41,.35); filter: blur(4px); z-index: -1; }
  .gym-ring { position: absolute; left: -12%; right: -12%; bottom: -12%; height: 25%; border-radius: 50%; border: 3px solid var(--accent); box-shadow: 0 0 18px var(--accent); animation: gym-ring 1.8s ease-out infinite; z-index: -1; }

  /* Снаряд без картинки (в разработке) — полупрозрачный силуэт того же
     размера, что будет у готового: фон просвечивает, место на полу видно. */
  .gym-ph { width: 100%; height: 100%; box-sizing: border-box; border-radius: 22px; border: 2px dashed rgba(255,255,255,.75); background: rgba(255,255,255,.22); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); color: #485C6A; display: flex; align-items: center; justify-content: center; }
  .gym-ph svg { width: 44%; height: auto; max-height: 56px; }
  .gym-ph-emoji { font-size: calc(40px * var(--z, 1)); line-height: 1; opacity: .55; filter: grayscale(.4); }
  /* Пометка «в разработке» — неяркая, на самом снаряде: в табличку на
     телефоне она не помещалась. */
  .gym-soon { position: absolute; left: 50%; bottom: 8px; transform: translateX(-50%); font-size: 10px; font-weight: 600; letter-spacing: .06em; text-transform: lowercase; background: rgba(255,255,255,.8); color: #657B8B; padding: 1px 7px; border-radius: 999px; white-space: nowrap; pointer-events: none; }

  /* Подсказка снаряда */
  .gym-tip { position: absolute; left: 50%; bottom: calc(100% + 8px); width: 190px; margin-left: -95px; background: #fff; border-radius: 12px; padding: 8px 10px; box-shadow: 0 8px 22px rgba(15,30,41,.28); font-size: 12px; line-height: 1.4; opacity: 0; visibility: hidden; transform: translateY(6px); transition: opacity .15s, transform .15s, visibility .15s; z-index: 5; }
  .gym-station.tip-start .gym-tip { left: 0; margin-left: 0; }
  .gym-station.tip-end .gym-tip { left: auto; right: 0; margin-left: 0; }
  .gym-station:hover .gym-tip, .gym-station:focus-within .gym-tip, .gym-station.tip-open .gym-tip { opacity: 1; visibility: visible; transform: none; }
  .gym-tip-title { font-weight: 600; font-size: 13px; }
  .gym-tip-tagline { font-size: 11px; color: #657B8B; margin-bottom: 4px; }
  .gym-tip-cta { margin-top: 4px; font-weight: 600; color: #1D4ED8; }
  .gym-tip-soon { display: inline-block; margin-bottom: 4px; font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px; background: #FEF3C7; color: #92400E; }

  /* Закрытый зал */
  .gym-locked { position: absolute; inset: 0; z-index: 120; border-radius: inherit; background: rgba(15,30,41,.72); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; text-align: center; padding: 32px; }
  .gym-locked-icon { width: 72px; height: 72px; border-radius: 50%; background: rgba(255,255,255,.12); display: flex; align-items: center; justify-content: center; }
  .gym-locked-icon svg { width: 36px; height: 36px; }
  .gym-locked-title { font-size: 24px; font-weight: 600; }
  .gym-locked-note { font-size: 15px; color: #E1E9EF; max-width: 360px; line-height: 1.45; }

  /* ===== ПК: вкладки и зал слева, тренер и «Упражнения на сегодня» справа ===== */
  .gym-desk { display: none; }
  .gym-main { flex: 999 1 640px; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
  /* Сцена целиком влезает в экран: высота окна минус шапка сайта и
     заголовок страницы. */
  .gym-desk .gym-scene { aspect-ratio: 7 / 5; max-height: 760px; }
  .gym-aside { flex: 1 1 280px; max-width: 340px; min-width: 0; display: flex; flex-direction: column; gap: 16px; }

  /* ===== Телефон: мини-локация ===== */
  .gym-mobile { display: flex; flex-direction: column; gap: 12px; }
  .gym-mobile .gym-coach-pic, .gym-mobile .gym-coach-pic img { width: 56px; height: 56px; }
  /* overflow: hidden — на телефоне подсказки не всплывают над сценой
     (они в .gym-mini-info), так что ничто не выходит за её рамку. */
  .gym-mini { aspect-ratio: 10 / 11; border-radius: 20px; overflow: hidden; box-shadow: 0 8px 22px rgba(15,30,41,.14); }
  /* На телефоне — свой вертикальный фон (background_phone): стена низкая,
     пол — больше половины сцены. Срезается низ пола, стена видна целиком. */
  .gym-mini .gym-bg { background-image: var(--bg-phone, var(--bg)); background-size: cover; background-position: 50% 10%; }
  /* В мини-локации уменьшается только сам снаряд — подписи остаются
     читаемыми (масштаб всей станции сделал бы их мельче 8px). */
  .gym-mini .gym-station { left: var(--mx); top: var(--my); transform: translate(-50%, -100%); width: auto; }
  .gym-mini .gym-hit { gap: 4px; }
  .gym-mini .gym-object { width: calc(76px * var(--s, 1) * var(--mz, 1)); height: calc(76px * var(--s, 1) * var(--mz, 1)); }
  .gym-mini .gym-hit:hover .gym-object, .gym-mini .gym-station.tip-open .gym-object { transform: translateY(-3px); }
  .gym-mini .gym-tag { font-size: 10px; gap: 3px; padding: 1px 5px; border-radius: 8px; border-bottom-width: 2px; }
  .gym-mini .gym-tag-emoji { font-size: 10px; margin-right: 0; }
  .gym-mini .gym-count { min-width: 15px; height: 15px; padding: 0 4px; font-size: 9px; }
  .gym-mini .gym-done { width: 14px; height: 14px; }
  .gym-mini .gym-done svg { width: 10px; height: 10px; }
  .gym-mini .gym-ph { border-width: 1.5px; border-radius: 14px; }
  .gym-mini .gym-ph-emoji { font-size: calc(26px * var(--s, 1) * var(--mz, 1)); }
  .gym-mini .gym-soon { font-size: 8px; bottom: 4px; padding: 0 5px; }
  .gym-mini .gym-ring { border-width: 2px; box-shadow: 0 0 10px var(--accent); }
  /* Подсказка снаряда на телефоне — плашкой под локацией: поверх сцены
     она закрывала бы соседние снаряды. */
  .gym-mini .gym-tip { display: none; }
  .gym-mini .gym-station.tip-open .gym-tag { box-shadow: 0 0 0 3px #fff, 0 4px 12px rgba(15,30,41,.25); }
  .gym-mini-info { position: relative; background: #fff; border-radius: 16px; padding: 12px 44px 12px 14px; font-size: 14px; line-height: 1.45; box-shadow: 0 4px 14px rgba(37,99,235,.10); }
  .gym-mini-info .gym-tip-title { font-size: 15px; }
  .gym-mini-info .gym-tip-tagline { font-size: 12px; }
  .gym-mini-info .gym-tip-soon { font-size: 11px; }
  .gym-mini-close { position: absolute; top: 4px; right: 4px; width: 40px; height: 40px; border: 0; background: none; color: #657B8B; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 12px; }
  .gym-mini-close svg { width: 18px; height: 18px; }
  .gym-mini .gym-locked { padding: 20px; gap: 8px; }
  .gym-mini .gym-locked-icon { width: 48px; height: 48px; }
  .gym-mini .gym-locked-icon svg { width: 24px; height: 24px; }
  .gym-mini .gym-locked-title { font-size: 18px; }
  .gym-mini .gym-locked-note { font-size: 13px; }

  @keyframes gym-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
  @keyframes gym-ring { 0% { opacity: .9; transform: scale(.9); } 100% { opacity: 0; transform: scale(1.25); } }
  @media (prefers-reduced-motion: reduce) { .gym-coach-pic img, .gym-tag .gym-count, .gym-ring { animation: none; } }

  /* В самом конце — чтобы перебить display мобильной и ПК-версий выше. */
  @media (min-width: 768px) {
    .gym { padding: 24px 24px 40px; }
    .gym-title { font-size: 32px; }
    .gym-desk { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
    .gym-mobile { display: none; }
    .gym-head { display: none; }
  }
</style>

@php
  $icons = [
      'dumbbell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.5 6v12M3.5 9v6M17.5 6v12M20.5 9v6M6.5 12h11"/></svg>',
      'lock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
      'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>',
  ];
@endphp

<div class="gym" data-active="{{ $active }}">

  <div class="gym-head">
    <h1 class="gym-title">Качалка</h1>
    {{-- На ПК корм — в правом верхнем углу сцены (.gym-scene-bar). --}}
    <div class="gym-chips gym-head-corm">
      <span class="gym-chip">
        <img src="{{ auth()->user()->fish_food_icon_url }}" alt="">
        Корм <b style="font-weight:600">{{ $cormBalance }}</b>
      </span>
    </div>
  </div>

  {{-- ===== ПК ===== --}}
  <div class="gym-desk">
    <div class="gym-main">
      <div class="gym-scene">
        @include('student.trainers.partials.hall-scene')
        {{-- Поверх сцены: залы — слева вверху, корм — справа вверху. --}}
        <div class="gym-scene-bar">
          @include('student.trainers.partials.hall-tabs', ['labelKey' => 'short'])
          <span class="gym-chip">
            <img src="{{ auth()->user()->fish_food_icon_url }}" alt="">
            Корм <b style="font-weight:600">{{ $cormBalance }}</b>
          </span>
        </div>
      </div>
    </div>
    <aside class="gym-aside">
      {{-- Заголовок на ПК — здесь, в одном ряду с вкладками и кормом поверх
           сцены; шапка .gym-head на ПК скрыта. --}}
      <h1 class="gym-title gym-aside-title">Качалка</h1>
      @include('student.trainers.partials.coach')
      @include('student.trainers.partials.quests')
    </aside>
  </div>

  {{-- ===== Телефон ===== --}}
  <div class="gym-mobile">
    @include('student.trainers.partials.coach')
    @include('student.trainers.partials.quests')
    @include('student.trainers.partials.hall-tabs', ['labelKey' => 'short', 'full' => true])
    <div class="gym-scene gym-mini">
      @include('student.trainers.partials.hall-scene')
    </div>
    <div class="gym-mini-info" aria-live="polite" hidden>
      <div data-mini-info-body></div>
      <button type="button" class="gym-mini-close" data-mini-info-close aria-label="Закрыть">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
  </div>

</div>

<script>
(function () {
  var root = document.querySelector('.gym');
  if (!root) return;

  // Переключение залов: вкладки на ПК и на телефоне синхронны, зал — в ?hall=
  // (обновление страницы и «назад» из тренажёра вернут в тот же зал).
  // Анимация — GSAP из layouts/main (грузится с defer; к первому клику уже
  // есть). Без GSAP или при «уменьшить движение» залы просто меняются.
  var current = root.getAttribute('data-active');
  var order = Array.prototype.map.call(root.querySelectorAll('.gym-desk .gym-hall'), function (el) {
    return el.getAttribute('data-hall-panel');
  });
  var desktop = window.matchMedia('(min-width: 768px)');
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  var tl = null;

  function panel(scope, key) {
    return root.querySelector(scope + ' [data-hall-panel="' + key + '"]');
  }

  function setHall(key) {
    if (key === current) return;
    var prev = current;
    current = key;
    if (tl) { tl.progress(1).kill(); tl = null; }

    root.querySelectorAll('[data-hall-panel]').forEach(function (el) {
      el.hidden = el.getAttribute('data-hall-panel') !== key;
    });

    if (window.gsap && !reduced.matches) {
      var dir = order.indexOf(key) > order.indexOf(prev) ? 1 : -1;
      tl = desktop.matches
        ? animateDesktop(prev, key, dir)
        : animateMobile(prev, key);
    }

    // Классы активной/обычной вкладки — те же, что в x-ui.tab.
    var on = ['bg-white', 'text-zinc-900', 'shadow-sm', 'font-medium'];
    var off = ['text-zinc-500', 'hover:text-zinc-900'];
    root.querySelectorAll('[data-hall-tab]').forEach(function (el) {
      var active = el.getAttribute('data-hall-tab') === key;
      el.setAttribute('aria-selected', active ? 'true' : 'false');
      on.forEach(function (c) { el.classList.toggle(c, active); });
      off.forEach(function (c) { el.classList.toggle(c, !active); });
    });
    closeTips();
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('hall', key);
      history.replaceState(null, '', url);
    } catch (e) {}
  }
  root.querySelectorAll('[data-hall-tab]').forEach(function (el) {
    el.addEventListener('click', function () { setHall(el.getAttribute('data-hall-tab')); });
  });

  // ПК — «проходим» в соседний зал: камера панорамирует вбок (зал справа
  // по вкладкам — уезжаем влево), снаряды нового зала вырастают из пола
  // один за другим, в карточке тренера (она остаётся на месте) проявляется
  // новая реплика.
  function animateDesktop(prev, key, dir) {
    var scene = root.querySelector('.gym-desk .gym-scene');
    var from = panel('.gym-desk .gym-scene', prev);
    var to = panel('.gym-desk .gym-scene', key);
    var hits = to.querySelectorAll('.gym-hit');
    var coach = panel('.gym-desk .gym-coach-card', key);

    from.hidden = false; // уходящий зал виден, пока уезжает
    return gsap.timeline({
      defaults: { ease: 'power3.inOut' },
      onComplete: function () {
        from.hidden = true;
        gsap.set([scene, from, to, hits, coach], { clearProps: 'transform,transformOrigin,opacity,overflow' });
      }
    })
      .set(scene, { overflow: 'hidden' })
      .fromTo(from, { xPercent: 0, opacity: 1 }, { xPercent: -100 * dir, opacity: 0.4, duration: 0.7 }, 0)
      .fromTo(to, { xPercent: 100 * dir }, { xPercent: 0, duration: 0.7 }, 0)
      .from(hits, { scaleY: 0, scaleX: 0.6, opacity: 0, transformOrigin: '50% 100%', duration: 0.45, ease: 'back.out(2)', stagger: 0.08 }, 0.5)
      .from(coach, { y: 8, opacity: 0, duration: 0.35, ease: 'power2.out' }, 0.35);
  }

  // Телефон — «шагаем в дверь»: старый зал отдаляется и гаснет, новый
  // наплывает на нас, снаряды выпрыгивают из пола по очереди, в карточке
  // тренера (она на месте) проявляется новая реплика.
  function animateMobile(prev, key) {
    var scene = root.querySelector('.gym-mobile .gym-scene');
    var from = panel('.gym-mobile .gym-scene', prev);
    var to = panel('.gym-mobile .gym-scene', key);
    var hits = to.querySelectorAll('.gym-hit');
    var coach = panel('.gym-mobile .gym-coach-card', key);

    from.hidden = false;
    return gsap.timeline({
      onComplete: function () {
        from.hidden = true;
        gsap.set([scene, from, to, hits, coach], { clearProps: 'transform,transformOrigin,opacity,overflow,zIndex' });
        gsap.set(from.querySelectorAll('.gym-station'), { clearProps: 'opacity' });
      }
    })
      // Старый зал не гаснет и не уменьшается — новый проявляется поверх
      // него: иначе в середине перехода просвечивал тёмный фон сцены.
      .set(scene, { overflow: 'hidden' })
      .set(to, { zIndex: 2 })
      .to(from.querySelectorAll('.gym-station'), { opacity: 0, duration: 0.2, ease: 'power2.in' }, 0)
      .fromTo(to, { scale: 1.12, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.4, ease: 'power2.out' }, 0.1)
      .from(hits, { scale: 0, transformOrigin: '50% 100%', duration: 0.4, ease: 'back.out(2.2)', stagger: 0.07 }, 0.35)
      .from(coach, { y: 6, opacity: 0, duration: 0.3, ease: 'power2.out' }, 0.1);
  }

  // Снаряд «в разработке»: на ПК подсказка по наведению, а по клику —
  // закрепляется. На телефоне наведения нет: по тапу текст подсказки
  // показывается плашкой под мини-локацией, чтобы не закрывать снаряды.
  var info = root.querySelector('.gym-mini-info');
  var infoBody = info ? info.querySelector('[data-mini-info-body]') : null;

  function closeTips(except) {
    root.querySelectorAll('.gym-station.tip-open').forEach(function (el) {
      if (el !== except) {
        el.classList.remove('tip-open');
        var b = el.querySelector('[data-station-toggle]');
        if (b) b.setAttribute('aria-expanded', 'false');
      }
    });
    if (info && !except) info.hidden = true;
  }
  root.querySelectorAll('[data-station-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var station = btn.closest('.gym-station');
      closeTips(station);
      var open = station.classList.toggle('tip-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (info && station.closest('.gym-mini')) {
        if (open) infoBody.innerHTML = station.querySelector('.gym-tip').innerHTML;
        info.hidden = !open;
      }
    });
  });
  if (info) {
    info.addEventListener('click', function (e) { e.stopPropagation(); });
    info.querySelector('[data-mini-info-close]').addEventListener('click', function () { closeTips(); });
  }
  document.addEventListener('click', function () { closeTips(); });
})();
</script>
@endsection
