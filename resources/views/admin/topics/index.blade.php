@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Темы</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        @foreach ($topics as $topic)
            <a href="{{route('admin.topic.show', $topic)}}"><p class="mt-2 text-zinc-400">{{$topic->id}}. <span class="text-zinc-900">{{$topic->title}}</span></p></a>
        @endforeach
        <a href="{{route('admin.topic.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать тему</button></a>


@endsection
