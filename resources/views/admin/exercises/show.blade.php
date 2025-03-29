@extends('admin.layouts.main')

@section('content')
    <div>

        <h1 class="text-xl sans mb-4">Упражнение {{$exercise->title}}</h1> 
        <div><span>Id: {{$exercise->id}}</span> — Название: {{$exercise->title}}</div>
        <div class="flex gap-2">
            <a href="{{route('admin.exercise.edit', $exercise)}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Редактировать упражнение</button></a>
            <form action="{{ route('admin.exercise.delete', $exercise->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Удалить упражнение</button>
            </form>
        </div>

        

@endsection
