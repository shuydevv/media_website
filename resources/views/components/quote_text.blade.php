<div style="border-color: rgb(217 119 6);" class="border-l-2 md:pl-8 pl-4 container mx-auto max-w-screen-md px-2 md:mt-16 mt-10 md:mb-20 mb-12">
    <p class="text-zinc-900">«{{$text}}»</p>
    <p class="md:text-xl text-l md:mt-5 mt-4 text-zinc-400 tracking-wider">
        @if ($source == null)
        
        @else
        — {{$source}}    
        @endif
        </p>
</div>