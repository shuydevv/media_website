<div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-16 px-3">
    <h1 class="md:leading-normal leading-normal md:text-4xl text-2xl font-medium md:mb-6 mb-4 text-center tracking-wide text-zinc-900"><span class="sans">{{$title1}}</span><span class="md:text-4xl text-2xl font-normal tracking-wider">{{$title2}}</span></h1>
    <p class="md:text-xl text-center text-l flex justify-center opacity-90 tracking-wide text-zinc">{{$description}}</p>

    {{-- <div>
        <div class="md:mt-12 mt-8 md:mb-16 mb-12 flex md:gap-4 gap-3 justify-center flex-wrap">
            <button class="md:w-auto w-full md:px-8 md:py-4 px-6 py-4 bg-indigo-500 text-white font-semimedium tracking-wider rounded-lg">Попробовать бесплатно <img class="inline-block ml-1" src="{{asset('img/arrow_white-button.svg')}}" alt="arrow"></button>
            <button class="md:w-auto w-full md:px-8 md:py-4 px-6 py-4 border-2 border-indigo-500 font-semimedium tracking-wider rounded-lg">Купить за <span class="font-semibold text-indigo-500">3900 ₽</span><span class="ml-2 line-through text-zinc-400"> 5900 ₽</span></button>
        
        </div>
    </div> --}}

    <img class="w-full md:mt-12 mt-8 rounded-lg" src="{{$img}}" alt="" srcset="">
    <div class="md:mt-8 mt-4">

        <ul class="flex justify-center flex-wrap gap-2">

            @foreach ($tags as $tag)
            <li class="md:py-2 py-1 md:px-3 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80 tracking-wide"> {{$tag->title}} </li>
            @endforeach
            {{-- <li class="md:py-2 py-1 md:px-3 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80">История</li>
            <li class="md:py-2 py-1 md:px-3 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80">20 век</li>
            <li class="md:py-2 py-1 md:px-3 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80">Хрущев</li>
            <li class="md:py-2 py-1 md:px-3 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80">Карибский кризис</li> --}}
        </ul>

    </div>

</div>