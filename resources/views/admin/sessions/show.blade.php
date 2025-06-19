@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Категория {{$category->title}}</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        {{-- @foreach ($categories as $category) --}}
        <div><span>Id: {{$category->id}}</span> — Название: {{$category->title}}</div>
            {{-- <a href="{{route('')}}"><p class="mt-2 text-zinc-400">{{$category->id}}. <span class="text-zinc-900">{{$category->title}}</span></p></a> --}}
        {{-- @endforeach --}}
        <div class="flex gap-2">
            <a href="{{route('admin.category.edit', $category)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать категорию</button></a>
            <form action="{{ route('admin.category.delete', $category->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить категорию</button>
            </form>
        </div>

        

@endsection
