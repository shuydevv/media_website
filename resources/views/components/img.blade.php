@php
    $image = $images[$img] ?? null;
@endphp
<div class="md:mt-12 md:mb-16 mt-8 mb-10">
    @if($image)
        <img class="md:rounded-lg rounded-lg w-full" src="{{ asset('storage/' . $image->name) }}" alt="{{ $description }}">
    @endif
    <p class="sans text-zinc-400 md:mt-3 mt-2 md:text-l text-sm tracking-wide">{{$description}}</p>
</div>