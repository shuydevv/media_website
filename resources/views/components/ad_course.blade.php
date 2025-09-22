{{-- 
<div class="max-w-screen-lg mx-auto bg-blue-600 h-200 md:pt-8 pt-6 pb-6 md:px-8 px-6 md:pb-8 cursor-pointer md:mb-20 mb-16 rounded-2xl md:mx-auto mx-1 px-2">
    <img class="md:w-1/4 w-1/3 m-auto md:mb-6 mb-6 rotate-6" src="{{asset('img/tg-3d.png')}}" alt="">
    <h3 class="md:text-3xl text-xl font-regular line text-white text-center tracking-wider">Бесплатный телеграм-канал для подготовки к ЕГЭ
        @if (isset($subject))
            {{$subject == "История" ? 'по истории' : 'по обществу'}}
        @else
            @php
                $subject = rand("1", "2");
            @endphp
            {{$subject == "1" ? 'по истории' : 'по обществу'}}
        @endif
        на 100 баллов!</h3>
    <div class="flex justify-center md:mt-10 mt-8">
        <button class="md:px-8 md:w-auto w-full md:py-4 px-6 py-3 bg-stone-900 text-white font-medium tracking-wider rounded-lg">Подписаться <img class="inline-block ml-1" src="{{asset('img/arrow_white-button.svg')}}" alt="arrow"></button>
    </div>

</div> --}}

@php
  // Нормализуем subject (если не задан)
  if (!isset($subject) || !in_array($subject, ['История','Обществознание'], true)) {
      $subject = mt_rand(0,1) ? 'История' : 'Обществознание';
  }

  /** 1) История (desktop): группы по 3 картинки */
  $historyDesktopGroups = [
[
    asset('img/covers/petr_blue_1-min.jpg'),
    asset('img/covers/petr_blue_2-min.jpg'),
    asset('img/covers/petr_blue_3-min.jpg'),
],
[
    asset('img/covers/stalin_red_1-min.jpg'),
    asset('img/covers/stalin_red_2-min.jpg'),
    asset('img/covers/stalin_red_3-min.jpg'),
],
[
    asset('img/covers/stalin_green_red_1-min.jpg'),
    asset('img/covers/stalin_green_red_2-min.jpg'),
    asset('img/covers/stalin_green_red_3-min.jpg'),
],
[
    asset('img/covers/lenin_pale_1-min.jpg'),
    asset('img/covers/lenin_pale_2-min.jpg'),
    asset('img/covers/lenin_pale_3-min.jpg'),
],
[
    asset('img/covers/lenin_bright_1-min.jpg'),
    asset('img/covers/lenin_bright_2-min.jpg'),
    asset('img/covers/lenin_bright_3-min.jpg'),
],
[
    asset('img/covers/nikolay_1-min.jpg'),
    asset('img/covers/nikolay_2-min.jpg'),
    asset('img/covers/nikolay_3-min.jpg'),
],
[
    asset('img/covers/napoleon_war_1-min.jpg'),
    asset('img/covers/napoleon_war_2-min.jpg'),
    asset('img/covers/napoleon_war_3-min.jpg'),
],
[
    asset('img/covers/stalin_green_1.jpg'),
    asset('img/covers/stalin_green_2.jpg'),
    asset('img/covers/stalin_green_3.jpg'),
],
  ];

  /** 2) История (mobile): по 1 картинке в группе */
  $historyMobileGroups = [
    asset('img/covers/stalin_green_1.jpg'),
    asset('img/covers/stalin_green_3.jpg'),
    asset('img/covers/nikolay_2-min.jpg'),
    asset('img/covers/nikolay_3-min.jpg'),
    asset('img/covers/napoleon_war_1-min.jpg'),
    asset('img/covers/napoleon_war_2-min.jpg'),
    asset('img/covers/napoleon_war_3-min.jpg'),
    asset('img/covers/stalin_green_red_1-min.jpg'),
    asset('img/covers/stalin_green_red_2-min.jpg'),
    asset('img/covers/stalin_green_red_3-min.jpg'),
    asset('img/covers/lenin_pale_1-min.jpg'),
    asset('img/covers/lenin_pale_2-min.jpg'),
    asset('img/covers/lenin_pale_3-min.jpg'),
    asset('img/covers/lenin_bright_2-min.jpg'),

    // Добавляй ещё…
  ];

  /** 3) Общество (desktop): группы по 3 картинки */
  $societyDesktopGroups = [
[
    asset('img/covers/putin_loli_1-min.jpg'),
    asset('img/covers/putin_loli_2-min.jpg'),
    asset('img/covers/putin_loli_3-min.jpg'),
],
[
    asset('img/covers/generali_1-min.jpg'),
    asset('img/covers/generali_2-min.jpg'),
    asset('img/covers/generali_3-min.jpg'),
],
[
    asset('img/covers/lawyers_1-min.jpg'),
    asset('img/covers/lawyers_2-min.jpg'),
    asset('img/covers/lawyers_3-min.jpg'),
],
];

  /** 4) Общество (mobile): по 1 картинке */
  $societyMobileGroups = [
    asset('img/covers/putin_loli_1-min.jpg'),
    asset('img/covers/generali_2-min.jpg'),
    asset('img/covers/generali_3-min.jpg'),
    asset('img/covers/lawyers_1-min.jpg'),
    asset('img/covers/lawyers_2-min.jpg'),
    asset('img/covers/lawyers_3-min.jpg'),
];

  // Выбор случайной группы в нужной категории
  if ($subject === 'История') {
      $desktopGroup = $historyDesktopGroups[array_rand($historyDesktopGroups)];
      $mobileImage  = $historyMobileGroups[array_rand($historyMobileGroups)];
  } else { // 'Общество'
      $desktopGroup = $societyDesktopGroups[array_rand($societyDesktopGroups)];
      $mobileImage  = $societyMobileGroups[array_rand($societyMobileGroups)];
  }
