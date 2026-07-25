{{--
    Единая разметка содержания ОДНОГО задания — общая для формы банка
    (admin/tasks/create|edit.blade.php, $name='') и карточки задания в
    конструкторе домашки (admin/homeworks/create|edit.blade.php,
    $name='tasks[N]').

    Статичные части (карточка, пассаж, вопрос, картинка, таблица,
    "Правильный ответ") — БУКВАЛЬНО та же разметка/классы, что у
    resources/views/student/submissions/show.blade.php (карточки
    "Подробно по заданиям" на /student/submissions/{id}). Варианты ответа
    и колонки "Соотнесения" — обычные текстовые поля (один элемент на
    строку), не визуальный конструктор — так проще редактировать.

    Скрытые/видимые textarea/input с исходными именами (options/
    matches.left/matches.right/table_content/answer) остаются источником
    истины для бэкенда — App\Support\TaskContentRules/TaskContentNormalizer
    не видят разницы, читают их напрямую.

    Источник/банк-переключатель (own/bank), "сохранить в банк",
    порядок/баллы — НЕ часть этого компонента: это homework-специфичная
    обвязка вокруг него, а не содержание задания.
--}}
@props([
    'name' => '',
    'task' => null,
    // Список уже использованных номеров в банке — подсказки в datalist
    // поля "№ в ЕГЭ". Без @props эта переменная утекала бы в $attributes
    // (общий "остаток" непойманных атрибутов) и Laravel пытался бы
    // вывести массив как HTML-атрибут на корневом <div>, падая в trim().
    'numberOptions' => [],
])

