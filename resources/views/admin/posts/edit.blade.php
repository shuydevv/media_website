@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Редактировать пост</h1>
        <form action="{{ route('admin.post.update', $post->path) }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-zinc-800 text-sm">Заголовок поста #1</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{$post->title}}" name="title"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Заголовок поста #2</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{$post->title2}}" name="title2"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Описание поста</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{$post->description}}" name="description"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Путь к посту</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{$post->path}}" placeholder="Введите путь к посту" name="path"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-заголовок</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{$post->html_title}}" placeholder="Введите html-заголовок" name="html_title"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-описание</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{$post->html_description}}" placeholder="Введите html-описание" name="html_description"></input>
            </div>
            <div>
                <textarea rows='1' class="p-2 block border w-full min-h-80" type="text" placeholder="Содержание поста" name="content">{{$post->content}}</textarea>
            </div>

            @error('post')
            <p class="mt-2 text-red-400">*{{ $message }}</p>
            @enderror


            
            <div class="mt-5">
                <label class="mr-5">Выберите категорию</label>
                <select value="{{$post->category}}" name="category_id">
                    @foreach ($categories as $category)
                    <option value="{{$category->id}}" {{ $category->id == $post->category_id ? ' selected' : '' }}>{{$category->title}}</option>
                    @endforeach
                </select>
            </div>
            <div class="mt-5 mb-5">
                <p class="mr-5 font-medium mb-1">Выберите тэги:</p>
                {{-- @dd($post->tags) --}}
                @foreach ($tags as $tag)
                <div>
                    <input type="checkbox" value="{{ $tag->id }}" name="tag_ids[]" id=""
                    
                    @foreach ($post->tags as $posttag)
                        @if ($posttag->id == $tag->id)
                        checked
                        @endif
                    @endforeach
                    >
                    <label>{{ $tag->title }}</label>
                </div>
                    {{-- @foreach ($post->tags as $posttag)
                        @if ($posttag->id == $tag->id)
                        
                        <div>
                            <input type="checkbox" value="{{ $post->tag }}" name="tag_ids[]" id="" checked>
                            <label>{{ $tag->title }}</label>
                        </div>
                        @else
                        <div>
                            <input type="checkbox" value="{{ $post->tag }}" name="tag_ids[]" id="">
                            <label>{{ $tag->title }}</label>
                        </div>
                        @endif
                    @endforeach --}}


                @endforeach

            </div>
            <div>
                <p class="mb-2 mt-5 font-medium">Обложка</p>
                <img class="w-60" src="{{ asset('storage/' . $post->main_image) }}" alt="img">
            </div>
            <div>
                <p class="mb-2 mt-5 font-medium">Изображения в посте</p>
                <div class="flex gap-2 items-start flex-wrap">
                    @foreach ($images as $image)
                    @if ($image->post_id == $post->path)
                            <img class="w-36" src="{{ asset('storage/' . $image->name) }}" alt="img">
                    @endif
                    @endforeach
                </div>
                
            </div>
            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files">Главное изображение (обложка)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files" name="main_image" type="file"></div>
            
            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files2">Изображения в посте (загрузить списком)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files2" name="multi_images[]" type="file" multiple>
            </div>
            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Обновить пост</button></a>
        </form>
        


@endsection
