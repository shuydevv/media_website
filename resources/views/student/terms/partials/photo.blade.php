{{-- resources/views/student/terms/partials/photo.blade.php
     Фото термина. $blurred — размытая копия (способ А до ответа): отдельный
     файл, а не CSS-фильтр, который снимается в инструментах браузера. Файлы
     лежат под обезличенными именами — исходное имя совпадает с названием
     термина. Пока фото не загружено, место занимает заглушка. --}}
@php
  $path = ($blurred ?? false) ? $term->image_blur_path : $term->image_path;
@endphp
@if($path)
  {{-- hx-preserve: пока на экране та же картинка (тот же термин и то же
       размытие), htmx не пересоздаёт её при подмене шага. Иначе фото
       «мигало» на каждом клике в квизе — новая <img> пару кадров
       декодируется на пустом месте. id меняется вместе с картинкой, поэтому
       новый термин или проявление фото после ответа подменяются как обычно. --}}
  <img id="term-photo-{{ $term->id }}-{{ ($blurred ?? false) ? 'b' : 's' }}" hx-preserve="true"
       src="{{ asset('storage/'.$path) }}" alt=""
       class="w-full h-40 sm:h-52 lg:h-72 object-cover rounded-xl bg-gray-100">
@else
  <div class="w-full h-24 rounded-xl bg-gray-100 flex items-center justify-center">
    <x-icon name="image-01" class="w-7 h-7 text-zinc-300" />
  </div>
@endif
