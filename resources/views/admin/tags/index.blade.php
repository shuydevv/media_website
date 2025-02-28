@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Тэги</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        @foreach ($tags as $tag)
            <a href="{{route('admin.tag.show', $tag)}}"><p class="mt-2 text-zinc-400">{{$tag->id}}. <span class="text-zinc-900">{{$tag->title}}</span></p></a>
        @endforeach
        <a href="{{route('admin.tag.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать тэг</button></a>


@endsection
