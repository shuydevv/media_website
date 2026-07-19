    @php
        $image = $images[$img] ?? null;
    @endphp
    <div class="rounded-lg border md:p-4 p-2 md:mt-16 mt-10 md:mb-20 mb-12 flex justify-center flex-col items-center">
        <img class="md:mb-3 mb-1" src="{{asset('img/quote.svg')}}" alt="quote">
        <p class="text-center italic md:text-lg text-base sans font-medium text-zinc-900">{{$text}}</p>
        <div class="mt-6 flex justify-center flex-col items-center">
            @if($image)
                <img class="rounded-full w-20 h-20 object-cover" src="{{ asset('storage/' . $image->name) }}" alt="{{ $name }}">
            @endif
            <p class="not-italic mt-3 text-lg font-medium text-zinc-900">{{$name}}</p>
            <p class="not-italic md:text-base text-sm text-zinc-400 text-center">{{$description}}</p>
        </div>
    </div>