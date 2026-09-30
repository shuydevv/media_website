<div class="mt-10">
    <label class="block mb-2 text-sm font-medium text-gray-900" for="multiple_files2">Добавить изображения в пост</label>
    <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" id="multiple_files2" name="multi_images[]" type="file" accept="image/*" multiple>
    <p class="mt-2 text-sm text-zinc-500">
        Новые файлы добавляются к уже загруженным. Файл с тем же именем, что у загруженной картинки, заменит её.
        В тексте картинка указывается по имени файла без расширения:
        <code class="px-1 bg-zinc-100">&lt;x-img src="kalka-1" description="Подпись" /&gt;</code>
        (так же <code class="px-1 bg-zinc-100">src</code> работает в <code class="px-1 bg-zinc-100">&lt;x-person&gt;</code> и <code class="px-1 bg-zinc-100">&lt;x-quote&gt;</code>).
        Картинки до 5 МБ.
    </p>
</div>
