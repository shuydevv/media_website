@extends('admin.layouts.main')

@section('content')
    <div>

        <h1 class="text-xl sans mb-4">Шпаргалки</h1> 
        @foreach ($shpargalkas as $shpargalka)
            <a href="{{route('admin.shpargalka.show', $shpargalka)}}"><p class="mt-2 text-zinc-400">{{$shpargalka->id}}. <span class="text-zinc-900">{{$shpargalka->title}}</span></p></a>
        @endforeach
        <a href="{{route('admin.shpargalka.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать шпаргалку</button></a>


@endsection
