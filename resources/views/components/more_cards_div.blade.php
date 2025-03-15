<style>
    .if-container-empty {
        display: none;
    }
    .if-container-empty:has(.list_exists) {
        display: block;
        /* В more_card есть класс list_exists, если похожих статей не существует, то display none */
    }
    img[src=""] {
        display: none;
    }
</style>

<div class="container mx-auto max-w-screen-lg md:mt-20 mt-12 md:mb-20 mb-12 px-3 if-container-empty">
    <h2 class="md:text-2xl text-xl md:mb-4 mb-3 mt-10 text-zinc-900 tracking-wider">

        @if ($title == '')
        Статьи по теме:
        @else
            {{ $title }}
        @endif

    </h2>

    <div class="grid md:grid-cols-2 grid-cols-1 gap-4">
        {{$slot}}
    </div>
    
    <div class="flex justify-center mt-8">
        {{-- <button class="px-8 py-4 border-2 border-black bg-white text-black font-semimedium tracking-wider rounded-lg">Все статьи <img class="inline-block ml-1" src="img/arrow_black-button.svg" alt="" srcset=""></button> --}}
        {{ $pagination }}
    </div>
</div>
