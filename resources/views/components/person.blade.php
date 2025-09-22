<div class="flex flex-col items-center justify-center md:mt-10 md:mb-12 mt-8 mb-8">
    <img class="rounded-full w-28 h-28 mb-3 object-cover" src="{{asset('storage/' . $images[$img]->name)}}" alt="portrait">
    <h3 class="md:text-xl font-medium text-lg text-zinc-900">{{$title}}</h3>
    <p class="sans font-normal md:text-lg text-sm text-center mt-1 text-zinc-400">{{$description}}</p>
</div>