@endphp

{{-- БАННЕР TG: высота = высоте картинок (моб: 1 картинка, деск: 3 колонки) --}}
<div class="relative overflow-hidden rounded-2xl max-w-screen-lg md:mx-auto mx-2 md:mb-20 mb-16 cursor-pointer">

  {{-- Фоновые изображения --}}
  {{-- Мобилка: одна высокая картинка (чуть ниже, чем раньше) --}}
  <div class="relative md:hidden">
    <div class="w-full aspect-[4/5] sm:aspect-[4/5] min-h-[240px]">
      <img
        src="{{ $mobileImage }}"
        alt=""
        class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none"
        loading="lazy"
        draggable="false"
      >
    </div>
  </div>

  {{-- Десктоп: три в ряд, выбранная случайная группа --}}
  <div class="hidden md:grid grid-cols-3">
    @foreach ($desktopGroup as $url)
      <div class="relative">
        <div class="w-full aspect-[2/3]">
          <img
            src="{{ $url }}"
            alt=""
            class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none"
            loading="lazy"
          >
        </div>
      </div>
    @endforeach
  </div>

  {{-- Лёгкое общее затемнение + нижний градиент для читаемости --}}
  {{-- <div aria-hidden="true" class="absolute inset-0 bg-black/20 z-0"></div> --}}
  <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-40 md:h-40 bg-gradient-to-t from-black/70 via-black/40 to-transparent z-10 pointer-events-none"></div>

  {{-- Контент поверх (иконка над текстом, прижато к низу) --}}
  <div class="absolute inset-0 z-20 flex flex-col items-center justify-end px-6 md:px-8 pb-4 md:pb-6">
    <div class="flex flex-col items-center text-white gap-2 md:gap-3 mb-1">
      <img class="w-10 h-10 md:w-12 md:h-12 rotate-6" src="{{ asset('img/tg-3d.png') }}" alt="Telegram">
      <h3 class="md:text-3xl text-xl font-medium tracking-wider text-center">
        Бесплатный телеграм-канал для подготовки к ЕГЭ
        {{ $subject === 'История' ? 'по истории' : 'по обществу' }}
        на 100 баллов!
      </h3>
    </div>
    {{-- Кнопка, если понадобится --}}
    {{-- <button class="mt-3 md:px-8 md:w-auto w-full md:py-4 px-6 py-3 bg-stone-900 text-white font-medium tracking-wider rounded-lg">
      Подписаться
      <img class="inline-block ml-1" src="{{ asset('img/arrow_white-button.svg') }}" alt="arrow">
    </button> --}}
  </div>

</div>


</div>