@php
    // Имя HTML-поля из точечного пути: '' + 'matches.left' -> 'matches[left]';
    // 'tasks[0]' + 'matches.left' -> 'tasks[0][matches][left]'.
    $fname = function (string $field) use ($name) {
        $segments = explode('.', $field);
        if ($name === '') {
            $out = array_shift($segments);
        } else {
            $out = $name;
        }
        foreach ($segments as $seg) {
            $out .= "[{$seg}]";
        }
        return $out;
    };

    // Тот же путь, но в точечной нотации — то, что понимают old()/@error().
    $ename = function (string $field) use ($name) {
        if ($name === '') {
            return $field;
        }
        $dotPrefix = rtrim(str_replace(['[', ']'], ['.', ''], $name), '.');
        return "{$dotPrefix}.{$field}";
    };

    $old = fn (string $field, $default = null) => old($ename($field), data_get($task, $field, $default));
    $textOf = fn ($value) => is_array($value) ? implode("\n", $value) : (string) ($value ?? '');

    // options/matches/image_auto_options хранятся в БД как array (cast), а
    // на экране всегда textarea-строка — data_get($task, ...) с текстовым
    // default'ом всё равно вернёт "сырой" массив, если атрибут на модели
    // существует (default в data_get срабатывает только при отсутствии
    // ключа, а не когда он не той формы) — поэтому эти поля НЕ идут через
    // общий $old(), a разворачиваются в текст явно, до сравнения с old().
    $oldText = function (string $field, $rawValue) use ($ename, $textOf) {
        $submitted = old($ename($field));
        if ($submitted !== null) {
            return is_array($submitted) ? implode("\n", $submitted) : (string) $submitted;
        }
        return $textOf($rawValue);
    };

    $currentType = $old('type', '');
    $optionsText = $oldText('options', $task->options ?? []);
    $matchesLeftText = $oldText('matches.left', data_get($task, 'matches.left', []));
    $matchesRightText = $oldText('matches.right', data_get($task, 'matches.right', []));
    $imageAutoOptionsText = $oldText('image_auto_options', $task->image_auto_options ?? []);
    $tableContentRaw = data_get($task, 'table_content');
    $tableContentText = $oldText('table_content', $tableContentRaw ? json_encode($tableContentRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '');
    $orderMattersDefault = $task ? (bool) data_get($task, 'order_matters') : true;
    $taskNumber = $old('number', data_get($task, 'number'));
    // Уникальный id на каждый экземпляр компонента — в конструкторе домашки
    // на странице одновременно несколько карточек, у каждой свой datalist
    // ("task-numbers-list" одним и тем же id на все карточки был бы
    // невалидным HTML, и list= у второй карточки указывал бы непонятно куда).
    $numberListId = 'task-numbers-list-' . ($name === '' ? 'bank' : preg_replace('/[^a-zA-Z0-9]+/', '-', $name));

    // Прогрессивное раскрытие: тип не показываем, пока не выбрана категория
    // (только в банке — 'category_id' лежит в bank-fields.blade.php, том же
    // <form>, поэтому old()/data_get читают её напрямую по имени поля, без
    // передачи через проп); контент не показываем, пока не выбран тип.
    $categoryChosen = $name !== '' || (bool) old('category_id', data_get($task, 'category_id'));

    // Правильный ответ для авто-проверяемых типов — те же квадратики, что
    // сравнивает /student/submissions/{id} ("Ваш ответ"/"Правильный
    // ответ"), а не оторванный от содержания текстовый инпут. Изначально —
    // не больше одной строки квадратиков: точный стартовый размер сам
    // досчитает pin-field-script.blade.php под ширину карточки
    // (oneRowCapacity()), тут только нижняя граница на случай, если answer
    // уже длиннее одной "естественной" строки.
    $answerFieldName = $fname('answer');
    $answerPinAllowed = $currentType === 'image_auto' ? 'alnum' : 'digits';
    $answerPinCount = 1;
    if ($currentType === 'matching') {
        $leftCount = count(array_filter(preg_split('/\R/u', $matchesLeftText), fn ($l) => trim($l) !== ''));
        if ($leftCount > 0) $answerPinCount = $leftCount;
    }
    if ($currentType === 'table') {
        $decodedTable = json_decode($tableContentText, true);
        $blanksCount = is_array($decodedTable['blanks'] ?? null) ? count($decodedTable['blanks']) : 0;
        if ($blanksCount > 0) $answerPinCount = $blanksCount;
    }
    $answerValue = (string) $old('answer');
    $answerPinChars = preg_split('//u', $answerValue, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $answerPinCount = max($answerPinCount, count($answerPinChars));

    // Editable-поверх-контента базовый класс: не выглядит инпутом, пока
    // не в фокусе — просто текст, ровно как у студента.
    $editableText = 'task-editable w-full resize-none border-0 outline-none bg-transparent p-0 rounded focus:ring-2 focus:ring-blue-200 transition';
@endphp

<div class="task-content-fields" data-category-gated="{{ $name === '' ? '1' : '0' }}" {{ $attributes }}>
    <div class="task-type-wrap {{ $categoryChosen ? '' : 'hidden' }}">
        {{-- Номер в ЕГЭ — та же подложка, что в карточке результата у
             студента, только с инпутом. Нужен и в банке, и в конструкторе
             домашки: критерии/баллы общие на пару (категория, номер) —
             без номера "своё" задание в домашке не может ни показать
             подсказку про существующие критерии, ни (если отметить
             "сохранить в банк") попасть в банк с правильным номером.
             Категория в домашке не выбирается явно — берётся с курса
             (см. data-category на <option> курса и checkNumberCriteria()
             в task-editor-script.blade.php). Показывается вместе с типом
             сразу после выбора категории — не зависит от типа. --}}
        <div class="mb-2">
            <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-xs rounded-full bg-zinc-100 border border-zinc-200 text-zinc-700">
                №<input type="text" name="{{ $fname('number') }}" value="{{ $taskNumber }}"
                         class="task-number-input w-10 border-0 bg-transparent p-0 text-xs text-zinc-700 focus:outline-none focus:ring-0"
                         placeholder="?" list="{{ $numberListId }}"> в ЕГЭ
            </span>
            <datalist id="{{ $numberListId }}">
                @foreach(($numberOptions ?? []) as $n)
                    <option value="{{ $n }}"></option>
                @endforeach
            </datalist>
            <span class="task-number-hint ml-2 text-xs"></span>
        </div>
        <label class="block text-xs text-zinc-500 mb-1">Тип задания</label>
        <select name="{{ $fname('type') }}" data-field="type" class="task-type w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">Выберите тип</option>
            <option value="test" @selected($currentType==='test')>Тест с вариантами</option>
            <option value="text_with_questions" @selected($currentType==='text_with_questions')>Текст с вопросами</option>
            <option value="matching" @selected($currentType==='matching')>Соотнесение</option>
            <option value="image_auto" @selected($currentType==='image_auto')>Картинка (автопроверка)</option>
            <option value="image_manual" @selected($currentType==='image_manual')>Картинка (ручная проверка)</option>
            <option value="written" @selected($currentType==='written')>Развёрнутый ответ</option>
            <option value="table" @selected($currentType==='table')>Таблица</option>
        </select>
        @error($ename('type'))<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
    </div>

    {{-- Всё остальное — контент задания, "Правильный ответ", подсказка,
         "Порядок важен" — не показываем, пока не выбран тип. --}}
    <div class="task-body {{ $currentType ? '' : 'hidden' }}">

    {{-- Ниже — БУКВАЛЬНО разметка карточки задания из student/submissions/
         show.blade.php ("Подробно по заданиям"), с редактируемыми полями
         вместо статичного текста. --}}
    <x-ui.card class="task-preview-surface mt-5">
        <div class="mb-6 space-y-3">
            {{-- Пассаж (text_with_questions/written) --}}
            <div class="task-passage hidden mb-6 p-3 rounded-lg bg-gray-50 border">
                <textarea name="{{ $fname('passage_text') }}" data-field="passage_text"
                          class="{{ $editableText }} task-autosize text-base whitespace-pre-wrap"
                          rows="2" placeholder="Текст (пассаж)…">{{ $old('passage_text') }}</textarea>
            </div>

            {{-- Вопрос --}}
            <div class="task-question">
                <textarea name="{{ $fname('question_text') }}" data-field="question_text"
                          class="{{ $editableText }} task-autosize text-base text-zinc-800 whitespace-pre-wrap"
                          rows="1" placeholder="Текст вопроса…">{{ $old('question_text') }}</textarea>
            </div>

            {{-- Картинка (image_auto/image_manual) --}}
            <div class="task-image hidden">
                <label class="task-image-drop w-full h-40 rounded-lg border border-gray-200 bg-gray-100 flex items-center justify-center text-gray-300 cursor-pointer overflow-hidden relative">
                    @if(data_get($task, 'image_path'))
                        <img src="{{ \Illuminate\Support\Facades\Storage::url(data_get($task, 'image_path')) }}" alt="" class="task-image-preview w-full h-full object-contain">
                    @else
                        <div class="task-image-placeholder flex flex-col items-center gap-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="M21 15l-5-5L5 21"></path>
                            </svg>
                            <span class="text-xs">Нажмите, чтобы загрузить изображение</span>
                        </div>
                    @endif
                    <input type="file" name="{{ $fname('image') }}" class="task-image-input hidden">
                </label>
            </div>
        </div>

        {{-- Таблица --}}
        <div class="task-table hidden mt-1 mb-5 sm:mb-6">
            <div class="task-table-builder"></div>
            <button type="button" class="task-table-toggle-json text-xs text-blue-600 hover:underline mt-2">Редактировать JSON вручную</button>
            <textarea name="{{ $fname('table_content') }}" data-field="table_content" rows="8" class="task-table-json hidden w-full border rounded-lg px-3 py-2 font-mono text-xs mt-2">{{ $tableContentText ?: '{
    "cols": ["Колонка 1", "Колонка 2"],
    "rows": [["ячейка_1", "ячейка_2"]],
    "blanks": [{"r": 0, "c": 1, "key": "А", "value": ""}]
}' }}</textarea>
            <p class="task-table-json-hint hidden text-xs text-amber-600 mt-1">Не удалось распознать JSON как таблицу — конструктор недоступен, редактируйте текст вручную.</p>
        </div>

        {{-- Варианты (test/table) — обычное поле, один вариант на строку. --}}
        <div class="task-options hidden mt-3 sm:mt-4">
            <label class="block text-xs text-zinc-500 mb-1">Варианты ответа (каждый — с новой строки)</label>
            <textarea name="{{ $fname('options') }}" data-field="options" rows="5"
                      class="task-lines-input w-full border rounded-lg px-3 py-2 text-sm font-mono">{{ $optionsText }}</textarea>
            {{-- Живая нумерация — сразу видно соответствие "вариант №N = цифра в ответе". --}}
            <div class="task-lines-preview mt-2 text-sm text-zinc-600 space-y-0.5"></div>
        </div>

        {{-- Соотнесение — обычные поля, как раньше: заголовок колонки +
             список по строкам. --}}
        <div class="task-matches hidden grid md:grid-cols-2 gap-4 mt-3 sm:mt-4 mb-4">
            <div>
                <label class="block text-xs text-zinc-500 mb-1">Заголовок левой колонки</label>
                <input type="text" name="{{ $fname('left_title') }}" data-field="left_title"
                       class="w-full border rounded-lg px-3 py-2 text-sm mb-2"
                       placeholder="Левая колонка" value="{{ $old('left_title') }}">
                <label class="block text-xs text-zinc-500 mb-1">Левая колонка (по строке)</label>
                <textarea name="{{ $fname('matches.left') }}" data-field="matches.left" rows="4"
                          class="task-lines-input w-full border rounded-lg px-3 py-2 text-sm" placeholder="А&#10;Б&#10;В">{{ $matchesLeftText }}</textarea>
                <div class="task-lines-preview mt-2 text-sm text-zinc-600 space-y-0.5"></div>
            </div>
            <div>
                <label class="block text-xs text-zinc-500 mb-1">Заголовок правой колонки</label>
                <input type="text" name="{{ $fname('right_title') }}" data-field="right_title"
                       class="w-full border rounded-lg px-3 py-2 text-sm mb-2"
                       placeholder="Правая колонка" value="{{ $old('right_title') }}">
                <label class="block text-xs text-zinc-500 mb-1">Правая колонка (по строке)</label>
                <textarea name="{{ $fname('matches.right') }}" data-field="matches.right" rows="4"
                          class="task-lines-input w-full border rounded-lg px-3 py-2 text-sm" placeholder="1&#10;2&#10;3">{{ $matchesRightText }}</textarea>
                <div class="task-lines-preview mt-2 text-sm text-zinc-600 space-y-0.5"></div>
            </div>
        </div>

        {{-- image_auto — необязательные варианты под картинкой --}}
        <div class="task-image-auto-extra hidden mt-3 sm:mt-4">
            <label class="block text-xs text-zinc-500 mb-1">Варианты ответа (по одному в строке, необязательно)</label>
            <textarea name="{{ $fname('image_auto_options') }}" data-field="image_auto_options" rows="3"
                      class="task-lines-input w-full border rounded-lg px-3 py-2 text-sm">{{ $imageAutoOptionsText }}</textarea>
            <div class="task-lines-preview mt-2 text-sm text-zinc-600 space-y-0.5"></div>
        </div>

        {{-- Правильный ответ — те же квадратики w-9 h-9 border-2 rounded-lg,
             которыми /student/submissions/{id} сравнивает "ваш ответ" и
             "правильный ответ" (только один ряд, и он редактируемый). --}}
        <div class="mt-5">
            <div class="task-answer-pin hidden rounded-xl mt-2">
                <div class="text-xs text-zinc-500 mb-2">Правильный ответ</div>
                <input type="text" name="{{ $answerFieldName }}" data-field="answer" class="pin-hidden-input" autocomplete="off" value="{{ $answerValue }}" @if($currentType === 'table') readonly @endif>
                <div class="pin-field task-answer-boxes" tabindex="0" data-for="{{ $answerFieldName }}" data-allowed="{{ $answerPinAllowed }}">
                    <div class="pin-boxes flex flex-wrap gap-2">
                        @for ($i = 0; $i < $answerPinCount; $i++)
                            <div class="pin-box w-9 h-9 sm:w-10 sm:h-10 border-2 border-zinc-300 rounded-lg flex items-center justify-center text-base sm:text-lg font-medium bg-white select-none {{ isset($answerPinChars[$i]) ? 'filled' : '' }}">{{ isset($answerPinChars[$i]) ? mb_strtoupper($answerPinChars[$i]) : '' }}</div>
                        @endfor
                    </div>
                </div>
                {{-- Для таблицы это ЕДИНСТВЕННЫЙ источник правды — значение
                     пропуска в таблице выше. Поле неактивно для ручного
                     ввода здесь: если бы можно было редактировать оба
                     места, они бы могли молча разойтись. --}}
                <p class="task-answer-derived-note hidden text-xs text-zinc-500 mt-2">Выводится само из значений пропусков в таблице выше — впишите ответ там, не здесь.</p>
            </div>

            {{-- written/image_manual: образцовый ответ — та же зелёная
                 плашка ("Образцовый ответ"), в которой студент увидит его
                 на /student/submissions/{id} после проверки куратором. --}}
            <div class="task-answer-text">
                <div class="rounded-xl p-3 px-4" style="background-color: #e2f4ef">
                    <div class="text-xs mb-2" style="color: #33a885">Образцовый ответ</div>
                    <textarea name="{{ $fname('answer') }}" data-field="answer"
                              class="task-editable task-autosize w-full resize-none border-0 outline-none bg-transparent p-0 rounded text-sm whitespace-pre-wrap"
                              rows="2" placeholder="Образцовый ответ…">{{ $old('answer') }}</textarea>
                </div>
            </div>

            @error($ename('answer'))<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
        </div>
    </x-ui.card>

    {{-- Подсказка — не часть условия, покажется студенту отдельно по кнопке. --}}
    <div class="mt-4">
        <label class="block text-xs text-zinc-500 mb-1">Подсказка (необязательно, покажется студенту по кнопке)</label>
        <textarea name="{{ $fname('hint') }}" data-field="hint" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $old('hint') }}</textarea>
    </div>

    {{-- "Порядок важен" — для matching/table это всегда true при проверке
         (AutoGrader форсирует порядок для этих типов независимо от
         значения поля), выбирать тут нечего — просто скрытое поле,
         включённое, только пока показан один из этих двух типов. Для
         остальных типов поле вообще не нужно. --}}
    <input type="hidden" name="{{ $fname('order_matters') }}" data-field="order_matters"
           value="1" class="task-order-matters-input"
           @unless(in_array($currentType, ['matching', 'table'], true)) disabled @endunless>

    {{-- Итоговое превью — та же рендер-функция, что покажет сохранённому
         заданию: полезно свериться без add/delete-кнопок и селекторов. --}}
    <div class="task-preview mt-4">
        <button type="button" class="task-preview-toggle text-xs text-blue-600 hover:underline">Показать итоговый вид</button>
        <div class="task-preview-panel hidden mt-2 rounded-xl border border-gray-200 bg-white p-4 sm:p-6"></div>
    </div>
    </div>
</div>
