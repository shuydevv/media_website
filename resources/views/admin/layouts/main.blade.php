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
        <div class="flex justify-between mb-4 p-4 border w-full bg-white z-10 items-center flex-wrap grow-0">
            <a href="{{route('main.index')}}"><h2 class="md:text-xl md:mb-0 mb-4 text-lg tracking-wide font-medium">Админ-панель</h2></a>
            <ul class="gap-10 md:flex hidden">
                <li style="color: rgb(217 119 6);" class="tracking-wide"></li>
                <li class="tracking-wide"></li>
                {{-- <li><a href="{{route('logout')}}">Выйти</a></li> --}}
                <!-- <li>Бесплатные статьи</li> -->
                <!-- <li>Вход / Регистрация</li> -->
            </ul>
        </div>
        <div class="container mx-auto max-w-screen-xl px-3 md:mb-20 mb-16 mt-8">
            <div class="grid md:grid-cols-3 grid-cols-1 gap-4">
                <div class="col-span-1">
                    <ul class="border p-4">
                        <a href="{{route('main.index')}}"><li class="mb-2 hover:opacity-50">Главная</li></a>
                        <a href="{{route('admin.category.index')}}"><li class="mb-2 hover:opacity-50">Категории</li></a>
                        <a href="{{route('admin.section.index')}}"><li class="mb-2 hover:opacity-50">Разделы</li></a>
                        <a href="{{route('admin.topic.index')}}"><li class="mb-2 hover:opacity-50">Темы</li></a>
                        <a href="{{route('admin.tag.index')}}"><li class="mb-2 hover:opacity-50">Тэги</li></a>
                        <a href="{{route('admin.post.index')}}"><li class="mb-2 hover:opacity-50">Посты</li></a>
                        <a href="{{route('admin.user.index')}}"><li class="mb-2 hover:opacity-50">Пользователи</li></a>
                        <a href="{{route('admin.shpargalka.index')}}"><li class="mb-2 hover:opacity-50">Шпаргалки</li></a>
                        <hr class="mt-4 mb-4">
                        
                        <a href="{{route('admin.exercise.index')}}"><li class="mb-2 hover:opacity-50">Упражнения</li></a>
                        {{-- <a href="{{route('admin.topic.index')}}"><li class="mb-2 hover:opacity-50">Тема</li></a>
                        <a href="{{route('admin.section.index')}}"><li class="mb-2 hover:opacity-50">Раздел</li></a> --}}
                        
                        <form action="{{route('logout')}}" method="post">
                            @csrf
                            <button type="submit" class="mt-16 hover:opacity-50">Выйти</button>
                        </form>
                        
                    </ul>
                </div>
                <div class="col-span-2 border p-4">
                    @yield('content')
                </div>
            </div>
        </div>
    </body>
</html>