{{-- Общие стили станции «Задания» (.eg*). Свои, не Tailwind: не зависят
     от пересборки CSS. Визуальный язык — как у «Верных суждений». --}}
<style>
  .eg-page { max-width: 760px; margin: 0 auto; padding: 24px 16px 40px; }
  .eg-h1 { font-size: 26px; font-weight: 600; color: #0F1E29; margin: 0 0 14px; }
  .eg-card { padding: 18px; border-radius: 20px; background: #fff; border: 1px solid #DCEAF3; box-shadow: 0 1px 2px rgba(11, 102, 162, .05), 0 4px 16px rgba(11, 102, 162, .06); }
  .eg-card + .eg-card { margin-top: 14px; }
  .eg-card-title { font-size: 19px; font-weight: 600; color: #0F1E29; }
  .eg-muted { color: #657B8B; font-size: 14px; }
  .eg-notice { margin-bottom: 14px; padding: 12px 14px; border-radius: 14px; border: 1px solid #DCEAF3; background: #F8FAFC; font-size: 14px; color: #344856; }
  .eg-error { margin-top: 8px; font-size: 14px; color: #e11d48; }

  /* Вид кнопок — «игровой», общий для тренажёров: partials/game-buttons. */
  .eg-btn-big { width: 100%; min-height: 54px; font-size: 17px; }
  @media (hover: hover) {
  }
  .eg-row { display: flex; flex-wrap: wrap; gap: 8px; }
  @media (min-width: 640px) { .eg-row-desk { flex-wrap: nowrap; } .eg-row-desk > * { flex: 1; } }

  /* Корм сегодня — как .stc в «Верных суждениях» */
  .egc { position: relative; display: flex; align-items: center; gap: 12px; margin-bottom: 14px; padding: 12px 14px; border-radius: 18px; border: 1px solid #DCEAF3; background: #fff; box-shadow: 0 1px 2px rgba(11, 102, 162, .05), 0 4px 16px rgba(11, 102, 162, .06); }
  .egc-text { flex: 1; min-width: 0; line-height: 1.3; }
  .egc-text b { display: block; font-size: 15px; font-weight: 600; color: #0F1E29; }
  .egc-text span { font-size: 13px; color: #657B8B; }
  .egc-wallet { flex: none; display: inline-flex; align-items: center; gap: 6px; height: 28px; box-sizing: border-box; background: #fff; border: 1px solid #DCEAF3; border-radius: 999px; padding: 3px 12px 3px 6px; font-size: 14px; color: #0F1E29; white-space: nowrap; }
  .egc-wallet img { width: 18px; height: 18px; }
  /* Слоты, как в «Верных суждениях»: с кормом — раунд (отправка) ещё есть, ✓ — использован. */
  /* Слоты чуть внахлёст — белое кольцо отделяет соседей. */
  .egc-slots { display: flex; flex: none; }
  .egc-slot { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; box-shadow: 0 0 0 2px #fff; }
  .egc-slot + .egc-slot { margin-left: -9px; }
  .egc-slot.is-free { background: #eff6ff; box-shadow: 0 0 0 2px #fff, inset 0 0 0 1.5px #bfdbfe; }
  .egc-slot.is-free img { width: 26px; height: 26px; }
  .egc-slot.is-used { background: #F1F6F9; color: #94A9B8; font-size: 18px; font-weight: 700; }
  .egc-earned { display: inline-flex; align-items: center; gap: 3px; }
  .egc-earned b { display: inline !important; color: #15803d; font-size: 13px !important; }
  .egc-earned img { width: 16px; height: 16px; }
  /* Телефон: сверху текст и «?», снизу слоты и баланс — в одну строку. */
  @media (max-width: 560px) {
    .egc { display: grid; grid-template-columns: 1fr auto auto; grid-template-areas: "text text text" "slots help wallet"; column-gap: 8px; row-gap: 10px; }
    .egc-text { grid-area: text; }
    .egc-help { grid-area: help; align-self: center; width: 26px; height: 26px; }
    .egc-slots { grid-area: slots; align-self: center; }
    .egc-slot + .egc-slot { margin-left: -7px; }
    .egc-wallet { grid-area: wallet; align-self: center; }
    .egc-slot { width: 28px; height: 28px; border-radius: 9px; font-size: 15px; }
    .egc-slot.is-free img { width: 19px; height: 19px; }
  }
  .egc-help { flex: none; width: 28px; height: 28px; border-radius: 99px; border: 1px solid #DCEAF3; background: #fff; color: #657B8B; font-size: 13px; font-weight: 700; cursor: pointer; }
  .egc-tiers { position: absolute; z-index: 20; top: calc(100% + 6px); right: 0; width: 250px; padding: 12px 14px; border-radius: 14px; border: 1px solid #DCEAF3; background: #fff; box-shadow: 0 10px 30px rgba(15, 23, 42, .12); font-size: 14px; color: #344856; }
  .egc-tiers div { display: flex; justify-content: space-between; padding: 3px 0; }
  .egc-tiers p { margin: 6px 0 0; padding-top: 8px; border-top: 1px solid #E2EEF6; font-size: 12px; color: #657B8B; }

  /* Продолжить / Повторить */
  .eg-action { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; padding: 12px 12px 12px 16px; border-radius: 18px; background: #fff; border: 1px solid #bfdbfe; box-shadow: 0 4px 16px rgba(37, 99, 235, .08); }
  .eg-action-text { flex: 1; min-width: 0; line-height: 1.3; }
  .eg-action-text b { display: block; font-size: 15px; font-weight: 600; color: #0F1E29; }
  .eg-action-text span { display: block; font-size: 13px; color: #657B8B; }

  /* Части */
  .eg-tabs { display: flex; gap: 4px; padding: 4px; margin-bottom: 14px; border-radius: 14px; background: #F1F6F9; }
  .eg-tab { flex: 1; text-align: center; padding: 9px 0; border-radius: 10px; font-size: 15px; font-weight: 600; color: #485C6A; text-decoration: none; }
  .eg-tab.is-on { background: #fff; color: #0F1E29; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
  /* «Часть 1» и диапазон номеров — разные вещи: диапазон отдельной плашкой. */
  .eg-tab small { display: inline-block; margin-left: 6px; padding: 1px 8px; border-radius: 99px; background: #E1E9EF; color: #485C6A; font-size: 12px; font-weight: 600; vertical-align: 1px; }
  .eg-tab.is-on small { background: #EAF3FD; color: #1d4ed8; }

  /* Плитки номеров */
  /* Плитки номеров: телефон — 2 в ряд, шире — 3, ПК — 5. */
  .eg-nums { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
  @media (min-width: 480px) { .eg-nums { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (min-width: 640px) { .eg-nums { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
  .eg-num { position: relative; display: flex; flex-direction: column; align-items: flex-start; min-height: 78px; padding: 10px 10px 10px 12px; border-radius: 14px; border: 1.5px solid #DCEAF3; background: #fff; cursor: pointer; user-select: none; text-align: left; }
  .eg-num input { position: absolute; opacity: 0; pointer-events: none; }
  .eg-num-n { display: inline-flex; align-items: center; gap: 4px; font-size: 24px; font-weight: 500; line-height: 1; color: #0F1E29; }
  /* Эмодзи не перекрасить — делаем ярче фильтром. */
  .eg-num-b { display: block; width: 100%; margin-top: 3px; font-size: 12px; line-height: 1.25; color: #657B8B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .eg-num-star { font-size: 16px; filter: saturate(1.8) brightness(1.08); }
  /* Плитка: номер → название (главное) → блок одной строкой → «Решено» (бледнее всего). */
  .eg-num-t { margin-top: 6px; font-size: 14px; font-weight: 600; color: #0F1E29; line-height: 1.2; }
  .eg-num-s { margin-top: auto; padding-top: 8px; font-size: 11px; color: #94A9B8; }
  .eg-num-s .due { color: #c2410c; }
  .eg-num.is-off { opacity: .4; cursor: default; }
  .eg-num-all { background: #eff6ff; border-color: #bfdbfe; }
  .eg-num-all .eg-num-n { font-size: 20px; color: #1d4ed8; }
  .eg-num:has(input:checked), .eg-num-all[aria-pressed="true"] { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .1); }
  .eg-num:has(input:focus-visible) { outline: 2px solid #2563eb; outline-offset: 2px; }
  @media (hover: hover) { .eg-num:not(.is-off):not(:has(input:checked)):not([aria-pressed="true"]):hover { border-color: #93c5fd; } }

  /* Блоки — чипы */
  .eg-sub { margin-top: 16px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94A9B8; }
  .eg-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .eg-chip { position: relative; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; border: 1.5px solid #DCEAF3; background: #fff; font-size: 14px; color: #344856; cursor: pointer; user-select: none; }
  .eg-chip input { position: absolute; opacity: 0; pointer-events: none; }
  .eg-chip:has(input:checked) { border-color: #2563eb; color: #1d4ed8; background: #f8fbff; }
  .eg-chip-note { font-size: 12px; color: #94A9B8; margin-top: 6px; }
  /* Часть 1: выбран блок — номера берутся все сами, плитки приглушены. */
  .eg-nums.is-auto .eg-num { opacity: .5; }
  .eg-or { display: flex; align-items: center; gap: 10px; margin-top: 16px; font-size: 13px; font-weight: 600; color: #94A9B8; text-transform: uppercase; letter-spacing: .04em; }
  .eg-or::before, .eg-or::after { content: ''; flex: 1; height: 1px; background: #E2EEF6; }

  /* На проверке */
  .eg-rev { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-top: 1px solid #E2EEF6; text-decoration: none; color: inherit; }
  .eg-rev:first-of-type { border-top: 0; }
  .eg-rev-n { flex: none; width: 36px; height: 36px; border-radius: 10px; background: #F1F6F9; display: grid; place-items: center; font-weight: 700; color: #344856; }
  .eg-rev-text { flex: 1; min-width: 0; font-size: 14px; color: #344856; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .eg-rev-status { flex: none; font-size: 13px; color: #657B8B; }
  .eg-rev-status.is-checked { color: #15803d; font-weight: 600; }
  .eg-rev-new { width: 8px; height: 8px; border-radius: 99px; background: #2563eb; flex: none; }

  /* Текст задания */
  .eg-q { white-space: pre-line; font-size: 16px; line-height: 1.55; color: #0F1E29; }
  .eg-img { display: block; max-width: 100%; height: auto; margin: 12px auto 0; border-radius: 12px; border: 1px solid #E2EEF6; }
  .eg-badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: #F1F6F9; font-size: 13px; font-weight: 600; color: #344856; }
</style>
