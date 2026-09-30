@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Редактировать пост</h1>
        @include('admin.posts._form_errors')
        <form action="{{ route('admin.post.update', $post->id) }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div>
                <label class="text-zinc-800 text-sm">Заголовок поста #1</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{ old('title', $post->title) }}" name="title"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Заголовок поста #2</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{ old('title2', $post->title2) }}" name="title2"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Описание поста</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{ old('description', $post->description) }}" name="description"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Путь к посту</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{ old('path', $post->path) }}" placeholder="Введите путь к посту" name="path"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-заголовок</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{ old('html_title', $post->html_title) }}" placeholder="Введите html-заголовок" name="html_title"></input>
            </div>
            <div>
                <label class="text-zinc-800 text-sm">Html-описание</label>
                <input rows='1' class="p-2 block border mb-4" type="text" value="{{ old('html_description', $post->html_description) }}" placeholder="Введите html-описание" name="html_description"></input>
            </div>
            <div>
                <textarea rows='1' class="p-2 block border w-full min-h-80 @error('content') border-red-400 @enderror" type="text" placeholder="Содержание поста" name="content">{{ old('content', $post->content) }}</textarea>
            </div>

            <div class="mt-5">
                <label class="mr-5">Выберите категорию</label>
                <select name="category_id">
                    @foreach ($categories as $category)
                    <option value="{{$category->id}}" @selected(old('category_id', $post->category_id) == $category->id)>{{$category->title}}</option>
                    @endforeach
                </select>
            </div>
            <div class="mt-5 mb-5">
                <p class="mr-5 font-medium mb-1">Выберите тэги:</p>
                @php $checkedTagIds = array_map('intval', old('tag_ids', $post->tags->pluck('id')->all())); @endphp
                @foreach ($tags as $tag)
                <div>
                    <input type="checkbox" value="{{ $tag->id }}" name="tag_ids[]" @checked(in_array($tag->id, $checkedTagIds, true))>
                    <label>{{ $tag->title }}</label>
                </div>
                @endforeach
            </div>
            <div>
                <p class="mb-2 mt-5 font-medium">Обложка</p>
                <img class="w-60" src="{{ asset('storage/' . $post->main_image) }}" alt="img">
            </div>
            <div>
                <p class="mb-2 mt-5 font-medium">Изображения в посте</p>
                @php $deleteIds = array_map('intval', old('delete_images', [])); @endphp
                <div class="flex gap-4 items-start flex-wrap">
                    @forelse ($images as $index => $image)
                        @php
                            $src = \App\Support\PostContent\PostImages::srcFor($image->original_name);
                            $tag = $src !== ''
                                ? '<x-img src="' . $src . '" description="" />'
                                : '<x-img img="' . $index . '" description="" />';
                        @endphp
                        <div class="w-40 text-xs">
                            <img class="w-40 h-28 object-cover border" src="{{ asset('storage/' . $image->name) }}" alt="{{ $src }}">
                            <p class="mt-1 font-mono break-all text-zinc-800">{{ $src !== '' ? $src : 'без имени, img="' . $index . '"' }}</p>
                            <button type="button" class="mt-1 px-2 py-1 bg-zinc-100 hover:bg-zinc-200" data-copy-tag="{{ $tag }}">Скопировать тег</button>
                            <label class="flex items-center gap-1 mt-1 text-red-600 cursor-pointer">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" @checked(in_array($image->id, $deleteIds, true))>
                                удалить
                            </label>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">Картинок пока нет.</p>
                    @endforelse
                </div>
            </div>
            <div class="mt-10">
                <label class="block mb-2 text-sm font-medium text-gray-900 dark:text-white" for="multiple_files">Главное изображение (обложка)</label>
                <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" id="multiple_files" name="main_image" type="file"></div>

            @include('admin.posts._images_upload')

            <a><button type="submit" class="mt-12 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Обновить пост</button></a>
        </form>

        <script>
            document.querySelectorAll('[data-copy-tag]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var tag = button.getAttribute('data-copy-tag');
                    var done = function () {
                        button.textContent = 'Скопировано';
                        setTimeout(function () { button.textContent = 'Скопировать тег'; }, 1500);
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(tag).then(done);
                    } else {
                        // http без TLS (локально) — clipboard API недоступен.
                        var area = document.createElement('textarea');
                        area.value = tag;
                        document.body.appendChild(area);
                        area.select();
                        document.execCommand('copy');
                        area.remove();
                        done();
                    }
                });
            });
        </script>

@endsection
