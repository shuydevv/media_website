{{-- resources/views/student/terms/partials/region.blade.php
     Содержимое #terms-app: шапка сессии и текущий шаг ($step: card | a | b |
     c | result). Рендерится и внутри полной страницы, и как htmx-фрагмент.

     Задание лежит в #term-card поверх «стопки» из одной-двух карточек-теней:
     когда номер карточки (data-key) меняется, скрипт в session.blade.php
     отбрасывает старую в сторону, и под ней видна новая. data-flash — что
     произошло в последнем действии, по нему тот же скрипт выбирает анимацию. --}}
{{-- Итогу сессии на широком экране нужны две колонки — он шире задания. --}}
<div class="{{ $step === 'result' ? 'max-w-5xl' : 'max-w-3xl' }} mx-auto px-3 sm:px-4 py-5 sm:py-6">

  <h1 class="sans-medium text-lg sm:text-xl text-zinc-900 mb-4">{{ $kindLabel }}</h1>

  @if($step === 'result')
    @include('student.terms.partials.step-result')
  @else
    @php
      // Рыба сразу приходит в нужном настроении. Радость скрипт через пару
      // секунд сменит на спокойную; задумчивость после ошибки держится, пока
      // ученик не перейдёт к следующему заданию, — поэтому она считается от
      // состояния шага, а не от вспышки (и переживает обновление страницы).
      // Грустной рыбы здесь нет.
      $missed = ($current['reveal'] ?? null) !== null
          || (($current['result'] ?? null) !== null && !$current['result']['correct']);
      $mood = $missed ? 'thinking' : (in_array($flash, ['correct', 'slot']) ? 'correct' : 'default');
    @endphp
    {{-- Рыба, прогресс, серия и реплика — закреплены вне карточки, при
         прокрутке не уезжают. Два вида на одной разметке:

         • Широкий экран (xl): рыба всегда стоит справа от карточки, прогресс —
           кольцом вокруг неё, под ней подпись, серия и реплика.
           25.5rem = половина ширины карточки (max-w-3xl) + отступ.

         • Телефон и планшет: рыбы на экране обычно НЕТ — она появляется
           сверху, только когда ей есть что сказать ($say), и уходит со
           следующим заданием (после неверного ответа — когда ученик нажмёт
           «Далее»). Прогресс и серия — отдельной полоской в самом низу, на
           месте нижнего меню платформы (на экранах сессии меню скрыто, см.
           <style> в session.blade.php).

         Подпись и серия поэтому есть в разметке дважды (под рыбой и в нижней
         полоске) — скрипт ищет их по data-атрибутам, а не по id. Внешний
         div рыбы только позиционирует, анимирует скрипт внутренний #term-hud. --}}
    @php
      $ringLength = 289.03; /* длина окружности кольца: 2π · r, r = 46 */
      $comboClasses = 'text-xs sans-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-2.5 py-0.5 whitespace-nowrap';
    @endphp
    {{-- На телефоне — по всей ширине с отступами и по центру через flex, а не
         left-1/2 + сдвиг: при left:50% блоку доставалась только правая
         половина экрана, и облачко с репликой сжималось в узкую колонку. --}}
    {{-- Высоту на телефоне подставляет скрипт (placeHud() в session.blade.php):
         прямо под шапкой сайта, а когда шапка уехала при прокрутке — у самого
         верха экрана. top-16 — запасное место без скрипта. --}}
    <div id="term-hud-place" class="{{ $say ? 'flex' : 'hidden' }} xl:block fixed z-30 inset-x-4 top-16 justify-center
                xl:inset-x-auto xl:top-28 xl:left-[calc(50%+25.5rem)] xl:w-40">
      <div id="term-hud" class="flex items-center gap-2 xl:flex-col xl:gap-3 xl:text-center">
        <div class="relative shrink-0 w-24 h-24 rounded-full bg-white shadow-lg xl:w-40 xl:h-40 xl:bg-transparent xl:shadow-none">
          <svg class="hidden xl:block absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
            <circle cx="50" cy="50" r="46" fill="none" stroke="#f3f4f6" stroke-width="5"></circle>
            <circle id="term-ring" cx="50" cy="50" r="46" fill="none" stroke="#3b82f6" stroke-width="5" stroke-linecap="round"
                    data-length="{{ $ringLength }}" data-percent="{{ $percent }}"
                    stroke-dasharray="{{ $ringLength }}" stroke-dashoffset="{{ round($ringLength * (1 - $percent / 100), 2) }}"></circle>
          </svg>
          <div class="absolute inset-[4%] xl:inset-[7%] rounded-full bg-blue-50 overflow-hidden">
            <img id="term-mascot" src="{{ $mascot[$mood] }}" data-default-src="{{ $mascot['default'] }}" data-hold="{{ $missed ? 1 : 0 }}"
                 data-preload='@json(array_values($mascot))' alt=""
                 class="w-full h-full object-contain">
          </div>
        </div>

        @if($say)
          <div id="term-bubble" role="status"
               class="max-w-[62vw] rounded-2xl border bg-white shadow-lg px-4 py-2.5 text-sm sans-medium xl:max-w-none xl:w-full
                      {{ $say['ok'] ? 'border-emerald-200 text-emerald-800' : 'border-gray-200 text-zinc-800' }}">
            {{ $say['text'] }}
          </div>
        @endif

        <div class="hidden xl:flex flex-col items-center gap-2 w-full">
          <div data-progress-label class="text-sm text-zinc-600 whitespace-nowrap">{{ $progressLabel }}</div>
          <div data-combo="{{ $combo }}" class="{{ $comboClasses }} {{ $combo >= 2 ? '' : 'hidden' }}">🔥 {{ $combo }} подряд</div>
        </div>
      </div>
    </div>

    {{-- Телефон и планшет: прогресс полоской в самом низу экрана. Заполнение
         полосы — ровно то, что написано в подписи («Выучено 1 из 5» — пятая
         часть), а не номера вопросов: очередь меняется, когда термин
         возвращается на круг. Серия верных подряд — только для азарта,
         корма не даёт; пока её нет, плашка скрыта. --}}
    <div id="term-bottom-strip" class="xl:hidden fixed z-30 inset-x-0 bottom-0 border-t border-gray-200 bg-white/95 backdrop-blur px-4 pt-2.5 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
      <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between gap-3 min-h-[1.5rem]">
          <div data-progress-label class="text-sm text-zinc-600 whitespace-nowrap">{{ $progressLabel }}</div>
          <div data-combo="{{ $combo }}" class="{{ $comboClasses }} {{ $combo >= 2 ? '' : 'hidden' }}">🔥 {{ $combo }} подряд</div>
        </div>
        <div class="mt-1.5 h-2 rounded-full bg-gray-100 overflow-hidden">
          <div id="term-bar" class="h-full rounded-full bg-blue-500" style="width: {{ $percent }}%"></div>
        </div>
      </div>
    </div>

    {{-- Стопка: края следующих карточек выглядывают над текущей, каждая
         чуть уже и на 6px выше предыдущей. --}}
    <div class="relative" style="padding-top: {{ $stackDepth * 6 }}px;">
      @for($i = $stackDepth; $i >= 1; $i--)
        <div class="term-stack-layer absolute rounded-2xl border border-gray-200 bg-white"
             style="left: {{ $i * 10 }}px; right: {{ $i * 10 }}px; top: {{ ($stackDepth - $i) * 6 }}px; bottom: {{ $i * 6 }}px;" aria-hidden="true"></div>
      @endfor

      <div id="term-card" data-key="{{ $cardKey }}" data-flash="{{ $flash }}" data-step="{{ $step }}"
           class="relative rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
        @include('student.terms.partials.step-'.$step)

        @if($autoNext)
          {{-- Верный ответ: кнопки «Далее» нет, скрипт через data-delay сам
               отправит этот запрос (и при обновлении страницы тоже — иначе
               экран завис бы на решённом задании). --}}
          <div id="term-auto-next" data-delay="{{ $autoNext }}"
               hx-post="{{ route('student.terms.session.next', $session) }}"
               hx-trigger="autonext" hx-target="#terms-app" hx-swap="innerHTML"></div>
        @endif
      </div>
    </div>
    {{-- Место под нижнюю полоску прогресса: без него она закрывала бы низ
         карточки с кнопкой «Далее». На xl полоски нет. --}}
    <div class="h-20 xl:hidden" aria-hidden="true"></div>
  @endif

</div>
