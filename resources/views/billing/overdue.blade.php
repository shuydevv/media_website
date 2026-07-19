@extends('layouts.main')

@section('content')
<div id="lab-door-overlay" class="fixed inset-0 z-[999] overflow-hidden" aria-hidden="true">
    <div id="lab-door-left" class="lab-door lab-door-left">
        <div class="lab-door-panel-grid"></div>
        <div class="lab-door-lights lab-door-lights-left">
            <div class="lab-door-light-housing"><div class="lab-door-light"></div></div>
            <div class="lab-door-light-housing"><div class="lab-door-light"></div></div>
            <div class="lab-door-light-housing"><div class="lab-door-light"></div></div>
        </div>
    </div>
    <div id="lab-door-right" class="lab-door lab-door-right">
        <div class="lab-door-panel-grid"></div>
        <div class="lab-door-lights lab-door-lights-right">
            <div class="lab-door-light-housing"><div class="lab-door-light"></div></div>
            <div class="lab-door-light-housing"><div class="lab-door-light"></div></div>
            <div class="lab-door-light-housing"><div class="lab-door-light"></div></div>
        </div>

        <div id="lab-door-wheel" class="lab-door-wheel">
            <div class="lab-door-wheel-spoke" style="transform: rotate(0deg)"></div>
            <div class="lab-door-wheel-spoke" style="transform: rotate(60deg)"></div>
            <div class="lab-door-wheel-spoke" style="transform: rotate(120deg)"></div>
        </div>
    </div>

    <div id="lab-door-seam" class="lab-door-seam"></div>

    <div id="lab-door-label" class="lab-door-label">ДОСТУП&nbsp;ЗАБЛОКИРОВАН</div>
</div>

<div id="overdue-card" class="lab-panel max-w-2xl mx-4 sm:mx-auto mt-16 sm:mt-28 relative z-[1000]" style="opacity:0; visibility:hidden;">
    <div class="lab-panel-strip"></div>
    <div class="lab-panel-eyebrow">Терминал оплаты · доступ закрыт</div>
    <h1 class="lab-panel-title">Доступ к курсу приостановлен</h1>
    <p class="lab-panel-text">
        Курс «{{ $course->title }}» — платёж просрочен
        @if($dueAt) (срок был {{ $dueAt->format('d.m.Y') }}) @endif.
    </p>

    @if ($errors->any())
        <div class="lab-panel-error">
            @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-3">
        <a href="{{ route('checkout.course.show', $course) }}" class="lab-btn lab-btn-primary flex-1">
            Оплатить сейчас
        </a>

        @if($promiseAvailable)
            <form method="POST" action="{{ route('billing.promise', $course) }}" class="flex-1">
                @csrf
                <button type="submit" class="lab-btn lab-btn-secondary w-full">
                    Взять обещанный платёж (5 дней)
                </button>
            </form>
        @else
            <div class="lab-btn lab-btn-disabled flex-1">
                Обещанный платёж уже использован в этом цикле
            </div>
        @endif
    </div>
</div>

