<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Школа Александра Полтавского</title>
  {{-- Раньше этот <link> был обычным блокирующим stylesheet: до ответа cdn.jsdelivr.net
       страница вообще ничего не рисовала (белый/чёрный экран на нестабильной мобильной
       сети — та же причина, что чинили в layouts/main.blade.php). rel="preload" грузит
       CSS в фоне, не блокируя парсинг/первую отрисовку; onload переключает его в реальный
       stylesheet, когда он готов. noscript — на случай отключённого JS. --}}
  <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"></noscript>
  

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=104147441', 'ym');

    ym(104147441, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/104147441" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->






    <style>
    details[open] summary svg {
      transform: rotate(180deg);
    }
    summary::-webkit-details-marker {
      display: none;
    }
  </style>
<style>
  /* Вся модалка поверх всего сайта */
  #lead-modal { z-index: 9999; }

  /* Внутри модалки: оверлей ниже карточки */
  #lead-overlay { z-index: 10; }
  #lead-modal [data-card-wrap] { z-index: 20; }
</style>
<style>
  /* iOS-специфика для селекта «Как связаться» */
  .contact-method {
    font-size: 16px;            /* чтобы не было зума на фокус в iOS */
    min-height: 44px;           /* доступная высота тач-таргета */
  }

  /* Убираем нативный рендер у iOS/бразуеров WebKit и разрешаем паддинги */
  @supports (-webkit-touch-callout: none) {
    .contact-method {
      -webkit-appearance: none;
      appearance: none;
      padding-top: .75rem;      /* = py-3 */
      padding-bottom: .75rem;
      line-height: 1.25rem;     /* leading-5 */
    }
  }
</style>



</head>
<body class="bg-white font-sans">

  <!-- Хедер -->
  <header class="w-full bg-white fixed top-0 z-50">
    <div class="max-w-7xl mx-auto flex items-center justify-between md:px-4 px-2 py-4">
      <div class="flex items-center space-x-4 text-purple-600 md:text-xl text-sm font-bold tracking-wide">
        Школа Полтавского
      </div>
      <!-- <nav class="hidden md:flex space-x-6 text-sm">
        <a href="#" class="hover:text-pink-600">Курсы</a>
        <a href="#" class="hover:text-pink-600">Как учим</a>
        <a href="#" class="hover:text-pink-600">Преподаватели</a>
        <a href="#" class="hover:text-pink-600">Отзывы</a>
      </nav> -->
      <div class="flex items-center md:space-x-3 space-x-1">
        <!-- <a href="#" class="px-4 py-2 bg-purple-100 text-purple-800 text-sm rounded font-semibold">НАЧАТЬ БЕСПЛАТНО</a> -->
        <a data-open-form="header" data-label="Кнопка в шапке сайта" class="btn cursor-pointer md:py-2 py-1 md:px-2 px-1 bg-pink-500 text-white md:text-sm text-xs rounded font-semibold uppercase">Записаться на урок</a>
        {{-- <a href="{{route('home')}}" class="py-1 px-1 md:text-sm text-xs text-indigo-900 font-semibold uppercase">Войти</a> --}}
      </div>
    </div>
  </header>

  <!-- Hero-секция -->
  <section class="md:pt-32 pt-24 md:pb-16 pb-8 md:bg-gradient-to-r bg-gradient-to-b from-purple-200 via-indigo-100 to-purple-300 relative">
    <div class="max-w-7xl mx-auto md:px-4 px-3 flex flex-col md:flex-row items-center justify-between">
      <div class="md:w-1/2 md:space-y-8 space-y-6">
        <!-- <div class="flex space-x-2 bg-white p-1 rounded-full w-max">
          <button class="bg-purple-900 text-white text-sm px-4 py-1 rounded-full">УЧЕНИКАМ</button>
          <button class="text-purple-800 text-sm px-4 py-1 rounded-full">РОДИТЕЛЯМ</button>
        </div> -->
        <h1 class="text-2xl md:text-5xl font-bold text-gray-900 tracking-wide">
          Готовлю к ЕГЭ по обществу и истории. <br>
          <span class="text-purple-700 md:mt-2 mt-2 inline-block">Нескучно и эффективно!</span>
        </h1>
        <p class="text-gray-800 md:text-xl text-base max-w-md ">
          Внимательный подход с нуля до результата — помогу сдать на 85+ и поступить на бюджет в вуз мечты
        </p>

      <div class="block relative md:hidden z-10 w-64 !mt-24 mx-auto rounded-2xl 
             border-4 border-blue-200">
            <img style="rotate: 3deg"
            src="img/smile_prepod.jpg"
            alt=""
            aria-hidden="true"
            class="rounded-xl"
            >
            <p style="rotate: 3deg" class="absolute bottom-2 bg-blue-500 text-white font-medium  rounded-2xl px-2 pb-1 pt-0.5 text-xs">Александр Полтавский</p>
      </div>

            <div class="flex flex-wrap gap-3"> 
            <span class="bg-green-100 border border-green-300 text-green-700 md:text-sm text-sm font-semibold px-4 py-2 rounded-full tracking-wide">💻 Занятия на удобной платформе</span>
            <span class="bg-pink-100 border border-pink-300 text-pink-700 md:text-sm text-sm font-semibold px-4 py-2 rounded-full tracking-wide">📚 Интересные и полезные уроки</span>
            <span class="bg-blue-100 border border-blue-300 text-blue-700 md:text-sm text-sm font-semibold px-4 py-2 rounded-full tracking-wide">🎯 Максимальная поддержка и результат</span>
            </div>
      </div>

      <div class="hidden md:block absolute z-20 w-48 rounded-2xl 
             left-1/2 -translate-x-1/2 top-1/2 -translate-y-[60%]
             pointer-events-none select-none drop-shadow-xl border-4 border-blue-200">
            <img style="rotate: 5deg"
            src="img/smile_prepod.jpg"
            alt=""
            aria-hidden="true"
            class="rounded-xl"
            >
            <p style="rotate: 5deg" class="absolute bottom-2 bg-blue-500 text-white font-medium  rounded-2xl px-2 pb-1 pt-0.5 text-xs">Александр Полтавский</p>
      </div>

      <!-- Форма -->
      <div class="md:w-1/2 mt-10 md:mt-0 flex justify-end">

      <form method="POST" action="{{ route('lead.store') }}">
        @csrf
        <div class="bg-white rounded-3xl p-6 space-y-4 w-full max-w-sm">

          <p class="text-gray-900 font-semibold text-lg">Заполни форму, чтобы попасть на бесплатное пробное занятие!</p>
          <input type="text" name="name" placeholder="Имя" class="w-full bg-purple-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300">
          <input type="hidden" name="form_type" value="Форма №1">

              <!-- скрытые поля источника (модалка уже их заполняет) -->
            <input type="hidden" name="cta" value="">
            <input type="hidden" name="cta_label" value="">
            <input type="hidden" name="page" value="">

            <div class="relative">
            <select name="method"
                    class="contact-method w-full bg-purple-50 border border-gray-300 rounded-lg px-3 h-12 appearance-none text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-300">
                <option value="" disabled selected class="text-gray-400">Как с вами связаться?</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="telegram">Telegram</option>
            </select>

            <!-- своя стрелка -->
            <svg class="pointer-events-none absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-500"
                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 011.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>

            <p class="telegram-warning text-xs mt-2 text-yellow-500 hidden">
                Если у вас скрыт номер телефона в ТГ, указывайте свой ник
            </p>
            </div>
          <input required name="phone" type="text" placeholder="Номер телефона или Username" class="w-full bg-purple-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300">
          <button class="w-full bg-pink-500 text-white font-bold py-3 rounded-lg">Начать обучение</button>
          <p class="text-xs text-gray-500">Нажимая кнопку, вы принимаете <a href="#" class="underline">положение об обработке персональных данных</a></p>
            @error('form')
                <div class="mt-2 text-red-600 text-sm">{{ $message }}</div>
            @enderror
        </div>  
        </form>
        
      </div>

    </div>
  </section>

   <!-- Блок "Онлайн-школа, которой доверяют" -->
  <section class="max-w-6xl mx-auto bg-white md:py-20 py-12 px-4">
    <div class="max-w-6xl mx-auto text-center">
      <h2 class="text-2xl md:text-4xl font-bold text-gray-900 md:mb-6 mb-4">Преподаватель, которому доверяют</h2>
      <!-- <p class="text-gray-700 mb-10 max-w-2xl mx-auto">Нам доверяют десятки тысяч учеников и их родителей — и это не просто слова.</p> -->

      <div class="flex justify-between md:flex-row flex-col">
        <div class="bg-white p-6 rounded-2xl text-center">
          <p class="text-5xl font-medium text-purple-600">68%</p>
          <p class="mt-2 md:text-lg text-base text-blue-600 bg-blue-100 rounded-full inline-block py-1 px-3">советуют меня друзьям</p>
        </div>
        <div class="bg-white p-6 rounded-2xl text-center">
          <p class="text-5xl font-medium text-purple-600">80+<span class="md:text-lg text-base font-normal text-yellow-600 bg-yellow-100 rounded-xl inline-block py-1 px-3">баллов</span></p>
          <p class="md:text-lg text-base text-yellow-600 bg-yellow-100 rounded-xl inline-block py-1 px-3 font-base">у каждого 3-го</p>
        </div>
        <div class="bg-white p-6 rounded-2xl text-center">
          <p class="text-5xl font-medium text-purple-600">400+</p>
          <p class="mt-2 md:text-lg text-base text-pink-600 bg-pink-100 rounded-full inline-block py-1 px-3">учеников</p>
        </div>
      </div>
    </div>
    <div style="background-color: #F8F7FF;" class="bg-white rounded-2xl md:p-6 p-4 mt-4">
        <h3 class="md:text-2xl text-xl md:mb-8 mb-6 font-semibold text-gray-800 text-left">Мои ученики поступают куда хотят, а не куда возьмут!</h3>
        <div class="flex flex-wrap justify-between md:gap-16">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/ranhigs.png" alt="">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/spbgu.png" alt="">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/kfu.png" alt="">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/mgu.png" alt="">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/vshe.png" alt="">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/reu.png" alt="">
            <img class="shrink min-w-0 max-w-[100px] md:h-24 md:block hidden h-12 object-contain" src="https://egeturbo.ru/build/images/layout/parents/vuz/ufu.png" alt="">
        </div>
    </div>
  </section>


    <!-- Блок "Мы будем рядом, чтобы помочь" -->
  <section class="md:bg-gradient-to-r bg-gradient-to-b from-blue-100 via-blue-50 to-blue-100 md:py-20 py-8 relative overflow-hidden">
    <div class="max-w-6xl mx-auto px-4">
      <div class="relative md:mb-14 mb-10">
        <h2 class="text-2xl md:text-4xl font-bold text-gray-900 inline-block relative">
          Уютная и результативная подготовка
          <!-- <span class="absolute right-0 top-1/2 transform -translate-y-1/2 translate-x-1/3">
            <div class="bg-white shadow-lg w-12 h-12 rounded-full flex items-center justify-center">
              <i data-feather="smile" class="text-purple-700 w-6 h-6"></i>
            </div>
          </span> -->
        </h2>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 md:gap-6 gap-8">
        <div class="bg-white rounded-2xl md:p-6 p-4">
          <div class="md:-mt-10 -mt-8 md:w-1/2 w-1/3 relative flex items-center justify-center mb-4">
            <img class="-rotate-2 rounded-2xl" src="{{ asset('img/picture_1.jpg') }}" src="img/picture_1.jpg" alt="">
          </div>
          <h3 class="md:text-xl text-lg font-semibold text-gray-800 text-left">Отвечу на любые вопросы</h3>
          <p class="text-gray-600 md:text-base text-sm mt-2 text-left">Что-то осталось непонятно? Отвечаем быстро в телеграме почти 24/7</p>
        </div>
        <div class="bg-white rounded-2xl md:p-6 p-4">
          <div class="md:-mt-10 -mt-8 md:w-1/2 w-1/3 relative flex items-center justify-center mb-5">
            <img class="rounded-2xl" src="img/picture_2.jpg" alt="">
          </div>
          <h3 class="md:text-xl text-lg font-semibold text-gray-800 text-left">Быстро проверяю домашки</h3>
          <p class="text-gray-600 md:text-base text-sm text-sm mt-2 text-left">Тестовые задания проверяются мгновенно автоматически, а письменную часть работы обычно я проверяю в течение дня.</p>
        </div>
        <div class="bg-white rounded-2xl md:p-6 p-4">
          <div class="md:-mt-10 -mt-8 md:w-1/2 w-1/3 relative flex items-center justify-center mb-4">
            <img class="rounded-2xl" src="img/picture_3.jpg" alt="">
          </div>
          <h3 class="md:text-xl text-lg font-semibold text-gray-800 text-left">Крепкая связь с преподавателем</h3> 
          <p class="text-gray-600 md:text-base text-sm text-sm mt-2 text-left">Близкие неформальные отношения. Можно не стесняться быть собой и не бояться осуждения, если чего-то не знаешь.</p>
        </div>
      </div>
      
<div class="bg-white rounded-2xl mt-5 flex flex-col md:flex-row overflow-hidden min-h-[220px]">
  <!-- Картинка -->
  <div class="w-1/2 md:w-1/5 relative h-40 md:h-auto order-2 md:order-1">
    <img src="img/picture_transparent.png" alt=""
         class="absolute bottom-0 md:static w-full h-full object-cover object-top md:object-contain md:rounded-l-2xl">
  </div>

  <!-- Текст -->
  <div class="p-6 flex flex-col justify-center order-1 md:order-2">
    <h3 class="md:text-xl text-lg font-semibold text-gray-800 text-left">
      Мне не всё равно, как ты сдашь
    </h3>
    <p class="text-gray-600 md:text-base text-sm mt-2 text-left">
      Я очень тщательно слежу за тем, чтобы всем ученикам было комфортно учиться на максимум. 
      А иногда мы просто разговариваем по-душам. Ведь всем нам нужна поддержка...
    </p>
  </div>
</div>

    


    </div>
  </section>


  <!-- Блок "У тебя будет всё необходимое для успешной подготовки к ЕГЭ" -->
  <section class="bg-white md:py-16 py-12 px-4 relative z-10">
    <div class="max-w-6xl mx-auto text-start md:mb-4 mb-8">
    <h2 style="color: #02D4FF" class="text-xl md:text-4xl text-gray-900 leading-none md:mb-6 mb-2 tracking-wide">
      Эффективные занятия
    </h2>
    <!-- Анимированная волна внутри текста -->
    <div class="w-full h-[120px] bg-[#fdf3e8] flex items-start justify-start">
      <svg viewBox="0 0 800 150" class="w-full h-full">
        <defs>
          <linearGradient id="waveGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#00d4ff" />
            <stop offset="100%" stop-color="#90f7ec" />
          </linearGradient>

          <mask id="text-mask" x="0" y="0" width="100%" height="120" maskUnits="userSpaceOnUse">
            <text x="0" dx="-10" y="45%" class="font-black text-9xl tracking-wider" text-anchor="start" font-family="sans-serif" fill="white" dominant-baseline="middle">
              БЕЗ ВОДЫ
            </text>
          </mask>
        </defs>

        <g mask="url(#text-mask)">
          <rect width="800" height="120" fill="url(#waveGradient)" />
          <path fill="white" fill-opacity="0.4">
            <animate attributeName="d" dur="5s" repeatCount="indefinite"
              values="
              M0,60 C150,120 350,0 500,60 C650,120 850,0 1000,60 L1000,120 L0,120 Z;
              M0,60 C150,0 350,120 500,60 C650,0 850,120 1000,60 L1000,120 L0,120 Z;
              M0,60 C150,120 350,0 500,60 C650,120 850,0 1000,60 L1000,120 L0,120 Z" />
          </path>
        </g>
      </svg>
    </div>
      <!-- <div class="mt-12 text-xl">У нас нет длинных вебинаров по 2-3 часа</div> -->
  </div>


    

    <div class="max-w-6xl md:mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 md:gap-6 -mx-2 gap-10 text-left">
        <div style="background-color: oklch(98.062% 0.02404 200.191);" class=" md:p-6 p-4 rounded-2xl flex flex-col h-full">
            <div style="background-color: #b2faff; " class=" md:-mt-10 -mt-8 w-1/3 mb-6 rounded-xl relative">
                <img class="rounded-xl" src="img/free_time_blue.jpg" alt="">
            </div>
            <div class="flex items-center justify-between mb-2">
            <h3 style="color: #0dbfeb" class="md:text-xl text-lg font-semibold">Только самое важное для ЕГЭ</h3>
            <!-- <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m2 0a2 2 0 10-4 0 2 2 0 004 0zm-4 0a2 2 0 10-4 0 2 2 0 004 0z"/></svg> -->
            </div>
            <p class="md:text-base text-sm text-gray-600">У меня нет утомительных занятий по 2-3 часа, как у многих других преподавателей. И теорию, и практику обычно мы умещаем в 1-1,5 часа. Без потери качества знаний.</p>
        </div>

        <div style="background-color: oklch(98.062% 0.02404 200.191);" class="md:p-6 p-4 rounded-2xl flex flex-col h-full">
            <div style="background-color: #b2faff; " class="md:-mt-10 -mt-8 w-1/3 mb-6 rounded-xl relative">
                <img class="rounded-xl" src="img/orange_juice_blue.jpg" alt="">
            </div>
            <div class="flex items-center justify-between mb-2">
            <h3 style="color: #0dbfeb"  class="md:text-xl text-lg font-semibold text-purple-800">"Сок" — видео-выжимка урока, если нет времени на урок целиком</h3>
            <!-- <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> -->
            </div>
            <p class="md:text-base text-sm text-gray-600">Можно пересматривать видеоуроки, где я кратко пересказываю занятие по презентации. Идеально для повторения.</p>
        </div>

        <div style="background-color: oklch(98.062% 0.02404 200.191);" class="md:p-6 p-4 rounded-2xl flex flex-col h-full">
            <div style="background-color: #b2faff; " class="md:-mt-10 -mt-8 w-1/3 mb-6 rounded-xl relative">
                <img class="rounded-xl" src="img/book_blue.jpg" alt="">
            </div>
            <div class="flex items-center justify-between mb-2">
            <h3 style="color: #0dbfeb"  class="md:text-xl text-lg font-semibold text-purple-800">PDF-конспекты с теорией и шпаргалки</h3>
            <!-- <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 20h.01" /></svg> -->
            </div>
            <p class="md:text-base text-sm text-gray-600">После каждого занятия ты получишь подробный конспект, который всегда можно будет перечитать, чтобы освежить память.</p>
        </div>
        </div>
    </div>
    </section>

<!-- from-purple-400 via-purple-500 to-purple-500 -->
<section class="md:bg-gradient-to-r bg-gradient-to-b p-4 from-gray-900 via-gray-800 to-gray-900 text-white md:py-16 py-6 md:mx-4 mx-2 my-2 md:mb-0 mb-8 rounded-3xl">
  <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center md:gap-16 md:gap-8 gap-6">
    <!-- Фото -->
    <div class="bg-[#1e1e2f] rounded-3xl w-full md:w-1/2 flex justify-center">
      <img src="img/prepod(1-1)-1 2.jpg" alt="Преподаватель" class="rounded-2xl max-h-[400px] object-contain">
    </div>

    <!-- Текст -->
    <div class="w-full md:w-1/2 md:space-y-6 space-y-4">
      <h2 class="text-2xl md:text-4xl font-bold leading-tight">
        <span class="text-purple-400">Твой преподаватель <span class="md:inline hidden">—</span></span>
        Александр Полтавский
      </h2>
      <p class="text-gray-300 md:text-lg text-base leading-relaxed">
        Готовлю к ЕГЭ по обществознанию уже 5 лет. Мои ученики стабильно сдают на 85+ баллов и поступают в топовые вузы. Объясняю сложные темы простым языком и делаю занятия живыми и интересными.
      </p>
      <ul class="space-y-3 pt-3">
        <li class="bg-gradient-to-r from-gray-800 via-gray-900 to-gray-900 border-2 border-purple-900 rounded-2xl px-4 py-3 flex items-center gap-2 md:text-lg text-base text-gray-100">
          🏆 Более 150 учеников сдали на 80+ баллов
        </li>
        <li class="bg-gradient-to-r from-gray-800 via-gray-900 to-gray-900 border-2 border-purple-900 rounded-2xl px-4 py-3 flex items-center gap-2 md:text-lg text-base text-gray-100">
          🎓 Сдал ЕГЭ в 2018 году. 96 баллов по обществу и 92 по истории
        </li>
        <li class="bg-gradient-to-r from-gray-800 via-gray-900 to-gray-900 border-2 border-purple-900 rounded-2xl px-4 py-3 flex items-center gap-2 md:text-lg text-base text-gray-100">
          📚 Преподаватель и детский психолог
        </li>
        <li class="bg-gradient-to-r from-gray-800 via-gray-900 to-gray-900 border-2 border-purple-900 rounded-2xl px-4 py-3 flex items-center gap-2 md:text-lg text-base text-gray-100">
          💬 Обратная связь и поддержка 24/7
        </li>
      </ul>
    </div>
  </div>
</section>



  <!-- Блок "Начни учиться с Турбо" -->
  <section style="background-color: #C7F9FB;" class=" md:bg-gradient-to-r bg-gradient-to-b from-blue-100 via-blue-50 to-blue-100 md:py-12 py-8 md:px-4 px-3 rounded-[2rem] md:mx-4 mx-2 md:my-12 my-8 rounded-3xl">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-start justify-between md:gap-8 gap-4">

      <!-- Левая часть -->
      <div class="md:w-1/2 text-center md:text-left">
        <h2 class="text-2xl md:text-4xl font-bold text-gray-900 md:mb-4 mb-3">Попробуйте бесплатное занятие!</h2>
        <p class="text-black md:text-xl text-base md:mb-6 mb-4">
          Оставьте свои контакты – я свяжусь любым удобным для вас способом и всё подробно расскажу!
        </p>

        <!-- Картинка и стрелка (только на десктопе) -->
        <div class="md:block hidden">
          <div class="relative top-8 left-80 ">
            <img src="img/plane.png" alt="сердце" class="w-32 h-32">
          </div>
          <div class="relative">
            <img src="img/loop.svg" alt="" class="absolute w-64 left-12 -top-8">
          </div>
          <div class="relative ">
            <img src="img/arrow_right2.svg" alt="" class="absolute h-28 -right-40 -top-56">
          </div>
        </div>

        
      </div>

      <!-- Форма -->
<div class="md:w-1/2 w-full max-w-sm">
  <form method="POST" action="{{ route('lead.store') }}" class="space-y-4">
    @csrf
    <input type="text" name="name" placeholder="Имя" class="w-full px-4 py-3 rounded-xl bg-white text-gray-700 focus:outline-none">
    <input type="hidden" name="form_type" value="Форма №2">
    <div class="relative">
    <select name="method"
            class="contact-method w-full bg-white  rounded-xl px-3 h-12 appearance-none text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-300">
        <option value="" disabled selected class="text-gray-400">Как с вами связаться?</option>
        <option value="whatsapp">WhatsApp</option>
        <option value="telegram">Telegram</option>
    </select>

    <!-- своя стрелка -->
    <svg class="pointer-events-none absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-500"
        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 011.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
    </svg>

    <p class="telegram-warning text-xs mt-2 text-yellow-500 hidden">
        Если у вас скрыт номер телефона в ТГ, указывайте свой ник
    </p>
    </div>

        <input type="hidden" name="cta" value="">
        <input type="hidden" name="cta_label" value="">
        <input type="hidden" name="page" value="">

    <input required name="phone" type="text" placeholder="Номер телефона или @Username" class="w-full px-4 py-3 rounded-xl bg-white text-gray-700 focus:outline-none">

    <button type="submit" class="w-full block text-center bg-pink-500 text-white font-semibold py-3 rounded-xl text-lg">
      Начать обучение
    </button>

    <p class="text-xs text-purple-500">
      Нажимая кнопку, вы принимаете <a href="#" class="underline">положение об обработке персональных данных</a>
    </p>
  </form>
</div>
    </div>
  </section>

  <!-- <section class="bg-[#fdf3e8] py-16">
  <div class="max-w-6xl container mx-auto px-4">
    <h2 class="text-2xl md:text-4xl font-bold text-gray-900 text-center mb-10">
      Что говорят ученики
    </h2>

    <div class="grid md:grid-cols-3 gap-6">
      <div class="relative rounded-2xl overflow-hidden shadow-lg group cursor-pointer" onclick="openVideo('https://www.youtube.com/embed/VIDEO_ID_1')">
        <img src="https://img.youtube.com/vi/VIDEO_ID_1/hqdefault.jpg" alt="Видеоотзыв" class="w-full h-auto transition-transform group-hover:scale-105 duration-300" />
        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
          <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
      </div>

      <div class="relative rounded-2xl overflow-hidden shadow-lg group cursor-pointer" onclick="openVideo('https://www.youtube.com/embed/VIDEO_ID_2')">
        <img src="https://img.youtube.com/vi/VIDEO_ID_2/hqdefault.jpg" alt="Видеоотзыв" class="w-full h-auto transition-transform group-hover:scale-105 duration-300" />
        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
          <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
      </div>

            <div class="relative rounded-2xl overflow-hidden shadow-lg group cursor-pointer" onclick="openVideo('https://www.youtube.com/embed/VIDEO_ID_2')">
        <img src="https://img.youtube.com/vi/VIDEO_ID_2/hqdefault.jpg" alt="Видеоотзыв" class="w-full h-auto transition-transform group-hover:scale-105 duration-300" />
        <div class="absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center">
          <svg class="w-16 h-16 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
      </div>
    </div>
  </div> -->

  <!-- Модальное окно для видео -->
  <!-- <div id="videoModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
    <div class="relative w-full max-w-3xl aspect-video px-4">
      <iframe id="videoFrame" class="w-full h-full rounded-2xl" src="" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
      <button onclick="closeVideo()" class="absolute -top-4 -right-4 bg-white text-black rounded-full p-2 shadow-md">
        ✕
      </button>
    </div>
  </div>
</section> -->

<section class="px-3">
    <h2 class="max-w-6xl mx-auto text-2xl md:text-4xl font-bold md:mb-8 mb-4">Отзывы моих учеников</h2>
{{-- Тот же приём, что у Tailwind-стилей выше — не блокируем рендер в ожидании CDN. --}}
<link rel="preload" as="style" href="https://unpkg.com/swiper@10/swiper-bundle.min.css" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://unpkg.com/swiper@10/swiper-bundle.min.css"></noscript>

<div class="w-full mb-8">
  <div class="swiper reviews-swiper w-full max-w-6xl mx-auto">
    <div class="swiper-wrapper ">

      <!-- СЛАЙД 1 -->
      <div class="swiper-slide">
        <div class="bg-white rounded-2xl overflow-hidden h-full flex flex-col">
          <div class="relative w-full pb-[56.25%]">
            <iframe class="frame-video w-full h-64" src="https://dzen.ru/embed/vnG99BqO9U2k?from_block=partner&from=zen&backoffice=1&mute=0&autoplay=0&tv=0" allow="autoplay; fullscreen; accelerometer; gyroscope; picture-in-picture; encrypted-media" frameborder="0" scrolling="no" allowfullscreen=""></iframe>
          </div>

          <div class="p-3 space-y-4 border-b-4 border-l-4 border-r-4 border-blue-100 rounded-bl-2xl rounded-br-2xl">
            <div class="flex items-center">
              <img src="img/avatars/otz-1.png" alt="Отзыв-1" class="w-14 h-14 border-3 border-purple-100 rounded-full object-cover">
              <div class="ml-4">
                <p class="text-lg font-semibold">Полина</p>
                <p class="text-sm text-gray-500">Срок подготовки: 12 месяцев</p>
              </div>
            </div>

            <hr class="border-blue-100">

            <div class="space-y-3">
                <div class="flex items-center gap-2">
                <img class="w-5 h-5" src="img/soc_icon.svg" alt="">
                <p class="text-base">ЕГЭ Обществознание: <b>100 баллов</b></p>
                </div>
                {{-- <div class="flex items-center gap-2">
                <img class="w-5 h-5" src="img/hist_icon.svg" alt="">
                <p class="text-base">ЕГЭ История: <b>94 балла</b></p>
                </div> --}}
            </div>

            <figure class="mt-8 max-w-3xl overflow-hidden rounded-2xl py-2 px-4 shadow-sm bg-purple-50">
            <div class="relative">
                <!-- иконка кавычки -->
                <img src="img/chat-quote.svg" class="absolute -left-1 top-1 h-8 w-8 text-purple-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <blockquote class="pl-10">
                <p class="md:text-lg text-base italic text-purple-700">
                    «Я пришла на экзамен и поняла, что незнакомых тем тут нет... Некоторые задания были точь-в-точь такими, как давал Александр»
                </p>
                </blockquote>
            </div>
            <!-- <figcaption class="mt-4 text-sm text-gray-500">— Автор цитаты</figcaption> -->
            </figure>

          </div>
        </div>
      </div>

      <!-- СЛАЙД 2 -->
      <div class="swiper-slide">
        <div class="bg-white rounded-2xl overflow-hidden h-full flex flex-col">
          <div class="relative w-full pb-[56.25%]">
            <iframe class="frame-video w-full h-64" src="https://dzen.ru/embed/vRU-E1_bGeFk?from_block=partner&from=zen&backoffice=1&mute=1&autoplay=0&tv=0" allow="autoplay; fullscreen; accelerometer; gyroscope; picture-in-picture; encrypted-media" frameborder="0" scrolling="no" allowfullscreen=""></iframe>
          </div>

          <div class="p-3 space-y-4 border-b-4 border-l-4 border-r-4 border-blue-100 rounded-bl-2xl rounded-br-2xl">
            <div class="flex items-center">
              <img src="img/avatars/otz-2.png" alt="Отзыв-2" class="w-14 h-14 border-3 border-purple-100 rounded-full object-cover">
              <div class="ml-4">
                <p class="text-lg font-semibold">Сергей</p>
                <p class="text-sm text-gray-500">Срок подготовки: 9 месяцев</p>
              </div>
            </div>

            <hr class="border-blue-100">

            <div class="space-y-3">
                <div class="flex items-center gap-2">
                <img class="w-5 h-5" src="img/soc_icon.svg" alt="">
                <p class="text-base">ЕГЭ Обществознание: <b>98 баллов</b></p>
                </div>
                <div class="flex items-center gap-2">
                <img class="w-5 h-5" src="img/hist_icon.svg" alt="">
                <p class="text-base">ЕГЭ История: <b>94 балла</b></p>
                </div>
            </div>

            <figure class="mt-8 max-w-3xl overflow-hidden rounded-2xl py-2 px-4 shadow-sm bg-purple-50">
            <div class="relative">
                <!-- иконка кавычки -->
                <img src="img/chat-quote.svg" class="absolute -left-1 top-1 h-8 w-8 text-purple-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <blockquote class="pl-10">
                <p class="md:text-lg text-base italic text-purple-700">
                    «Сначала моя семья с недоверием отнеслась к Александру. Мол, он слишком молодой. Но за одно занятие с ним я узнал больше, чем за год в школе»
                </p>
                </blockquote>
            </div>
            <!-- <figcaption class="mt-4 text-sm text-gray-500">— Автор цитаты</figcaption> -->
            </figure>

          </div>
        </div>
      </div>

      <!-- СЛАЙД 3 -->
      <div class="swiper-slide">
        <div class="bg-white rounded-2xl overflow-hidden h-full flex flex-col">
          <div class="relative w-full pb-[56.25%]">
            <iframe class="frame-video w-full h-64" src="https://dzen.ru/embed/vAKuN44E4BU4?from_block=partner&from=zen&backoffice=1&mute=0&autoplay=0&tv=0" allow="autoplay; fullscreen; accelerometer; gyroscope; picture-in-picture; encrypted-media" frameborder="0" scrolling="no" allowfullscreen=""></iframe>
          </div>

          <div class="p-3 space-y-4 border-b-4 border-l-4 border-r-4 border-blue-100 rounded-bl-2xl rounded-br-2xl">
            <div class="flex items-center">
              <img src="img/avatars/otz-5.png" alt="Отзыв-3" class="w-14 h-14 border-3 border-purple-100 rounded-full object-cover">
              <div class="ml-4">
                <p class="text-lg font-semibold">Настя</p>
                <p class="text-sm text-gray-500">Срок подготовки: 10 месяцев</p>
              </div>
            </div>

            <hr class="border-blue-100">

            <div class="space-y-3">
                <div class="flex items-center gap-2">
                <img class="w-5 h-5" src="img/soc_icon.svg" alt="">
                <p class="text-base">ЕГЭ Обществознание: <b>94 балла</b></p>
                </div>
                <div class="flex items-center gap-2">
                <img class="w-5 h-5" src="img/hist_icon.svg" alt="">
                <p class="text-base">ЕГЭ История: <b>89 баллов</b></p>
                </div>
            </div>

            <figure class="mt-8 max-w-3xl overflow-hidden rounded-2xl py-2 px-4 shadow-sm bg-purple-50">
            <div class="relative">
                <!-- иконка кавычки -->
                <img src="img/chat-quote.svg" class="absolute -left-1 top-1 h-8 w-8 text-purple-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <blockquote class="pl-10">
                <p class="md:text-lg text-base italic text-purple-700">
                    «Благодаря Саше я полюбила историю и обществознание и с удовольствием ходила к нему на занятия»
                </p>
                </blockquote>
            </div>
            <!-- <figcaption class="mt-4 text-sm text-gray-500">— Автор цитаты</figcaption> -->
            </figure>

          </div>
        </div>
      </div>

    </div>

    <!-- Пагинация: только на мобильных -->
    <div class="swiper-pagination mt-6 md:hidden"></div>
  </div>
</div>
</section>


<!-- Блок "Единый тариф для всех" -->
  <section style="background-color: oklch(93% 0.034 272.788);" class=" md:py-20 py-8 md:px-4 px-3">
        <h2 class="max-w-6xl mx-auto text-2xl md:text-4xl font-bold md:mb-6 mb-4">
      Стоимость занятий
    </h2>
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-end justify-between md:gap-8 gap-4">
      <!-- В группе -->
      <div class=" md:w-1/2 w-full space-y-6 order-2 md:order-none">
        <div class="bg-white rounded-3xl md:p-6 p-4">
          <img class="mb-4" src="img/group_class.jpg" alt="group">
          <h3 class="md:text-2xl text-xl mb-1 font-semibold "><img class="w-8 inline-block relative bottom-1" src="img/with-friend.svg" alt="В группе"> Занятия в группе</h3>

          <!-- Цена -->
          <div class="text-start">
            <p class="md:text-4xl text-3xl font-bold text-gray-900 text-purple-600 mb-6">540 ₽<span class="text-lg font-semibold text-gray-400"> / за урок</span></p>
          </div>

          <!-- Скидки и бонусы -->
          <div class="mb-4">
            <div class="rounded-xl bg-purple-50 border border-purple-200 p-3 flex md:items-center items-start gap-3 justify-center">
                <img class="rounded-full border-4 border-blue-200 p-2" src="img/discount.svg" alt="discount">
                <div>
                    <p class="text-lg font-bold text-gray-800 md:mb-1 mb-3 leading-tight">250 ₽ <span class="font-normal text-base">за второй предмет!</span></p>
                    <p class="text-xs text-green-600 font-bold">СКИДКА 50% на второй предмет (история и общество)</p>
                </div>
            </div>
          </div>
          <!-- Кнопка -->
          <a data-open-form="group_class" data-label="Групповые занятия" class="cursor-pointer block text-center bg-pink-500 600 text-white font-semibold py-3 rounded-xl text-lg">Хочу на бесплатный урок</a>
        </div>
      </div>
      <!-- Индивидуально -->
      <div class=" md:w-1/2 w-full space-y-6 order-2 md:order-none">
        <div class="bg-white rounded-3xl md:p-6 p-4">
          <img class="mb-4" src="img/personal_class.jpg" alt="personal">
          <h3 class="md:text-2xl text-xl mb-1 font-semibold "><img class="w-8 inline-block relative bottom-1" src="img/personal-price.svg" alt="Индивидуально"> Индивидуально</h3>

          <!-- Цена -->
          <div class="text-start">
            <p class="md:text-4xl text-3xl font-bold text-gray-900 text-purple-600 mb-6">2000 ₽<span class="text-lg font-semibold text-gray-400"> / за урок</span></p>
          </div>

          <!-- Скидки и бонусы -->
          <div class="mb-4">
            <div class="rounded-xl bg-purple-50 border border-purple-200 p-3 flex items-center gap-3 justify-center">
                <img class="rounded-full border-4 border-blue-200 p-2" src="img/discount.svg" alt="discount">
                <div>
                    <p class="text-lg font-bold text-gray-800 md:mb-1 mb-3 leading-tight">1800 ₽ <span class="font-normal text-base">за занятие при оплате за сразу месяц!</span></p>
                    <p class="text-xs text-green-600 font-bold">СКИДКА 10% на абонемент!</p>
                </div>
            </div>
          </div>
          <!-- Кнопка -->
          <a data-open-form="personal_class" data-label="Индивидуальные занятия" class="cursor-pointer block text-center bg-pink-500 600 text-white font-semibold py-3 rounded-xl text-lg">Хочу на бесплатный урок</a>
        </div>
      </div>

      
    </div>
     <!-- Раскрывающийся блок «Что входит в курс» -->
    <div class="max-w-6xl bg-white rounded-2xl w-full md:mt-4 mt-4 mx-auto">
          <details class="md:p-6 p-4 py-6">
            <summary class="cursor-pointer text-left md:text-xl text-lg font-semibold text-purple-800 flex justify-between items-center">
              Что входит в курс?
              <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </summary>
            <ul class="mt-4 space-y-2 text-sm text-gray-700">
              <li class="flex gap-2 md:text-lg text-base mt-6"><span class="text-purple-500">★</span>От 8 до 12 онлайн-занятий в месяц</li>
              <li class="flex gap-2 md:text-lg text-base"><span class="text-purple-500">★</span>Все необходимые учебные материалы, конспекты, шпаргалки</li>
              <li class="flex gap-2 md:text-lg text-base"><span class="text-purple-500">★</span>Пробники ЕГЭ с проверкой каждый месяц</li>
              <li class="flex gap-2 md:text-lg text-base"><span class="text-purple-500">★</span>Индивидуальное отслеживание прогресса обучения</li>
              <li class="flex gap-2 md:text-lg text-base"><span class="text-purple-500">★</span>Постоянная связь с преподавателем лично</li>
              <li class="flex gap-2 md:text-lg text-base"><span class="text-purple-500">★</span>Быстрые ответы на любые вопросы</li>
              <li class="flex gap-2 md:text-lg text-base"><span class="text-purple-500">★</span>Проверка домашних работ</li>
            </ul>
          </details>
    </div>

  </section>

<!-- Блок "Вопросы и ответы" -->
  <section class="bg-white md:py-20 py-10 px-4">
    <div class="max-w-4xl mx-auto">
      <h2 class="text-2xl md:text-4xl font-bold text-center text-gray-900 md:mb-10 mb-6">Вопросы и ответы</h2>
      <div class="md:space-y-4 space-y-3">

        <!-- Вопрос 1 -->
        <details class="group bg-purple-50 border border-purple-200 rounded-xl p-4 transition-all duration-300">
          <summary class="cursor-pointer flex justify-between items-center md:text-xl text-base font-semibold text-purple-800">
            <span>Сколько длится курс?</span>
            <svg class="w-5 h-5 transition-transform duration-300 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </summary>
          <div class="mt-4 text-gray-700 md:text-lg text-sm leading-relaxed">
            Курс длится до наступления экзаменов. После окончания экзаменов я помогаю с поступлением. Если вы не согласны с выставленными баллами, помогу подать на аппеляцию
          </div>
        </details>

        <!-- Вопрос 2 -->
        <details class="group bg-purple-50 border border-purple-200 rounded-xl p-4 transition-all duration-300">
          <summary class="cursor-pointer flex justify-between items-center md:text-xl text-base font-semibold text-purple-800">
            <span>Что будет, если я пропущу занятие?</span>
            <svg class="w-5 h-5 transition-transform duration-300 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </summary>
          <div class="mt-4 text-gray-700 md:text-lg text-sm leading-relaxed">
            Все групповые занятия записываются. Ты сможешь пересмотреть вебинар и задать вопросы преподавателю в чате. Если заниматься индивидуально, то можно просто перенести занятие на другой день
          </div>
        </details>

        <!-- Вопрос 3 -->
        <details class="group bg-purple-50 border border-purple-200 rounded-xl p-4 transition-all duration-300">
          <summary class="cursor-pointer flex justify-between items-center md:text-xl text-base font-semibold text-purple-800">
            <span>Если передумаем заниматься, вы вернете деньги?</span>
            <svg class="w-5 h-5 transition-transform duration-300 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </summary>
          <div class="mt-4 text-gray-700 md:text-lg text-sm leading-relaxed">
            Да, если что-то не нравится, деньги всегда возвращаю. Можете даже не объяснять причину.
          </div>
        </details>

                <!-- Вопрос 3 -->
        <details class="group bg-purple-50 border border-purple-200 rounded-xl p-4 transition-all duration-300">
          <summary class="cursor-pointer flex justify-between items-center md:text-xl text-base font-semibold text-purple-800">
            <span>Есть ли на курсе пробники?</span>
            <svg class="w-5 h-5 transition-transform duration-300 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </summary>
          <div class="mt-4 text-gray-700 md:text-lg text-sm leading-relaxed">
            Да, мы решаем пробные варианты экзамена почти каждый месяц.
          </div>
        </details>

      </div>
    </div>
  </section>

<!-- Блок "Начни бесплатно" -->
  <section class="bg-gradient-to-br from-pink-100 to-purple-200 md:py-12 py-8 md:px-4 px-4 rounded-3xl mx-2 md:my-4 my-4">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-top justify-between md:gap-12 gap-8">

      <!-- Левая часть -->
      <div class="md:w-1/2 text-left md:text-left">
        <h2 class="text-2xl md:text-4xl font-bold text-gray-900 md:mb-6 mb-4">
          Попробуйте бесплатное занятие
          <!-- <span class="hidden md:inline-block align-middle ml-2">
            <svg class="w-8 h-8 text-pink-500 inline-block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
            </svg>
          </span> -->
        </h2>
        <ul class="space-y-3 text-left text-purple-900 text-sm md:text-base md:mt-6 mt-4">
          <li class="flex items-start gap-2 md:text-xl text-base">
            <svg class="w-5 h-5 text-pink-500 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Оцени формат занятий
          </li>
          <li class="flex items-start gap-2 md:text-xl text-base">
            <svg class="w-5 h-5 text-pink-500 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Познакомься с преподавателем и расписанием
          </li>
          <li class="flex items-start gap-2 md:text-xl text-base">
            <svg class="w-5 h-5 text-pink-500 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            Не понравится — платить не нужно!
          </li>
        </ul>
      </div>

      <!-- Форма -->
      <div class="md:w-1/2 w-full max-w-sm mx-auto">
        <form method="POST" action="{{ route('lead.store') }}" class="bg-white rounded-2xl space-y-4">
        @csrf
        <div class="bg-white rounded-3xl p-6 space-y-4 w-full max-w-sm">
            <p class="text-gray-900 font-semibold text-lg">Заполни форму, чтобы попасть на бесплатное пробное занятие!</p>
            <input name="name" type="text" placeholder="Имя" class="w-full bg-purple-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300">
            <input type="hidden" name="form_type" value="Форма №3">

            <input type="hidden" name="cta" value="">
            <input type="hidden" name="cta_label" value="">
            <input type="hidden" name="page" value="">

            <div class="relative">
            <select name="method"
                    class="contact-method w-full bg-purple-50 border border-gray-300 rounded-lg px-3 h-12 appearance-none text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-300">
                <option value="" disabled selected class="text-gray-400">Как с вами связаться?</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="telegram">Telegram</option>
            </select>

            <!-- своя стрелка -->
            <svg class="pointer-events-none absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-500"
                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 011.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>

            <p class="telegram-warning text-xs mt-2 text-yellow-500 hidden">
                Если у вас скрыт номер телефона в ТГ, указывайте свой ник
            </p>
            </div>
            <input required name="phone" type="text" placeholder="Номер телефона или @Username" class="w-full bg-purple-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300">
            <button class="w-full bg-pink-500 text-white font-bold py-3 rounded-lg">Начать обучение</button>
            <p class="text-xs text-gray-500">Нажимая кнопку, вы принимаете <a href="#" class="underline">положение об обработке персональных данных</a></p>
        </div>
        </form>
      </div>
    </div>
  </section>

<script>
  function handleSelectChange(select) {
    const warning = select.closest('.relative').querySelector('.telegram-warning');
    const selected = select.value;

    // Цвет текста
    if (selected === "") {
      select.classList.add("text-gray-400");
      select.classList.remove("text-gray-900");
    } else {
      select.classList.remove("text-gray-400");
      select.classList.add("text-gray-900");
    }

    // Предупреждение
    if (selected === "telegram" && warning) {
      warning.classList.remove("hidden");
    } else if (warning) {
      warning.classList.add("hidden");
    }
  }

  // Запуск на всех селекторах
  function initContactSelects() {
    const selects = document.querySelectorAll('select.contact-method');
    selects.forEach(select => {
      handleSelectChange(select);
      select.addEventListener('change', () => handleSelectChange(select));
    });
  }

  window.addEventListener('DOMContentLoaded', initContactSelects);
</script>

<script>
  function openVideo(url) {
    const modal = document.getElementById('videoModal');
    const frame = document.getElementById('videoFrame');
    frame.src = url + "?autoplay=1";
    modal.classList.remove('hidden');
  }

  function closeVideo() {
    const modal = document.getElementById('videoModal');
    const frame = document.getElementById('videoFrame');
    frame.src = "";
    modal.classList.add('hidden');
  }
</script>


<!-- === МОДАЛЬНОЕ ОКНО (вставить один раз перед </body>) ================== -->
<div id="lead-modal" class="fixed inset-0 z-40 hidden" role="dialog" aria-modal="true" aria-labelledby="lead-modal-title" aria-hidden="true">
  <!-- Полноэкранный тёмный фон -->
  <div id="lead-overlay"
       class="fixed inset-0 z-40 bg-black opacity-70 transition-opacity duration-200 pointer-events-none"
       data-close-modal>
  </div>

  <!-- Контейнер карточки поверх оверлея -->
  <div class="relative z-30 w-full h-full flex items-center justify-center p-4">
    <!-- Карточка модалки -->
    <div data-card
         class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 opacity-0 translate-y-4 transition duration-200">
      <!-- Крестик -->
      <button type="button"
              class="absolute -top-3 -right-3 bg-white shadow-md border rounded-full w-9 h-9 grid place-items-center hover:scale-105 transition"
              aria-label="Закрыть" data-close>
        ✕
      </button>

      <!-- Твоя форма (добавлены скрытые поля для фиксации источника) -->
      <form method="POST" action="{{ route('lead.store') }}" class="bg-white rounded-2xl space-y-4">
        @csrf
        <div class="bg-white rounded-3xl p-6 space-y-4 w-full">
          <p id="lead-modal-title" class="text-gray-900 font-semibold text-lg">
            Заполни форму, чтобы попасть на бесплатное пробное занятие!
          </p>

          <input name="name" type="text" placeholder="Имя"
                 class="w-full bg-purple-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300">

          <input type="hidden" name="form_type" value="Форма №4">
          <!-- Новые скрытые поля -->
          <input type="hidden" name="cta" value="">
          <input type="hidden" name="cta_label" value="">
          <input type="hidden" name="page" value="">

            <div class="relative">
            <select name="method"
                    class="contact-method w-full bg-purple-50 border border-gray-300 rounded-lg px-3 h-12 appearance-none text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-300">
                <option value="" disabled selected class="text-gray-400">Как с вами связаться?</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="telegram">Telegram</option>
            </select>

            <!-- своя стрелка -->
            <svg class="pointer-events-none absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-500"
                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 011.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>

            <p class="telegram-warning text-xs mt-2 text-yellow-500 hidden">
                Если у вас скрыт номер телефона в ТГ, указывайте свой ник
            </p>
            </div>

          <input required name="phone" type="text" placeholder="Номер телефона или @Username"
                 class="w-full bg-purple-50 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-300">

          <button class="w-full bg-pink-500 text-white font-bold py-3 rounded-lg hover:brightness-110 active:scale-[0.99]">
            Начать обучение
          </button>

          <p class="text-xs text-gray-500">
            Нажимая кнопку, вы принимаете
            <a href="#" class="underline">положение об обработке персональных данных</a>
          </p>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- ====================================================================== -->


<!-- === СКРИПТ МОДАЛКИ ==================================================== -->
<script>
(function () {
  const modal   = document.getElementById('lead-modal');
  const overlay = document.getElementById('lead-overlay');
  const card    = modal.querySelector('[data-card]');
  const closeBtns = modal.querySelectorAll('[data-close]');
  const form = modal.querySelector('form');

  // скрытые поля
  const inputCTA      = form.querySelector('input[name="cta"]');
  const inputCTALabel = form.querySelector('input[name="cta_label"]');
  const inputPage     = form.querySelector('input[name="page"]');

  // элементы для UX
  const contactSelect = form.querySelector('.contact-method');
  const tgWarning     = form.querySelector('.telegram-warning');

  inputPage.value = location.href;
  let lastTrigger = null;

  // Показ/скрытие подсказки для Telegram
  function toggleTgWarn() {
    if (contactSelect && tgWarning) {
      (contactSelect.value === 'telegram')
        ? tgWarning.classList.remove('hidden')
        : tgWarning.classList.add('hidden');
    }
  }
  contactSelect && contactSelect.addEventListener('change', toggleTgWarn);

  // Открыть модал
  function openModal(source, label, triggerEl) {
    lastTrigger = triggerEl || null;

    inputCTA.value = source || '';
    inputCTALabel.value = (label || '').trim();

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    // блокируем прокрутку страницы
    document.documentElement.style.overflow = 'hidden';

    // анимация появления
    requestAnimationFrame(() => {
      overlay.classList.remove('opacity-0', 'pointer-events-none');
      card.classList.remove('opacity-0', 'translate-y-4');
    });

    // фокус на первое поле
    const first = form.querySelector('input[name="name"]') || form.querySelector('input,select,textarea,button');
    first && first.focus();

    // синхронизировать подсказку под выбранный метод (если в old() был telegram)
    toggleTgWarn();
  }

  // Закрыть модал
  function closeModal() {
    overlay.classList.add('opacity-0');
    card.classList.add('opacity-0', 'translate-y-4');

    setTimeout(() => {
      overlay.classList.add('pointer-events-none');
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
      document.documentElement.style.overflow = '';
      if (lastTrigger) { try { lastTrigger.focus(); } catch(e) {} }
    }, 150);
  }

  // Делегирование клика по триггерам
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-open-form]');
    if (!btn) return;
    e.preventDefault();
    openModal(
      btn.getAttribute('data-open-form') || '',
      btn.getAttribute('data-label') || btn.textContent || '',
      btn
    );
  });

  // Закрытия: overlay, крестик, Esc, клик вне карточки
  overlay.addEventListener('click', closeModal);
  closeBtns.forEach(b => b.addEventListener('click', closeModal));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.getAttribute('aria-hidden') !== 'true') closeModal();
  });
  modal.addEventListener('click', (e) => {
    if (!card.contains(e.target) && e.target !== overlay) closeModal();
  });

  // Страховка: перед submit ещё раз проставим поля источника
  form.addEventListener('submit', () => {
    if (!inputCTA.value && lastTrigger)    inputCTA.value = lastTrigger.getAttribute('data-open-form') || '';
    if (!inputCTALabel.value && lastTrigger) inputCTALabel.value = lastTrigger.getAttribute('data-label') || lastTrigger.textContent || '';
  });
})();
</script>
<!-- ====================================================================== -->

