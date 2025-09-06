@extends('layouts.admin')

@section('content')
<body>


    {{-- @php
    $title = [
        'title 1' => 'История',
        'title 2' => '20 век',
        'title 3' => 'Хрущев',
    ];   
    @endphp --}}

<div>
<style>
    .sidebar {
        width: 64px;
        transition: width 0.3s ease;
    }

    .sidebar:hover {
        width: 208px;
    }

    .label {
        position: absolute;
        left: 3rem; /* смещаем текст правее иконки */
        white-space: nowrap;
        opacity: 0;
        transform: translateX(-10px);
        transition: all 0.3s ease;
    }

    .sidebar:hover .label {
        opacity: 1;
        transform: translateX(0);
    }
</style>

<div class="hidden sm:flex sidebar ml-4 pl-4 mt-6 fixed z-10 bg-white rounded-2xl shadow-md border border-gray-200 p-3 flex-col gap-4">
    <a href="#" class="relative flex items-center text-gray-900 hover:text-blue-600 text-sm h-8">
        <span class="text-xl w-8 text-center">🏠</span>
        <span class="label">Главная</span>
    </a>
    <a href="#" class="relative flex items-center text-gray-900 hover:text-blue-600 text-sm h-8">
        <span class="text-xl w-8 text-center">🎥</span>
        <span class="label">Вебинары</span>
    </a>
    <a href="#" class="relative flex items-center text-gray-900 hover:text-blue-600 text-sm h-8">
        <span class="text-xl w-8 text-center">📝</span>
        <span class="label">Домашка</span>
    </a>
    <a href="#" class="relative flex items-center text-gray-900 hover:text-blue-600 text-sm h-8">
        <span class="text-xl w-8 text-center">📈</span>
        <span class="label">Прогресс</span>
    </a>
    <a href="#" class="relative flex items-center text-gray-900 hover:text-blue-600 text-sm h-8">
        <span class="text-xl w-8 text-center">💬</span>
        <span class="label">Поддержка</span>
    </a>
    <a href="#" class="relative flex items-center text-gray-900 hover:text-blue-600 text-sm h-8 mt-auto">
        <span class="text-xl w-8 text-center">👤</span>
        <span class="label">Профиль</span>
    </a>
</div>

    {{-- Mobile Bottom Bar --}}
    <div class="sm:hidden fixed bottom-2 left-2 right-2 bg-white border border-gray-200 rounded-xl px-4 py-3 shadow-sm z-50">
        <div class="flex overflow-x-auto space-x-6 no-scrollbar justify-between">
            @foreach ([
                ['icon' => '🏠', 'label' => 'Главная'],
                ['icon' => '🎥', 'label' => 'Вебинары'],
                ['icon' => '📘', 'label' => 'Домашка'],
                ['icon' => '📈', 'label' => 'Прогресс'],
                ['icon' => '💬', 'label' => 'Поддержка'],
                ['icon' => '👤', 'label' => 'Профиль'],
            ] as $item)
                <a href="#" class="flex flex-col items-center text-xs text-gray-600 hover:text-blue-600 min-w-[56px] flex-shrink-0">
                    <span class="text-xl">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>

<style>
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>


<div class="w-full sm:px-4 px-2 py-3 sm:py-6">
    <div class="max-w-6xl mx-auto bg-white rounded-xl shadow-sm px-2 sm:px-4 py-4 sm:py-6">
        <div class="flex justify-between items-end mb-4 border-b border-gray-200 pb-2">
            <h2 class="text-xl md:text-xl lg:text-2xl font-medium text-gray-800 font-sans">Расписание</h2>
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
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>

<script>
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
    
    <div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-12 px-3">
        <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 text-start tracking-wide text-zinc-900"><span class="sans">Мои курсы</span><span class="font-normal tracking-wide"></span></h1>
        {{-- <x-text text="Бесплатный учебник для всех желающих улучшить свои знания" /> --}}
        <ul class="flex md:gap-4 gap-3 flex-wrap md:mt-10 mt-8">
            <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => null]) }}"><li class="{{request()->query('post_category') == null ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Все курсы</li></a>
            <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'social_science']) }}"><li class="{{request()->query('post_category') == 'social_science' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Обществознание</li></a>
            <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'history']) }}"><li class="{{request()->query('post_category') == 'history' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">История</li></a>
            
        </ul>
    </div>

    <div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-12 px-3">
        <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 text-start tracking-wide text-zinc-900"><span class="sans">Все курсы</span><span class="font-normal tracking-wide"></span></h1>
        <x-text text="Бесплатный учебник для всех желающих улучшить свои знания" />
        <ul class="flex md:gap-4 gap-3 flex-wrap md:mt-10 mt-8">
            <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => null]) }}"><li class="{{request()->query('post_category') == null ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Все курсы</li></a>
            <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'social_science']) }}"><li class="{{request()->query('post_category') == 'social_science' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">Обществознание</li></a>
            <a href="{{ request()->fullUrlWithQuery(['page' => null, 'post_category' => 'history']) }}"><li class="{{request()->query('post_category') == 'history' ? 'bg-zinc-900 text-white border-zinc-900' : ''}} md:text-base text-sm px-4 py-2 border-2 rounded-full">История</li></a>
            
        </ul>
    </div>
    {{-- {{dd(request()->query('list'))}} --}}
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
    {{-- <x-more_cards_div title="{{$post_category_name}}">
        @foreach ($posts as $post)
            <a href="{{route('post.show', $post->path)}}"><x-more_card title="{{$post->title}}" title2="{{$post->title2}}" description="Подзаголовок" :tags="$post->tags" img="{{'storage/' . $post->main_image}}" /></a>
        @endforeach

        
        <x-slot:pagination>
            {{$posts->links()}}
        </x-slot>
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
@endsection
