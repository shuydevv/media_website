@extends('admin.layouts.main')

@section('content')
    <div>

        <h1 class="text-xl sans mb-4">Пост {{$post->title}}</h1> 

        <div><span>Id: {{$post->id}}, Path: {{$post->path}}</span> — Название: {{$post->title}}</div>

        <div class="flex gap-2">
            <a href="{{route('admin.post.edit', $post)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать пост</button></a>
            <form action="{{ route('admin.post.delete', $post->path) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить пост</button>
            </form>
        </div>

        

@endsection