<style>
    #lab-door-overlay {
        /* Общий запас на нахлёст створок — используется и в отступах самих
           дверей, и в позиции шва, чтобы они не могли разъехаться. */
        --door-overlap: 40px;

        /* .lab-door-overlay — fixed inset-0, размером ровно с экран. Момент
           удара трясёт именно этот блок целиком (translateX), а у ровно
           экранного блока сдвиг по X на мгновение обнажает полоску реальной
           страницы за краем — оттуда и было видно "что за дверями". Оверскан
           (-20px со всех сторон) даёт запас больше амплитуды тряски (±10px),
           так что при сдвиге всегда виден тот же оверлей, а не фон под ним.
           Оверскан симметричный, поэтому 50%-позиционирование дверей и шва
           внутри (считается от границ ЭТОГО блока) остаётся привязано
           к истинному центру экрана — сам центр расширение не двигает. */
        top: -20px;
        right: -20px;
        bottom: -20px;
        left: -20px;
    }
    .lab-door {
        position: absolute;
        top: 0;
        bottom: 0;
        overflow: hidden;
        background:
            repeating-linear-gradient(97deg, rgba(255,255,255,0.03) 0 1px, transparent 1px 4px),
            radial-gradient(ellipse at 30% 0%, rgba(255,255,255,0.10) 0%, transparent 55%),
            linear-gradient(90deg, rgba(0,0,0,0.45) 0%, transparent 5%, transparent 95%, rgba(0,0,0,0.45) 100%),
            linear-gradient(180deg, rgba(255,255,255,0.08) 0%, transparent 10%, transparent 88%, rgba(0,0,0,0.35) 100%),
            linear-gradient(160deg, #4d545c 0%, #2d3238 42%, #17191c 100%);
        box-shadow: inset 0 0 140px rgba(0,0,0,0.75), inset 0 0 2px rgba(255,255,255,0.18);
    }
    /* Обе створки заданы от одной и той же точки — ровно 50% ширины экрана —
       и расширены НАВСТРЕЧУ друг другу на --door-overlap, чтобы гарантированно
       перекрываться и не давать щели. Правая створка идёт в разметке позже
       левой, поэтому в зоне нахлёста она рисуется поверх и полностью
       перекрывает левую — а значит реальная видимая граница между текстурами
       дверей проходит не по математической середине нахлёста, а по
       СОБСТВЕННОМУ левому краю правой створки, то есть на --door-overlap
       левее центра экрана. Декоративный шов (.lab-door-seam) должен стоять
       именно там, а не ровно на 50%. */
    .lab-door-left {
        left: 0;
        right: 50%;
        margin-right: calc(-1 * var(--door-overlap));
    }
    .lab-door-right {
        right: 0;
        left: 50%;
        margin-left: calc(-1 * var(--door-overlap));
    }
    /* Ряд заклёпок вдоль внутреннего края каждой створки — деталь тяжёлой
       бронедвери. Стоит за пределами зоны нахлёста (40px), поэтому не
       перекрывается соседней створкой и всегда видна целиком. */
    .lab-door-left::after,
    .lab-door-right::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        width: 10px;
        background-image: radial-gradient(circle, rgba(255,255,255,0.45) 0 2px, rgba(0,0,0,0.6) 2.5px 3.5px, transparent 4px);
        background-size: 10px 68px;
        background-repeat: repeat-y;
        opacity: 0.8;
        pointer-events: none;
    }
    .lab-door-left::after { right: 70px; background-position: 0 18px; }
    .lab-door-right::before { left: 70px; background-position: 0 50px; }

    .lab-door-panel-grid {
        position: absolute;
        inset: 0;
        background-image:
            radial-gradient(circle, rgba(255,255,255,0.28) 2.5px, transparent 3px),
            repeating-linear-gradient(0deg, rgba(0,0,0,0.35) 0 3px, transparent 3px 140px),
            repeating-linear-gradient(90deg, rgba(0,0,0,0.35) 0 3px, transparent 3px 140px);
        background-size: 140px 140px, 140px 140px, 140px 140px;
        background-position: 20px 20px, 0 0, 0 0;
        opacity: 0.9;
    }
    /* Стык створок — самостоятельный элемент. Его позиция — НЕ 50% экрана:
       правая створка стоит в разметке позже левой и потому в зоне нахлёста
       рисуется поверх неё, полностью закрывая левую створку до своего
       собственного левого края. Значит реальная видимая граница между
       текстурами дверей проходит по левому краю правой створки — это ровно
       центр минус --door-overlap, а не центр экрана. Если поставить шов
       на 50%, он окажется на --door-overlap правее настоящей границы.

       Раньше тут был симметричный градиент "тёмный край — светлый центр —
       тёмный край" — это ровно та заливка, которой рисуют ВЫПУКЛУЮ хромовую
       трубу (блик там, куда бьёт свет на изгибе цилиндра), поэтому стык и
       читался как труба, а не как щель. У настоящего провала между двумя
       плитами свет в самую глубину не попадает — там должно быть темнее
       всего. Блик, если и есть, — тонкая полоска с ОДНОГО края (кромка
       соседней створки, поймавшая свет), а не по центру. */
    .lab-door-seam {
        position: absolute;
        top: 0;
        bottom: 0;
        left: calc(50% - var(--door-overlap));
        width: 10px;
        transform: translateX(-50%);
        z-index: 1;
        opacity: 0;
        background: #0a0b0c;
        box-shadow:
            inset 2px 0 3px rgba(0,0,0,0.9),
            inset -2px 0 3px rgba(0,0,0,0.9),
            -7px 0 12px -6px rgba(0,0,0,0.8),
            7px 0 12px -6px rgba(0,0,0,0.8);
    }
    .lab-door-seam::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        right: -1px;
        width: 1px;
        background: rgba(255,255,255,0.22);
    }

    /* Штурвал — часть правой двери, едет вместе с ней. Стиль — простой,
       как в первой версии (без кольца болтов и ручки). Стоит ближе к стыку
       (к внутреннему/левому краю правой створки), а не у внешнего края.
       По вертикали — ближе к низу экрана, а не строго по центру: карточка
       оплаты появляется поверх дверей примерно в верхней половине экрана,
       и штурвал на прежнем месте (ровно в 50% высоты) почти всегда
       оказывался прямо под ней и был не виден. */
    .lab-door-wheel {
        position: absolute;
        bottom: 10%;
        left: 60px;
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: radial-gradient(circle at 50% 50%, #33363b, #1a1c1f 55%, #0c0d0e 100%);
        box-shadow: 0 0 0 4px #17181a, 0 8px 24px rgba(0,0,0,0.6), inset 0 0 12px rgba(0,0,0,0.6);
        opacity: 0;
        z-index: 2;
    }
    /* Ступица теперь стоит НАД спицами (явный z-index выше, чем auto у спиц),
       а не под ними — иначе спицы перекрывали её и место их схождения
       выглядело неряшливо. Светлая крышка-болт (::after) — ещё на уровень
       выше, поверх самой ступицы. Порядок снизу вверх: спицы → ступица →
       крышка-болт. */
    .lab-door-wheel::before {
        content: '';
        position: absolute;
        inset: 30px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 30%, #9a9fa6, #2a2d31 70%);
        box-shadow: inset 0 0 8px rgba(0,0,0,0.7);
        z-index: 1;
    }
    .lab-door-wheel::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 22px;
        height: 22px;
        margin: -11px 0 0 -11px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 30%, #b7bcc3, #4a4e54 60%, #1c1e21 100%);
        box-shadow: 0 2px 4px rgba(0,0,0,0.6), inset 0 0 3px rgba(0,0,0,0.5);
        z-index: 2;
    }
    .lab-door-wheel-spoke {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 96px;
        height: 10px;
        margin: -5px 0 0 -48px;
        background: linear-gradient(90deg, #8a8f96, #dde1e4, #8a8f96);
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }

    /* По три лампочки на каждой створке, подальше от стыка — у внешнего
       края своей двери, а не в центре экрана. Каждая тройка сидит на своей
       серой монтажной пластине, а не прямо на металле двери. */
    .lab-door-lights {
        position: absolute;
        top: 6%;
        display: flex;
        gap: 20px;
        padding: 8px 12px;
        background: linear-gradient(180deg, #4a4d52, #34363a);
        border-radius: 6px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.08);
        z-index: 2;
    }
    .lab-door-lights-left { left: 50px; }
    .lab-door-lights-right { right: 50px; }
    /* Лампочка спрятана под стекло: тёмный врезной корпус-бортик всегда на
       месте (часть "железа" двери), а сама лампа внутри загорается вместе
       с остальным механизмом. Блик — отдельный ::after поверх лампы, не
       трогается GSAP-твином (тот анимирует только .lab-door-light), поэтому
       не ломается, когда меняется backgroundColor/boxShadow самой лампы. */
    .lab-door-light-housing {
        position: relative;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #0e0f10;
        box-shadow: 0 0 0 2px #1e2023, inset 0 1px 2px rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lab-door-light-housing::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 3.5px;
        width: 6px;
        height: 4px;
        border-radius: 50%;
        background: rgba(255,255,255,0.45);
        pointer-events: none;
    }
    .lab-door-light {
        width: 13px;
        height: 13px;
        border-radius: 50%;
        background: #5c1414;
        box-shadow: none;
        opacity: 0;
    }

    /* Табличка "ДОСТУП ЗАБЛОКИРОВАН" — с фоном и рамкой, как настоящая
       привинченная металлическая табличка, а не просто светящийся текст. */
    .lab-door-label {
        position: absolute;
        top: 9%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.85);
        padding: 10px 24px;
        background: linear-gradient(180deg, #2b2e32 0%, #16171a 100%);
        border: 2px solid #5a5e65;
        border-radius: 4px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
        color: #fff;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 700;
        font-size: 22px;
        letter-spacing: 0.12em;
        text-shadow: 0 0 14px rgba(255,45,45,0.9);
        opacity: 0;
        white-space: nowrap;
        z-index: 3;
    }
    .lab-door-label::before,
    .lab-door-label::after {
        content: '';
        position: absolute;
        top: 7px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 30%, #9a9fa6, #3c4046);
    }
    .lab-door-label::before { left: 7px; }
    .lab-door-label::after { right: 7px; }

    .lab-panel {
        position: relative;
        overflow: hidden;
        padding: 32px 28px 28px;
        background:
            linear-gradient(180deg, rgba(255,255,255,0.06) 0%, transparent 14%),
            linear-gradient(160deg, #3a3f46 0%, #24272c 55%, #16181b 100%);
        border: 2px solid #565b62;
        border-radius: 6px;
        box-shadow:
            0 24px 60px rgba(0,0,0,0.55),
            0 0 0 1px rgba(0,0,0,0.4),
            inset 0 1px 0 rgba(255,255,255,0.08);
    }
    .lab-panel::before,
    .lab-panel::after {
        content: '';
        position: absolute;
        top: 16px;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: radial-gradient(circle at 35% 30%, #9a9fa6, #3c4046);
        box-shadow: 0 1px 2px rgba(0,0,0,0.6);
        z-index: 1;
    }
    .lab-panel::before { left: 14px; }
    .lab-panel::after { right: 14px; }
    .lab-panel-strip {
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 6px;
        background: repeating-linear-gradient(135deg, #f2c200 0 10px, #17181a 10px 20px);
    }
    .lab-panel-eyebrow {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: #f2c200;
        margin-bottom: 10px;
    }
    .lab-panel-title {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 24px;
        font-weight: 700;
        color: #f5f6f7;
        letter-spacing: 0.01em;
        margin-bottom: 12px;
    }
    .lab-panel-text {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 14px;
        color: #a8adb4;
        line-height: 1.6;
        margin-bottom: 22px;
    }
    .lab-panel-error {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 13px;
        color: #ff9c9c;
        background: rgba(255,45,45,0.1);
        border: 1px solid rgba(255,45,45,0.35);
        border-radius: 4px;
        padding: 10px 12px;
        margin-bottom: 18px;
    }
    .lab-btn {
        display: block;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 700;
        font-size: 13px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 14px 16px;
        border-radius: 4px;
        text-align: center;
        cursor: pointer;
        border: none;
        transition: filter 0.15s ease, transform 0.05s ease;
    }
    .lab-btn:active { transform: translateY(1px); }
    .lab-btn-primary {
        background: linear-gradient(180deg, #ffd54a, #f2c200);
        color: #1a1a1a;
        box-shadow: 0 3px 0 #a17f00, 0 6px 14px rgba(0,0,0,0.4);
    }
    .lab-btn-primary:hover { filter: brightness(1.08); }
    .lab-btn-secondary {
        background: linear-gradient(180deg, #4a4f56, #2c3036);
        color: #f5f6f7;
        border: 1px solid #6a6f76;
        box-shadow: 0 3px 0 #17181a, 0 6px 14px rgba(0,0,0,0.4);
    }
    .lab-btn-secondary:hover { filter: brightness(1.15); }
    .lab-btn-disabled {
        background: rgba(255,255,255,0.04);
        color: #8a8f96;
        border: 1px dashed #4a4f56;
        font-weight: 500;
        text-transform: none;
        letter-spacing: normal;
        cursor: default;
    }

    @media (max-width: 640px) {
        .lab-door-label { font-size: 14px; padding: 8px 16px; }
        .lab-door-lights { gap: 10px; padding: 6px 8px; }
        .lab-door-lights-left { left: 24px; }
        .lab-door-lights-right { right: 24px; }
        .lab-door-light-housing { width: 14px; height: 14px; }
        .lab-door-light-housing::after { top: 1.5px; left: 2.5px; width: 5px; height: 3px; }
        .lab-door-light { width: 10px; height: 10px; }
        .lab-door-wheel { width: 60px; height: 60px; left: 30px; bottom: 8%; }
        .lab-door-wheel::before { inset: 18px; }
        .lab-door-wheel::after { width: 16px; height: 16px; margin: -8px 0 0 -8px; }
        .lab-door-wheel-spoke { width: 64px; margin: -4px 0 0 -32px; }
        .lab-door-left::after,
        .lab-door-right::before { width: 8px; background-size: 8px 54px; }
        .lab-door-left::after { right: 44px; }
        .lab-door-right::before { left: 44px; }
        .lab-panel { padding: 26px 18px 22px; }
        .lab-panel-title { font-size: 19px; }
    }

    /* Отдельная ось адаптива — высота вьюпорта, а не ширина: телефон в
       альбомной ориентации может быть широким (мобильный брейкпоинт по
       width не сработает), но очень низким, и всё, что привязано к % от
       высоты экрана (лампочки, табличка, штурвал) и к отступу карточки
       сверху, начинает теснить друг друга. */
    @media (max-height: 520px) {
        #overdue-card { margin-top: 12px; }
        .lab-panel { padding: 20px 18px 18px; }
        .lab-panel-text { margin-bottom: 14px; }
        .lab-door-label { top: 7%; font-size: 13px; padding: 6px 14px; }
        .lab-door-lights { top: 5%; }
        .lab-door-wheel { bottom: 6%; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('lab-door-overlay');
    var card = document.getElementById('overdue-card');
    if (!overlay || !card) return;

    function revealCardInstantly() {
        overlay.remove();
        card.style.opacity = 1;
        card.style.visibility = 'visible';
    }

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || typeof window.gsap === 'undefined') {
        revealCardInstantly();
        return;
    }

    function playClank() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            var ctx = new Ctx();
            var now = ctx.currentTime;

            var thud = ctx.createOscillator();
            var thudGain = ctx.createGain();
            thud.type = 'square';
            thud.frequency.setValueAtTime(90, now);
            thud.frequency.exponentialRampToValueAtTime(40, now + 0.25);
            thudGain.gain.setValueAtTime(0.0001, now);
            thudGain.gain.exponentialRampToValueAtTime(0.35, now + 0.02);
            thudGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
            thud.connect(thudGain).connect(ctx.destination);
            thud.start(now);
            thud.stop(now + 0.36);

            var clang = ctx.createOscillator();
            var clangGain = ctx.createGain();
            clang.type = 'triangle';
            clang.frequency.setValueAtTime(1200, now);
            clangGain.gain.setValueAtTime(0.0001, now);
            clangGain.gain.exponentialRampToValueAtTime(0.08, now + 0.01);
            clangGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.15);
            clang.connect(clangGain).connect(ctx.destination);
            clang.start(now);
            clang.stop(now + 0.16);
        } catch (e) {}
    }

    function playClick() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            var ctx = new Ctx();
            var now = ctx.currentTime;

            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'square';
            osc.frequency.setValueAtTime(1800, now);
            osc.frequency.exponentialRampToValueAtTime(600, now + 0.05);
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.18, now + 0.005);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.07);
            osc.connect(gain).connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.08);
        } catch (e) {}
    }

    try {
        var left = document.getElementById('lab-door-left');
        var right = document.getElementById('lab-door-right');
        var wheel = document.getElementById('lab-door-wheel');
        var lights = overlay.querySelectorAll('.lab-door-light');
        var label = document.getElementById('lab-door-label');
        var seam = document.getElementById('lab-door-seam');

        // Начальное положение дверей задаём через сам GSAP (а не через CSS
        // transform в стилях) — иначе GSAP при первом твине xPercent иногда
        // неверно считывает уже применённый CSS-трансформ и решает, что
        // дверь уже на месте, и не двигает её вообще.
        gsap.set(left, { xPercent: -100 });
        gsap.set(right, { xPercent: 100 });

        // Только двери, во весь экран, выезжают и закрываются. Карточка
        // появляется ПОВЕРХ дверей и только после того, как они полностью
        // сомкнулись — двери не открываются обратно, остаются закрытым фоном.
        var tl = gsap.timeline();

        // Створки едут не идеально синхронно (правая стартует на 0.06с позже) —
        // так тяжёлый механизм не читается как одна цифровая анимация. Каждая
        // чуть заходит за центр (xPercent 2/-2), а не жёстко стопорится ровно
        // на 0 — упругая отдача сразу после удара добавляет ощущение массы.
        tl.to(left,  { xPercent: 2,  duration: 1.05, ease: 'power3.in' }, 0)
          .to(right, { xPercent: -2, duration: 1.05, ease: 'power3.in' }, 0.06)
          .addLabel('impact', 1.11)
          .call(playClank, null, 'impact')
          .to(overlay, {
              duration: 0.35,
              keyframes: { x: [0, -10, 8, -6, 4, 0] },
              ease: 'power1.inOut',
          }, 'impact')
          .to(left,  { xPercent: 0, duration: 0.4, ease: 'elastic.out(1, 0.5)' }, 'impact')
          .to(right, { xPercent: 0, duration: 0.4, ease: 'elastic.out(1, 0.5)' }, 'impact+=0.03')
          // Штурвал делает два полных оборота перед фиксацией на прежнем
          // финальном угле (220°), а не один поворот на месте. Тормозит не
          // резко, а долго и плавно выкатывается — power4.out даёт длинный
          // "инерционный" хвост затухания, а не короткое торможение, как
          // было у power2.out. Щелчок — почти в самом конце этого хвоста,
          // когда он визуально садится в паз.
          .to(wheel, { opacity: 1, rotate: 940, duration: 1.3, ease: 'power4.out' }, 'impact+=0.1')
          .call(playClick, null, 'impact+=1.25')
          .to(seam, { opacity: 1, duration: 0.25 }, 'impact+=0.2')
          .to(lights, { opacity: 1, duration: 0.15 }, 'impact+=0.2')
          .to(label, { opacity: 1, scale: 1, duration: 0.35, ease: 'back.out(1.7)' }, 'impact+=0.9')
          .call(function () {
              // Лампочки мигают постоянно, пока экран заблокирован — отдельный
              // бесконечный твин, не встроенный в основной таймлайн с концом.
              gsap.to(lights, {
                  backgroundColor: '#ff2d2d',
                  boxShadow: '0 0 20px 6px rgba(255,45,45,0.9)',
                  duration: 0.45,
                  repeat: -1,
                  yoyo: true,
                  stagger: 0.15,
              });
          })
          .set(card, { visibility: 'visible' })
          .fromTo(card, { opacity: 0, y: 16, scale: 0.96 }, { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: 'power2.out' }, '+=0.2');
    } catch (e) {
        revealCardInstantly();
    }
});
</script>
@endsection
