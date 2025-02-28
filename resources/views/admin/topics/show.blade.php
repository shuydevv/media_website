@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Тема {{$topic->title}}</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        {{-- @foreach ($categories as $category) --}}
        <div><span>Id: {{$topic->id}}</span> — Название: {{$topic->title}} <br> Раздел: {{$topic->section->title}} </div>
            {{-- <a href="{{route('')}}"><p class="mt-2 text-zinc-400">{{$category->id}}. <span class="text-zinc-900">{{$category->title}}</span></p></a> --}}
        {{-- @endforeach --}}
        <div class="flex gap-2">
            <a href="{{route('admin.topic.edit', $topic)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать тему</button></a>
            <form action="{{ route('admin.topic.delete', $topic->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить тему</button>
            </form>
        </div>

        

@endsection
