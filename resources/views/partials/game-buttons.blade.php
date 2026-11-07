{{-- resources/views/partials/game-buttons.blade.php
     «Игровые» кнопки тренажёров — те же, что под рыбой на главной
     (.fish-btn в student/dashboard.blade.php): бледная заливка, обводка
     снаружи и объём внутри — блик сверху, полоса потемнее снизу; при
     нажатии объём уходит. Подключается в layouts/main после app.css.

     Классы: .game-btn — основа (серо-голубая, как кнопки тренажёров на
     главной); --main — голубая, как «Покормить» (главное действие);
     --ghost — снова серо-голубая (перебивает --main); --yes / --no —
     зелёная / красная (ответ «Верно» / «Неверно»).

     Старые классы тренажёров перечислены здесь же, чтобы не переписывать
     разметку: «Задания» (.eg-btn, .eg-btn-ghost), «Верные суждения»
     (.st-btn*, .st-action-btn, .st-go). В «Терминах» и «Датах» —
     x-ui.button с variant="game" / "game-main". Размеры кнопок (ширина,
     высота) — по-прежнему в стилях самих экранов. --}}
<style>
  .game-btn, .eg-btn, .st-btn, .st-action-btn, .st-go {
    --fill: #F6F8FA; --hover: #EEF2F5; --line: #C9D3DB; --low: #D5DEE5;
    position: relative; display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
    min-height: 2.75rem; padding: 0 1rem;
    border: 1.5px solid var(--line); border-radius: .875rem; background: var(--fill);
    font-weight: 600; font-size: 1rem; line-height: 1.1; color: #1D2E3A; text-align: center;
    text-decoration: none; cursor: pointer;
    box-shadow: inset 0 2px 0 rgba(255, 255, 255, .9), inset 0 -4px 0 var(--low);
    transition: transform .08s ease, box-shadow .08s ease, background-color .15s ease;
  }
  .game-btn--main, .eg-btn, .st-btn-main, .st-action-btn, .st-go { --fill: #EAF3FD; --hover: #DDEBFB; --line: #8DB8E8; --low: #C3DAF3; }
  .game-btn--ghost, .eg-btn-ghost, .st-btn-ghost { --fill: #F6F8FA; --hover: #EEF2F5; --line: #C9D3DB; --low: #D5DEE5; }
  .game-btn--yes, .st-btn-yes { --fill: #F0FDF4; --hover: #DCFCE7; --line: #86EFAC; --low: #B3E3C3; color: #15803D; }
  .game-btn--no, .st-btn-no { --fill: #FEF2F2; --hover: #FEE2E2; --line: #FCA5A5; --low: #FECACA; color: #B91C1C; }

  :is(.game-btn, .eg-btn, .st-btn, .st-action-btn, .st-go):not(:disabled):hover { background: var(--hover); color: #1D2E3A; }
  :is(.game-btn--yes, .st-btn-yes):not(:disabled):hover { color: #15803D; }
  :is(.game-btn--no, .st-btn-no):not(:disabled):hover { color: #B91C1C; }
  :is(.game-btn, .eg-btn, .st-btn, .st-action-btn, .st-go):not(:disabled):active { transform: translateY(1px); box-shadow: inset 0 1px 0 var(--low); }
  :is(.game-btn, .eg-btn, .st-btn, .st-action-btn, .st-go):focus-visible { outline: 3px solid #93C5FD; outline-offset: 2px; }
  :is(.game-btn, .eg-btn, .st-btn, .st-action-btn, .st-go):disabled {
    --fill: #F4F6F8; --hover: #F4F6F8; --line: #DCE3E8; --low: #E9EEF2;
    color: #94A3B8; cursor: not-allowed; transform: none;
  }
</style>
