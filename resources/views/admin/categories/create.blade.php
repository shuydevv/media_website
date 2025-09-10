@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Создать категорию</h1>
        <form action="{{ route('admin.category.store') }}" method="post">
            @csrf
            @method('POST')
            <div>
                <label class="text-zinc-800 text-sm">Название категории</label>
                <input class="p-2 block border" type="text" name="title">
            </div>
            @error('title')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror
            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Сохранить категорию</button></a>
        </form>
        


@endsection