{{-- <a class="noclass" href="{{route('post.show', $path)}}"> --}}
<div class="md:p-4 p-3 border group rounded-lg article-card cursor-pointer bg-white">
    <div class="relative">
        @if (isset($price))
        <div style="border-color: #d2deff" class="absolute top-2 left-2 bg-white border-2 px-3 py-0.5 blue-color rounded-full">{{$price}} ₽</div>
        @endif


        <img class="mb-3 aspect-video object-cover rounded-l" src="{{$img}}" srcset="">
    </div>
    <div class="flex align-baseline justify-between items-start flex-col">
        <h3 class="title-card group-hover:text-amber-700 transition-all md:text-xl tracking-wide text-l mb-2 text-zinc-900">{{$title}}{{isset($title2) ? '. ' . $title2 : ' '}}</h3>
        <div class="flex items-start">

            <img class="md:w-6 w-5 mt-2 group-hover:rotate-45 transition-all" src="{{asset('img/arrow.svg')}}" alt="arrow" srcset="">
            <div>
                <ul class="ml-4 mt-1 flex flex-wrap md:gap-3 gap-2">
                    @foreach ($tags as $tag)
                        <li class="text-zinc-400 tracking-wide rounded bg-zinc-100 px-2 py-1 text-sm">{{$tag->title}}</li>
                    @endforeach
                    
                </ul>
            </div>

        </div>
    </div>    
</div>
{{-- </a> --}}