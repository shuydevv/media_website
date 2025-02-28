@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Категории</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        @foreach ($categories as $category)
            <a href="{{route('admin.category.show', $category)}}"><p class="mt-2 text-zinc-400">{{$category->id}}. <span class="text-zinc-900">{{$category->title}}</span></p></a>
        @endforeach
        <a href="{{route('admin.category.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать категорию</button></a>


@endsection
