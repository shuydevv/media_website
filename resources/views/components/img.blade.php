
@php
    $new_images = [];
    $path = asset('storage/');
@endphp
<div class="md:mt-12 md:mb-16 mt-8 mb-10">
    {{-- {{dd($images)}} --}}
    <img class="md:rounded-lg rounded-lg w-full" src="{{asset('storage/' . $images[$img]->name)}}">
    <p class="sans text-zinc-400 md:mt-3 mt-2 md:text-l text-sm tracking-wide">{{$description}}</p>
</div>

{{-- <x-img img="{{$new_images[0]->name}}" description="fdf" /> --}}