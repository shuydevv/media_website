@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Редактировать шпаргалку</h1>
        <form action="{{ route('admin.shpargalka.update', $shpargalka->id) }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-zinc-800 text-sm">Название шпаргалки</label>
                <input class="p-2 block border" type="text" value="{{$shpargalka->title}}" name="title">
            </div>
            @error('title')

            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror
            <div>
                <label class="text-zinc-800 text-sm">Цена шпаргалки</label>
                <input class="p-2 block border" type="text" value="{{$shpargalka->price}}" placeholder="Введите цену" name="price">
            </div>
            <div>
                <textarea rows='1' class="p-2 block border w-full min-h-80" type="text" placeholder="Содержание поста" name="content">{{$shpargalka->content}}</textarea>
            </div>
            <div>
                <textarea rows='1' class="p-2 block border w-full min-h-80" type="text" placeholder="Содержание поста" name="description">{{$shpargalka->description}}</textarea>
            </div>

            <div>
                <label class="text-zinc-800 text-sm">Путь к шпаргалке</label>
                <input rows='1' class="p-2 block border mb-4" value="{{$shpargalka->path}}" type="text" placeholder="Введите путь к посту" name="path"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-заголовок</label>
                <input rows='1' class="p-2 block border mb-4" value="{{$shpargalka->html_title}}" type="text" placeholder="Введите html-заголовок" name="html_title"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-описание</label>
                <input rows='1' class="p-2 block border mb-4" value="{{$shpargalka->html_description}}" type="text" placeholder="Введите html-описание" name="html_description"></input>
            </div>

            <div class="mt-5">
                <label class="mr-5">Выберите категорию</label>
                <select value="{{$shpargalka->category}}" name="category_id">
                    @foreach ($categories as $category)
                    <option value="{{$category->id}}" {{ $category->id == $shpargalka->category_id ? ' selected' : '' }}>{{$category->title}}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <p class="mb-2 mt-5 font-medium">Изображения в посте</p>
                <div class="flex gap-2 items-start flex-wrap">
                    @foreach ($images as $image)
                    @if ($image->shpargalka_id == $shpargalka->path)
                            <img class="w-36" src="{{ asset('storage/' . $image->name) }}" alt="img">
                    @endif
                    @endforeach
                </div>
            </div>

            {{-- @error('post')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror --}}

            <div>
                <p class="mb-2 mt-5 font-medium">Обложка</p>
                <img class="w-60" src="{{ asset('storage/' . $shpargalka->main_image) }}" alt="img">
            </div>



            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files">Главное изображение (обложка)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files" name="main_image" type="file"></div>
            
            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files2">Изображения в посте (загрузить списком)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files2" name="multi_images[]" type="file" multiple>
            </div>
            
            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Опубликовать изменения</button></a>
        </form>
        


@endsection
