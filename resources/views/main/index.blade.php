@extends('layouts.main')
@section('title')
Школа Полтавского. Курсы по подготовке к ЕГЭ и ОГЭ по истории и обществознанию
@endsection
@section('description')
Школа Полтавского. Курсы по подготовке к ЕГЭ и ОГЭ по истории и обществознанию
@endsection
@section('content')
    <body>
        {{-- <div class="relative">
            <div class="flex justify-between mb-4 p-4 border w-full bg-white z-10 items-center flex-wrap grow-0">
                <h2 class="md:text-xl md:mb-0 mb-4 text-lg tracking-wide font-medium">Школа Полтавского</h2>
                <div class="relative blue-bg rounded-full flex grow-0 self-center">
                    <h3 class=" p-2 pl-5 pr-8 grow-0 font-medium text-white tracking-wide">Все карты для ЕГЭ по истории!</h3>
                    <img class="absolute" style="right: -16px; width: 40px; height: 40px;" src="/img/map.png" alt="123" srcset="">
                </div>
                <ul class="gap-10 md:flex hidden">
                    <li style="color: rgb(217 119 6);" class="tracking-wide">Наши курсы</li>
                    <li class="tracking-wide">Групповые занятия</li>
                    <li >Занятия с репетитором</li>
                    <!-- <li>Бесплатные статьи</li> -->
                    <!-- <li>Вход / Регистрация</li> -->
                </ul>
            </div>
        </div> --}}
    <div class="bg-blue-600 md:mx-6 mx-0 py-1 rounded-3xl mb-16">
        <div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-6 px-3 ">
            {{-- <h1 class="md:text-6xl text-3xl font-bold md:mb-6 mb-5 text-center tracking-wide text-white"><span class="sans">Глубокие знания в нескучном формате! </span></h1> --}}
            <h1 class="md:text-6xl text-3xl font-medium md:mb-6 mb-5 text-center tracking-wide text-white"><span class="sans font-semibold">Глубокие знания в нескучном формате! </span></h1>

            <h3 class="md:text-2xl text-lg mt-3 text-center mb-6 tracking-wider font-regular text-white opacity-90">Подготовим к ЕГЭ и ОГЭ по истории и обществознанию на высокие баллы</h3>
            <div class="md:h-64 h-48 w-full bg-blue-400 md:mt-16 mt-10">
                {{-- <video src=""></video> --}}
            </div>
            {{-- <div class="swiper mySwiper3 mt-12">
                <div class="swiper-wrapper">
                    <div class="swiper-slide group cursor-pointer article-card bg-white">
                        <img class="w-full" src="img/portrait.jpg" alt="">
                        <div class="rounded-b-lg border border-inherit px-4 pb-4">
                            <h3 class="group-hover:text-amber-700 transition-all md:text-xl text-xl md:mt-3 mt-2 mb-2 sans font-medium text-zinc-900 title-card tracking-wide">Онлайн-курсы</h3>
                            <p class="text-zinc-400 md:text-l text-sm tracking-wide">Между кабинетами двух президентов была установлена прямая телефонная связь...</p>
                            <img class="mt-5 group-hover:rotate-45 transition-all" src="img/arrow.svg" alt="arrow" srcset="">
                        </div>

                    </div> --}}
                    {{-- <div class="swiper-slide group cursor-pointer article-card bg-white">
                        <img class="w-full" src="img/portrait.jpg" alt="">
                        <div class="rounded-b-lg border border-inherit px-4 pb-4">
                            <h3 class="group-hover:text-amber-700 md:text-xl text-xl md:mt-3 mt-2 mb-2 sans font-medium text-zinc-900 title-card tracking-wide">Мини-группы</h3>
                            <p class="text-zinc-400 md:text-l text-sm tracking-wide">Между кабинетами двух президентов была установлена прямая телефонная связь...</p>
                            <img class="mt-5 group-hover:rotate-45 transition-all" src="img/arrow.svg" alt="arrow123" srcset="">
                        </div>
                    </div>
                    <div class="swiper-slide group cursor-pointer article-card bg-white">
                        <img class="w-full" src="img/portrait.jpg" alt="">
                        <div class="rounded-b-lg border border-inherit px-4 pb-4">
                            <h3 class="group-hover:text-amber-700 md:text-xl text-xl md:mt-3 mt-2 mb-2 sans font-medium text-zinc-900 title-card tracking-wide">Занятия с репетитором</h3>
                            <p class="text-zinc-400 md:text-l text-sm tracking-wide">Между кабинетами двух президентов была установлена прямая телефонная связь...</p>
                            <img class="mt-5 group-hover:rotate-45 transition-all" src="img/arrow.svg" alt="arrow" srcset="">
                        </div>
                    </div>
    
                </div>
            </div> --}}
            <div class="flex justify-center md:mt-8 mt-8">
                <button class="md:px-8 md:py-4 px-6 md:w-auto w-full py-3 bg-white text-black font-semimedium tracking-wider rounded-lg">Записаться на занятия <img class="inline-block ml-1" src="{{ asset('img/arrow_black-button.svg') }}" alt="" srcset=""></button>
            </div>
        </div>

    </div>    
        <div class="container mx-auto max-w-screen-lg md:mt-24 mt-12 md:mb-20 mb-6 px-3">
            <h2 class="sans text-4xl text-center tracking-wide">Поступай куда хочешь, а не куда возьмут!</h2>

            {{-- <div></div> --}}


            {{-- <x-h2 title="Как это началось?" /> --}}
            <div class="flex mt-12 gap-6 shrink">
                <img class="w-1/2 rounded-2xl" src="{{ asset('img/mgu.jpg') }}" alt="">
                <div class="w-1/2 flex flex-col gap-6 shrink">
                    <div class="bg-blue-100 py-6 px-6 rounded-2xl flex gap-6">
                        {{-- <div class="w-16 h-full bg-blue-400"></div> --}}
                        <div>
                            <h3 style="color: #0C1C52" class="text-3xl mb-1 text-zinc-900 tracking-wider">80+ баллов</h3>
                            <p style="color: #0C1C52" class="text-lg">Получает каждый третий наш ученик</p>
                        </div>
                    </div>
                    <div style="background-color: #FFC9FF" class="bg-blue-100 py-6 px-6 rounded-2xl">
                        <h3 style="color: #520C52" class="text-3xl mb-1 text-zinc-900 tracking-wider">80+ баллов</h3>
                        <p style="color: #520C52" class="text-lg">Получает каждый третий наш ученик</p>
                    </div>
                    <div style="background-color: #FFD6CB" class="bg-blue-100 py-6 px-6 rounded-2xl">
                        <h3 style="color: #4C190E" class="text-3xl mb-1 text-zinc-900 tracking-wider">80+ баллов</h3>
                        <p style="color: #4C190E" class="text-lg">Получает каждый третий наш ученик</p>
                    </div>
                    
                </div>
            </div>
        </div>

        <div class="container mx-auto max-w-screen-lg md:mt-24 mt-12 md:mb-20 mb-6 px-3">
            <h2 class="sans text-4xl text-center tracking-wide">Полностью интерактивные занятия!</h2>
            <p>У нас нет скучных лекций. Вся теория подаётся простым языком с интересными презентациями и понятными примерами</p>
        </div>

        <div class="container mx-auto max-w-screen-lg md:mt-24 mt-12 md:mb-20 mb-6 px-3">
            <h2 class="sans text-4xl text-center tracking-wide">Эту школу создал учитель, а не маркетолог</h2>
            <p>Вместо сотен учеников в потоке — внимание к каждому. Вместо сухих лекций — живые занятия, где можно задавать вопросы и не бояться ошибок. Вместо зубрежки — понимание.

            Если вам важно не просто сдать ЕГЭ, а действительно освоить предмет, я буду рад помочь!</p>
        </div>

        {{-- @php
        $title = [
            'title 1' => 'История',
            'title 2' => '20 век',
            'title 3' => 'Хрущев',
        ];   
        @endphp --}}
        {{-- <x-h2 title="Как это продолжилось?" />
        <x-text text="Владимир Ильич был не дурак и поэтому всё предусмотрел. С другой стороны новая модель организационной деятельности влечет за собой" />
        <x-ul text='"Апрельские тезисы Ленина":'>
        <x-li class="mt-6 text-white" text="Пункт 1"></x-li>
        <x-li text="Пункт 1"></x-li>
        <x-li text="Пункт 1"></x-li> --}}
        {{-- <x-img img="{{ asset('')}}"></x-img> --}}
        {{-- <x-cover title1="Иван Грозный. " title2="Убивал ли сына на самом деле?" description="Поход на Казань, Опричнина, Ливонская война" img="/img/ivan.webp" :tags="$title" />

         --}}
        {{-- <x-block>
            <x-h2 title="Как это началось?" />
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            <x-person title="Малюта Скуратов" description="Главный опричник" img="/img/portrait.jpg"/>
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            <x-img img="images/0ckzS3RADMrwTBF619eo6H2YRqiw3p1IBqjYvcII.png" description="Между кабинетами двух президентов была установлена прямая телефонная связь" />
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            
            <x-h2 title="Как это продолжилось?" />
            <x-text text="Владимир Ильич был не дурак и поэтому всё предусмотрел. С другой стороны новая модель организационной деятельности влечет за собой" />
            <x-ul text='"Апрельские тезисы Ленина":'>
                <x-li class="mt-6 text-white" text="Пункт 1"></x-li>
                <x-li text="Пункт 1"></x-li>
                <x-li text="Пункт 1"></x-li>
            </x-ul>
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            
            <x-quote_text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития" source="Повесть временых лет" />
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            
            <x-date date="19 февраля 1961 г." fact="В России отменили Крепостное право" />
            <x-h2 title="Как это закончилось?" />
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            

            <x-quote text="Князья вернулись в Москву «опальными». Это страшное слово во всем его тогдашнем громадном значении не совсем и не всем понятно в настоящее время." name="Малюта Скуратов" description="Главный опричник Ивана Грозного" img="/img/portrait.jpg"/>
            <x-text text="С другой стороны новая модель организационной деятельности влечет за собой процесс внедрения и модернизации модели развития. Идейные соображения высшего порядка, а также реализация намеченных плановых заданий требуют от нас анализа дальнейших направлений развития. Товарищи! начало повседневной работы по формированию позиции влечет за собой процесс внедрения и модернизации системы обучения кадров, соответствует насущным потребностям. С другой стороны рамки и место обучения кадров позволяет выполнять важные задания по разработке форм развития." />
            
        </x-block> --}}

        {{-- <x-ad_course />

        <x-more_cards_div title="Читайте наш блог">
            @foreach ($posts as $post)
                <x-more_card $typeOfContent="post" path="{{$post->path}}" title="{{$post->title}}" title2="{{$post->title2}}" description="Подзаголовок" :tags="$post->tags" img="{{'storage/' . $post->main_image}}"/>
            @endforeach
            <x-slot:pagination>
                <div class="flex justify-center md:mt-8 mt-1">
                    <button class="md:px-8 md:py-4 px-6 py-3 border-2 border-black bg-white text-black font-semimedium tracking-wider rounded-lg">Все статьи <img class="inline-block ml-1" src="{{ asset('img/arrow_black-button.svg') }}" alt="" srcset=""></button>
                </div>
            </x-slot:pagination>

         </x-more_cards_div> --}}



        {{-- <x-material></x-material> --}}
        <x-footer />
    
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        <script>
            var swiper = new Swiper(".mySwiper3", {
                slidesPerView: 1.35,
                spaceBetween: 24,
                freeMode: true,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                },
                breakpoints: {
                    799: {
                        slidesPerView: 3,
                        spaceBetween: 32,
                    }
                },
            });
        </script>
    </body>
</html>

@endsection
