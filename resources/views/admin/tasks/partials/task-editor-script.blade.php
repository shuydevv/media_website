{{--
    Единый JS для .task-content-fields. Подключается ОДИН раз на странице
    (даже если карточек .task-content-fields несколько, как в конструкторе
    домашки) — все обработчики делегированные.

    Общий принцип для options/matches/table: скрытая textarea с исходным
    именем поля остаётся источником истины для бэкенда (ничего не меняется
    в App\Support\TaskContentRules/TaskContentNormalizer), а видимый слой —
    та же вёрстка, что у task-prompt.blade.php, только редактируемая —
    строится и синхронизируется JS-ом поверх нёе. answer для
    test/image_auto/matching/table выводится САМ из отметок "верный
    вариант"/пар в "Соотнесении"/значений пропусков — руками печатать
    цифры нужно только для text_with_questions.
--}}
@include('components.pin-field-script')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Метки пропусков в таблице — буквами (А, Б, В…), не цифрами: цифры
    // заняты под сами квадратики "Правильный ответ", буквы визуально не
    // путаются с ними. Ё и Й пропущены — как в обычной буквенной нумерации.
    const BLANK_LETTERS = 'АБВГДЕЖЗИКЛМНОПРСТУФХЦЧШЩЭЮЯ'.split('');
    function blankLetter(index) {
        if (index < BLANK_LETTERS.length) return BLANK_LETTERS[index];
        const outer = BLANK_LETTERS[Math.floor(index / BLANK_LETTERS.length) - 1] || 'А';
        const inner = BLANK_LETTERS[index % BLANK_LETTERS.length];
        return outer + inner;
    }

    const VISIBILITY_CLASSES = [
        'task-options', 'task-matches', 'task-image', 'task-table',
        'task-passage', 'task-image-auto-extra',
    ];

    // Авто-проверяемые типы отвечают квадратиками (тот же формат, что вводит
    // студент), ручные — связным текстом (образцовый ответ). Оба поля рендерятся
    // в разметке с одним и тем же name="answer" — активным должно быть ровно
    // одно, иначе форма отправит два значения на одно и то же имя. Поэтому
    // переключаем не только .hidden, но и disabled — задизейбленный инпут
    // браузер не отправляет вовсе.
    const ANSWER_PIN_TYPES = ['test', 'text_with_questions', 'matching', 'image_auto', 'table'];

    function toggleAnswerField(root, type) {
        const pinWrap = root.querySelector('.task-answer-pin');
        const textWrap = root.querySelector('.task-answer-text');
        if (!pinWrap || !textWrap) return;

        const usePin = ANSWER_PIN_TYPES.includes(type);
        pinWrap.classList.toggle('hidden', !usePin);
        textWrap.classList.toggle('hidden', usePin);
        pinWrap.querySelector('.pin-hidden-input')?.toggleAttribute('disabled', !usePin);
        textWrap.querySelector('textarea')?.toggleAttribute('disabled', usePin);

        // "Объяснение задания" — только у авто-проверяемых типов (усе те же,
        // что отвечают квадратиками); для written/image_manual этот же смысл
        // уже несёт "образцовый ответ" в textWrap выше. disabled, а не только
        // hidden — иначе значение всё равно уйдёт на сервер для типа, где оно
        // не должно применяться.
        const explanationWrap = root.querySelector('.task-explanation');
        explanationWrap?.classList.toggle('hidden', !usePin);
        explanationWrap?.querySelector('textarea')?.toggleAttribute('disabled', !usePin);

        // Для таблицы ответ выводится ТОЛЬКО из значений пропусков в
        // таблице — печатать его тут же второй раз нельзя, иначе два места
        // могут молча разойтись (см. sync() в renderTableBuilder).
        const isDerived = (type === 'table');
        pinWrap.querySelector('.pin-hidden-input')?.toggleAttribute('readonly', isDerived);
        pinWrap.querySelector('.task-answer-boxes')?.classList.toggle('opacity-60', isDerived);
        pinWrap.querySelector('.task-answer-boxes')?.classList.toggle('pointer-events-none', isDerived);
        pinWrap.querySelector('.task-answer-derived-note')?.classList.toggle('hidden', !isDerived);
    }

    // "Порядок важен" — при проверке всегда true для matching/table
    // (AutoGrader форсирует это для этих двух типов независимо от значения
    // поля), выбирать тут нечего, поэтому поле скрытое — просто включено/
    // выключено в зависимости от типа.
    function toggleOrderMatters(root, type) {
        const input = root.querySelector('.task-order-matters-input');
        if (input) input.disabled = !(type === 'matching' || type === 'table');
    }

    // Прогрессивное раскрытие: тип не показываем, пока не выбрана категория.
    // Категория — одна на страницу (только банк, конструктор домашки её не
    // использует), поэтому ищем глобально, а не внутри root.
    function toggleTypeWrap(root) {
        if (root.dataset.categoryGated !== '1') return;
        const categorySelect = document.querySelector('.task-category-select');
        const wrap = root.querySelector('.task-type-wrap');
        if (!wrap) return;
        const hasCategory = !!(categorySelect && categorySelect.value);
        wrap.classList.toggle('hidden', !hasCategory);
        if (!hasCategory) root.querySelector('.task-body')?.classList.add('hidden');
    }

    // Живая подсказка "есть ли уже критерии/баллы у этого номера" — не
    // блокирует сохранение (номер может быть совершенно новым, это
    // нормально), просто предупреждает, что подставится значение по
    // умолчанию (1 балл, без критериев), пока их не заполнят отдельно.
    let criteriaCheckDebounce = null;
    // Категория — либо явный селектор (банк), либо категория выбранного
    // курса домашки (в конструкторе домашки своего селектора категории нет
    // вовсе, category_id всегда берётся с курса, см. StoreController/
    // UpdateController::copyIntoBank и <option data-category="..."> у
    // #course_id в create/edit.blade.php).
    function currentCategoryId() {
        const categorySelect = document.querySelector('.task-category-select');
        if (categorySelect) return categorySelect.value || '';
        const courseSelect = document.getElementById('course_id');
        return courseSelect?.selectedOptions?.[0]?.dataset.category || '';
    }
    async function checkNumberCriteria(root) {
        const hint = root.querySelector('.task-number-hint');
        const numberInput = root.querySelector('.task-number-input');
        if (!hint || !numberInput) return;

        const categoryId = currentCategoryId();
        const number = numberInput.value.trim();
        if (!categoryId || !number) { hint.textContent = ''; return; }

        try {
            const url = '{{ route('admin.tasks.criteria-check') }}' + `?category_id=${encodeURIComponent(categoryId)}&number=${encodeURIComponent(number)}`;
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) { hint.textContent = ''; return; }
            const data = await res.json();
            if (data.exists === true) {
                hint.textContent = `есть критерии/баллы (${data.max_score ?? 1})`;
                hint.className = 'task-number-hint ml-2 text-xs text-emerald-600';
            } else if (data.exists === false) {
                hint.textContent = 'новый номер — критериев и баллов ещё нет, подставится 1 балл по умолчанию';
                hint.className = 'task-number-hint ml-2 text-xs text-amber-600';
            } else {
                hint.textContent = '';
            }
        } catch (e) {
            hint.textContent = '';
        }
    }
    function scheduleCriteriaCheck(root) {
        clearTimeout(criteriaCheckDebounce);
        criteriaCheckDebounce = setTimeout(() => checkNumberCriteria(root), 400);
    }

    // Живой предпросмотр задания по ID для поля "Задание из банка" в
    // конструкторе домашки — раньше это был <select> со всеми заданиями
    // категории курса разом (нежизнеспособно при сотнях заданий в банке),
    // теперь просто вводится ID, а сюда — короткая карточка для проверки,
    // что это тот самый номер/тип/вопрос, до сохранения формы.
    let taskIdLookupDebounce = null;
    async function lookupTaskId(taskItemRoot) {
        const input = taskItemRoot.querySelector('.task-id-input');
        const preview = taskItemRoot.querySelector('.task-id-preview');
        if (!input || !preview) return;

        const id = input.value.trim();
        if (!id) { preview.textContent = ''; preview.className = 'task-id-preview text-xs mt-1'; return; }

        try {
            const url = '{{ url('/admin/tasks/lookup') }}/' + encodeURIComponent(id);
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) {
                preview.textContent = 'Задание с таким ID не найдено.';
                preview.className = 'task-id-preview text-xs mt-1 text-red-600';
                return;
            }
            const data = await res.json();
            const courseCategoryId = document.getElementById('course_id')?.selectedOptions?.[0]?.dataset.category || '';
            const mismatch = !!(courseCategoryId && data.category_id && String(courseCategoryId) !== String(data.category_id));
            const label = [`№ ${data.number ?? '—'}`, data.type, data.preview].filter(Boolean).join(' — ');
            preview.textContent = `${label} · категория: ${data.category_title ?? '—'}`
                + (mismatch ? ' — другая категория, не как у курса этой домашки!' : '');
            preview.className = 'task-id-preview text-xs mt-1 ' + (mismatch ? 'text-amber-600' : 'text-emerald-600');
        } catch (e) {
            preview.textContent = '';
        }
    }
    function scheduleTaskIdLookup(taskItemRoot) {
        clearTimeout(taskIdLookupDebounce);
        taskIdLookupDebounce = setTimeout(() => lookupTaskId(taskItemRoot), 400);
    }

    // Смена типа — все поля содержания очищаются, чтобы от предыдущего типа
    // не оставалось "невидимых" данных (варианты/пары/таблица/картинка/
    // ответ прошлого типа, которые никто не видит, но которые всё равно
    // уйдут на сервер).
    function clearContentFields(root) {
        root.querySelectorAll('.task-body [data-field]').forEach(el => {
            const field = el.dataset.field;
            if (field === 'order_matters') return; // не трогаем — своё переключение
            if (el.type === 'file') { el.value = ''; return; }
            if (el.type === 'checkbox') { el.checked = false; return; }
            el.value = '';
        });
        setAnswerPin(root, '');

        const drop = root.querySelector('.task-image-drop');
        if (drop) {
            drop.querySelector('.task-image-preview')?.remove();
            if (!drop.querySelector('.task-image-placeholder')) {
                const ph = document.createElement('div');
                ph.className = 'task-image-placeholder flex flex-col items-center gap-2';
                // Тот же глиф "image-01" из Untitled UI, что и в components/icon/image-01.blade.php —
                // тут не Blade-компонент, а чистый JS-шаблон (создаётся динамически при очистке
                // поля), поэтому разметка продублирована руками, а не через компонент x-icon.
                ph.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-10 h-10"><path d="M16.2 21H6.931c-.605 0-.908 0-1.049-.12a.5.5 0 0 1-.173-.42c.014-.183.228-.397.657-.826l8.503-8.503c.396-.396.594-.594.822-.668a1 1 0 0 1 .618 0c.228.074.426.272.822.668L21 15v1.2M16.2 21c1.68 0 2.52 0 3.162-.327a3 3 0 0 0 1.311-1.311C21 18.72 21 17.88 21 16.2M16.2 21H7.8c-1.68 0-2.52 0-3.162-.327a3 3 0 0 1-1.311-1.311C3 18.72 3 17.88 3 16.2V7.8c0-1.68 0-2.52.327-3.162a3 3 0 0 1 1.311-1.311C5.28 3 6.12 3 7.8 3h8.4c1.68 0 2.52 0 3.162.327a3 3 0 0 1 1.311 1.311C21 5.28 21 6.12 21 7.8v8.4M10.5 8.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="text-xs">Нажмите, чтобы загрузить изображение</span>';
                drop.insertBefore(ph, drop.querySelector('.task-image-input'));
            }
        }

        const tableTextarea = root.querySelector('.task-table-json');
        if (tableTextarea) {
            tableTextarea.value = JSON.stringify({ cols: ['Колонка 1', 'Колонка 2'], rows: [['', '']], blanks: [] }, null, 4);
        }
    }

    // Дорастить число квадратиков ответа, не трогая уже введённые символы.
    function resizeAnswerPin(root, minCount) {
        root.querySelector('.task-answer-pin .pin-field')?._growPinField?.(minCount);
    }

    // Задать значение квадратиков целиком (вывод из отметок/пар/значений).
    function setAnswerPin(root, value) {
        root.querySelector('.task-answer-pin .pin-field')?._setPinFieldValue?.(value);
    }

    function currentType(root) {
        return root.querySelector('.task-type')?.value || '';
    }

    // --- Автовысота textarea, что выглядят как обычный текст задания ---
    function autosize(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight) + 'px';
    }
    function autosizeAll(root) {
        root.querySelectorAll('.task-autosize').forEach(autosize);
    }

    // --- Любое поле "один элемент на строку" (варианты ответа, обе колонки
    //     "Соотнесения", варианты под картинкой) — живая нумерация под ним,
    //     чтобы сразу было видно, что получится по строкам. ---
    function renderLinesPreview(textarea) {
        if (!textarea) return;
        const preview = textarea.parentElement?.querySelector('.task-lines-preview');
        if (!preview) return;
        const lines = textarea.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
        preview.innerHTML = lines.length
            ? lines.map((line, i) => `<div>${i + 1}. ${line.replace(/</g, '&lt;')}</div>`).join('')
            : '';
    }
    function renderAllLinesPreviews(root) {
        root.querySelectorAll('.task-lines-input').forEach(renderLinesPreview);
    }

    // --- Соотнесение — обычные поля (заголовок + список по строкам).
    //     Число квадратиков ответа подстраивается под число строк левой
    //     колонки (см. делегированный обработчик input ниже), но сам
    //     ответ вводится вручную, как и для "Текста с вопросами". ---

    // --- Таблица — тот же визуал, что у task-prompt.blade.php: пропуски
    //     показаны янтарным бейджем, под ним — поле "правильное значение"
    //     (это то, что видит только админ — не часть условия для студента,
    //     поэтому единственное место, где остаётся обычный маленький инпут).
    function parseTableJson(raw) {
        try {
            const data = JSON.parse(raw);
            if (data && Array.isArray(data.cols) && Array.isArray(data.rows)) {
                return {
                    cols: data.cols.map(String),
                    rows: data.rows.map(row => (Array.isArray(row) ? row : []).map(String)),
                    blanks: Array.isArray(data.blanks) ? data.blanks.map(b => ({
                        r: Number(b.r) || 0, c: Number(b.c) || 0,
                        key: String(b.key ?? ''), value: String(b.value ?? ''),
                    })) : [],
                };
            }
        } catch (e) { /* невалидный JSON — вернём null, покажем ручной режим */ }
        return null;
    }

    function blankAt(state, r, c) {
        return state.blanks.find(b => b.r === r && b.c === c);
    }

    function renderTableBuilder(root) {
        const container = root.querySelector('.task-table-builder');
        const textarea = root.querySelector('.task-table-json');
        const hint = root.querySelector('.task-table-json-hint');
        if (!container || !textarea) return;
        // Ручной режим включён — не перетираем то, что печатает админ.
        if (!textarea.classList.contains('hidden')) return;

        // Пустая textarea (например, новая карточка задания, клонированная
        // и очищенная в конструкторе домашки) — не поломанный JSON, а
        // просто "ещё не заполнено": стартовая сетка 2×2, а не ошибка.
        let state = parseTableJson(textarea.value);
        if (!state && textarea.value.trim() === '') {
            state = { cols: ['Колонка 1', 'Колонка 2'], rows: [['', '']], blanks: [] };
        }
        if (!state) {
            container.innerHTML = '';
            hint?.classList.remove('hidden');
            return;
        }
        hint?.classList.add('hidden');

        const sync = () => {
            textarea.value = JSON.stringify({ cols: state.cols, rows: state.rows, blanks: state.blanks }, null, 4);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            const orderedValues = state.blanks
                .slice()
                .sort((a, b) => (a.r - b.r) || (a.c - b.c))
                .map(b => b.value || '');
            setAnswerPin(root, orderedValues.join(''));
            resizeAnswerPin(root, state.blanks.length);
        };

        const wrap = document.createElement('div');
        wrap.className = 'overflow-auto rounded-xl border border-gray-200 mt-1';

        const table = document.createElement('table');
        table.className = 'min-w-full border-collapse';

        const thead = document.createElement('thead');
        thead.className = 'bg-gray-50';
        const theadRow = document.createElement('tr');
        state.cols.forEach((col, c) => {
            const th = document.createElement('th');
            th.className = 'border border-gray-200 px-3 py-2 sm:py-3 text-left text-xs sm:text-sm font-medium text-zinc-700';
            const input = document.createElement('input');
            input.type = 'text';
            input.value = col;
            input.className = 'w-full border-0 bg-transparent p-0 font-medium text-xs sm:text-sm text-zinc-700';
            input.addEventListener('input', () => { state.cols[c] = input.value; sync(); });
            th.appendChild(input);
            if (state.cols.length > 1) {
                const del = document.createElement('button');
                del.type = 'button';
                del.textContent = '×';
                del.title = 'Удалить столбец';
                del.className = 'text-red-400 hover:text-red-600 text-xs ml-1 align-middle';
                del.addEventListener('click', () => {
                    state.cols.splice(c, 1);
                    state.rows.forEach(row => row.splice(c, 1));
                    state.blanks = state.blanks.filter(b => b.c !== c).map(b => b.c > c ? { ...b, c: b.c - 1 } : b);
                    sync();
                    renderTableBuilder(root);
                });
                th.appendChild(del);
            }
            theadRow.appendChild(th);
        });
        const addColTh = document.createElement('th');
        addColTh.className = 'border border-gray-200 px-2';
        const addColBtn = document.createElement('button');
        addColBtn.type = 'button';
        addColBtn.textContent = '+ столбец';
        addColBtn.className = 'text-xs text-blue-600 hover:underline whitespace-nowrap';
        addColBtn.addEventListener('click', () => {
            state.cols.push('Колонка ' + (state.cols.length + 1));
            state.rows.forEach(row => row.push(''));
            sync();
            renderTableBuilder(root);
        });
        addColTh.appendChild(addColBtn);
        theadRow.appendChild(addColTh);
        thead.appendChild(theadRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        state.rows.forEach((row, r) => {
            const tr = document.createElement('tr');
            tr.className = 'odd:bg-white';
            state.cols.forEach((_, c) => {
                const td = document.createElement('td');
                td.className = 'px-3 py-2 sm:py-3 align-top border border-gray-200';

                const blank = blankAt(state, r, c);
                if (blank) {
                    const badgeWrap = document.createElement('div');
                    badgeWrap.className = 'inline-flex flex-col gap-1';

                    const badgeRow = document.createElement('div');
                    badgeRow.className = 'inline-flex items-center gap-1.5';
                    const badge = document.createElement('input');
                    badge.type = 'text';
                    badge.value = blank.key;
                    badge.title = 'Метка, которую увидит студент';
                    badge.className = 'w-8 text-center text-[10px] sm:text-xs font-medium rounded-md bg-amber-50 border border-amber-200 text-amber-700 py-0.5';
                    badge.addEventListener('input', () => { blank.key = badge.value; sync(); });
                    badgeRow.appendChild(badge);
                    const unmark = document.createElement('button');
                    unmark.type = 'button';
                    unmark.title = 'Убрать пропуск';
                    unmark.className = 'text-gray-400 hover:text-red-500 text-xs';
                    unmark.textContent = '×';
                    unmark.addEventListener('click', () => {
                        state.blanks = state.blanks.filter(b => !(b.r === r && b.c === c));
                        sync();
                        renderTableBuilder(root);
                    });
                    badgeRow.appendChild(unmark);
                    badgeWrap.appendChild(badgeRow);

                    const valueInput = document.createElement('input');
                    valueInput.type = 'text';
                    valueInput.value = blank.value;
                    valueInput.placeholder = 'ответ';
                    valueInput.title = 'Правильное значение — админу для проверки, студент его не видит';
                    valueInput.className = 'w-16 text-[11px] border border-dashed border-amber-300 rounded px-1 py-0.5 text-amber-800';
                    valueInput.addEventListener('input', () => { blank.value = valueInput.value; sync(); });
                    badgeWrap.appendChild(valueInput);

                    td.appendChild(badgeWrap);
                } else {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.value = row[c] ?? '';
                    input.className = 'w-full border-0 outline-none bg-transparent p-0 text-sm sm:text-[15px] text-zinc-800';
                    input.addEventListener('input', () => { row[c] = input.value; sync(); });
                    td.appendChild(input);

                    const mark = document.createElement('button');
                    mark.type = 'button';
                    mark.className = 'block mt-1 text-[10px] text-gray-400 hover:text-amber-600';
                    mark.textContent = 'сделать пропуском';
                    mark.addEventListener('click', () => {
                        state.blanks.push({ r, c, key: blankLetter(state.blanks.length), value: '' });
                        sync();
                        renderTableBuilder(root);
                    });
                    td.appendChild(mark);
                }

                tr.appendChild(td);
            });
            const delTd = document.createElement('td');
            delTd.className = 'px-2 align-top border border-gray-200';
            if (state.rows.length > 1) {
                const del = document.createElement('button');
                del.type = 'button';
                del.textContent = '× строка';
                del.className = 'text-red-400 hover:text-red-600 text-xs whitespace-nowrap';
                del.addEventListener('click', () => {
                    state.rows.splice(r, 1);
                    state.blanks = state.blanks.filter(b => b.r !== r).map(b => b.r > r ? { ...b, r: b.r - 1 } : b);
                    sync();
                    renderTableBuilder(root);
                });
                delTd.appendChild(del);
            }
            tr.appendChild(delTd);
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);

        container.innerHTML = '';
        container.appendChild(wrap);

        const addRowBtn = document.createElement('button');
        addRowBtn.type = 'button';
        addRowBtn.textContent = '+ строка';
        addRowBtn.className = 'text-xs text-blue-600 hover:underline mt-2';
        addRowBtn.addEventListener('click', () => {
            state.rows.push(state.cols.map(() => ''));
            sync();
            renderTableBuilder(root);
        });
        container.appendChild(addRowBtn);

        sync();
    }

    // --- Картинка — клик по плейсхолдеру открывает выбор файла, превью
    //     подставляется в тот же блок, что увидит студент. ---
    function wireImageUpload(root) {
        const drop = root.querySelector('.task-image-drop');
        const fileInput = root.querySelector('.task-image-input');
        if (!drop || !fileInput) return;
        fileInput.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = () => {
                drop.querySelector('.task-image-placeholder')?.remove();
                let img = drop.querySelector('.task-image-preview');
                if (!img) {
                    img = document.createElement('img');
                    img.className = 'task-image-preview w-full h-full object-contain';
                    drop.insertBefore(img, fileInput);
                }
                img.src = reader.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function toggleTaskFields(root, type) {
        VISIBILITY_CLASSES.forEach(cls => {
            root.querySelector('.' + cls)?.classList.add('hidden');
        });

        root.querySelector('.task-body')?.classList.toggle('hidden', !type);

        if (type === 'test') root.querySelector('.task-options')?.classList.remove('hidden');
        if (type === 'text_with_questions' || type === 'written') root.querySelector('.task-passage')?.classList.remove('hidden');
        if (type === 'matching') root.querySelector('.task-matches')?.classList.remove('hidden');
        if (type === 'table') {
            root.querySelector('.task-table')?.classList.remove('hidden');
            root.querySelector('.task-options')?.classList.remove('hidden');
        }
        if (type === 'image_auto') {
            root.querySelector('.task-image')?.classList.remove('hidden');
            root.querySelector('.task-image-auto-extra')?.classList.remove('hidden');
        }
        if (type === 'image_manual') root.querySelector('.task-image')?.classList.remove('hidden');

        toggleAnswerField(root, type);
        toggleOrderMatters(root, type);
    }

    function initRoot(root) {
        const type = currentType(root);
        // Идемпотентно (см. pin-field-script.blade.php) — для карточек,
        // уже настроенных при загрузке страницы, это no-op; для только что
        // склонированной карточки (см. "Добавить задание") — единственное
        // место, где её .pin-field вообще получает обработчики: без этого
        // клон был бы либо мёртвым виджетом, либо (что хуже) писал бы в
        // ответ оригинальной карточки, у которой то же имя поля до клонирования.
        const pinField = root.querySelector('.task-answer-pin .pin-field');
        if (pinField) window.setupPinField?.(pinField);
        toggleTaskFields(root, type);
        toggleTypeWrap(root);
        autosizeAll(root);
        renderAllLinesPreviews(root);
        // Только для типа "Таблица" — sync() внутри renderTableBuilder()
        // перезаписывает "Правильный ответ" значениями пропусков таблицы;
        // для остальных типов там нет реального table_content, а textarea
        // всегда содержит JSON-заглушку с одним пустым пропуском, и
        // безусловный вызов затирал бы уже сохранённый ответ (например,
        // "Тест с вариантами"/"Текст с вопросами") пустой строкой при
        // каждой загрузке страницы редактирования.
        if (type === 'table') renderTableBuilder(root);
        wireImageUpload(root);
        if (root.querySelector('.task-number-input')) scheduleCriteriaCheck(root);
    }

    document.querySelectorAll('.task-content-fields').forEach(initRoot);

    // .task-id-input живёт рядом с .task-content-fields (в .task-item), не
    // внутри неё — предпросмотр уже заполненных при загрузке страницы
    // (редактирование домашки с заданиями из банка) даём сразу, не дожидаясь
    // первого ввода.
    document.querySelectorAll('.task-item').forEach(item => {
        if (item.querySelector('.task-id-input')?.value.trim()) lookupTaskId(item);
    });

    // Категория — одна на страницу (см. toggleTypeWrap), но тип раскрывать
    // нужно во всех .task-content-fields на странице разом (на всякий
    // случай — в банке карточка одна, но обработчик остаётся общим).
    document.querySelector('.task-category-select')?.addEventListener('change', () => {
        document.querySelectorAll('.task-content-fields').forEach(root => {
            toggleTypeWrap(root);
            if (root.querySelector('.task-number-input')) scheduleCriteriaCheck(root);
        });
    });

    // Конструктор домашки: своего селектора категории нет — категория
    // определяется курсом, так что смену курса тоже нужно перепроверять
    // (и подсказку про критерии, и предупреждение о несовпадении категории
    // у уже введённого ID задания из банка).
    document.getElementById('course_id')?.addEventListener('change', () => {
        document.querySelectorAll('.task-content-fields').forEach(root => {
            if (root.querySelector('.task-number-input')) scheduleCriteriaCheck(root);
        });
        document.querySelectorAll('.task-item').forEach(item => {
            if (item.querySelector('.task-id-input')?.value.trim()) lookupTaskId(item);
        });
    });

    // Делегированные обработчики — работают и для карточек, добавленных
    // позже JS-ом конструктора домашки (клонирование строки "Добавить
    // задание"), не нужно перепривязывать слушатели вручную.
    document.addEventListener('change', (e) => {
        const root = e.target.closest('.task-content-fields');
        if (!root) return;
        if (e.target.classList.contains('task-type')) {
            clearContentFields(root);
            toggleTaskFields(root, e.target.value);
            if (e.target.value === 'table') renderTableBuilder(root);
            renderAllLinesPreviews(root);
            // Поля, скрытые при первой отрисовке (autosize() на display:none
            // элементе всегда даёт scrollHeight=0), при показе остаются
            // залипшими на нулевой высоте, пока в них не введут текст —
            // выглядит как "нельзя вставить текст". Пересчитываем высоту
            // сразу после того, как нужный блок стал видимым.
            autosizeAll(root);
        }
    });

    document.addEventListener('input', (e) => {
        if (e.target.classList.contains('task-autosize')) autosize(e.target);
        if (e.target.classList.contains('task-lines-input')) {
            renderLinesPreview(e.target);
        }
        if (e.target.dataset.field === 'matches.left') {
            const root = e.target.closest('.task-content-fields');
            const count = e.target.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean).length;
            if (root && count > 0) resizeAnswerPin(root, count);
        }
        if (e.target.classList.contains('task-number-input')) {
            scheduleCriteriaCheck(e.target.closest('.task-content-fields'));
        }
        if (e.target.classList.contains('task-id-input')) {
            const item = e.target.closest('.task-item');
            if (item) scheduleTaskIdLookup(item);
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.classList.contains('task-table-toggle-json')) return;
        const root = e.target.closest('.task-content-fields');
        const textarea = root?.querySelector('.task-table-json');
        const builder = root?.querySelector('.task-table-builder');
        if (!textarea || !builder) return;

        const switchingToManual = textarea.classList.contains('hidden');
        if (switchingToManual) {
            textarea.classList.remove('hidden');
            builder.classList.add('hidden');
            e.target.textContent = 'Вернуться к конструктору';
        } else {
            textarea.classList.add('hidden');
            builder.classList.remove('hidden');
            e.target.textContent = 'Редактировать JSON вручную';
            renderTableBuilder(root);
        }
    });

    // --- Live-превью «как видит студент» ---
    // Собираем значения по data-field (а не по name — префикс имени поля
    // отличается между банком и домашкой, data-field везде одинаковый).
    function collectPreviewData(root) {
        const data = {};
        root.querySelectorAll('[data-field]').forEach(el => {
            if (el.disabled) return;
            if (el.type === 'checkbox' && !el.checked) return;
            const value = el.type === 'checkbox' ? '1' : el.value;
            const path = el.dataset.field.split('.');
            let target = data;
            while (path.length > 1) {
                const key = path.shift();
                target = target[key] = target[key] || {};
            }
            target[path[0]] = value;
        });
        return data;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    let previewDebounce = null;
    function schedulePreview(root) {
        const panel = root.querySelector('.task-preview-panel');
        if (!panel || panel.classList.contains('hidden')) return;
        clearTimeout(previewDebounce);
        previewDebounce = setTimeout(() => runPreview(root), 400);
    }

    async function runPreview(root) {
        const panel = root.querySelector('.task-preview-panel');
        if (!panel) return;
        try {
            const res = await fetch('{{ route('admin.tasks.preview') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'text/html',
                },
                body: JSON.stringify(collectPreviewData(root)),
            });
            panel.innerHTML = res.ok ? await res.text() : '<p class="text-sm text-red-500">Не удалось обновить превью.</p>';
        } catch (e) {
            panel.innerHTML = '<p class="text-sm text-red-500">Не удалось обновить превью.</p>';
        }
    }

    document.addEventListener('click', (e) => {
        if (!e.target.classList.contains('task-preview-toggle')) return;
        const root = e.target.closest('.task-content-fields');
        const panel = root?.querySelector('.task-preview-panel');
        if (!panel) return;
        const opening = panel.classList.contains('hidden');
        panel.classList.toggle('hidden');
        e.target.textContent = opening ? 'Скрыть итоговый вид' : 'Показать итоговый вид';
        if (opening) runPreview(root);
    });

    document.addEventListener('input', (e) => {
        if (!e.target.closest('.task-content-fields')) return;
        if (e.target.hasAttribute('data-field')) schedulePreview(e.target.closest('.task-content-fields'));
    });
    document.addEventListener('change', (e) => {
        const root = e.target.closest('.task-content-fields');
        if (!root) return;
        if (e.target.hasAttribute('data-field')) schedulePreview(root);
    });

    // Для карточек, добавленных динамически (см. #add-task в конструкторе
    // домашки) — инициализировать видимость сразу после появления в DOM.
    window.initTaskContentFields = (root) => initRoot(root);
    // Точка входа для восстановления черновика домашки (см. localStorage-
    // скрипт в admin/homeworks/create.blade.php) — после того как черновик
    // проставляет table_content textarea напрямую, конструктор таблицы
    // нужно перерисовать из этого значения явно, initRoot() тут не подходит
    // (заново вызвал бы toggleTaskFields/renderAllLinesPreviews и т.д.).
    window.renderTableBuilder = renderTableBuilder;
});
</script>
