@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Редактировать тэг</h1>
        <form action="{{ route('admin.tag.update', $tag->id) }}" method="post">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-zinc-800 text-sm">Название тэга</label>
                <input class="p-2 block border" type="text" value="{{$tag->title}}" name="title">
            </div>
            @error('title')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror
            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Опубликовать изменения</button></a>
        </form>
        


@endsection
