@extends('admin.layouts.main')

@section('content')
    <div>

        <h1 class="text-xl sans mb-4">Упражнения</h1> 
        @foreach ($exercises as $exercise)
            <a href="{{route('admin.exercise.show', $exercise)}}"><p class="mt-2 text-zinc-400">{{$exercise->id}}. <span class="text-zinc-900">{{$exercise->title}}</span></p></a>
        @endforeach
        <a href="{{route('admin.exercise.create')}}"><button class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать упражнение</button></a>


@endsection
