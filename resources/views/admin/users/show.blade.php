@extends('admin.layouts.main')

@section('content')
    <div>
        {{-- @dd($categories) --}}

        <h1 class="text-xl sans mb-4">Пользователь {{$user->name}}</h1> 
        {{-- <p class="mt-2">Категории</p> --}}
        {{-- @foreach ($categories as $category) --}}
        <div><span>Id: {{$user->id}}</span> — Имя: {{$user->name}}</div>
            {{-- <a href="{{route('')}}"><p class="mt-2 text-zinc-400">{{$category->id}}. <span class="text-zinc-900">{{$category->title}}</span></p></a> --}}
        {{-- @endforeach --}}
        <div class="flex gap-2">
            <a href="{{route('admin.user.edit', $user)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать пользователя</button></a>
            <form action="{{ route('admin.user.delete', $user->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить пользователя</button>
            </form>
        </div>

        

@endsection
