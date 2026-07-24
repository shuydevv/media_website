@extends('layouts.main')

@section('back_url', route('student.dashboard'))

@section('content')
{{-- Обычным <style>, не Tailwind-классами с квадратными скобками
     (mt-[clamp(...)]) и не sm:/md:/lg:-вариантами для сетки прошедших
     занятий: в этом браузере часть таких классов ненадёжно применяется —
     та же причина, по которой на дашборде (dashboard.blade.php) сетку
     карточек и ширину колонок в итоге тоже перевели на обычный CSS. --}}
<style>
    .course-hero-divider {
        border-width: 1px;
        border-color: #bfdbfe;
        margin-top: 10px;
        margin-bottom: 28px;
    }
    .course-past-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    @media (min-width: 768px) {
        .course-past-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
    }
    @media (min-width: 1024px) {
        .course-past-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>
{{-- Отступы контейнера — как на остальных student-страницах (dashboard,
     homeworks/index и т.д.): плоский px-4, без sm:/lg: прогрессии. --}}
<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="mb-6">
    <h1 class="sans-medium text-2xl md:text-3xl mb-2 md:mb-3 text-zinc-900">{{ $course->title }}</h1>

    {{-- Сроки курса: надёжный вывод, даже если поля не кастятся к Carbon --}}
    @if(!empty($course->start_date) || !empty($course->end_date))
      <div class="mt-1 text-base text-zinc-600">
        @php
          $fmt = function($v) {
            try { return $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : null; } catch (\Throwable $e) { return $v; }
          };
        @endphp
        Дата: {{ $fmt($course->start_date) ?? '—' }} — {{ $fmt($course->end_date) ?? '—' }}
      </div>
    @endif

    @if(optional($course->category)->name)
      <div class="mt-1 text-sm text-zinc-600">Категория: {{ $course->category->name }}</div>
    @endif

    {{-- @if(!empty($course->description))
      <p class="mt-3 text-sm md:text-base text-gray-600">{{ $course->description }}</p>
    @endif --}}
  </div>

{{-- Ближайшая сессия --}}
<div id="next-session" class="mb-10">
  

  @if(!$nextSession)
    <div class="text-sm text-zinc-600">Ближайшее занятие не запланировано.</div>
  @else
    @php
      $s = $nextSession;
      $lesson = $s->lesson; // может быть null
      // route('student.lessons.show', ...) проверяется один раз здесь и
      // переиспользуется ниже (заголовок + кнопка "Перейти к уроку") —
      // раньше заголовок ссылку на урок не проверял вовсе, а карточки
      // прошедших занятий (ниже) проверяли — несогласованно.
      $lessonHref = $lesson && \Illuminate\Support\Facades\Route::has('student.lessons.show')
          ? route('student.lessons.show', $lesson)
          : null;
    @endphp
    <x-ui.card tone="blue">
      <div class="flex flex-col md:flex-row md:items-stretch md:gap-7 gap-4">
        {{-- Картинка урока: заглушка, если у урока нет image_url --}}
        @if($lesson)
          <div class="w-full md:w-1/2">
            <div class="relative aspect-[16/10]">
              @if($lesson->image_url)
                <img src="{{ $lesson->image_url }}"
                     alt="{{ $lesson->title }}"
                     class="w-full h-full rounded-xl object-cover border border-blue-200">
              @else
                <div class="w-full h-full rounded-xl border border-blue-200 bg-blue-100/50 flex items-center justify-center text-blue-300">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-12 h-12">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <path d="M21 15l-5-5L5 21"></path>
                  </svg>
                </div>
              @endif

              <h2 class="absolute top-3 left-3 bg-white/60 px-3 py-1 rounded-2xl text-xs md:text-base font-medium text-blue-900 mb-3"><img class="inline-block md:mr-2 mr-1 md:w-auto w-4" src=" {{asset('/img/Return.svg')}} " alt="">Следующее занятие</h2>

              @include('student.partials.lesson-image-badges', ['lesson' => $lesson, 'homeworkColor' => $s->_homeworkColor])
            </div>
          </div>
        @endif

        <div class="w-full md:w-1/2 flex flex-col mt-0 md:mt-1 min-w-0">
          {{-- Дата и таймер — в один ряд через flex-wrap: сами встают рядом,
               если места хватает, и переносятся на вторую строку, если нет
               (например, на узком мобильном) — без ручных брейкпоинтов. --}}
          <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <div class="text-sm md:text-base tracking-wide opacity-60 text-blue-800">
              <img class="inline-block relative bottom-0.5 mr-1 w-4 h-4 md:w-5 md:h-5"
                   src="{{ asset('img/Date_range.svg') }}"
                   alt="Date">
              @if($s->display_date)
                {{ \Illuminate\Support\Carbon::parse($s->display_date)->translatedFormat('j F') }}
                @if($s->display_time) в {{ \Illuminate\Support\Str::substr($s->display_time, 0, 5) }} @endif
              @else
                Дата не указана
              @endif
            </div>

            {{-- Живой отсчёт: время старта передаём в ISO8601 со смещением
                 (toIso8601String даёт "+03:00", т.к. $s->_start уже в
                 config('app.timezone') = Europe/Moscow) — так JS парсит его
                 однозначно на любом устройстве, независимо от часового пояса
                 браузера пользователя. Подложка — та же скруглённая пилюля,
                 что и статус-чипы в расписании/событиях дашборда (bg + rounded-
                 full), а не голый текст — так таймер читается как отдельный
                 акцентный элемент, а не ещё одна строка подписи. Иконка —
                 соседний с текстом узел, не внутри #next-session-countdown:
                 JS ниже перезаписывает его через textContent каждую секунду,
                 и если бы иконка была внутри, она бы стиралась при первом же
                 тике. --}}
            @if($s->_start)
              <div class="inline-flex items-center gap-1.5 bg-white/70 rounded-full pl-2.5 pr-3 py-1 text-sm md:text-base font-medium text-blue-900">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2.5"></path></svg>
                <span id="next-session-countdown" data-start="{{ $s->_start->toIso8601String() }}"></span>
              </div>
            @endif
          </div>

          {{-- truncate + min-w-0 — иначе длинное название урока без
               пробелов может распереть колонку шире контейнера (та же
               причина, что уже задокументирована в dashboard.blade.php
               для карточки ближайших событий). --}}
          <div class="mt-3 md:mt-4 md:mb-2 mb-1 min-w-0">
            @if($lesson && $lessonHref)
              <a href="{{ $lessonHref }}"
                 class="block truncate text-3xl md:text-4xl tracking-wide font-medium text-blue-900">
                {{ $lesson->title }}
              </a>
            @elseif($lesson)
              <div class="truncate text-3xl md:text-4xl tracking-wide font-medium text-blue-900">
                {{ $lesson->title }}
              </div>
            @else
              <div class="text-base md:text-lg font-medium text-blue-900">Тема урока пока неизвестна</div>
            @endif
          </div>

          @if($lesson?->description)
            <div class="text-sm md:text-lg tracking-wide text-blue-800 mt-1">
              {{ $lesson->description }}
              {{-- <hr class="mt-8 mb-4 border-1 border-blue-200"> --}}
            </div>
          @endif

            {{-- эластичный «разделитель» свободного пространства:
       на десктопе растягиваем, на мобиле не мешаем естественному потоку --}}
          <div class="md:block hidden flex-1"></div>

          @if($lesson?->description)
            <hr class="course-hero-divider">
          @endif


          @if($lessonHref)
            <div class="mt-8 md:mt-10">
              <x-ui.button href="{{ $lessonHref }}" class="w-full md:w-auto">
                Перейти к уроку
              </x-ui.button>
            </div>
          @endif
        </div>
      </div>
    </x-ui.card>
  @endif
</div>


  {{-- Прошедшие занятия (только с привязанным уроком) --}}
{{-- Прошедшие занятия (группировка по месяцам) --}}
<div id="past-sessions">
  {{-- <h2 class="text-3xl font-semibold font-sans text-gray-900 mb-3">Прошедшие занятия</h2> --}}

  @if(($pastByMonth ?? collect())->isEmpty())
    <div class="text-sm text-zinc-600">
      Прошедших занятий пока нет.
      {{-- @if(($pastHiddenCount ?? 0) > 0)
        <div class="mt-1">
          Есть {{ $pastHiddenCount }} прошедш{{ $pastHiddenCount === 1 ? 'ая сессия' : ($pastHiddenCount < 5 ? 'ие сессии' : 'их сессий') }} без привязанного урока — они скрыты.
        </div>
      @endif --}}
    </div>
  @else
    <div class="space-y-6">
      @foreach($pastByMonth as $monthKey => $items)
        {{-- Заголовок месяца, с заглавной буквы и двоеточием --}}
        <div>
          <div class="flex justify-center">
            <div class="md:text-base tracking-wide text-lg border-2 rounded-2xl border-blue-100 inline-block py-1.5 px-4 font-normal text-blue-800 mt-6 md:mb-6 mb-4">
              <img class="inline-block relative bottom-1 mr-1" src="{{ asset('img/Date_range.svg') }}" alt="">
              {{ \Illuminate\Support\Str::ucfirst($monthKey) }}:
            </div>
          </div>


          <div class="course-past-grid">
            @foreach($items as $s)
              @php
                $lesson = $s->lesson; // гарантированно есть
              @endphp
              <x-ui.card tone="gray">
                <div class="gap-4">
                  {{-- Картинка урока: заглушка, если у урока нет image_url --}}
                  <div class="relative aspect-[16/10]">
                    @if($lesson->image_url ?? false)
                      <img src="{{ $lesson->image_url }}" alt="{{ $lesson->title }}" class="w-full h-full object-cover rounded-xl object-cover border border-gray-200">
                    @else
                      <div class="w-full h-full rounded-xl border border-gray-200 bg-gray-100 flex items-center justify-center text-gray-300">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10">
                          <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                          <circle cx="8.5" cy="8.5" r="1.5"></circle>
                          <path d="M21 15l-5-5L5 21"></path>
                        </svg>
                      </div>
                    @endif

                    @include('student.partials.lesson-image-badges', ['lesson' => $lesson, 'homeworkColor' => $s->_homeworkColor])
                  </div>

                  {{-- Заголовок и ссылка на страницу урока --}}
                  <h3 class="sans-medium text-lg mt-3 md:mb-2 mb-1 text-zinc-900">
                    {{ $lesson->title }}
                  </h3>

                  <div class="flex-1">
                    {{-- Дата занятия: "2 сентября в 15:30" (без секунд, как договорились) --}}
                    <div class="text-base text-zinc-600">
                      {{-- Дата занятия: --}}
                      <img class="inline-block opacity-50 relative bottom-0.5 mr-1 w-4 h-4 md:w-5 md:h-5" 
                      src="{{ asset('img/Date_range.svg') }}" 
                      alt="Date">
                      @if($s->display_date)
                        {{ \Carbon\Carbon::parse($s->display_date)->locale('ru')->isoFormat('D MMMM') }}
                        @if($s->display_time) в {{ \Illuminate\Support\Str::substr($s->display_time, 0, 5) }} @endif
                      @else
                        Дата не указана
                      @endif
                    </div>


                    @if(Route::has('student.lessons.show'))
                      <x-ui.button href="{{ route('student.lessons.show', $lesson) }}" block class="mt-6">
                        Перейти к уроку
                      </x-ui.button>
                    @endif

                    {{-- @if($lesson->description)
                      <div class="text-sm text-gray-700 mt-1">{{ $lesson->description }}</div>
                    @endif --}}
                  </div>
                </div>
              </x-ui.card>

            @endforeach
            
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

<script>
    (function () {
        var el = document.getElementById('next-session-countdown');
        if (!el) return;

        var startMs = new Date(el.dataset.start).getTime();

        function pad(n) {
            return String(n).padStart(2, '0');
        }

        function render() {
            var diff = startMs - Date.now();

            if (diff <= 0) {
                el.textContent = 'Занятие уже началось';
                return;
            }

            var totalSeconds = Math.floor(diff / 1000);
            var days = Math.floor(totalSeconds / 86400);
            var hours = Math.floor((totalSeconds % 86400) / 3600);
            var minutes = Math.floor((totalSeconds % 3600) / 60);
            var seconds = totalSeconds % 60;

            el.textContent = 'До занятия: ' + (days > 0 ? days + ' дн ' : '') + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
        }

        render();
        var timer = setInterval(function () {
            render();
            if (startMs - Date.now() <= 0) {
                clearInterval(timer);
            }
        }, 1000);
    })();
</script>

@endsection
