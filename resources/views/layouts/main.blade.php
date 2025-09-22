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



    </head>
    <body>
        <div class="relative border-b pb-3">
            <div class=" flex relative justify-between p-4 w-full z-10 items-center flex-wrap grow-0 md:mb-4 mb-2 max-w-6xl mx-auto">

                {{-- <div class="relative blue-bg rounded-full flex grow-0 self-center">
                    <h3 class=" p-2 pl-5 pr-8 grow-0 font-medium text-white tracking-wide">Все карты для ЕГЭ по истории!</h3>
                    <img class="absolute" style="right: -16px; width: 40px; height: 40px;" src="/img/map.png" alt="123" srcset="">
                </div> --}}
                <a class="inline-block" href="{{route('index')}}"><h2 class="font-oktyabrina md:text-[26px] md:mt-0 md:mb-0 mb-4 text-xl tracking-wide absolute left-0 right-0 ml-0 mr-0 text-center antialiased text-zinc-800 mb-4">Школа Полтавского</h2></a>
                {{-- <ul class="gap-10 md:flex hidden">
                    <li style="color: rgb(217 119 6);" class="tracking-wide"></li>
                    <li class="tracking-wide"><span><img class="inline-block mr-1 b-4 w-5" src="{{asset('img/person.svg')}}" alt="alt" srcset=""> Войти</span><span></span></li>
                </ul> --}}
            </div>
        </div>
        <div class="relative">
            {{-- <div class="md:flex hidden justify-center md:mb-8 mb-4 p-4 border w-full z-10 items-center flex-wrap grow-0"> --}}
                {{-- <ul class="gap-16 md:flex hidden">

                    <a class="tracking-wide" href="{{route('main.repetitor')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Курсы</li></a>
                    <a class="tracking-wide" href="{{route('exercise.index')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Упражнения</li></a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600">Выйти</button>
                    </form>
                </ul> --}}
            {{-- </div> --}}
        </div>
        @yield('content')
        @yield('scripts')
        @stack('page-scripts')
    </body>
</html>