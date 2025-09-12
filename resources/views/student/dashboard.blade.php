{{-- resources/views/student/dashboard.blade.php --}}
@extends('layouts.main') {{-- замени на свой layout, если другой --}}

@php
    use Illuminate\Support\Carbon;

if (!function_exists('money_fmt')) {
    function money_fmt($cents, $cur = '₽') {
        return number_format(($cents ?? 0)/100, 2, ',', ' ') . ' ' . $cur;
    }
}
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
<h1 class="md:text-3xl text-2xl font-normal tracking-wide font-sans text-center mb-6">
  Привет, {{ auth()->user()?->first_name ?? 'друг' }}!
</h1>

    @if(session('success'))
        <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    {{-- <div class="w-full py-3 sm:py-6">
    <div class="max-w-6xl mx-auto bg-white rounded-xl border px-2 sm:px-4 py-4 sm:py-6">
        <div class="flex justify-between items-end mb-4 border-b border-gray-200 pb-2">
            <h2 class="text-xl md:text-2xl lg:text-3xl tracking-wide font-medium font-oktyabrina text-zinc-800"><img class="inline-block relative bottom-1 mr-1" src="{{ asset('img/Date_range.svg') }}" alt=""> Расписание уроков</h2>
            <div class="flex gap-3">
                <button id="swiper-prev" class="text-2xl text-gray-500 hover:text-gray-700 disabled:text-gray-300" disabled>&larr;</button>
                <button id="swiper-next" class="text-2xl text-gray-500 hover:text-gray-700">&rarr;</button>
            </div>
        </div>

        <div class="swiper mySwiper pt-8">
            <div class="swiper-wrapper">
                @php
                    $days = [
                        ['day' => 'Пн', 'date' => '17 июня', 'highlight' => false, 'items' => [
                            ['type' => 'Вебинар', 'subject' => 'История', 'title' => 'Разбор заданий', 'time' => '15:00', 'color' => 'blue', 'status' => 'past'],
                            ['type' => 'Домашка (выполнена)', 'subject' => 'Математика', 'title' => 'Тригонометрия', 'time' => 'до 23:59', 'color' => 'yellow', 'status' => 'completed']
                        ]],
                        ['day' => 'Вт', 'date' => '18 июня', 'highlight' => false, 'items' => [
                            ['type' => 'Домашка (просрочена)', 'subject' => 'Русский', 'title' => 'Словообразование', 'time' => 'до 22:00', 'color' => 'red', 'status' => 'overdue']
                        ]],
                        ['day' => 'Ср', 'date' => '19 июня', 'highlight' => true, 'items' => [
                            ['type' => 'Вебинар', 'subject' => 'Физика', 'title' => 'Законы Ньютона', 'time' => '18:00', 'color' => 'blue'],
                            ['type' => 'Пробник', 'subject' => 'Обществознание', 'title' => 'Анализ графиков', 'time' => '19:00', 'color' => 'orange']
                        ]],
                        ['day' => 'Чт', 'date' => '20 июня', 'highlight' => false, 'items' => []],
                        ['day' => 'Пт', 'date' => '21 июня', 'highlight' => false, 'items' => [
                            ['type' => 'Домашка', 'subject' => 'Английский', 'title' => 'Эссе', 'time' => 'до 21:00', 'color' => 'yellow']
                        ]]
                    ];
                @endphp

                @foreach ($days as $day)
                    <div class="swiper-slide">
                        <div class="flex flex-col gap-4 w-full pr-2">
                            <div class="text-center font-medium text-sm text-gray-700">
                                <div class="block sm:hidden">
                                    <span class="{{ $day['highlight'] ? 'text-indigo-600 font-semibold' : 'text-gray-700' }}">{{ $day['day'] }}</span>
                                    <span class="text-gray-400"> · {{ $day['date'] }}</span>
                                </div>
                                <div class="hidden sm:block">
                                    <div class="{{ $day['highlight'] ? 'text-indigo-600 font-semibold' : 'text-gray-700' }}">{{ $day['day'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $day['date'] }}</div>
                                </div>
                            </div>

                            @if (empty($day['items']))
                                <div class="border border-dashed border-gray-300 rounded-xl px-3 py-4 text-left text-gray-600 space-y-2">
                                    <div class="flex items-center text-xs">
                                        <span class="icon mr-2">🌿</span>
                                        <span class="tracking-wide text-gray-500">Выходной</span>
                                    </div>
                                    <div class="text-base text-gray-500">
                                        Нет запланированных занятий. Можно отдохнуть 🌤
                                    </div>
                                </div>
                            @else
                                @foreach ($day['items'] as $item)
                                    @php
                                        $status = $item['status'] ?? null;
                                        $opacity = $status === 'completed' ? 'opacity-50' : '';
                                        $border = 'border-' . $item['color'] . '-200';
                                        $bg = 'bg-' . $item['color'] . '-100';
                                        $text = 'text-' . $item['color'] . '-800';
                                    @endphp
                                    <div class="{{ $bg }} border {{ $border }} rounded-xl px-3 py-3 text-left space-y-2 {{ $opacity }}">
                                        <div class="flex items-center text-xs {{ $text }}">
                                            <span class="icon mr-1">🔔</span>
                                            <span>{{ $item['type'] }}</span>
                                            <span class="ml-auto text-gray-400">{{ $item['time'] }}</span>
                                        </div>
                                        <div class="font-medium text-base text-gray-800 leading-snug">{{ $item['title'] }}</div>
                                        <div>
                                            <span class="inline-block bg-white text-gray-700 text-xs px-2 py-0.5 rounded-md shadow-sm">{{ $item['subject'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div> --}}

    <div class="w-full py-3 sm:py-6">
    <div class="max-w-6xl mx-auto bg-white rounded-xl border md:px-3 px-3 md:px-4 pt-4 pb-2 md:pt-6 md:pb-4">
        <div class="flex justify-between items-end mb-4 border-b border-gray-200 pb-2 px-1">
            <h2 class="text-base md:text-xl tracking-wide font-normal font-sans text-zinc-800"><img class="inline-block relative bottom-1 mr-1" src="{{ asset('img/Date_range.svg') }}" alt=""> Расписание уроков</h2>
            <div class="flex gap-3">
                <button id="swiper-prev" class="text-2xl text-gray-500 hover:text-gray-700 disabled:text-gray-300" disabled>&larr;</button>
                <button id="swiper-next" class="text-2xl text-gray-500 hover:text-gray-700">&rarr;</button>
            </div>
        </div>

        <div class="swiper mySwiper pt-8">
            <div class="swiper-wrapper">


                @foreach ($days as $day)
  <div class="swiper-slide" data-highlight="{{ !empty($day['highlight']) ? 1 : 0 }}">
    <div class="flex flex-col gap-4 w-full pr-2">
      <div class="text-center font-medium text-sm text-gray-700">
        <div class="block sm:hidden capitalize">
            
          <span class="uppercase {{ $day['highlight'] ? 'text-indigo-600 font-semibold ' : ' text-gray-700' }}">
            {{ $day['day'] }}
          </span>
          <span class="text-gray-400 "> · {{ $day['date'] }}</span>
        </div>
        <div class="hidden sm:block">
          <div class="uppercase font-medium {{ $day['highlight'] ? 'text-indigo-600 font-semibold' : 'text-gray-700' }}">{{ $day['day'] }}</div>
          <div class="capitalize font-normal text-xs text-gray-400">{{ $day['date'] }}</div>
        </div>
      </div>

      @if (empty($day['items']))
        <div class="border border-dashed border-gray-300 rounded-xl px-3 py-4 text-left text-gray-600 space-y-2">
          <div class="flex items-center text-xs">
            <span class="icon mr-2">🌿</span>
            <span class="tracking-wide text-gray-500">Выходной</span>
          </div>
          <div class="text-base text-gray-500">Сегодня нет занятий и домашек. Можно отдохнуть</div>
        </div>
      @else
        @foreach ($day['items'] as $item)
          @php
            $status  = $item['status'] ?? null; // 'completed' | 'overdue' | null
            $dim = $status === 'completed' ? 'opacity-60' : '';

            // Статические классы Tailwind (чтобы их не выпилил purge)
            $color     = $item['color'] ?? 'blue';
            $bgMap     = [
            'blue'   => 'bg-blue-100',
            'purple' => 'bg-purple-100',
            'orange' => 'bg-orange-100',
            'yellow' => 'bg-yellow-100',
            'red'    => 'bg-red-100',
            ];
            $borderMap = [
            'blue'   => 'border-blue-200',
            'purple' => 'border-purple-200',
            'orange' => 'border-orange-200',
            'yellow' => 'border-yellow-200',
            'red'    => 'border-red-200',
            ];
            $textMap   = [
            'blue'   => 'text-blue-700',
            'purple' => 'text-purple-700',
            'orange' => 'text-orange-700',
            'yellow' => 'text-yellow-700',
            'red'    => 'text-red-700',
            ];

            $bg     = $bgMap[$color]     ?? $bgMap['blue'];
            $border = $borderMap[$color] ?? $borderMap['blue'];
            $text   = $textMap[$color]   ?? $textMap['blue'];

            // ссылки (если контроллер положил объекты)
            $lesson   = $item['lesson']   ?? null;
            $homework = $item['homework'] ?? null;
            $title    = $item['title'] ?? '—';
          @endphp

          <div class="{{ $bg }} border {{ $border }} rounded-xl px-3 py-3 text-left space-y-2">
             <div class="flex items-center text-xs {{ $text }}">
               <span class="icon mr-1 {{ $dim }}">🔔</span>
               <span class="{{ $dim }}">{{ $item['type'] }}</span>
               @if($status === 'overdue')
                 <span class="ml-2 inline-block px-1.5 pt-[1px] pb-[3px] text-xs border border-red-300 rounded text-red-700 {{ $dim }}">Срок истёк</span>
               @elseif($status === 'completed')
                 <span class="ml-2 inline-block px-1.5 pt-[1px] pb-[3px] rounded bg-emerald-500/20 text-emerald-700">Выполнена</span>
               @endif
               <span class="ml-auto text-gray-400 {{ $dim }}">{{ $item['time'] }}</span>
             </div>

            {{-- Заголовок: если это урок — линк на урок; если домашка — линк на форму/результат --}}
            <div class="font-medium text-base text-gray-800 leading-snug {{ $dim }}">
              @if($lesson && Route::has('student.lessons.show'))
                <a href="{{ route('student.lessons.show', $lesson) }}" class="hover:underline">{{ $title }}</a>
              @elseif($homework && Route::has('student.submissions.create'))
                <a href="{{ route('student.submissions.create', $homework) }}" class="hover:underline">{{ $title }}</a>
              @else
                {{ $title }}
              @endif
            </div>

            <div class="{{ $dim }}">
              <span class="inline-block bg-white text-gray-700 text-xs px-2 pt-0.5 pb-1 rounded-md">
                {{ $item['subject'] ?? 'Курс' }}
              </span>
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </div>
@endforeach
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>

<script>

   // NEW: найдём индекс опорного дня (highlight)
   const slidesInDom = document.querySelectorAll('.mySwiper .swiper-slide');
   const initialIndex = Array.from(slidesInDom).findIndex(
     el => el.dataset.highlight === '1'
   );

    const swiper = new Swiper(".mySwiper", {
        slidesPerView: 1.15,
        centeredSlides: false,
        spaceBetween: 10,
        breakpoints: {
            640: { slidesPerView: 2.1 },
            768: { slidesPerView: 3 },
            1024: { slidesPerView: 4 },
        },
        navigation: false,
        initialSlide: initialIndex > -1 ? initialIndex : 0,
    });

    const prevBtn = document.getElementById('swiper-prev');
    const nextBtn = document.getElementById('swiper-next');

    function updateButtons() {
        prevBtn.disabled = swiper.isBeginning;
        nextBtn.disabled = swiper.isEnd;
        prevBtn.classList.toggle('text-gray-300', swiper.isBeginning);
        nextBtn.classList.toggle('text-gray-300', swiper.isEnd);
    }

    swiper.on('slideChange', updateButtons);

    prevBtn.addEventListener('click', () => swiper.slidePrev());
    nextBtn.addEventListener('click', () => swiper.slideNext());

    updateButtons();
</script>

    <h2 class="text-base md:text-xl font-normal font-sans tracking-wide md:mb-4 mb-3 mt-4 text-zinc-800">Мои курсы</h2>

    @if($courses->isEmpty())
        <div class="p-6 rounded-xl border bg-white text-gray-600">
            Пока нет активных курсов. Если у вас есть промокод — активируйте его на странице
            <a href="{{ route('promo.redeem.form') }}" class="text-blue-600 underline">Активация промокода</a>.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-2">
            @foreach($courses as $course)
                @php
    $expiresAtRaw = data_get($course, 'pivot.expires_at'); // безопасно достаём поле
    $expiresAt    = $expiresAtRaw ? \Illuminate\Support\Carbon::parse($expiresAtRaw) : null;
    $expiresSoon  = $expiresAt?->isAfter(now()) && $expiresAt?->diffInDays(now()) <= 3;
    $next         = $course->nextSession;
                @endphp

                <div class="rounded-2xl border bg-white md:p-4 p-3 flex flex-col">
                    {{-- обложка, если есть --}}
                    @if(!empty($course->main_image))
                        <img src="{{ asset('storage/'.$course->main_image) }}" alt="{{ $course->title }}"
                             class="w-full object-cover rounded-xl mb-3">
                    @endif

                    <h3 class="font-medium  text-xl mb-1">{{ $course->title }}</h3>
                    <p class="text-base text-gray-600 line-clamp-2 md:mb-8 mb-6">{{ $course->description }}</p>
                    <div class="mt-auto flex gap-2">
                        @if(Route::has('student.courses.show'))
                            <a href="{{ route('student.courses.show', $course) }}"
                            class="block ml-auto text-center mr-auto w-full px-3 py-4 text-base tracking-wide font-medium rounded-xl bg-zinc-800 border text-white hover:bg-zinc-900 transition">
                            Перейти к курсу
                            </a>

                        @endif

                        @if(isset($expiresSoon) && $expiresSoon && Route::has('checkout.course.show'))
                            <a href="{{ route('checkout.course.show', $course) }}"
                               class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                Продлить доступ
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    <div class="flex justify-between md:mt-12 mt-8 items-center border-t pt-4">
    <div class="font-oktyabrina md:text-2xl text-xl">Школа Полтавского</div>
                        <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600">Выйти</button>
                    </form>
    </div>

</div>
@endsection
