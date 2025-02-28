@extends('layouts.main')
@section('title')
{{$material->title}}
@endsection
@section('description')
{{$material->description}}
@endsection
@section('content')
    <body>
        <style>
            a:not([class]) {
                color: rgb(180 83 9);
                border-bottom: 1px dashed rgb(180 83 9);  
                padding-bottom: 2px;  
            }
            p:not([class]) {
                color: rgb(63 63 70);
                margin-top: 1rem;
                line-height: 1.625;
                
            }
            @media (min-width: 768px) { 
                p:not([class]) {
                    font-size: 1.25rem; /* 20px */
                    line-height: 1.75rem; /* 28px */
                    margin-top: 1.5rem;
            }
                a:not([class]) {
                /* color: black; */
                }

            }
            li {
                margin-bottom: 4px;
                padding-bottom: 2px;
            }
            li:last-child {
                margin-bottom: 0px;
                /* padding-bottom: 0px; */
            }
        </style>
        <div class="px-3 md:mt-10 mt-4 container mx-auto max-w-screen-lg md:mb-12 mb-6 text-zinc-400 leading-7"><a class="a" href="{{route('index')}}">
        <span class="md:mr-4 mr-2 border-b-2 pb-1 border-dashed">Главная</span>
        </a> <span class="text-zinc-300">></span>
        <a class="a" href="{{route('shpargalka.index')}}"><span class="md:ml-4 ml-2 md:mr-4 mr-2 border-b-2 pb-1 border-dashed">Материалы</span></a> <span class="text-zinc-300">></span>
        <span class="md:ml-4 ml-2 text-zinc-300">{{$material->title}}</span></div>
        <div class="container mx-auto max-w-screen-lg md:mt-20 mt-8 md:mb-20 mb-12 px-3">

            <div class="flex justify-between md:flex-nowrap flex-wrap-reverse md:gap-10 gap-1">
                <div class="md:w-4/6 w-full">
                    <h1 class="md:mb-0 mb-4 md:mt-0 mt-6 md:text-3xl text-2xl md:mb-6 mb-2 text-start tracking-wide text-zinc-900 sans">{{$material->title}}</h1>
                    {{-- <x-ul text=''>
                        <x-li class="mt-6 text-white" text="Все 450 планов по обществознанию"></x-li>
                        <x-li text="Каждый план написан на максимальные 4 из 4 баллов"></x-li>
                        <x-li text="Удобно носить с собой. Легко помещаются в карман"></x-li>
                    </x-ul> --}}
                    <ul class="list-outside list-disc text-lg mb-8">
                        <li>Все 450 планов по обществознанию</li>
                        <li>Каждый план написан на максимальные 4 из 4 баллов</li>
                        <li>Удобно носить с собой. Легко помещаются в карман</li>
                        {{-- {!! Blade::render($material->description) !!} --}}
                    </ul>
                    <p class="inline-block text-lg"><span>Цена:</span> <span style="border-color: #d2deff" class="font-medium pb-1 border-dashed border-b-2">{{$material->price}} рублей</span></p>
                    <div class="mt-12">
                        <button class="md:w-auto w-full md:px-8 md:py-4 px-6 py-4 bg-indigo-500 text-white font-semimedium tracking-wider rounded-lg">Перейти к покупке <img class="inline-block ml-1" src="{{asset('img/arrow_white-button.svg')}}" alt="arrow"></button>
                    </div>

                </div>
                <div class="md:w-1/2 w-full">
                    <img class="object-cover rounded h-full" src="{{ asset('storage/' . $material->main_image)}}" alt="" srcset="">
                </div>
            </div>
        </div>
        <div class="container mx-auto max-w-screen-lg md:mt-20 mt-8 md:mb-20 mb-12 px-3">



        <h2 class="sans md:mb-6 mb-4 md:text-3xl text-2xl tracking">Как выглядит документ:</h2>

        {!! Blade::render($material->content, $new_images) !!}
        {{-- <h2 class="sans md:mb-6 mb-4 md:text-2xl text-xl">Отзывы:</h2> --}}
        </div>

        <div class="px-3 md:mt-10 mt-4 container mx-auto max-w-screen-lg">
            <h2 class="sans md:mb-6 mb-4 md:text-3xl text-2xl">Как происходит покупка:</h2>
            <ul>
                <div class="flex items-start mb-3">
                    <span class="px-3 py-1 rounded-full border mr-4 border-2 text-indigo-500 font-medium border-indigo-200">1</span><li class="mb-3 sans md:text-xl text-l text-zinc-700 leading-relaxed"> Нажимаете кнопку "купить" и переходите на страницу оплаты</li>
                </div>
                <div class="flex items-start mb-3">
                    <span class="px-3 py-1 rounded-full border mr-4 border-2 text-indigo-500 font-medium border-indigo-200">2</span><li class="mb-3 sans md:text-xl text-l text-zinc-700 leading-relaxed"> Оплачиваете покупку любым удобным способом: Банковская карта или СБП</li>
                </div>
                <div class="flex items-start">
                    <span class="px-3 py-1 rounded-full border mr-4 border-2 text-indigo-500 font-medium border-indigo-200">3</span><li class="mb-3 sans md:text-xl text-l text-zinc-700 leading-relaxed"> На указанную на странице оплаты электронную почту приходит купленный файл</li>
                </div>
            </ul>
            <div class="md:mt-12 mt-8">
                <button class="md:w-auto w-full md:px-8 md:py-4 px-6 py-4 bg-indigo-500 text-white font-semimedium tracking-wider rounded-lg">Перейти к покупке<img class="inline-block ml-1" src="{{asset('img/arrow_white-button.svg')}}" alt="arrow"></button>
            </div>
        </div>

        {{-- <x-ad_course /> --}}

        {{-- @php
            dd($posts->toArray());
            $postsCheck = $posts ? 'yes' : 'no';
            dd($postsCheck);
        @endphp --}}
        <x-more_cards_div title="Другие материалы:">
            @foreach ($posts as $post)
            <a class="noclass" href="{{route('shpargalka.show', $post->id)}}"><x-more_card title="{{$post->title}}" description="Подзаголовок" :tags="isset($post) ? $categories->where('id', $post->category_id) : []" :price="$post->price" img="{{'/storage/' . $post->main_image}}" /></a>
            @endforeach
            
            <x-slot:pagination>
                <div class="flex justify-center md:mt-8 mt-1">
                    <a class="noclass" href="{{route('shpargalka.index')}}"><button class="md:px-8 md:py-4 px-6 py-3 border-2 border-black bg-white text-black font-semimedium tracking-wider rounded-lg">Все материалы <img class="inline-block ml-1" src="{{ asset('img/arrow_black-button.svg') }}" alt="" srcset=""></button></a>
                </div>
            </x-slot:pagination>
            {{-- <x-more_card title="Отмена крепостного права. Как это было" description="Подзаголовок" :tags="$title" img="/img/ivan.webp"/>
            <x-more_card title="Заголовок" description="Подзаголовок" :tags="$title" img="/img/ivan.webp"/>
            <x-more_card title="Заголовок" description="Подзаголовок" :tags="$title" img="/img/ivan.webp"/> --}}
        </x-more_cards_div>


        
        <x-footer />
    
        {{-- <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
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
        </script> --}}
    </body>
</html>

@endsection
