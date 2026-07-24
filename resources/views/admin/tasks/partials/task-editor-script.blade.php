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
                ph.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg><span class="text-xs">Нажмите, чтобы загрузить изображение</span>';
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
        const pinField = root.querySelector('.task-answer-pin .pin-field');
        if (!pinField) return;
        const name = pinField.getAttribute('data-for');
        window.growPinField?.[name]?.(minCount);
    }

    // Задать значение квадратиков целиком (вывод из отметок/пар/значений).
    function setAnswerPin(root, value) {
        const pinField = root.querySelector('.task-answer-pin .pin-field');
        if (!pinField) return;
        const name = pinField.getAttribute('data-for');
        window.setPinFieldValue?.[name]?.(value);
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
                        state.blanks.push({ r, c, key: String(state.blanks.length + 1), value: '' });
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
        toggleTaskFields(root, type);
        toggleTypeWrap(root);
        autosizeAll(root);
        renderAllLinesPreviews(root);
        renderTableBuilder(root);
        wireImageUpload(root);
    }

    document.querySelectorAll('.task-content-fields').forEach(initRoot);

    // Категория — одна на страницу (см. toggleTypeWrap), но тип раскрывать
    // нужно во всех .task-content-fields на странице разом (на всякий
    // случай — в банке карточка одна, но обработчик остаётся общим).
    document.querySelector('.task-category-select')?.addEventListener('change', () => {
        document.querySelectorAll('.task-content-fields').forEach(toggleTypeWrap);
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
});
</script>
