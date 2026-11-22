{{-- Стили тренажёра личностей (.prs-*). Свои, не Tailwind: не зависят от
     пересборки CSS. Цвета и радиусы — как у станции «Задания» и
     «Верных суждений»; соответствие — тот же вид, что в задании ЕГЭ на
     станции «Задания» (student/ege/round). --}}
<style>
  /* Портрет: квадрат 512×512, лицо по центру. Без портрета — инициалы. */
  .prs-portrait { display: block; width: var(--s, 96px); height: var(--s, 96px); flex: none; border-radius: 10%; object-fit: cover; background: #EEF3F7; box-shadow: 0 1px 2px rgba(15, 30, 41, .06), 0 4px 14px rgba(15, 30, 41, .08); }
  .prs-ph { display: grid; place-items: center; width: var(--s, 96px); height: var(--s, 96px); flex: none; border-radius: 10%; background: linear-gradient(150deg, #E8EFF5, #D9E5EE); color: #657B8B; font-weight: 600; font-size: calc(var(--s, 96px) * .32); letter-spacing: .02em; }
  /* Ещё не изученная личность на карте — серая и полупрозрачная. */
  .prs-locked { filter: grayscale(1); opacity: .45; box-shadow: none; }
  .prs-ph.is-mystery { font-size: calc(var(--s, 96px) * .5); color: #94A9B8; }

  /* Эпоха — подпись, без плашки: рамок на экране и так хватает. */
  .prs-era { display: block; font-size: 13px; font-weight: 600; color: #657B8B; white-space: nowrap; }
  .prs-head { display: flex; align-items: center; gap: 14px; }
  .prs-head-text { min-width: 0; }
  .prs-name { font-size: 22px; line-height: 1.2; font-weight: 600; color: #0F1E29; overflow-wrap: anywhere; }
  .prs-desc { margin-top: 4px; font-size: 15px; line-height: 1.45; color: #485C6A; }
  @media (min-width: 640px) { .prs-name { font-size: 26px; } .prs-desc { font-size: 16px; } }

  /* Карточка знакомства: портрет слева, на телефоне — сверху по центру. */
  .prs-intro { display: grid; gap: 16px; }
  /* Ширина колонки — по портрету: подпись к нему переносится, а не растягивает колонку. */
  .prs-intro-pic { justify-self: center; text-align: center; width: 148px; }
  @media (min-width: 640px) { .prs-intro { grid-template-columns: auto minmax(0, 1fr); gap: 22px; align-items: start; } .prs-intro-pic { justify-self: start; text-align: left; } }
  .prs-intro .prs-head-text { text-align: center; }
  @media (min-width: 640px) { .prs-intro .prs-head-text { text-align: left; } }

  /* «Чем известен» — главное на карточке: то, что спросят в ЕГЭ. */
  /* Заливка без обводки: обводки внутри карточки автор не любит. */
  .prs-assoc { margin-top: 14px; padding: 11px 14px; border-radius: 14px; background: #EEF4FC; text-align: left; }
  .prs-assoc-t { display: block; margin-bottom: 2px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6B8BB3; }
  .prs-assoc-v { font-size: 16px; line-height: 1.4; font-weight: 600; color: #1E3A5F; }
  .prs-related { display: flex; gap: 8px; margin-top: 10px; font-size: 14px; line-height: 1.45; color: #485C6A; text-align: left; }
  .prs-related span:first-child { flex: none; }

  .prs-q { margin: 18px 0 8px; font-size: 14px; font-weight: 600; color: #657B8B; }

  /* «Чем известен?»: после ответа под каждым вариантом — чей он. */
  .prs-owner { display: block; margin-top: 3px; font-size: 12px; font-weight: 600; color: #94A9B8; }
  .prs-owner.is-right { color: #059669; }

  /* «Кто это?»: подсказка по признакам — описание и ассоциация. */
  .prs-clue { }
  .prs-clue-desc { font-size: 16px; line-height: 1.5; color: #0F1E29; }
  @media (min-width: 640px) { .prs-clue-desc { font-size: 17px; } }
  /* Низ «Кто это?»: маленькая кнопка подсказки слева, «Сообщить об ошибке» справа. */
  .prs-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 12px; margin-top: 16px; }
  /* Открытая подсказка — та же плашка того же размера, что кнопка: строка не скачет. */
  .prs-foot .prs-hint { flex: 0 1 auto; gap: 5px; margin: 0; padding: 5px 11px; border-radius: 14px; background: transparent; font-size: 13px; line-height: 18px; font-weight: 500; color: #4A6A93; }
  .prs-report { margin-left: auto; }
  .prs-report[open] { flex-basis: 100%; }
  /* Ссылка «Все личности» на главной тренажёра — плашка, как «Подсказка». */
  .prs-hint-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px; border: 0; border-radius: 14px; background: #EFF6FF; font-size: 13px; line-height: 18px; font-weight: 500; color: #4A6A93; cursor: pointer; transition: background-color .2s; }
  @media (hover: hover) { .prs-hint-btn:hover { background: #DBEAFE; } }
  .prs-hint { display: flex; gap: 6px; font-size: 14px; line-height: 1.45; color: #344856; }

  /* Плитки личностей: портрет и имя, без рамок. После ответа — кольцо вокруг портрета. */
  .prs-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
  @media (min-width: 640px) { .prs-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
  .prs-tile { display: flex; flex-direction: column; align-items: stretch; gap: 8px; width: 100%; padding: 8px 8px 10px; border-radius: 18px; border: 0; background: transparent; text-align: center; font-size: 14px; line-height: 1.25; font-weight: 500; color: #0F1E29; cursor: pointer; transition: background-color .2s, opacity .3s, transform .15s; }
  /* Портрет — во всю ширину плитки, квадрат. */
  .prs-tile .prs-portrait, .prs-tile .prs-ph { width: 100%; height: auto; aspect-ratio: 1 / 1; font-size: 28px; box-shadow: 0 1px 2px rgba(15, 30, 41, .06), 0 4px 14px rgba(15, 30, 41, .08); transition: box-shadow .25s; }
  /* Заливка — только при наведении. */
  @media (hover: hover) { button.prs-tile:hover { background: #EEF3F7; } }
  button.prs-tile:active { transform: scale(.98); }
  .prs-tile.is-right { color: #047857; }
  .prs-tile.is-right .prs-portrait, .prs-tile.is-right .prs-ph { box-shadow: 0 0 0 3px #fff, 0 0 0 6px #34D399; }
  .prs-tile.is-chosen { color: #92400E; }
  .prs-tile.is-chosen .prs-portrait, .prs-tile.is-chosen .prs-ph { box-shadow: 0 0 0 3px #fff, 0 0 0 6px #FBBF24; }
  .prs-tile.is-faded { opacity: .45; }
  .prs-tile:disabled { cursor: default; }

  /* Ввод фамилии: портрет скрыт до ответа. */
  .prs-type-head { display: flex; align-items: flex-start; gap: 14px; }

  /* Соответствие — как в задании ЕГЭ на станции «Задания». */
  .prs-tag { display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 99px; background: #EFF6FF; color: #1D4ED8; font-size: 12px; font-weight: 600; letter-spacing: .02em; }
  .prs-task { margin-top: 10px; font-size: 15px; line-height: 1.5; color: #0F1E29; }
  .prs-match { margin-top: 14px; display: grid; gap: 14px; }
  @media (min-width: 640px) { .prs-match { grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); } }
  .prs-col-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94A9B8; margin-bottom: 6px; }
  .prs-left { display: grid; gap: 8px; }
  .prs-lrow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 10px 12px; border-radius: 12px; border: 1.5px solid #DCEAF3; background: #fff; transition: border-color .3s, background-color .3s; }
  .prs-lrow-text { flex: 1 1 12rem; min-width: 0; font-size: 15px; line-height: 1.4; color: #0F1E29; }
  .prs-lrow-text b { margin-right: 6px; }
  .prs-picks { flex: none; display: flex; flex-wrap: wrap; gap: 4px; }
  .prs-pick { min-width: 30px; height: 30px; padding: 0 4px; border-radius: 8px; font-size: 13px; border: 1.5px solid #DCEAF3; background: #fff; font-weight: 600; color: #485C6A; cursor: pointer; }
  .prs-pick.is-on { background: #BFDBFE; border-color: #93C5FD; color: #1E40AF; }
  @media (hover: hover) { .prs-pick:not(:disabled):not(.is-on):hover { border-color: #93C5FD; } }
  .prs-lrow.r-ok { border-color: #86EFAC; background: #F0FDF4; }
  .prs-lrow.r-bad { border-color: #FCA5A5; background: #FEF2F2; }
  .prs-lrow-fix { flex-basis: 100%; font-size: 13px; font-weight: 600; color: #15803D; }
  .prs-right { list-style: none; margin: 0; padding: 0; display: grid; gap: 7px; }
  .prs-right li { display: flex; gap: 8px; font-size: 15px; line-height: 1.4; color: #0F1E29; }
  .prs-right li span:first-child { flex: none; font-weight: 600; color: #485C6A; min-width: 1.4em; }
  .prs-right li.is-extra { color: #94A9B8; }
  .prs-answer { margin-top: 18px; display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; }
  .prs-answer .pin-field { flex: none; width: calc(4 * 42px + 3 * 12px); }
  .prs-cell { display: flex; flex-direction: column; align-items: center; gap: 4px; }
  .prs-cell .pin-box { width: 100%; transition: border-color .25s, background-color .25s, color .25s; }
  .prs-cell-l { font-size: 12px; font-weight: 600; color: #94A9B8; line-height: 1; }
  .prs-pin-input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; border: 0; padding: 0; font-size: 16px; color: transparent; caret-color: transparent; background: transparent; cursor: text; }
  .prs-answer .pin-box.is-ok { border-color: #16A34A; background: #F0FDF4; color: #15803D; }
  .prs-answer .pin-box.is-bad { border-color: #DC2626; background: #FEF2F2; color: #B91C1C; }
  .prs-check { min-height: 44px; padding: 0 28px; }
  /* Ровно четыре клетки — по буквам, как в бланке. */
  .prs-answer .pin-boxes { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  @media (max-width: 640px) { .prs-check { width: 100%; } .prs-lrow { padding: 9px 10px; } }
  /* Звания Адмирала Клешни: окно «Приказ по флоту» (partials/rank-modal)
     и полоска звания на главной. */
  .prs-rank { position: fixed; inset: 0; z-index: 90; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 30, 41, .55); backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); }
  .prs-rank[hidden] { display: none; }
  /* overflow-x: hidden — погон влетает увеличенным и на миг шире окна. */
  .prs-rank-card { width: 100%; max-width: 420px; max-height: calc(100dvh - 32px); overflow-y: auto; overflow-x: hidden; border-radius: 24px; background: #fff; padding: 22px 22px 20px; text-align: center; box-shadow: 0 20px 60px rgba(15, 30, 41, .3); }
  .prs-rank-head { font-size: 12px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: #8A9BA8; }
  .prs-rank-pics { display: flex; align-items: flex-end; justify-content: center; gap: 10px; margin-top: 10px; }
  .prs-rank-mascot { width: 150px; height: 150px; object-fit: contain; }
  .prs-rank-board { padding-bottom: 14px; }
  .prs-rank-say { position: relative; margin-top: 12px; padding: 12px 14px; border-radius: 16px; background: #F1F4F7; font-size: 15px; line-height: 1.45; color: #23343F; text-align: left; }
  .prs-rank-say::before { content: ''; position: absolute; top: -6px; left: 50%; width: 12px; height: 12px; margin-left: -6px; transform: rotate(45deg); background: #F1F4F7; }
  .prs-rank-skip { font-weight: 600; color: #B7791F; }
  .prs-rank-label { margin-top: 16px; font-size: 13px; color: #8A9BA8; }
  .prs-rank-title { margin-top: 2px; font-size: 28px; line-height: 1.15; font-weight: 600; color: #1F2A44; }
  .prs-rank-note { margin-top: 6px; font-size: 13px; color: #657B8B; }
  @media (max-height: 640px) { .prs-rank-mascot { width: 110px; height: 110px; } .prs-rank-title { font-size: 24px; } }

  /* Чип корма — как .stc-wallet в «Верных суждениях». Два экземпляра:
     ПК — у заголовка, телефон — справа от «Посмотреть всех». Переключение
     своё, не Tailwind (md:inline-flex нет в собранном CSS). */
  .prs-wallet { flex: none; display: inline-flex; align-items: center; gap: 6px; height: 28px; box-sizing: border-box; background: #fff; border: 1px solid #e4e4e7; border-radius: 999px; padding: 3px 12px 3px 6px; font-size: 14px; color: #18181b; white-space: nowrap; }
  .prs-wallet img { width: 18px; height: 18px; }
  .prs-wallet b { font-weight: 600; }
  .prs-wallet-ph { margin-left: auto; }
  .prs-wallet-pc { display: none; }
  @media (min-width: 768px) { .prs-wallet-pc { display: inline-flex; } .prs-wallet-ph { display: none; } }
  .prs-rankbar { display: flex; align-items: center; gap: 14px; padding: 12px 16px 12px 12px; border-radius: 16px; min-width: 0; }
  /* Квадратик с погоном — светлая заливка без обводки, как плитки в карточках. */
  .prs-rankbar-pic { flex: none; display: grid; place-items: center; width: 68px; height: 68px; border-radius: 14px; background: #F1F4F7; }
  .prs-rankbar-now { min-width: 0; flex: 1; }
  .prs-rankbar-t { font-size: 15px; font-weight: 600; color: #1F2A44; }
  .prs-rankbar-n { font-size: 13px; color: #657B8B; }
  .prs-rankbar-line { margin-top: 6px; height: 6px; border-radius: 3px; background: #DDE5EC; overflow: hidden; }
  .prs-rankbar-line span { display: block; height: 100%; border-radius: 3px; background: #E8B931; }
</style>
