@extends('layouts.main')
@section('title')
Школа Полтавского. Курсы по подготовке к ЕГЭ и ОГЭ по истории и обществознанию
@endsection
@section('description')
Школа Полтавского. Курсы по подготовке к ЕГЭ и ОГЭ по истории и обществознанию
@endsection
@section('content')
    <body>
        <div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-12 px-3">
            <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 text-start tracking-wide text-zinc-900"><span class="sans">Конспекты и шпаргалки</span><span class="font-normal tracking-wide"></span></h1>
            <x-text text="Бесплатный учебник для всех желающих улучшить свои знания" />
            <ul class="flex md:gap-4 gap-3 flex-wrap md:mt-10 mt-8">
                <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => null]) }}"><li class="{{request()->query('post_category') == null ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Все материалы</li></a>
                <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'social_science']) }}"><li class="{{request()->query('post_category') == 'social_science' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Обществознание</li></a>
                <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'history']) }}"><li class="{{request()->query('post_category') == 'history' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">История</li></a>
                
            </ul>
        </div>
        @php
        $post_category_name = '';
        if(request()->query('post_category') == 'social_science') {
            $post_category_name = 'Статьи по обществознанию';
        } elseif (request()->query('post_category') == 'history') {
            $post_category_name = 'Статьи по истории';
        } else {
            $post_category_name = 'Все статьи';
        }
        @endphp

        {{-- <x-block>            </x-block> --}}
        {{-- {{$posts->onEachSide(5)->links()}} --}}


        
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
