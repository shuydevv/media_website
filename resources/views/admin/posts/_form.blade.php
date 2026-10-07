{{--
    Общие поля формы поста — create.blade.php и edit.blade.php.
    $post   — редактируемый пост или null при создании
    $images — уже загруженные картинки поста (только edit)
--}}
@php
    $post = $post ?? null;
    $images = $images ?? collect();
    $checkedTagIds = array_map('intval', old('tag_ids', $post ? $post->tags->pluck('id')->all() : []));
    $deleteIds = array_map('intval', old('delete_images', []));
    // Кнопки под картинкой — тот же набор классов у отрисованных сервером и у
    // созданных из JS (_images_upload.blade.php).
    $imageBtn = 'px-2.5 min-h-9 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-left text-xs text-zinc-800';
@endphp

<x-ui.card class="space-y-4">
    <x-ui.input name="title" label="Заголовок поста #1" value="{{ old('title', $post?->title) }}" placeholder="Введите заголовок" />
    <x-ui.input name="title2" label="Заголовок поста #2" value="{{ old('title2', $post?->title2) }}" placeholder="Введите заголовок" />
    <x-ui.input name="description" label="Описание поста" value="{{ old('description', $post?->description) }}" placeholder="Введите описание поста" />

    <x-ui.textarea name="content" label="Содержание поста" rows="16" class="font-mono md:text-[13px] min-h-80"
                   placeholder="Содержание поста">{{ old('content', $post?->content) }}</x-ui.textarea>
</x-ui.card>

<x-ui.card class="space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Рубрики</h2>

    <x-ui.select name="category_id" label="Категория">
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $post?->category_id) == $category->id)>{{ $category->title }}</option>
        @endforeach
    </x-ui.select>

    @include('admin.posts._topic_select', ['post' => $post])

    <x-ui.field label="Тэги" error="tag_ids">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
            @foreach ($tags as $tag)
                <x-ui.checkbox name="tag_ids[]" :value="$tag->id" :checked="in_array($tag->id, $checkedTagIds, true)">{{ $tag->title }}</x-ui.checkbox>
            @endforeach
        </div>
    </x-ui.field>
</x-ui.card>

<x-ui.card class="space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Адрес и SEO</h2>
    <x-ui.input name="path" label="Путь к посту" value="{{ old('path', $post?->path) }}" placeholder="Введите путь к посту" autocomplete="off" />
    <x-ui.input name="html_title" label="Html-заголовок" value="{{ old('html_title', $post?->html_title) }}" placeholder="Введите html-заголовок" />
    <x-ui.input name="html_description" label="Html-описание" value="{{ old('html_description', $post?->html_description) }}" placeholder="Введите html-описание" />
</x-ui.card>

<x-ui.card class="space-y-5">
    <h2 class="sans-medium text-lg text-zinc-900">Изображения</h2>

    @if ($post)
        <div>
            <div class="ui-label">Текущая обложка</div>
            <img class="w-60 max-w-full rounded-lg border border-gray-200" src="{{ asset('storage/' . $post->main_image) }}" alt="Обложка поста">
        </div>
    @endif

    <x-ui.input type="file" name="main_image" id="multiple_files" label="Главное изображение (обложка)" accept="image/*" />

    @if ($post)
        <div>
            <div class="ui-label">Изображения в посте</div>
            <div class="flex gap-4 items-start flex-wrap">
                @forelse ($images as $index => $image)
                    @php
                        $src = \App\Support\PostContent\PostImages::srcFor($image->original_name);
                        $tag = $src !== ''
                            ? '<x-img src="' . $src . '" description="" />'
                            : '<x-img img="' . $index . '" description="" />';
                    @endphp
                    <div class="w-40 text-xs">
                        <img class="w-40 h-28 object-cover rounded-lg border border-gray-200" src="{{ asset('storage/' . $image->name) }}" alt="{{ $src }}">
                        <p class="mt-1 font-mono break-all text-zinc-800">{{ $src !== '' ? $src : 'без имени, img="' . $index . '"' }}</p>
                        <div class="flex flex-col gap-1 mt-1">
                            <button type="button" class="{{ $imageBtn }}" data-copy-tag="{{ $tag }}">Скопировать тег</button>
                            <button type="button" class="{{ $imageBtn }}" data-insert-tag="{{ $tag }}">Вставить в текст</button>
                        </div>
                        <label class="flex items-center gap-2 mt-2 min-h-9 text-apple-red-650 cursor-pointer">
                            <input type="checkbox" class="checkbox-custom" name="delete_images[]" value="{{ $image->id }}" @checked(in_array($image->id, $deleteIds, true))>
                            удалить
                        </label>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Картинок пока нет.</p>
                @endforelse
            </div>
        </div>
    @endif

    @include('admin.posts._images_upload', ['existingImages' => $images])
</x-ui.card>
