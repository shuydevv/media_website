@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Создать пост</h1>
        <form action="{{route('admin.post.store')}}" method="post" enctype="multipart/form-data">
            @csrf
            <div>
                <label class="text-zinc-800 text-sm">Заголовок поста #1</label>
                <input rows='1' class="p-2 block border mb-4" type="text" placeholder="Введите заголовок" name="title"></input> 
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Заголовок поста #2</label>
                <input rows='1' class="p-2 block border mb-4" type="text" placeholder="Введите заголовок" name="title2"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Описание поста</label>
                <input rows='1' class="p-2 block border mb-4" type="text" placeholder="Введите описание поста" name="description"></input>
            </div>
            @error('title')
            <p class=" mb-8 text-red-400">*{{ $message }}</p>
            @enderror
            <div>
                <label class="text-zinc-800 text-sm">Путь к посту</label>
                <input rows='1' class="p-2 block border mb-4" type="text" placeholder="Введите путь к посту" name="path"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-заголовок</label>
                <input rows='1' class="p-2 block border mb-4" type="text" placeholder="Введите html-заголовок" name="html_title"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-описание</label>
                <input rows='1' class="p-2 block border mb-4" type="text" placeholder="Введите html-описание" name="html_description"></input>
            </div>
            <div>
                {{-- <label class="text-zinc-800 text-sm">Поста тэга</label> --}}
                <textarea rows='1' class="p-2 block border w-full min-h-80" type="text" placeholder="Содержание поста" name="content"></textarea>
            </div>
            @error('post')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror
            
            <div class="mt-5">
                <label class="mr-5">Выберите категорию</label>
                <select name="category_id">
                    @foreach ($categories as $category)
                    <option value="{{$category->id}}">{{$category->title}}</option>
                    @endforeach
                    {{-- <option value="">История</option>
                    <option value="">Обществознание</option> --}}
                </select>
            </div>
            <div class="mt-5">
                <label class="mr-5">Выберите тэги:</label>
                @foreach ($tags as $tag)
                <div>
                    <input type="checkbox" value="{{ $tag->id }}" name="tag_ids[]" id="">
                    <label>{{ $tag->title }}</label>
                </div>
                @endforeach

            </div>
            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files">Главное изображение (обложка)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files" name="main_image" type="file"></div>
            
            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files2">Изображения в посте (загрузить списком)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files2" name="multi_images[]" type="file" multiple>
            </div>
            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать пост</button></a>    
        </form>

        


@endsection
