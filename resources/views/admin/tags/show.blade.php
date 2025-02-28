@extends('admin.layouts.main')

@section('content')
    <div>

        <h1 class="text-xl sans mb-4">Тэг {{$tag->title}}</h1> 

        <div><span>Id: {{$tag->id}}</span> — Название: {{$tag->title}}</div>

        <div class="flex gap-2">
            <a href="{{route('admin.tag.edit', $tag)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать тэг</button></a>
            <form action="{{ route('admin.tag.delete', $tag->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить тэг</button>
            </form>
        </div>

        

@endsection
