{{-- resources/views/student/ege/round.blade.php
     Раунд первой части станции «Задания». Задания приходят в $boot (без
     ответов), ответ уходит POST'ом на answer и возвращает разбор; итог —
     в ответе на последнее задание. Вся отрисовка — скрипт ниже.

     Ответ — как на экзамене: строка с цифрами. В заданиях с вариантами
     цифры можно нажимать в списке, в соответствиях — выбрать цифру у
     каждой буквы. Enter — «Проверить», потом «Дальше». Анимация — GSAP,
     как в «Верных суждениях»; без GSAP экран работает без движения. --}}
@extends('layouts.main')

@section('title', 'Задания — Школа Полтавского')

@section('back_url', route('student.ege.index'))

@section('content')
<div id="eg-app" class="egr" data-boot="{{ json_encode($boot, JSON_UNESCAPED_UNICODE) }}">
  <div class="egr-top">
    <span class="eg-badge" id="egr-num"></span>
    <div class="egr-progress"><div class="egr-progress-bar" id="egr-bar"></div></div>
    <div class="egr-count" id="egr-count"></div>
  </div>
  {{-- Кеша — как рыба в «Терминах»: на широком экране стоит справа от
       карточки, вокруг — кольцо прогресса, под ним счёт, серия и реплика.
       На телефоне его здесь нет: он говорит в разборе и в тосте «Верно!». --}}
  <div class="egr-hud" id="egr-hud" aria-live="polite">
    <div class="egr-hud-pic">
      <svg viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="46" class="egr-hud-track"/><circle cx="50" cy="50" r="46" class="egr-hud-ring" id="egr-ring"/></svg>
      <div class="egr-hud-pic-bg"><img id="egr-mascot" src="{{ asset('img/diagnostic/squid-idle.webp') }}" alt="{{ config('task_trainer.mascot.name') }}"></div>
    </div>
    <div class="egr-hud-say" id="egr-hud-say" role="status"></div>
    <div class="egr-hud-count" id="egr-hud-count"></div>
    <div class="egr-hud-streak" id="egr-hud-streak"></div>
  </div>
  <div id="egr-stage"></div>
  <noscript><div class="eg-notice">Для тренажёра нужен JavaScript.</div></noscript>
</div>
@endsection

