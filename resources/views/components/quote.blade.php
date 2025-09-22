    <!-- Do what you can, with what you have, where you are. - Theodore Roosevelt -->
    {{-- <div class="container mx-auto max-w-screen-md px-3 md:mb-20 mb-16"> --}}
    <div class="rounded-lg border md:p-4 p-2 md:mt-16 mt-10 md:mb-20 mb-12 flex justify-center flex-col items-center">
        <img class="md:mb-3 mb-1" src="{{asset('img/quote.svg')}}" alt="quote">
        <p class="text-center italic md:text-lg text-base sans font-medium text-zinc-900">{{$text}}</p>
        <div class="mt-6 flex justify-center flex-col items-center">
            <img class="rounded-full w-20 h-20 object-cover" src="{{asset('storage/' . $images[$img]->name)}}" alt="portrait">
            <p class="not-italic mt-3 text-lg font-medium text-zinc-900">{{$name}}</p>
            <p class="not-italic md:text-base text-sm text-zinc-400 text-center">{{$description}}<p>
        </div>
    </div>
    {{-- </div> --}}