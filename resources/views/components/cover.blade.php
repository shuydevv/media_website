<div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-16 mb-8 px-3">
    <h1 class=" md:text-4xl text-2xl font-medium md:mb-6 mb-4 {{ $img ? 'text-center' : 'md:text-center text-start' }} tracking-wide text-zinc-900"><span class="sans">{{$title1}}</span><span class="md:text-4xl text-2xl font-normal tracking-wider">{{$title2}}</span></h1>
    <p class="md:text-xl text-center text-base flex justify-center opacity-90 tracking-wide text-zinc-900">{{$description}}</p>

    <style>
        .if-container-empty {
            display: none;
        }
        .if-container-empty:has(.list_exists) {
            display: block;
        }
    </style>
    {{-- Раньше проверка была "$img !== '/storage'" — $img всегда абсолютный
         URL (или пусто), поэтому со строкой '/storage' никогда не совпадал:
         картинка без обложки (main_image = null) всё равно рендерилась,
         просто с битым src. --}}
    @if($img)
    <img class="w-full md:mt-12 mt-8 rounded-lg" src="{{ $img }}" alt="{{ $title1 }}">
    @endif
    <div class="md:mt-8 mt-4 if-container-empty">
        @if(!$isTherePlans)
        <ul class="flex justify-center flex-wrap gap-2">
            @foreach ($tags as $tag)
            <li class="md:py-2 py-1 md:px-3 px-2 rounded-lg border-2 backdrop-blur-xl text-sm opacity-80 tracking-wide list_exists"> {{$tag->title}} </li>
            @endforeach
        </ul>
        @endif
    </div>

</div>