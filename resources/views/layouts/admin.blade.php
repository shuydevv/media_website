<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Школа Полтавского</title>

        <!-- Styles -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        @vite('resources/css/app.css')
    </head>
    <body>
        <div class="relative">
            <div class="flex justify-between mb-4 p-4 border w-full bg-white z-10 items-center flex-wrap grow-0">
                <a href="{{route('main.index')}}"><h2 class="md:text-xl md:mb-0 mb-4 text-lg tracking-wider font-medium">Школа</h2></a>
                <ul class="gap-10 md:flex hidden">
                    <li style="color: rgb(217 119 6);" class="tracking-wide"></li>
                    <li class="tracking-wide"></li>
                    <li><a href="{{route('logout')}}">Выйти</a></li>
                    <!-- <li>Бесплатные статьи</li> -->
                    <!-- <li>Вход / Регистрация</li> -->
                </ul>
            </div>
        </div>
        @yield('content')
    </body>
</html>