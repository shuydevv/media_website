{{--
    Общие поля формы шпаргалки — create.blade.php и edit.blade.php.
    $shpargalka — редактируемая шпаргалка или null при создании
    $images     — все картинки (только edit; к шпаргалке относятся те, у
                  которых shpargalka_id совпадает с её path)
--}}
@php
    $shpargalka = $shpargalka ?? null;
    $images = $images ?? collect();
@endphp

<x-ui.card class="space-y-4">
    <x-ui.input name="title" label="Название шпаргалки" value="{{ old('title', $shpargalka?->title) }}" placeholder="Введите название" />
    <x-ui.input name="price" label="Цена шпаргалки" value="{{ old('price', $shpargalka?->price) }}" placeholder="Введите цену" inputmode="decimal" wrap="max-w-xs" />

    <x-ui.textarea name="description" label="Описание шпаргалки" rows="8" class="min-h-40">{{ old('description', $shpargalka?->description) }}</x-ui.textarea>
    <x-ui.textarea name="content" label="Содержание шпаргалки" rows="16" class="font-mono md:text-[13px] min-h-80">{{ old('content', $shpargalka?->content) }}</x-ui.textarea>

    <x-ui.select name="category_id" label="Категория">
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_id', $shpargalka?->category_id) == $category->id)>{{ $category->title }}</option>
        @endforeach
    </x-ui.select>
</x-ui.card>

<x-ui.card class="space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Адрес и SEO</h2>
    <x-ui.input name="path" label="Путь к шпаргалке" value="{{ old('path', $shpargalka?->path) }}" placeholder="Введите путь" autocomplete="off" />
    <x-ui.input name="html_title" label="Html-заголовок" value="{{ old('html_title', $shpargalka?->html_title) }}" placeholder="Введите html-заголовок" />
    <x-ui.input name="html_description" label="Html-описание" value="{{ old('html_description', $shpargalka?->html_description) }}" placeholder="Введите html-описание" />
</x-ui.card>

<x-ui.card class="space-y-5">
    <h2 class="sans-medium text-lg text-zinc-900">Изображения</h2>

    @if ($shpargalka)
        <div>
            <div class="ui-label">Текущая обложка</div>
            <img class="w-60 max-w-full rounded-lg border border-gray-200" src="{{ asset('storage/' . $shpargalka->main_image) }}" alt="Обложка шпаргалки">
        </div>

        @php $ownImages = $images->filter(fn ($image) => $image->shpargalka_id == $shpargalka->path); @endphp
        @if ($ownImages->isNotEmpty())
            <div>
                <div class="ui-label">Изображения в шпаргалке</div>
                <div class="flex gap-2 items-start flex-wrap">
                    @foreach ($ownImages as $image)
                        <img class="w-36 rounded-lg border border-gray-200" src="{{ asset('storage/' . $image->name) }}" alt="Изображение шпаргалки">
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <x-ui.input type="file" name="main_image" id="multiple_files" label="Главное изображение (обложка)" accept="image/*" />
    <x-ui.input type="file" name="multi_images[]" id="multiple_files2" label="Изображения в шпаргалке (загрузить списком)" accept="image/*" multiple />
</x-ui.card>
