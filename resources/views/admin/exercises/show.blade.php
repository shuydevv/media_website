@extends('admin.layouts.main')

@section('content')
    <div>

        <h1 class="text-xl sans mb-4">Шпаргалка {{$shpargalka->title}}</h1> 
        <div><span>Id: {{$shpargalka->id}}</span> — Название: {{$shpargalka->title}}</div>
        <div class="flex gap-2">
            <a href="{{route('admin.shpargalka.edit', $shpargalka)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать шпаргалку</button></a>
            <form action="{{ route('admin.shpargalka.delete', $shpargalka->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить шпаргалку</button>
            </form>
        </div>

        

@endsection
