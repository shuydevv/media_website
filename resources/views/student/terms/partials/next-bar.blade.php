{{-- resources/views/student/terms/partials/next-bar.blade.php
     После верного ответа экран сам переходит дальше через пару секунд (см.
     #term-auto-next в region.blade.php). Эта полоска показывает, сколько
     осталось, — заполняется ровно за это время (скрипт в session.blade.php,
     scheduleAutoNext()), — а «Дальше» переходит сразу. Заодно она занимает
     место, где до ответа были поле ввода и кнопки: без неё в карточке
     оставалась пустота. --}}
<div class="term-next-bar mt-5">
  <div class="flex items-center justify-between gap-3">
    <span class="text-xs text-zinc-400">Следующее задание</span>
    <button type="button" data-next-now class="text-sm text-blue-600 hover:underline">Дальше</button>
  </div>
  <div class="mt-1.5 h-1 rounded-full bg-gray-100 overflow-hidden">
    <div class="term-next-fill h-full rounded-full bg-emerald-400" style="width: 0%"></div>
  </div>
</div>