@push('page-scripts')
@include('student.ege.partials.styles')
<style>
  .egr { max-width: 680px; margin: 0 auto; padding: 16px 16px 40px; color: #0F1E29; }
  .egr-top { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  #egr-num { flex: none; padding: 4px 12px; font-size: 14px; font-weight: 600; color: #0F1E29; background: #fff; border: 1px solid #DCEAF3; }
  #egr-num:empty { display: none; }
  .egr-progress { flex: 1; height: 8px; border-radius: 99px; background: #E1E9EF; overflow: hidden; }
  .egr-progress-bar { height: 100%; width: 0; background: #2563eb; border-radius: 99px; }
  .egr-count { font-size: 14px; color: #657B8B; white-space: nowrap; font-variant-numeric: tabular-nums; }

  .egr-card { position: relative; background: #fff; border: 1px solid #DCEAF3; border-radius: 20px; padding: 18px; box-shadow: 0 1px 2px rgba(11, 102, 162, .05), 0 4px 16px rgba(11, 102, 162, .06); transition: border-color .3s, background-color .3s; }
  /* Результат показывают квадратики ответа, а не рамка карточки:
     зелёный — верная цифра, красный — лишняя/неверная, жёлтый — «частично»
     (пока ответ не раскрыт), пунктир — пропущенная верная цифра. */
  .egr-input-row .pin-box { transition: border-color .25s ease, box-shadow .25s ease, background-color .25s ease, color .25s ease; }
  .egr-input-row .pin-box.is-ok { border-color: #16a34a; background: #f0fdf4; color: #15803d; }
  .egr-input-row .pin-box.is-bad { border-color: #dc2626; background: #fef2f2; color: #b91c1c; }
  .egr-input-row .pin-box.is-half { border-color: #f59e0b; background: #fffbeb; color: #b45309; }
  .egr-input-row .pin-box.is-miss { border-style: dashed; border-color: #16a34a; background: #fff; color: #15803d; }
  .egr-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }

  .egr-opts { list-style: none; margin: 14px 0 0; padding: 0; display: grid; gap: 6px; }
  .egr-opt { display: flex; gap: 10px; align-items: flex-start; width: 100%; padding: 10px 12px; border-radius: 12px; border: 1.5px solid #DCEAF3; background: #fff; text-align: left; font-size: 15px; line-height: 1.45; color: #0F1E29; cursor: pointer; }
  .egr-opt-n { flex: none; width: 26px; height: 26px; border-radius: 8px; background: #F1F6F9; display: grid; place-items: center; font-weight: 600; font-size: 14px; color: #485C6A; }
  .egr-opt.is-on { border-color: #93c5fd; background: #f8fbff; }
  .egr-opt.is-on .egr-opt-n { background: #bfdbfe; color: #1e40af; }
  .egr-opt:disabled { cursor: default; }
  .egr-opt.r-hit { border-color: #86efac; background: #f0fdf4; }
  .egr-opt.r-hit .egr-opt-n { background: #16a34a; color: #fff; }
  .egr-opt.r-extra { border-color: #fca5a5; background: #fef2f2; }
  .egr-opt.r-extra .egr-opt-n { background: #dc2626; color: #fff; }
  .egr-opt.r-miss { border-style: dashed; border-color: #16a34a; }
  .egr-opt.r-miss .egr-opt-n { background: #dcfce7; color: #15803d; }
  @media (hover: hover) { .egr-opt:not(:disabled):hover { border-color: #60a5fa; } }

  .egr-match { margin-top: 14px; display: grid; gap: 14px; }
  @media (min-width: 640px) { .egr-match { grid-template-columns: 1.4fr 1fr; } }
  .egr-col-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94A9B8; margin-bottom: 6px; }
  .egr-left { display: grid; gap: 8px; }
  /* Строка соответствия: текст слева, маленькие цифры выбора — справа. */
  .egr-lrow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 10px 12px; border-radius: 12px; border: 1.5px solid #DCEAF3; }
  .egr-lrow-text { flex: 1 1 0; min-width: 0; font-size: 15px; line-height: 1.4; }
  .egr-lrow-text b { margin-right: 6px; }
  .egr-picks { flex: none; max-width: 50%; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }
  .egr-pick { min-width: 28px; height: 28px; padding: 0 4px; border-radius: 8px; font-size: 13px; border: 1.5px solid #DCEAF3; background: #fff; font-weight: 600; color: #485C6A; cursor: pointer; }
  .egr-pick.is-on { background: #bfdbfe; border-color: #93c5fd; color: #1e40af; }
  .egr-pick:disabled { cursor: default; }
  .egr-lrow.r-ok { border-color: #86efac; background: #f0fdf4; }
  .egr-lrow.r-bad { border-color: #fca5a5; background: #fef2f2; }
  .egr-lrow-fix { flex-basis: 100%; margin-top: 0; font-size: 13px; font-weight: 600; color: #15803d; }
  .egr-right { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
  .egr-right li { display: flex; gap: 8px; font-size: 15px; line-height: 1.4; }
  .egr-right li span:first-child { flex: none; font-weight: 600; color: #485C6A; }

  /* Ответ — клеточки, как в бланке. Поверх лежит прозрачный input:
     он ловит набор (и цифровую клавиатуру на телефоне), клеточки — рисуют.
     Сами клеточки — классы домашки (.pin-field/.pin-boxes/.pin-box из
     app.css), чтобы выглядели один в один. */
  /* Клеточки и кнопка — в один ряд, если влезают (иначе кнопка ниже).
     Ширина поля — ровно под --n клеточек по размерам из app.css (42/12, на телефоне 32/10). */
  .egr-input-row { margin-top: 18px; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; }
  .egr-input-row .pin-field { flex: none; width: calc(var(--n) * 42px + (var(--n) - 1) * 12px); }
  /* На телефоне — как в домашке: клеточки того же размера, а их столько,
     сколько влезает в ряд (лишние просто пустые, см. fillRow в скрипте). */
  @media (max-width: 640px) { .egr-input-row .pin-field { width: 100%; } }
  .egr-cell { display: flex; flex-direction: column; align-items: center; gap: 4px; }
  .egr-cell .pin-box { width: 100%; }
  .egr-cell-l { font-size: 12px; font-weight: 600; color: #94A9B8; line-height: 1; }
  .egr-pin-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; border: 0; padding: 0; font-size: 16px; color: transparent; caret-color: transparent; background: transparent; cursor: text; }
  .egr-pin-input:disabled { cursor: default; }
  .egr-check { min-height: 44px; padding: 0 28px; }
  .egr-check:disabled { background: #bfdbfe; color: #fff; }
  @media (max-width: 640px) { .egr-check { width: 100%; } }
  /* Подсказка про порядок и «Enter — проверить» — под карточкой. */
  .egr-foot { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 10px; }
  .egr-foot > span { padding: 4px 12px; border-radius: 99px; background: rgba(255, 255, 255, .75); font-size: 13px; color: #485C6A; }
  .egr-foot .egr-keys { margin: 0; text-align: left; }
  @media (hover: none) { .egr-foot .egr-keys { display: none; } }

  .egr-verdict { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 16px; font-size: 17px; font-weight: 600; }
  .egr-verdict.right { color: #15803d; }
  .egr-verdict.partial { color: #b45309; }
  .egr-verdict small { font-size: 13px; font-weight: 600; opacity: .75; }
  .egr-verdict.wrong { color: #b91c1c; }
  .egr-points { padding: 2px 10px; border-radius: 99px; font-size: 14px; background: currentColor; }
  .egr-points span { color: #fff; }
  .egr-key { margin-top: 6px; font-size: 15px; color: #344856; }
  .egr-key b { letter-spacing: .1em; }
  .egr-explain { margin-top: 12px; padding: 12px 14px; border-radius: 14px; background: #F8FAFC; border: 1px solid #E2EEF6; font-size: 15px; line-height: 1.55; color: #344856; white-space: pre-line; }
  .egr-explain-t { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94A9B8; margin-bottom: 4px; white-space: normal; }
  .egr-hud { display: none; }
  .egr-qnum { display: none; }
  @media (min-width: 1180px) {
    /* Карточка — 648px по центру, Кеша — сразу справа от неё. */
    .egr-hud { position: fixed; z-index: 20; top: 112px; left: calc(50% + 352px); width: 170px; display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
    /* ПК: прогресс — кольцом у Кеши, номер — в строке вопроса; верхняя строка не нужна. */
    .egr-top { display: none; }
    .egr-qnum { display: inline-flex; align-items: center; margin-right: 8px; padding: 1px 10px; border-radius: 99px; background: #F1F6F9; color: #657B8B; font-size: 14px; font-weight: 600; vertical-align: 1px; white-space: nowrap; }
    .egr-shark { display: none !important; }
  }
  .egr-hud-pic { position: relative; width: 160px; height: 160px; }
  .egr-hud-pic svg { position: absolute; inset: 0; width: 100%; height: 100%; transform: rotate(-90deg); }
  .egr-hud-track { fill: none; stroke: #E2EEF6; stroke-width: 5; }
  .egr-hud-ring { fill: none; stroke: #2563eb; stroke-width: 5; stroke-linecap: round; stroke-dasharray: 289.03; stroke-dashoffset: 289.03; }
  .egr-hud-pic-bg { position: absolute; inset: 7%; border-radius: 50%; background: #eff6ff; overflow: hidden; }
  .egr-hud-pic-bg img { width: 100%; height: 100%; object-fit: contain; display: block; }
  .egr-hud-count { font-size: 14px; font-weight: 600; color: #485C6A; font-variant-numeric: tabular-nums; }
  .egr-hud-streak { padding: 2px 10px; border-radius: 99px; background: #fff7ed; color: #c2410c; font-size: 13px; font-weight: 600; }
  .egr-hud-streak:empty, .egr-hud-say:empty { display: none; }
  .egr-hud-say.is-ok { border-color: #a7f3d0 !important; color: #065f46 !important; }
  .egr-hud-say { width: 100%; box-sizing: border-box; padding: 10px 14px; font-weight: 500; border-radius: 16px; background: #fff; border: 1px solid #DCEAF3; box-shadow: 0 1px 2px rgba(11, 102, 162, .05), 0 4px 16px rgba(11, 102, 162, .06); font-size: 14px; line-height: 1.35; color: #344856; }
  .egr-shark { display: flex; align-items: flex-end; gap: 10px; margin-top: 14px; }
  /* Телефон: Кеша крупнее — справа от карточки его тут нет, он говорит в разборе, тосте и итоге. */
  @media (max-width: 640px) {
    .egr-shark img { width: 96px !important; height: 96px !important; }
    .egr-toast { width: 240px; }
    .egr-toast-pic { width: 128px !important; height: 128px !important; }
    .egr-who img { width: 128px !important; height: 128px !important; }
  }
  .egr-shark img { width: 56px; height: 56px; object-fit: contain; flex: none; }
  .egr-bubble { background: #fff; border: 1px solid #DCEAF3; border-radius: 16px 16px 16px 4px; padding: 8px 12px; font-size: 14px; color: #344856; }
  .egr-next { width: 100%; margin-top: 14px; }
  /* Вторая попытка: выбор «перерешать / разбор». */
  .egr-choice { display: grid; gap: 8px; margin-top: 14px; }
  @media (min-width: 480px) { .egr-choice { grid-template-columns: 1fr 1fr; } }
  .egr-choice-note { margin-top: 8px; font-size: 13px; color: #657B8B; }
  /* «Ошибка в задании?» — как в «Терминах»: раскрывается форма. */
  .egr-report { margin-top: 14px; text-align: right; }
  .egr-report summary { display: inline-block; list-style: none; cursor: pointer; font-size: 13px; color: #94A9B8; }
  .egr-report summary::-webkit-details-marker { display: none; }
  @media (hover: hover) { .egr-report summary:hover { color: #485C6A; } }
  .egr-report form { margin-top: 8px; text-align: left; }
  .egr-report textarea { width: 100%; min-height: 64px; padding: 8px 12px; border-radius: 12px; border: 1px solid #DCEAF3; font: inherit; font-size: 14px; resize: vertical; }
  .egr-report textarea:focus { outline: none; border-color: #2563eb; }
  .egr-report .eg-btn { margin-top: 8px; min-height: 38px; font-size: 14px; }
  .egr-report-done { font-size: 13px; color: #15803d; }
  /* Тост «Верно!» — как в ДЗ: Кеша в кружке, реплика снизу. */
  .egr-toasts { position: fixed; top: 16px; left: 50%; transform: translateX(-50%); z-index: 80; display: flex; flex-direction: column; align-items: center; gap: 8px; pointer-events: none; }
  .egr-toast { width: 220px; padding: 14px 16px; border-radius: 20px; background: #fff; box-shadow: 0 6px 24px rgba(15, 30, 41, .12); display: flex; flex-direction: column; align-items: center; gap: 8px; }
  .egr-toast-pic { width: 96px; height: 96px; border-radius: 50%; background: #F1F6F9; display: grid; place-items: center; overflow: hidden; }
  .egr-toast-pic img { width: 100%; height: 100%; object-fit: contain; }
  .egr-toast-text { width: 100%; padding: 6px 10px; border-radius: 12px; background: #f0fdf4; color: #15803d; font-size: 15px; font-weight: 600; text-align: center; }
  /* Комбо — как в «Верных суждениях»: оранжево-красная плашка с бликом. */
  .egr-combo { position: relative; overflow: hidden; display: table; margin: 6px auto 0; padding: 3px 11px; border-radius: 99px; background: linear-gradient(90deg, #f97316, #ef4444); color: #fff; font-size: 13px; font-weight: 600; }
  .egr-combo span { position: relative; }
  .egr-combo-shine { position: absolute; top: 0; left: 0; width: 40%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .55), transparent); transform: translateX(-120%) skewX(-20deg); pointer-events: none; }
  .egr-keys { margin-top: 8px; text-align: center; font-size: 12px; color: #94A9B8; }
  .egr-keys kbd { display: inline-block; min-width: 22px; padding: 1px 6px; border: 1px solid #DCEAF3; border-bottom-width: 2px; border-radius: 6px; background: #fff; font-family: inherit; color: #485C6A; }
  @media (hover: none) { .egr-keys { display: none; } }

  /* Итог — «стекло», как в «Верных суждениях». */
  .egr-result { text-align: center; padding: 18px 16px 20px; border-radius: 20px;
    background: linear-gradient(160deg, rgba(255, 255, 255, .3), rgba(255, 255, 255, .12));
    border: 1px solid rgba(255, 255, 255, .7);
    box-shadow: 0 8px 28px rgba(37, 99, 235, .08), inset 0 1px 0 rgba(255, 255, 255, .9), inset 0 0 0 1px rgba(228, 228, 231, .6);
    -webkit-backdrop-filter: blur(2px) saturate(1.15); backdrop-filter: blur(2px) saturate(1.15); }
  @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) { .egr-result { background: rgba(255, 255, 255, .85); } }
  .egr-who { display: flex; align-items: flex-end; justify-content: center; gap: 10px; }
  .egr-who img { width: 88px; height: 88px; object-fit: contain; flex: none; }
  .egr-who .egr-bubble { max-width: 320px; text-align: left; margin-bottom: 14px; }
  .egr-tag { display: inline-block; margin-bottom: 10px; padding: 3px 12px; border-radius: 99px; background: #F1F6F9; color: #485C6A; font-size: 13px; font-weight: 600; }
  .egr-ring { position: relative; width: 168px; height: 168px; margin: 10px auto 4px; }
  .egr-ring svg { width: 100%; height: 100%; display: block; }
  .egr-ring-track { fill: none; stroke: #E1E9EF; stroke-width: 8; }
  .egr-ring-fill { fill: none; stroke-width: 8; stroke-linecap: round; }
  .egr-ring-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .egr-score { display: flex; align-items: baseline; gap: 3px; line-height: 1; font-weight: 600; font-variant-numeric: tabular-nums; }
  .egr-score-n { font-size: 40px; letter-spacing: -0.02em; }
  .egr-score-of { font-size: 18px; color: #94A9B8; font-weight: 600; }
  .egr-ring-label { font-size: 12px; color: #657B8B; margin-top: 2px; }
  .egr-corm-plus { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; padding: 4px 12px; border-radius: 99px; background: #fff7ed; color: #c2410c; font-weight: 600; }
  .egr-corm-plus img { width: 20px; height: 20px; }
  .egr-corm-note { margin-top: 6px; font-size: 14px; color: #657B8B; }
  .egr-section { background: #fff; border: 1px solid #DCEAF3; border-radius: 16px; padding: 14px 16px; margin-top: 12px; }
  .egr-section h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #94A9B8; margin: 0 0 8px; font-weight: 600; }
  .egr-section-note { margin: -4px 0 4px; font-size: 13px; color: #657B8B; }
  .egr-row { display: flex; align-items: center; gap: 10px; font-size: 14px; margin: 8px 0; }
  .egr-row-n { flex: none; width: 28px; height: 28px; border-radius: 8px; background: #F1F6F9; display: grid; place-items: center; font-weight: 600; color: #344856; }
  .egr-row-title { flex: none; max-width: 40%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .egr-meter { flex: 1; min-width: 40px; height: 8px; border-radius: 99px; background: #E1E9EF; overflow: hidden; }
  .egr-meter i { display: block; height: 100%; border-radius: 99px; }
  .egr-row-count { flex: none; width: 40px; text-align: right; color: #485C6A; font-variant-numeric: tabular-nums; }
  .egr-mistake { padding: 10px 0; border-top: 1px solid #E2EEF6; text-align: left; }
  .egr-mistake:first-of-type { border-top: 0; }
  .egr-mistake .q { font-size: 15px; line-height: 1.45; white-space: pre-line; }
  .egr-mistake-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
  .egr-mistake-pts { font-size: 12px; font-weight: 600; padding: 1px 8px; border-radius: 99px; background: #fef2f2; color: #b91c1c; }
  .egr-mistake-pts.is-partial { background: #fffbeb; color: #b45309; }
  /* Ответ ученика цифрами: зелёная — верно, жёлтая — лишняя, пунктир — пропущена. */
  .egr-digits { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .egr-dg { min-width: 28px; height: 28px; padding: 0 6px; border-radius: 8px; display: inline-grid; place-items: center; font-size: 14px; font-weight: 600; border: 1.5px solid transparent; }
  .egr-dg.hit { background: #16a34a; color: #fff; }
  .egr-dg.extra { background: #f59e0b; color: #fff; }
  .egr-dg.miss { border: 1.5px dashed #16a34a; color: #15803d; background: #fff; }
  .egr-mcell { display: flex; flex-direction: column; align-items: center; gap: 2px; }
  .egr-mcell-l { font-size: 11px; font-weight: 600; color: #94A9B8; }
  .egr-mcell-fix { font-size: 12px; font-weight: 600; color: #15803d; line-height: 1; }
  .egr-mistake details { margin-top: 4px; font-size: 14px; color: #485C6A; }
  .egr-mistake summary { cursor: pointer; color: #2563eb; font-size: 13px; }
  .egr-mistake details div { white-space: pre-line; margin-top: 4px; }
  .egr-actions { display: grid; gap: 10px; margin-top: 16px; }
  @media (min-width: 640px) { .egr-actions { grid-auto-flow: column; grid-auto-columns: 1fr; } }
  .egr-actions form { display: block; }
  .egr-actions a { display: flex; }
  .egr-actions .eg-btn { width: 100%; }

  /* Телефон: без «карточки в карточке» — у внешней карточки меньше отступы,
     варианты без рамок, через тонкие линии; ответ и «Проверить» прижаты к
     низу экрана, пока видна карточка. Меню платформы здесь скрыто совсем
     (как в «Терминах»): кнопка «Раскрыть меню» в углу легла бы на панель. */
  @media (max-width: 640px) {
    #student-bottom-nav, #student-bottom-nav-reveal { display: none !important; }
    body.student-portal { padding-bottom: 0 !important; }
    .egr { padding: 12px 10px 24px; }
    .egr-top { margin-bottom: 10px; }
    .egr-card { padding: 14px 12px 0; border-radius: 18px; }
    .egr-card > :last-child { margin-bottom: 12px; }
    .egr-opts { gap: 0; margin-top: 10px; }
    .egr-opts li + li .egr-opt { border-top-color: #E2EEF6; }
    .egr-opt { gap: 8px; padding: 9px 6px; border: 1.5px solid transparent; border-radius: 10px; }
    .egr-opt-n { width: 24px; height: 24px; font-size: 13px; }
    .egr-opt.is-on { border-color: transparent; }
    .egr-opt.r-miss { border-color: #16a34a; }
    .egr-lrow { padding: 9px 10px; }
    .egr-input-row { position: sticky; bottom: 0; z-index: 5; margin: 12px -12px 0; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid #E2EEF6; border-radius: 0 0 18px 18px; box-shadow: 0 -6px 16px rgba(15, 30, 41, .05); gap: 10px; }
    .egr-card > .egr-input-row:last-child { margin-bottom: 0; }
  }
</style>
<script>
/*
 * Экран раунда станции «Задания». GSAP подключён в layouts/main с defer —
 * всё стартует на DOMContentLoaded. Без GSAP или при «уменьшить движение»
 * экран работает так же, просто без движения.
 */
(function () {
  var root = document.getElementById('eg-app');
  if (!root) return;
  var boot = JSON.parse(root.getAttribute('data-boot'));
  var T = boot.texts;
  var stage = document.getElementById('egr-stage');
  var bar = document.getElementById('egr-bar');
  var counter = document.getElementById('egr-count');
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
  var position = boot.position;
  var total = boot.total;
  var onKey = null;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var LETTERS = 'АБВГДЕЖ';

  function gs() { return !reduced && typeof window.gsap !== 'undefined' ? window.gsap : null; }
  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') node.textContent = attrs[k];
      else if (k === 'class') node.className = attrs[k];
      else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2), attrs[k]);
      else node.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return node;
  }
  function vibrate(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } }

  /* ---------- Кеша ---------- */

  var hud = {
    root: document.getElementById('egr-hud'),
    img: document.getElementById('egr-mascot'),
    ring: document.getElementById('egr-ring'),
    count: document.getElementById('egr-hud-count'),
    streak: document.getElementById('egr-hud-streak'),
    say: document.getElementById('egr-hud-say')
  };
  var RING = 289.03;
  var moodNow = 'idle', moodTimer = null;
  ['happy', 'sad'].forEach(function (m) { var i = new Image(); i.src = boot.mascot + '-' + m + '.webp'; });
  function wide() { return window.matchMedia('(min-width: 1180px)').matches; }
  // Радость держится пару секунд, грусть — пока не перешли к следующему заданию.
  function mood(name) {
    clearTimeout(moodTimer);
    if (name === 'happy') moodTimer = setTimeout(function () { mood('idle'); }, 2500);
    if (name === moodNow) return;
    moodNow = name;
    hud.img.src = boot.mascot + '-' + name + '.webp';
    var g = gs();
    if (g && wide()) {
      if (name === 'happy') g.fromTo(hud.img, { scale: 0.8, y: 6 }, { scale: 1, y: 0, duration: 0.6, ease: 'elastic.out(1, 0.45)', clearProps: 'transform' });
      else if (name === 'sad') g.fromTo(hud.img, { rotation: 0 }, { keyframes: [{ rotation: -6, duration: 0.12 }, { rotation: 4, duration: 0.12 }, { rotation: 0, duration: 0.16 }], clearProps: 'transform' });
      else g.fromTo(hud.img, { opacity: 0.6 }, { opacity: 1, duration: 0.3, clearProps: 'opacity' });
    }
  }
  // Реплика под Кешей, как облачко у рыбы в «Терминах»: на верный — зелёная.
  function speak(text, ok, combo) {
    hud.say.textContent = text || '';
    hud.say.classList.toggle('is-ok', !!ok);
    if (combo) {
      var badge = comboBadge(combo);
      hud.say.appendChild(badge);
      var gc = gs();
      if (gc && wide()) playCombo(gc, badge, 0.3);
    }
    var g = gs();
    if (text && g && wide()) g.fromTo(hud.say, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)', transformOrigin: '50% 0%', clearProps: 'transform,opacity' });
  }
  function streakChip(n) {
    hud.streak.textContent = n >= 2 ? '🔥 ' + n + ' подряд' : '';
    var g = gs();
    if (n >= 2 && g && wide()) g.fromTo(hud.streak, { scale: 0.5 }, { scale: 1, duration: 0.4, ease: 'back.out(3)', clearProps: 'transform' });
  }

  var shownDone = null;
  function progress(done) {
    hud.count.textContent = Math.min(done + 1, total) + ' / ' + total;
    var offset = RING * (1 - done / total);
    counter.textContent = Math.min(done + 1, total) + ' / ' + total;
    var to = Math.round(100 * done / total) + '%';
    var g = gs();
    if (!g || shownDone === null) { bar.style.width = to; hud.ring.style.strokeDashoffset = offset; }
    else if (done !== shownDone) {
      g.to(bar, { width: to, duration: 0.5, ease: 'power2.out' });
      g.to(hud.ring, { strokeDashoffset: offset, duration: 0.6, ease: 'power2.out' });
      g.fromTo(counter, { scale: 1.25 }, { scale: 1, duration: 0.4, ease: 'back.out(3)', clearProps: 'transform' });
    }
    shownDone = done;
  }

  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(data),
      credentials: 'same-origin'
    }).then(function (r) {
      if (r.status === 409) { window.location.reload(); throw new Error('stale'); }
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (!onKey || e.altKey || e.ctrlKey || e.metaKey || e.repeat) return;
    if (onKey(e)) e.preventDefault();
  });

  /* ---------- тост «Верно!» и серия ---------- */

  var streakKey = 'egr-streak:' + boot.answerUrl;
  function streak(next) {
    try {
      if (next === undefined) return parseInt(sessionStorage.getItem(streakKey), 10) || 0;
      sessionStorage.setItem(streakKey, String(next));
    } catch (e) {}
    return next || 0;
  }
  var toastRoot = null;
  // Плашка комбо + блик и искры (как в «Верных суждениях»).
  function comboBadge(text) {
    return el('div', { class: 'egr-combo' }, [el('span', { text: text }), el('i', { class: 'egr-combo-shine' })]);
  }
  function playCombo(g, badge, at) {
    g.timeline({ delay: at || 0 })
      .fromTo(badge, { scale: 0, rotation: -8 }, { scale: 1, rotation: 0, duration: 0.7, ease: 'elastic.out(1, 0.5)', clearProps: 'transform' })
      .fromTo(badge.querySelector('.egr-combo-shine'), { xPercent: -120 }, { xPercent: 320, duration: 0.6, ease: 'power2.inOut' }, 0.35)
      .add(function () { sparks(badge); }, 0.25);
  }
  function sparks(anchor) {
    var g = gs();
    if (!g || !anchor.isConnected) return;
    var rect = anchor.getBoundingClientRect();
    for (var i = 0; i < 5; i++) {
      (function (n) {
        var spark = el('span', { text: '🔥' });
        spark.style.cssText = 'position:fixed;z-index:90;pointer-events:none;font-size:' + (14 + Math.random() * 8) + 'px;left:'
          + (rect.left + rect.width * (0.15 + n * 0.17)) + 'px;top:' + (rect.top - 4) + 'px;';
        document.body.appendChild(spark);
        g.fromTo(spark, { y: 0, x: 0, scale: 0.4, opacity: 1 }, {
          y: -40 - Math.random() * 35, x: (Math.random() - 0.5) * 30, scale: 1, opacity: 0, rotation: (Math.random() - 0.5) * 40,
          duration: 0.8 + Math.random() * 0.3, delay: n * 0.05, ease: 'power2.out',
          onComplete: function () { spark.remove(); }
        });
      })(i);
    }
  }
  function comboText(n) {
    var list = (boot.combo || {})[n];
    return list && list.length ? list[Math.floor(Math.random() * list.length)] : null;
  }

  // Телефон: один тост — Кеша, его реплика и (если есть) плашка комбо.
  function toast(text, pic, combo) {
    if (wide()) return; // на ПК говорит Кеша справа, второй не нужен
    if (!toastRoot) { toastRoot = el('div', { class: 'egr-toasts' }); document.body.appendChild(toastRoot); }
    var picBox = el('div', { class: 'egr-toast-pic' }, [el('img', { src: pic, alt: '' })]);
    var line = el('div', { class: 'egr-toast-text', text: text });
    var badge = combo ? comboBadge(combo) : null;
    var node = el('div', { class: 'egr-toast' }, [picBox, el('div', { style: 'width:100%' }, [line, badge])]);
    toastRoot.appendChild(node);
    var g = gs();
    if (g) {
      g.timeline()
        .fromTo(node, { autoAlpha: 0, y: -16, scale: 0.85 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.35, ease: 'back.out(1.9)' })
        .fromTo(picBox, { scale: 0.5, rotation: -8, autoAlpha: 0 }, { scale: 1, rotation: 0, autoAlpha: 1, duration: 0.45, ease: 'elastic.out(1, 0.5)' }, '-=0.2');
      if (badge) playCombo(g, badge, 0.4);
    }
    setTimeout(function () {
      if (g) g.to(node, { autoAlpha: 0, y: -10, scale: 0.92, duration: 0.25, ease: 'power2.in', onComplete: function () { node.remove(); } });
      else node.remove();
    }, combo ? 2800 : 1800);
  }

  /* ---------- задание ---------- */

  function showCard() {
    progress(position);
    var card = boot.cards[position];
    if (moodNow === 'sad') mood('idle');
    speak('');
    // Номер задания — чипс слева от полосы прогресса.
    var num = document.getElementById('egr-num');
    num.textContent = '№ ' + card.number;
    var startedAt = Date.now();
    var mode = 'check';
    stage.innerHTML = '';

    var node = el('div', { class: 'egr-card' });
    node.appendChild(el('div', { class: 'eg-q' }, [el('span', { class: 'egr-qnum', text: '№ ' + card.number }), card.question]));
    if (card.image) node.appendChild(el('img', { class: 'eg-img', src: card.image, alt: 'Иллюстрация к заданию', loading: 'lazy' }));

    var optButtons = [];
    var picks = []; // соответствие: [буква] -> кнопки цифр
    var isMatching = card.type === 'matching' && card.matches;

    if (isMatching) {
      var match = el('div', { class: 'egr-match' });
      var left = el('div', { class: 'egr-left' });
      if (card.left_title) left.appendChild(el('div', { class: 'egr-col-title', text: card.left_title }));
      var rights = card.matches.right || [];
      (card.matches.left || []).forEach(function (text, i) {
        // Кнопок с цифрами у строк нет (решение автора от 08.11.2026): ответ
        // вводится с клавиатуры в клеточки, как в бланке. Строка остаётся в
        // picks — по ней красится разбор (верно / неверно по буквам).
        var row = el('div', { class: 'egr-lrow' }, [el('div', { class: 'egr-lrow-text' }, [el('b', { text: LETTERS[i] + ')' }), text])]);
        picks.push({ row: row, buttons: [] });
        left.appendChild(row);
      });
      var right = el('div');
      if (card.right_title) right.appendChild(el('div', { class: 'egr-col-title', text: card.right_title }));
      var list = el('ul', { class: 'egr-right' });
      rights.forEach(function (text, j) { list.appendChild(el('li', {}, [el('span', { text: (j + 1) + ')' }), el('span', { text: text })])); });
      right.appendChild(list);
      match.appendChild(left);
      match.appendChild(right);
      node.appendChild(match);
    } else if (card.options && card.options.length) {
      var opts = el('ol', { class: 'egr-opts' });
      card.options.forEach(function (text, i) {
        var b = el('button', { type: 'button', class: 'egr-opt' }, [el('span', { class: 'egr-opt-n', text: String(i + 1) }), el('span', { text: text })]);
        b.addEventListener('click', function () { toggleDigit(String(i + 1)); });
        optButtons.push(b);
        opts.appendChild(el('li', {}, [b]));
      });
      node.appendChild(opts);
    }

    // Клеточки: в соответствии — по одной на букву (с буквой сверху),
    // в выборе — по числу вариантов.
    var count = picks.length || optButtons.length || 6;
    var input = el('input', { class: 'egr-pin-input', type: 'text', inputmode: 'numeric', autocomplete: 'off', maxlength: String(count), 'aria-label': T.answer_placeholder });
    var pin = el('div', { class: 'pin-field', style: '--n:' + count });
    var pinBoxes = el('div', { class: 'pin-boxes' });
    var boxes = [];
    for (var k = 0; k < count; k++) {
      var box = el('div', { class: 'pin-box' });
      boxes.push(box);
      pinBoxes.appendChild(isMatching ? el('div', { class: 'egr-cell' }, [el('span', { class: 'egr-cell-l', text: LETTERS[k] }), box]) : box);
    }
    pin.appendChild(pinBoxes);
    pin.appendChild(input);

    var busy = false;
    var check = el('button', { type: 'button', class: 'eg-btn egr-check', text: T.check });
    check.disabled = true;

    // Строка ответа, клеточки и плитки — одно состояние.
    function digits() { return (input.value || '').replace(/\D/g, ''); }
    function toggleDigit(d) {
      var v = digits();
      v = v.indexOf(d) >= 0 ? v.replace(d, '') : v + d;
      input.value = v.split('').sort().join('');
      sync();
    }
    // В соответствии строка — цифры по буквам; пропущенная буква — «0»
    // (такой цифры нет, проверка посчитает её ошибкой).
    function setMatch(i, d) {
      var v = digits().split('');
      while (v.length < i) v.push('0');
      v[i] = String(d);
      input.value = v.join('').replace(/0+$/, '');
      sync();
    }
    function sync() {
      var v = digits();
      var focused = document.activeElement === input;
      boxes.forEach(function (b, i) {
        var c = v[i] && v[i] !== '0' ? v[i] : '';
        b.textContent = c;
        b.classList.toggle('filled', !!c);
        b.classList.toggle('active', focused && i === Math.min(v.length, count - 1));
      });
      optButtons.forEach(function (b, i) { b.classList.toggle('is-on', v.indexOf(String(i + 1)) >= 0); });
      picks.forEach(function (p, i) { p.buttons.forEach(function (b, j) { b.classList.toggle('is-on', v[i] === String(j + 1)); }); });
      check.disabled = busy || v.replace(/0/g, '').length === 0;
    }
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\D/g, '').slice(0, count);
      sync();
    });
    input.addEventListener('focus', sync);
    input.addEventListener('blur', sync);

    var inputRow = el('div', { class: 'egr-input-row' }, [pin, check]);
    node.appendChild(inputRow);
    stage.appendChild(node);
    // Телефон: добиваем ряд клеточками на всю ширину — та же формула, что
    // в домашке (pin-field-script: клетка 32px, зазор 10px). В соответствии
    // клеточек ровно по буквам — над ними подписи.
    if (!isMatching && window.matchMedia('(max-width: 640px)').matches) {
      var fit = Math.floor((pin.clientWidth + 10) / (32 + 10));
      for (var f = boxes.length; f < fit; f++) {
        var extra = el('div', { class: 'pin-box' });
        boxes.push(extra);
        pinBoxes.appendChild(extra);
      }
    }
    // Под карточкой: подсказка про порядок и «Enter — проверить».
    var keys = el('span', { class: 'egr-keys' }, [el('kbd', { text: 'Enter' }), ' — ' + T.check.toLowerCase()]);
    var hint = el('div', { class: 'egr-foot' }, [el('span', { text: isMatching ? T.hint_by_letters : T.hint_any_order }), keys]);
    stage.appendChild(hint);

    var g = gs();
    if (g) g.fromTo(node, { y: 24, opacity: 0, scale: 0.98 }, { y: 0, opacity: 1, scale: 1, duration: 0.45, ease: 'back.out(1.6)', clearProps: 'transform,opacity' });
    if (window.matchMedia('(hover: hover)').matches) input.focus();

    function lock(on) {
      input.disabled = on;
      optButtons.forEach(function (b) { b.disabled = on; });
      picks.forEach(function (p) { p.buttons.forEach(function (b) { b.disabled = on; }); });
      sync();
    }
    function send(sendMode) {
      var answer = digits();
      if (busy || (sendMode !== 'reveal' && !answer.replace(/0/g, ''))) return;
      busy = true;
      lock(true);
      post(boot.answerUrl, { position: card.position, answer: answer, ms: Date.now() - startedAt, mode: sendMode })
        .then(function (res) {
          if (res.pending) return choose(res.result);
          var view = { node: node, hint: hint, keys: keys, check: check, optButtons: optButtons, picks: picks, boxes: boxes, pinBoxes: pinBoxes };
          if (res.result.status === 'right') return rightAndGo(view, res);
          feedback(view, res);
        })
        .catch(function (err) {
          if (err.message === 'stale') return;
          busy = false;
          lock(false);
          alert('Не получилось отправить ответ. Проверь интернет и попробуй ещё раз.');
        });
    }
    check.addEventListener('click', function () { send(mode); });
    onKey = function (e) { if (e.key === 'Enter') { send(mode); return true; } return false; };

    // Страницу обновили, пока висел выбор «Перерешать / Разбор» — сразу к нему.
    var pend = boot.pending;
    if (pend && pend.result.position === card.position) {
      boot.pending = null;
      input.value = pend.result.answer || '';
      lock(true);
      setTimeout(function () { choose(pend.result); }, 0);
    }

    // Не на полный балл: «Перерешать» или «Посмотреть разбор».
    function choose(r) {
      streak(0);
      busy = false;
      if (r.answer && digits() !== r.answer) { input.value = r.answer; sync(); }
      // Какие цифры верны — пока не говорим: красим все введённые.
      mood('sad');
      speak(r.say);
      streakChip(0);
      var tone = r.status === 'partial' ? 'half' : 'bad';
      paint(boxes, boxes.map(function (b) { return b.textContent ? tone : null; }));
      check.style.display = 'none';
      hint.style.display = 'none';
      keys.style.display = 'none';

      var points = el('span', { class: 'egr-points' }, [el('span', { text: r.score + ' из ' + r.max })]);
      var verdict = el('div', { class: 'egr-verdict ' + r.status }, [(r.status === 'partial' ? '🟡 ' + T.partial : '❌ ' + T.wrong), points]);
      var retryBtn = el('button', { type: 'button', class: 'eg-btn', text: T.retry });
      var revealBtn = el('button', { type: 'button', class: 'eg-btn eg-btn-ghost', text: T.reveal });
      var note = el('div', { class: 'egr-choice-note', text: r.retry_max > r.score ? T.retry_note.replace(':n', r.retry_max).replace(':max', r.max) : T.retry_note_zero });
      var box = el('div', {}, [verdict, el('div', { class: 'egr-choice' }, [retryBtn, revealBtn]), note]);
      node.appendChild(box);

      var g2 = gs();
      if (g2) {
        if (r.status === 'partial') wobble(g2, boxes);
        else shake(g2, pinBoxes);
        g2.fromTo(box.children, { y: 8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.06, ease: 'power2.out', clearProps: 'transform,opacity' });
      }
      vibrate(r.status === 'partial' ? 40 : [60, 40, 60]);

      function retry() {
        box.remove();
        paint(boxes, []);
        mood('idle');
        speak('');
        check.style.display = '';
        hint.style.display = '';
        keys.style.display = '';
        mode = 'retry';
        lock(false);
        input.focus();
        onKey = function (e) { if (e.key === 'Enter') { send('retry'); return true; } return false; };
      }
      retryBtn.addEventListener('click', retry);
      revealBtn.addEventListener('click', function () { box.remove(); send('reveal'); });
      retryBtn.focus({ preventScroll: true });
      verdict.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'nearest' });
      onKey = function (e) { if (e.key === 'Enter') { retry(); return true; } return false; };
    }
  }

  /* ---------- квадратики: цвет и движение результата ---------- */

  function paint(boxes, states) {
    boxes.forEach(function (b, i) {
      b.classList.remove('is-ok', 'is-bad', 'is-half', 'is-miss');
      if (states[i]) b.classList.add('is-' + states[i]);
    });
  }
  // Неверно — ряд квадратиков «мотает головой».
  function shake(g, row) {
    g.fromTo(row, { x: 0 }, { x: 7, duration: 0.06, yoyo: true, repeat: 5, ease: 'power1.inOut', clearProps: 'transform' });
  }
  // Частично — квадратики по очереди покачиваются («почти»).
  function wobble(g, list) {
    g.fromTo(list, { rotation: 0 }, { keyframes: [{ rotation: -8, duration: 0.09 }, { rotation: 6, duration: 0.09 }, { rotation: 0, duration: 0.12 }], stagger: 0.06, ease: 'power1.inOut', clearProps: 'transform' });
  }

  /* ---------- верно: тост и сразу дальше ---------- */

  function rightAndGo(v, res) {
    var r = res.result;
    onKey = null;
    var lit = v.boxes.filter(function (b) { return b.textContent; });
    paint(v.boxes, v.boxes.map(function (b) { return b.textContent ? 'ok' : null; }));
    v.optButtons.forEach(function (b, i) { b.classList.remove('is-on'); if ((r.answer || '').indexOf(String(i + 1)) >= 0) b.classList.add('r-hit'); });
    v.picks.forEach(function (p) { p.row.classList.add('r-ok'); });
    var happy = boot.mascot + '-happy.webp';
    mood('happy');
    if (r.second) {
      speak(T.right + ' ' + T.second_try, true);
      toast(T.right + ' ' + T.second_try + ' · ' + r.score + ' из ' + r.max, happy);
    } else {
      var n = streak(streak() + 1);
      var combo = comboText(n);
      speak(r.say, true, combo);
      streakChip(n);
      toast(r.say, happy, combo);
    }
    var g = gs();
    if (g) g.fromTo(lit, { scale: 1, y: 0 }, { scale: 1.18, y: -6, duration: 0.16, yoyo: true, repeat: 1, stagger: 0.06, ease: 'power2.out', clearProps: 'transform' });
    // С комбо — подольше, чтобы плашка успела сыграть.
    setTimeout(function () { advance(r, res); }, (reduced ? 400 : 750 + lit.length * 60) + (combo ? 1000 : 0));
  }

  function advance(r, res) {
    onKey = null;
    position = r.position + 1;
    var after = function () {
      if (res.finished) showSummary(res.summary);
      else showCard();
      window.scrollTo({ top: 0, behavior: 'auto' });
    };
    var g = gs();
    if (g) g.to(stage.children, { x: -40, opacity: 0, duration: 0.22, ease: 'power2.in', onComplete: after });
    else after();
  }

  /* ---------- разбор ---------- */

  // «Ошибка в задании?» — как в «Терминах»: раскрывается маленькая форма.
  function reportBox(taskId) {
    var area = el('textarea', { maxlength: '1000', placeholder: T.report_placeholder });
    var btn = el('button', { type: 'submit', class: 'eg-btn eg-btn-ghost', text: T.report_send });
    var f = el('form', {}, [area, btn]);
    var box = el('details', { class: 'egr-report' }, [el('summary', { text: T.report }), f]);
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      btn.disabled = true;
      post(boot.reportUrl, { task_id: taskId, message: area.value.trim() || null })
        .then(function () { box.replaceChildren(el('div', { class: 'egr-report-done', text: T.report_sent })); })
        .catch(function () { btn.disabled = false; });
    });
    // Пока пишут жалобу, Enter — перенос строки, а не «Дальше».
    area.addEventListener('keydown', function (e) { e.stopPropagation(); });
    return box;
  }

  function feedback(v, res) {
    var r = res.result;
    var node = v.node;
    var answer = r.answer || '';
    var correct = r.correct || '';

    // Квадратики: в соответствии — каждая буква верно/неверно; в выборе —
    // введённые цифры верно/лишняя, а пропущенные верные цифры дописываются
    // в следующие пустые клеточки пунктиром.
    var states = [], missing = [];
    if (v.picks.length) {
      v.boxes.forEach(function (b, i) { if (i < v.picks.length) states[i] = answer[i] === correct[i] ? 'ok' : 'bad'; });
    } else {
      var typed = answer.split('');
      typed.forEach(function (d, i) { states[i] = correct.indexOf(d) >= 0 ? 'ok' : 'bad'; });
      correct.split('').filter(function (d) { return typed.indexOf(d) < 0; }).forEach(function (d, k) {
        var i = typed.length + k;
        if (!v.boxes[i]) return;
        v.boxes[i].textContent = d;
        states[i] = 'miss';
        missing.push(v.boxes[i]);
      });
    }
    paint(v.boxes, states);

    // Подсветка: в выборе — что верно выбрано, что лишнее, что пропущено;
    // в соответствии — каждая буква.
    v.optButtons.forEach(function (b, i) {
      var d = String(i + 1);
      var chosen = answer.indexOf(d) >= 0, need = correct.indexOf(d) >= 0;
      b.classList.remove('is-on');
      if (chosen && need) b.classList.add('r-hit');
      else if (chosen) b.classList.add('r-extra');
      else if (need) b.classList.add('r-miss');
    });
    v.picks.forEach(function (p, i) {
      var ok = answer[i] === correct[i];
      p.row.classList.add(ok ? 'r-ok' : 'r-bad');
      if (!ok && correct[i]) p.row.appendChild(el('div', { class: 'egr-lrow-fix', text: 'Верно: ' + correct[i] }));
    });

    v.keys.remove();
    v.hint.remove();
    v.check.remove();
    var icon = r.status === 'partial' ? '🟡' : '❌';
    var label = r.status === 'partial' ? T.partial : T.wrong;
    var points = el('span', { class: 'egr-points' }, [el('span', { text: r.score + ' из ' + r.max })]);
    var verdict = el('div', { class: 'egr-verdict ' + r.status }, [icon + ' ' + label, points, r.second ? el('small', { text: T.second_try }) : null]);
    var key = el('div', { class: 'egr-key' }, [T.correct_answer + ': ', el('b', { text: correct })]);
    var explain = el('div', { class: 'egr-explain' }, [el('span', { class: 'egr-explain-t', text: T.explanation }), r.explanation || T.no_explanation]);
    mood('sad');
    speak(r.say);
    var sharkImg = el('img', { src: boot.mascot + '-sad.webp', alt: '' });
    var bubble = el('div', { class: 'egr-bubble', text: r.say });
    var shark = el('div', { class: 'egr-shark' }, [sharkImg, bubble]);
    var next = el('button', { type: 'button', class: 'eg-btn egr-next', text: res.finished ? T.finish : T.next });
    var report = reportBox(r.task_id);

    [verdict, key, explain].forEach(function (n) { node.appendChild(n); });
    stage.appendChild(shark);
    stage.appendChild(next);
    stage.appendChild(report);

    var g = gs();
    if (g) {
      if (r.status === 'partial') wobble(g, v.boxes.filter(function (b, i) { return states[i] === 'ok' || states[i] === 'bad'; }));
      else shake(g, v.pinBoxes);
      if (missing.length) g.fromTo(missing, { scale: 0, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.4, stagger: 0.1, delay: 0.45, ease: 'back.out(2.5)', clearProps: 'transform,opacity' });
      g.timeline()
        .fromTo(verdict, { y: 8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.05)
        .fromTo(points, { scale: 0.4 }, { scale: 1, duration: 0.45, ease: 'back.out(3)', clearProps: 'transform' }, 0.15)
        .fromTo([key, explain], { y: 10, opacity: 0 }, { y: 0, opacity: 1, duration: 0.35, stagger: 0.08, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.2)
        .fromTo(sharkImg, { scale: 0.5, y: 10, opacity: 0 }, { scale: 1, y: 0, opacity: 1, duration: 0.5, ease: 'back.out(2.2)', clearProps: 'transform,opacity' }, 0.3)
        .fromTo(bubble, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)', transformOrigin: '0% 100%', clearProps: 'transform,opacity' }, 0.4)
        .fromTo([next, report], { y: 10, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.05, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.45);
    }
    vibrate(r.status === 'partial' ? 40 : [60, 40, 60]);
    next.focus({ preventScroll: true });
    verdict.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'nearest' });

    next.addEventListener('click', function () { advance(r, res); });
    onKey = function (e) { if (e.key === 'Enter') { advance(r, res); return true; } return false; };
  }

  /* ---------- итог ---------- */

  function form(action, fields, label, cls) {
    var f = el('form', { method: 'POST', action: action });
    f.appendChild(el('input', { type: 'hidden', name: '_token', value: csrf }));
    (fields || []).forEach(function (pair) { f.appendChild(el('input', { type: 'hidden', name: pair[0], value: pair[1] })); });
    f.appendChild(el('button', { class: 'eg-btn ' + (cls || ''), type: 'submit', text: label }));
    return f;
  }

  function confetti() {
    var g = gs();
    if (!g) return;
    var colors = ['#2563EB', '#10B981', '#F59E0B', '#EC4899', '#7C3AED'];
    var layer = el('div');
    layer.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:70;overflow:hidden;';
    document.body.appendChild(layer);
    for (var i = 0; i < 60; i++) {
      (function () {
        var bit = el('div');
        var size = 6 + Math.random() * 6;
        bit.style.cssText = 'position:absolute;left:50%;top:22%;width:' + size + 'px;height:' + (size * 0.4) + 'px;border-radius:2px;background:' + colors[Math.floor(Math.random() * colors.length)] + ';';
        layer.appendChild(bit);
        var dx = (Math.random() - 0.5) * window.innerWidth * 0.8, spin = (Math.random() - 0.5) * 720;
        g.timeline({ onComplete: function () { bit.remove(); } })
          .to(bit, { x: dx * 0.4, y: -80 - Math.random() * 80, rotation: spin * 0.3, duration: 0.35 + Math.random() * 0.15, ease: 'power2.out' })
          .to(bit, { x: dx, y: window.innerHeight * (0.5 + Math.random() * 0.5), rotation: spin, opacity: 0, duration: 1.1 + Math.random() * 0.6, ease: 'power1.in' });
      })();
    }
    setTimeout(function () { layer.remove(); }, 2600);
  }

  // Ответ ученика в списке ошибок — цифрами с той же подсветкой, что на карточке.
  function answerDigits(m) {
    var a = m.answer || '', c = m.correct || '';
    var row = el('div', { class: 'egr-digits' });
    if (m.type === 'matching') {
      for (var i = 0; i < c.length; i++) {
        var ok = a[i] === c[i];
        var mine = a[i] && a[i] !== '0' ? a[i] : '—';
        row.appendChild(el('div', { class: 'egr-mcell' }, [
          el('span', { class: 'egr-mcell-l', text: LETTERS[i] }),
          el('span', { class: 'egr-dg ' + (ok ? 'hit' : 'extra'), text: mine }),
          ok ? null : el('span', { class: 'egr-mcell-fix', text: c[i] })
        ]));
      }
      return row;
    }
    var all = (a + c).split('').filter(function (d, i, arr) { return arr.indexOf(d) === i; }).sort();
    all.forEach(function (d) {
      var chosen = a.indexOf(d) >= 0, need = c.indexOf(d) >= 0;
      row.appendChild(el('span', { class: 'egr-dg ' + (chosen && need ? 'hit' : (chosen ? 'extra' : 'miss')), text: d }));
    });
    return row;
  }

  function toneFor(score, max) {
    if (max > 0 && score >= max) return '#16a34a';
    return score * 3 >= max * 2 ? '#2563eb' : '#f97316';
  }

  function showSummary(s) {
    var top = root.querySelector('.egr-top');
    if (top) top.style.display = 'none';
    onKey = null;
    stage.innerHTML = '';
    var tone = toneFor(s.score, s.max);
    var good = s.score * 3 >= s.max * 2;

    var head = el('div', { class: 'egr-result' });
    var tag = s.kind === 'mistakes' ? el('div', { class: 'egr-tag', text: 'Повтор ошибок' }) : null;
    if (tag) head.appendChild(tag);
    if (hud.root) hud.root.style.display = 'none';
    var sharkImg = el('img', { src: boot.mascot + '-' + (good ? 'happy' : 'sad') + '.webp', alt: '' });
    var say = el('div', { class: 'egr-bubble', text: s.say });
    head.appendChild(el('div', { class: 'egr-who' }, [sharkImg, say]));

    var R = 62, C = 2 * Math.PI * R, part = s.max > 0 ? s.score / s.max : 0;
    var ring = el('div', { class: 'egr-ring' });
    ring.innerHTML = '<svg viewBox="0 0 140 140" aria-hidden="true"><circle cx="70" cy="70" r="' + R + '" class="egr-ring-track"/>'
      + '<circle cx="70" cy="70" r="' + R + '" class="egr-ring-fill" stroke="' + tone + '" stroke-dasharray="' + C.toFixed(2)
      + '" stroke-dashoffset="' + (C * (1 - part)).toFixed(2) + '" transform="rotate(-90 70 70)"/></svg>';
    var scoreN = el('span', { class: 'egr-score-n', text: String(s.score) });
    scoreN.style.color = tone;
    ring.appendChild(el('div', { class: 'egr-ring-text' }, [
      el('div', { class: 'egr-score' }, [scoreN, el('span', { class: 'egr-score-of', text: '/ ' + s.max })]),
      el('div', { class: 'egr-ring-label', text: T.summary_points })
    ]));
    head.appendChild(ring);

    var plus = null;
    if (s.corm > 0) {
      plus = el('div', { class: 'egr-corm-plus', text: '+' + s.corm + ' ' });
      for (var f = 0; f < s.corm; f++) plus.appendChild(el('img', { src: boot.foodIcon, alt: f === 0 ? 'корма' : '' }));
      head.appendChild(plus);
    }
    var note = s.corm_note ? el('div', { class: 'egr-corm-note', text: s.corm_note }) : null;
    if (note) head.appendChild(note);
    stage.appendChild(head);

    var sections = [], rows = [], meters = [];
    if (s.numbers.length > 1) {
      var byNum = el('div', { class: 'egr-section' }, [el('h3', { text: T.by_number })]);
      s.numbers.forEach(function (n) {
        var fill = el('i');
        fill.style.width = (n.max ? Math.round(100 * n.score / n.max) : 0) + '%';
        fill.style.background = toneFor(n.score, n.max);
        meters.push(fill);
        var row = el('div', { class: 'egr-row' }, [
          el('span', { class: 'egr-row-n', text: String(n.n) }),
          el('span', { class: 'egr-row-title', text: n.title || '' }),
          el('span', { class: 'egr-meter' }, [fill]),
          el('span', { class: 'egr-row-count', text: n.score + '/' + n.max })
        ]);
        rows.push(row);
        byNum.appendChild(row);
      });
      stage.appendChild(byNum);
      sections.push(byNum);
    }

    var mistakeNodes = [];
    if (s.mistakes.length) {
      var list = el('div', { class: 'egr-section' }, [el('h3', { text: T.mistakes })]);
      if (s.kind === 'regular') list.appendChild(el('div', { class: 'egr-section-note', text: T.mistakes_note }));
      s.mistakes.forEach(function (m) {
        var item = el('div', { class: 'egr-mistake' }, [
          el('div', { class: 'egr-mistake-head' }, [
            el('span', { class: 'eg-badge', text: '№ ' + m.number }),
            el('span', { class: 'egr-mistake-pts' + (m.score > 0 ? ' is-partial' : ''), text: m.score + ' из ' + m.max })
          ]),
          el('div', { class: 'q', text: m.question.length > 220 ? m.question.slice(0, 220) + '…' : m.question }),
          answerDigits(m)
        ]);
        if (m.explanation) item.appendChild(el('details', {}, [el('summary', { text: T.explanation }), el('div', { text: m.explanation })]));
        mistakeNodes.push(item);
        list.appendChild(item);
      });
      stage.appendChild(list);
      sections.push(list);
    }

    var actions = el('div', { class: 'egr-actions' });
    var startFields = (boot.numbers || []).map(function (n) { return ['numbers[]', n]; })
      .concat((boot.blocks || []).map(function (b) { return ['blocks[]', b]; }));
    if (s.kind === 'regular' && s.mistakes.length) actions.appendChild(form(boot.mistakesUrl, [], T.retry_mistakes));
    actions.appendChild(form(boot.startUrl, startFields, T.new_round, s.kind === 'regular' && s.mistakes.length ? 'eg-btn-ghost' : ''));
    actions.appendChild(el('a', { class: 'eg-btn eg-btn-ghost', href: boot.hallUrl, text: T.back_to_hall }));
    stage.appendChild(actions);

    var g = gs();
    if (!g) return;
    var tl = g.timeline();
    if (tag) tl.fromTo(tag, { y: -8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, clearProps: 'transform,opacity' }, 0);
    tl.fromTo(sharkImg, { scale: 0.4, rotation: -10, opacity: 0 }, { scale: 1, rotation: 0, opacity: 1, duration: 0.7, ease: 'elastic.out(1, 0.5)', clearProps: 'transform,opacity' }, 0.1)
      .fromTo(say, { scale: 0.6, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)', transformOrigin: '0% 100%', clearProps: 'transform,opacity' }, 0.3)
      .fromTo(ring, { scale: 0.85, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.4, ease: 'back.out(2)', clearProps: 'transform,opacity' }, 0.3);
    var fillDur = Math.min(1.2, 0.05 * s.score + 0.4);
    tl.fromTo(ring.querySelector('.egr-ring-fill'), { attr: { 'stroke-dashoffset': C } }, { attr: { 'stroke-dashoffset': C * (1 - part) }, duration: fillDur, ease: 'power2.out' }, 0.45);
    var counterObj = { n: 0 };
    tl.to(counterObj, { n: s.score, duration: fillDur, ease: 'power2.out', onUpdate: function () { scoreN.textContent = String(Math.round(counterObj.n)); } }, 0.45);
    var end = 0.45 + fillDur;
    tl.fromTo(ring, { scale: 1 }, { scale: 1.06, duration: 0.15, yoyo: true, repeat: 1, ease: 'power2.out', clearProps: 'transform' }, end);
    if (plus) {
      tl.fromTo(plus, { scale: 0.3, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.45, ease: 'back.out(3)', clearProps: 'transform,opacity' }, end + 0.1)
        .fromTo(plus.querySelectorAll('img'), { y: -18, opacity: 0, rotation: -30 }, { y: 0, opacity: 1, rotation: 0, duration: 0.4, stagger: 0.12, ease: 'bounce.out', clearProps: 'transform,opacity' }, end + 0.25);
    }
    if ((s.max > 0 && s.score >= s.max) || s.corm >= 3) tl.add(confetti, end + 0.2);
    if (note) tl.fromTo(note, { y: 6, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, clearProps: 'transform,opacity' }, end + 0.3);
    if (sections.length) tl.fromTo(sections, { y: 16, opacity: 0 }, { y: 0, opacity: 1, duration: 0.4, stagger: 0.1, ease: 'power2.out', clearProps: 'transform,opacity' }, 0.6);
    if (rows.length) tl.fromTo(rows, { x: -8, opacity: 0 }, { x: 0, opacity: 1, duration: 0.3, stagger: 0.07, clearProps: 'transform,opacity' }, 0.75);
    if (meters.length) tl.from(meters, { width: 0, duration: 0.6, stagger: 0.07, ease: 'power2.out' }, 0.8);
    if (mistakeNodes.length) tl.fromTo(mistakeNodes, { y: 8, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.05, clearProps: 'transform,opacity' }, 0.85);
    tl.fromTo(actions.children, { y: 12, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, stagger: 0.06, clearProps: 'transform,opacity' }, 0.6);
  }

  function start() {
    streakChip(streak());
    if (boot.summary) showSummary(boot.summary);
    else if (position < total) showCard();
    else window.location.href = boot.indexUrl;
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
</script>
@endpush
