{{--
    Загрузка картинок в пост + превью до сохранения.
    $existingImages — уже загруженные картинки поста (на форме редактирования).

    Имена в превью считаются так же, как PostImageService::plan(): файл с
    именем уже загруженной картинки её заменит, одинаковые имена в одной
    загрузке получают суффикс -2, -3. Если логику там поменять — поменять и тут.
--}}
@php
    $existingImageKeys = collect($existingImages ?? [])->map(fn ($image) => [
        'id' => $image->id,
        'key' => \App\Support\PostContent\PostImages::key($image->original_name),
    ])->values();
@endphp
<div class="mt-10" data-images-upload data-existing='@json($existingImageKeys)'>
    <label class="block mb-2 text-sm font-medium text-gray-900" for="multiple_files2">Добавить изображения в пост</label>
    <input class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" id="multiple_files2" name="multi_images[]" type="file" accept="image/*" multiple>
    <p class="mt-2 text-sm text-zinc-500">
        Новые файлы добавляются к уже загруженным. Файл с тем же именем, что у загруженной картинки, заменит её.
        В тексте картинка указывается по имени файла без расширения:
        <code class="px-1 bg-zinc-100">&lt;x-img src="kalka-1" description="Подпись" /&gt;</code>
        (так же <code class="px-1 bg-zinc-100">src</code> работает в <code class="px-1 bg-zinc-100">&lt;x-person&gt;</code> и <code class="px-1 bg-zinc-100">&lt;x-quote&gt;</code>).
        Картинки до 5 МБ.
    </p>

    <div class="mt-4 hidden" data-preview-wrap>
        <p class="mb-2 font-medium text-sm">Будут загружены при сохранении:</p>
        <div class="flex gap-4 items-start flex-wrap" data-preview></div>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('[data-images-upload]');
    if (!root) return;

    var input = root.querySelector('input[type=file]');
    var preview = root.querySelector('[data-preview]');
    var previewWrap = root.querySelector('[data-preview-wrap]');
    var form = root.closest('form');
    var content = form.querySelector('textarea[name=content]');
    var existing = JSON.parse(root.getAttribute('data-existing') || '[]');
    var selected = [];
    var urls = [];

    var baseName = function (name) { return name.replace(/\.[^.]*$/, ''); };
    var ext = function (name) { var m = name.match(/\.[^.]*$/); return m ? m[0] : ''; };
    var key = function (name) { return baseName(name).toLowerCase(); };

    var deletedIds = function () {
        return Array.prototype.map.call(
            form.querySelectorAll('input[name="delete_images[]"]:checked'),
            function (el) { return Number(el.value); }
        );
    };

    // То же, что PostImageService::plan() для новых файлов.
    var plan = function () {
        var deleted = deletedIds();
        var taken = {};   // key -> 'existing' | 'replaced' | 'new'
        existing.forEach(function (image) {
            if (deleted.indexOf(image.id) === -1) taken[image.key] = 'existing';
        });

        return selected.map(function (file) {
            var name = file.name;
            var k = key(name);
            if (taken[k] === 'existing') {
                taken[k] = 'replaced';
                return { file: file, name: name, replaces: true };
            }
            if (taken[k]) {
                for (var i = 2; taken[key(baseName(file.name) + '-' + i + ext(file.name))]; i++) {}
                name = baseName(file.name) + '-' + i + ext(file.name);
                k = key(name);
            }
            taken[k] = 'new';
            return { file: file, name: name, replaces: false };
        });
    };

    var syncInput = function () {
        var dt = new DataTransfer();
        selected.forEach(function (file) { dt.items.add(file); });
        input.files = dt.files;
    };

    var button = function (text, attrs) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'px-2 py-1 bg-zinc-100 hover:bg-zinc-200 text-left';
        b.textContent = text;
        Object.keys(attrs).forEach(function (a) { b.setAttribute(a, attrs[a]); });
        return b;
    };

    var render = function () {
        urls.forEach(URL.revokeObjectURL);
        urls = [];
        preview.innerHTML = '';
        previewWrap.classList.toggle('hidden', selected.length === 0);

        plan().forEach(function (item, index) {
            var src = baseName(item.name);
            // '<' + 'x-…' — иначе Blade примет это за компонент и отрендерит прямо в скрипт.
            var tag = '<' + 'x-img src="' + src + '" description="" />';
            var url = URL.createObjectURL(item.file);
            urls.push(url);

            var card = document.createElement('div');
            card.className = 'w-40 text-xs';

            var img = document.createElement('img');
            img.className = 'w-40 h-28 object-cover border';
            img.src = url;
            img.alt = src;
            card.appendChild(img);

            var name = document.createElement('p');
            name.className = 'mt-1 font-mono break-all text-zinc-800';
            name.textContent = src;
            card.appendChild(name);

            var note = document.createElement('p');
            note.className = item.replaces ? 'text-amber-700' : 'text-green-700';
            note.textContent = item.replaces ? 'заменит загруженную' : 'новая';
            card.appendChild(note);

            var actions = document.createElement('div');
            actions.className = 'flex flex-col gap-1 mt-1';
            actions.appendChild(button('Скопировать тег', { 'data-copy-tag': tag }));
            actions.appendChild(button('Вставить в текст', { 'data-insert-tag': tag }));
            var remove = button('Убрать', {});
            remove.className += ' text-red-600';
            remove.addEventListener('click', function () {
                selected.splice(index, 1);
                syncInput();
                render();
            });
            actions.appendChild(remove);
            card.appendChild(actions);

            preview.appendChild(card);
        });
    };

    input.addEventListener('change', function () {
        // Повторный выбор добавляет файлы к уже выбранным, а не заменяет их.
        Array.prototype.forEach.call(input.files, function (file) { selected.push(file); });
        syncInput();
        render();
    });

    // Галочка «удалить» у загруженной картинки меняет, заменит ли новый файл
    // существующий или добавится рядом.
    form.addEventListener('change', function (e) {
        if (e.target.name === 'delete_images[]') render();
    });

    var flash = function (el, text) {
        var label = el.getAttribute('data-label') || el.textContent;
        el.setAttribute('data-label', label);
        el.textContent = text;
        setTimeout(function () { el.textContent = label; }, 1500);
    };

    var copy = function (text, done) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
            return;
        }
        // http без TLS (локально) — clipboard API недоступен.
        var area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
        done();
    };

    var lastCaret = null;
    if (content) {
        ['keyup', 'click', 'select', 'blur'].forEach(function (ev) {
            content.addEventListener(ev, function () { lastCaret = content.selectionStart; });
        });
    }

    var insert = function (tag) {
        if (!content) return;
        var pos = lastCaret === null ? content.value.length : lastCaret;
        var before = content.value.slice(0, pos);
        var after = content.value.slice(pos);
        var text = (before && !/\n$/.test(before) ? '\n\n' : '') + tag + (after && !/^\n/.test(after) ? '\n\n' : '');
        content.value = before + text + after;
        lastCaret = pos + text.length;
        content.focus();
        content.setSelectionRange(lastCaret, lastCaret);
    };

    // Делегирование — работает и для уже загруженных картинок, и для превью.
    form.addEventListener('click', function (e) {
        var copyBtn = e.target.closest('[data-copy-tag]');
        if (copyBtn) {
            copy(copyBtn.getAttribute('data-copy-tag'), function () { flash(copyBtn, 'Скопировано'); });
            return;
        }
        var insertBtn = e.target.closest('[data-insert-tag]');
        if (insertBtn) {
            insert(insertBtn.getAttribute('data-insert-tag'));
            flash(insertBtn, 'Вставлено');
        }
    });
})();
</script>
