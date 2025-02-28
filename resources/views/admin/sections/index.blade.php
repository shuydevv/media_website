@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Разделы</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        @foreach ($sections as $section)
            <a href="{{route('admin.section.show', $section)}}"><p class="mt-2 text-zinc-400">{{$section->id}}. <span class="text-zinc-900">{{$section->title}}</span></p></a>
        @endforeach
        <a href="{{route('admin.section.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать раздел</button></a>


@endsection