<!-- Swiper JS (убери, если уже подключено). defer — не блокирует рендер того, что ниже. -->
<script src="https://unpkg.com/swiper@10/swiper-bundle.min.js" defer></script>

<script>
  // defer откладывает выполнение до конца парсинга — оборачиваем в DOMContentLoaded,
  // иначе на момент выполнения этого инлайн-скрипта window.Swiper ещё не определён.
  document.addEventListener('DOMContentLoaded', function () {
  if (typeof window.Swiper === 'undefined') return;

  // Инициализация
  const reviewsSwiper = new Swiper('.reviews-swiper', {
    // Мобильная базовая конфигурация
    slidesPerView: 1.1,          // видно 1.7 карточки
    spaceBetween: 16,            // отступ между карточками
    centeredSlides: false,
    centeredSlidesBounds: true,
    roundLengths: true,
    watchOverflow: true,         // если карточек мало, Swiper сам отключится
    grabCursor: true,

    // Пагинация — только мобильная (элемент скрыт на md+)
    pagination: {
      el: '.reviews-swiper .swiper-pagination',
      clickable: true,
    },

    // Чтобы не было скачков при скрытии/появлении контейнера
    observer: true,
    observeParents: true,

    // На десктопах — 3 карточки, свайп отключён
    breakpoints: {
      1024: {
        slidesPerView: 3,
        spaceBetween: 24,
        allowTouchMove: false,
        grabCursor: false,
      }
    }
  });

  // На всякий случай дополнительно гасим пагинацию, если она не нужна
  const onResize = () => {
    const pagEl = document.querySelector('.reviews-swiper .swiper-pagination');
    if (!pagEl) return;
    if (window.innerWidth >= 1024) {
      pagEl.style.display = 'none';
    } else {
      pagEl.style.display = '';
    }
  };
  window.addEventListener('resize', onResize);
  onResize();
  }); // конец DOMContentLoaded
</script>


</body>
</html>
