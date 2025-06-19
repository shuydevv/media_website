<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title')</title>
        <meta name="description" content="@yield('description')" />
        <style>
            
        </style>

        <!-- Styles -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        @vite('resources/css/app.css')
    </head>
    <body>
        <div class="relative md:border-0 border">
            <div class="flex relative justify-between p-4 w-full z-10 items-center flex-wrap grow-0 md:mb-4 mb-2">

                {{-- <div class="relative blue-bg rounded-full flex grow-0 self-center">
                    <h3 class=" p-2 pl-5 pr-8 grow-0 font-medium text-white tracking-wide">Все карты для ЕГЭ по истории!</h3>
                    <img class="absolute" style="right: -16px; width: 40px; height: 40px;" src="/img/map.png" alt="123" srcset="">
                </div> --}}
                <a class="inline-block mb-4" href="{{route('index')}}"><h2 class="md:text-2xl md:mt-0 md:mb-0 mb-4 text-lg tracking-wider font-medium absolute left-0 right-0 ml-0 mr-0 text-center">ШКОЛА</h2></a>
                {{-- <ul class="gap-10 md:flex hidden">
                    <li style="color: rgb(217 119 6);" class="tracking-wide"></li>
                    <li class="tracking-wide"><span><img class="inline-block mr-1 b-4 w-5" src="{{asset('img/person.svg')}}" alt="alt" srcset=""> Войти</span><span></span></li>
                </ul> --}}
            </div>
        </div>
        <div class="relative">
            <div class="md:flex hidden justify-center mb-4 p-4 border w-full z-10 items-center flex-wrap grow-0">
                <ul class="gap-16 md:flex hidden">
                    <a class="tracking-wide" href="{{route('post.index')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Статьи</li></a>
                    {{-- <li class="hover:text-amber-700 transition-all tracking-wide">Наши курсы</li> --}}
                    {{-- <li class="hover:text-amber-700 transition-all tracking-wide">Групповые занятия</li> --}}
                    <a class="tracking-wide" href="{{route('main.repetitor')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Курсы</li></a>
                    <a class="tracking-wide" href="{{route('exercise.index')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Упражнения</li></a>
                    <!-- <li>Бесплатные статьи</li> -->
                    <!-- <li>Вход / Регистрация</li> -->
                </ul>
            </div>
        </div>
        @yield('content')
    </body>
</html>