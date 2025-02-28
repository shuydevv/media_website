@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Посты</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        @foreach ($posts as $post)
            <a href="{{route('admin.post.show', $post)}}"><p class="mt-2 text-zinc-400">{{$post->id}}. <span class="text-zinc-900">{{$post->title}}</span></p></a>
            
            @endforeach
        <a href="{{route('admin.post.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать пост</button></a>


@endsection
