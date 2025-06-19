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
            <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 text-start tracking-wide text-zinc-900"><span class="sans">Страница в разработке</span><span class="font-normal tracking-wide"></span></h1>
            <x-text text="Скоро здесь появится тренажер для решения заданий. А пока вы можете посмотреть другие страницы" />
            {{-- <ul class="flex md:gap-4 gap-3 flex-wrap md:mt-10 mt-8">
                <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => null]) }}"><li class="{{request()->query('post_category') == null ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Все статьи</li></a>
                <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'social_science']) }}"><li class="{{request()->query('post_category') == 'social_science' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Обществознание</li></a>
                <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'history']) }}"><li class="{{request()->query('post_category') == 'history' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">История</li></a>
                
            </ul> --}}
            <button class="accordion attention-tag md:mt-10 mt-8 py-4 px-6 bg-blue-600 text-white rounded tracking-wide">Вернуться на главную</button>

        </div>

        {{-- @php
        $post_category_name = '';
        if(request()->query('post_category') == 'social_science') {
            $post_category_name = 'Статьи по обществознанию';
        } elseif (request()->query('post_category') == 'history') {
            $post_category_name = 'Статьи по истории';
        } else {
            $post_category_name = 'Все статьи';
        }
        @endphp --}}

        {{-- <div class="w-full container mx-auto max-w-screen-lg">
            @foreach ($posts as $post)
            <a class="noclass">
                <div class="md:mt-12 mt-8 md:mb-20 mb-12 px-6 py-6 bg-white mx-2 rounded-2xl">
                    <ul class="flex justify-start flex-wrap gap-2">
                        <li class="mb-6 md:py-2 py-1 md:px-4 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80 tracking-wide list_exists">Задание № {{$post->ex_number}} </li>
                    </ul>
                    <h3 class="sans md:text-xl text-l text-zinc-800 leading-relaxed mb-8">{{$post->title}}</h3>
                    <img class="max-w-48" src="{{'storage/' . $post->main_image}}" alt="">
                    {!! Blade::render($post->text_spoiler) !!} 
                </div>
            </a>
            @endforeach
        </div> --}}


            <x-slot:pagination>
                {{$posts->links()}}
            </x-slot>
        <x-material></x-material>
        <x-footer />
    
    </body>
</html>

@endsection
