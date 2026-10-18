{{-- resources/views/admin/terms/photos.blade.php
     Фото терминов тренажёра. Файл сопоставляется с термином по имени из базы
     терминов (поле image, например «lichnost.jpg»), расширение не важно.
     Выбранные файлы уходят на сервер по одному (см. Photos\StoreController —
     почему не пачкой), повторная загрузка заменяет фото термина. --}}
@extends('admin.layouts.main')

@section('title', 'Фото терминов')

@section('content')
<x-ui.page-header title="Тренажёр терминов">
    Задание 18 ЕГЭ по обществознанию.
</x-ui.page-header>

@include('admin.terms._tabs')

<p class="text-sm text-zinc-500 mb-4">Имя файла должно совпадать с именем из базы терминов, например <code>lichnost.jpg</code>. Повторная загрузка заменяет фото.</p>

<x-ui.card class="mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-5">
        <x-ui.stat label="Терминов">{{ $terms->count() }}</x-ui.stat>
        <x-ui.stat label="С фото">{{ $terms->count() - $missing->count() }}</x-ui.stat>
        <x-ui.stat label="Без фото">{{ $missing->count() }}</x-ui.stat>
    </div>

    <label class="block rounded-xl border-2 border-dashed border-gray-300 px-4 py-8 text-center cursor-pointer hover:bg-gray-50">
        <span class="block text-sm text-zinc-700">Выбери фото — можно сразу все</span>
        <span class="block text-xs text-zinc-500 mt-1">JPG, PNG или WebP, до 10 МБ каждое</span>
        <input id="term-photos-input" type="file" multiple accept=".jpg,.jpeg,.png,.webp" class="sr-only">
    </label>

    <div id="term-photos-progress" class="hidden mt-4">
        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
            <div id="term-photos-bar" class="h-full bg-blue-500" style="width:0%"></div>
        </div>
        <div id="term-photos-status" class="text-xs text-zinc-500 mt-1.5"></div>
        <ul id="term-photos-log" class="mt-3 max-h-64 overflow-y-auto space-y-1 text-sm"></ul>
    </div>
</x-ui.card>

@if($missing->isNotEmpty())
    <h2 class="sans-medium text-lg text-zinc-900 mb-3">Без фото</h2>
    <x-ui.card class="mb-6">
        <div class="grid gap-x-6 gap-y-1.5 sm:grid-cols-2 lg:grid-cols-3 text-sm">
            @foreach($missing as $term)
                <div class="min-w-0 truncate">
                    {{ $term->title }}@if($term->qualifier)<span class="text-zinc-400"> · {{ $term->qualifier }}</span>@endif
                    <span class="text-zinc-400">— {{ $term->image }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>
@endif

<h2 class="sans-medium text-lg text-zinc-900 mb-3">Загруженные</h2>
<div class="grid gap-3 grid-cols-2 sm:grid-cols-4 lg:grid-cols-6">
    @foreach($terms->whereNotNull('image_path') as $term)
        <div class="min-w-0">
            <img src="{{ asset('storage/'.$term->image_path) }}" alt="" loading="lazy" class="w-full aspect-[16/10] object-cover rounded-lg bg-gray-100">
            <div class="text-xs text-zinc-700 mt-1 truncate" title="{{ $term->title }}">{{ $term->title }}@if($term->qualifier) · {{ $term->qualifier }}@endif</div>
        </div>
    @endforeach
</div>

<script>
(function () {
    var input = document.getElementById('term-photos-input');
    var box = document.getElementById('term-photos-progress');
    var bar = document.getElementById('term-photos-bar');
    var status = document.getElementById('term-photos-status');
    var log = document.getElementById('term-photos-log');
    var url = @json(route('admin.terms.photos.store'));
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function line(ok, text) {
        var li = document.createElement('li');
        li.className = ok ? 'text-emerald-700' : 'text-rose-700';
        li.textContent = (ok ? '✓ ' : '✗ ') + text;
        log.appendChild(li);
        log.scrollTop = log.scrollHeight;
    }

    input.addEventListener('change', async function () {
        var files = Array.from(input.files);
        if (!files.length) return;

        box.classList.remove('hidden');
        log.innerHTML = '';
        var ok = 0, failed = 0;

        // По одному файлу за запрос — так не упираемся в лимиты PHP на размер
        // запроса и число файлов.
        for (var i = 0; i < files.length; i++) {
            status.textContent = 'Загружаю ' + (i + 1) + ' из ' + files.length + '…';
            var data = new FormData();
            data.append('photo', files[i]);

            try {
                var res = await fetch(url, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
                var json = await res.json().catch(function () { return {}; });
                if (res.ok && json.ok) {
                    ok++;
                    line(true, json.file + ' → ' + json.term);
                } else {
                    failed++;
                    var error = json.error || (json.errors && Object.values(json.errors)[0][0]) || ('ошибка ' + res.status);
                    line(false, files[i].name + ': ' + error);
                }
            } catch (e) {
                failed++;
                line(false, files[i].name + ': нет связи с сервером');
            }
            bar.style.width = Math.round((i + 1) / files.length * 100) + '%';
        }

        status.textContent = 'Готово: загружено ' + ok + (failed ? ', с ошибкой ' + failed : '') + '. Обнови страницу, чтобы увидеть фото.';
        input.value = '';
    });
})();
</script>
@endsection
