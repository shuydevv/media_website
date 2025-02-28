@extends('admin.layouts.main')

@section('content')
<div>
    {{-- @dd($categories) --}}

    <h1 class="text-xl sans mb-4">Пользователи</h1> 
    {{-- <p class="mt-2">Категории</p> --}}
    @foreach ($users as $user)
        <a href="{{route('admin.user.show', $user)}}"><p class="mt-2 text-zinc-400">{{$user->id}}. <span class="text-zinc-900">{{$user->name}}</span></p></a>
    @endforeach
    <a href="{{route('admin.user.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать пользователя</button></a>


@endsection
