@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Редактировать раздел</h1>
        <form action="{{ route('admin.section.update', $section->id) }}" method="post">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-zinc-800 text-sm">Название раздела</label>
                <input class="p-2 block border" type="text" value="{{$section->title}}" name="title">
            </div>
            @error('title')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror
            <div class="mt-5">
                <label class="mr-5">Выберите категорию</label>
                <select class="p-2 border" value="{{$section->category}}" name="category_id">
                    @foreach ($categories as $category)
                    <option value="{{$category->id}}" {{ $category->id == $section->category_id ? ' selected' : '' }}>{{$category->title}}</option>
                    @endforeach
                </select>
            </div>
            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Опубликовать изменения</button></a>
        </form>
        


@endsection
