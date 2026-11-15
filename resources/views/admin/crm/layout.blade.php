{{--
    Надстройка над общим каркасом админки для страниц CRM (index/archive):
    своей разметки здесь нет — только стили полей карточки ученика и общий
    для обеих страниц скрипт (статус, напоминания, заметка, дата доступа).
    Секцию content страницы объявляют сами, она уходит в admin.layouts.main
    как есть. Вкладки «В работе / Завершили» — x-ui.tabs в самих страницах.
--}}
@extends('admin.layouts.main')

@push('head')
    <style>
        {{-- .input-focus:not(:placeholder-shown) в app.css красит синим любое
             непустое поле — в плотном списке это шумно и читается как
             предупреждение, а не обычное значение. .crm-note-view показывается
             ТОЛЬКО когда заметка не пуста, то есть :not(:placeholder-shown)
             для неё матчился бы всегда — без перебивки была бы синей постоянно.
             Перебиваем для всех полей CRM (нужна специфичность выше одного
             класса+псевдокласса из app.css). --}}
        .input-focus.crm-note-input:not(:placeholder-shown),
        .input-focus.crm-access-input:not(:placeholder-shown),
        .input-focus.crm-payment-amount:not(:placeholder-shown) {
            border-color: #d4d4d8;
            background-color: #fff;
        }
        {{-- .crm-note-view показывается ТОЛЬКО когда заметка не пуста, то есть
             :not(:placeholder-shown) для неё матчился бы всегда — своя
             override-запись (не в общей группе выше: там фон #fff, здесь
             нарочно чуть темнее #fafafa, чтобы на глаз было видно "это не
             поле ввода сейчас, а просто текст"). --}}
        .input-focus.crm-note-view:not(:placeholder-shown) {
            border-color: #d4d4d8;
            background-color: #fafafa;
        }
        textarea.crm-note-input,
        textarea.crm-note-view {
            resize: none;
        }
        {{-- Селект статуса — сам себе бейдж, цвет меняется классами из JS. --}}
        select.crm-stage-select {
            font-weight: 500;
            cursor: pointer;
        }
        select.crm-stage-select:disabled {
            cursor: default;
        }
        {{-- [data-view]/[data-edit] в этом файле переключаются JS-тоглом
             (element.hidden = true/false) — но там, где на самих элементах
             есть display-утилита (flex/inline-flex — у access-date-спана,
             у note-строки, у reminder-row), Tailwind-препрефлайт [hidden]
             {display:none} (base-слой, идёт раньше в собранном CSS) молча
             проигрывает при равной специфичности .flex/.inline-flex
             (utilities-слой, идёт позже) — hidden-атрибут не скрывает
             элемент. Один общий оверрайд для обоих типов узлов. --}}
        [data-view][hidden], [data-edit][hidden] {
            display: none;
        }
        {{-- Окно «Оплатил» и галочки в строке курса — свои стили, а не новые
             Tailwind-классы (те требуют пересборки ассетов). --}}
        .crm-paid-dialog { width: calc(100vw - 2rem); max-width: 24rem; border: 1px solid #e4e4e7; box-shadow: 0 20px 40px rgba(0,0,0,.12); }
        .crm-paid-dialog::backdrop { background: rgba(24,24,27,.35); }
        .crm-paid-dialog [hidden] { display: none; }
        .crm-paid-dialog [data-paid-submit]:disabled { opacity: .5; cursor: default; }
        .crm-mark-box { width: 15px; height: 15px; accent-color: #27272a; cursor: pointer; }
    </style>
@endpush

@push('page-scripts')
    {{-- Шаблоны для JS: реальные <x-icon>-SVG внутри <template> — компилируются
         сервером как обычно, но не рендерятся в DOM, пока JS их не клонирует.
         Так новые строки, добавленные без перезагрузки страницы, получают те
         же иконки, что и серверный рендер, без дублирования SVG-путей в JS. --}}
    <template id="crm-reminder-row-template">
        @include('admin.crm.partials.reminder-row', ['reminder' => new \App\Models\CrmReminder(['due_at' => now(), 'note' => ''])])
    </template>
    <template id="crm-reminder-body-row-template">
        @include('admin.crm.partials.reminder-body-row', ['reminder' => new \App\Models\CrmReminder(['due_at' => now(), 'note' => ''])])
    </template>

    <dialog id="crm-paid-dialog" class="crm-paid-dialog rounded-2xl p-0 w-[calc(100vw-2rem)] max-w-sm">
        <form class="p-5 space-y-3" novalidate>
            <div>
                <div class="sans-medium text-base text-zinc-900">Оплатил</div>
                <div class="sans text-sm text-zinc-500" data-paid-title></div>
            </div>
            <label class="block">
                <span class="sans text-xs text-zinc-500">Сумма, ₽</span>
                <input type="number" name="amount_rub" min="0" step="1" required
                       class="mt-1 w-full border rounded-lg px-3 py-2 input-focus sans text-base md:text-sm">
                <span class="sans text-xs text-zinc-400" data-paid-hint></span>
            </label>
            <div class="flex gap-2">
                <label class="block flex-1">
                    <span class="sans text-xs text-zinc-500">Дата оплаты</span>
                    <input type="date" name="paid_at" required
                           class="mt-1 w-full border rounded-lg px-3 py-2 input-focus sans text-base md:text-sm">
                </label>
                <label class="block">
                    <span class="sans text-xs text-zinc-500">Период</span>
                    <select name="billing_interval_days" class="mt-1 border rounded-lg px-2 py-2 input-focus sans text-base md:text-sm">
                        <option value="30">месяц</option>
                        <option value="14">2 недели</option>
                    </select>
                </label>
            </div>
            <div class="sans text-sm text-zinc-700">Доступ продлится до <span class="sans-medium" data-paid-preview>—</span></div>
            <div class="sans text-xs text-zinc-400" data-paid-notify>Ученику придёт уведомление «Оплата получена».</div>
            <div class="sans text-sm text-rose-600" data-paid-error hidden></div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-paid-cancel class="px-3 min-h-10 md:min-h-9 text-sm text-zinc-500 hover:text-zinc-800 sans">Отмена</button>
                <button type="submit" data-paid-submit class="rounded-lg px-4 min-h-10 md:min-h-9 bg-zinc-800 text-white text-sm sans-medium hover:bg-zinc-900">ОК</button>
            </div>
        </form>
    </dialog>

    <script>
    (function () {
        window.CRM_ARCHIVE_VIEW = @json(request()->routeIs('admin.crm.archive'));

        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        function patch(url, body) {
            return fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            }).then(function (res) {
                if (!res.ok) throw new Error('request failed');
                return res.json();
            });
        }

        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            }).then(function (res) {
                if (!res.ok) throw new Error('request failed');
                return res.json();
            });
        }

        function del(url) {
            return fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
            }).then(function (res) {
                if (!res.ok) throw new Error('request failed');
                return res.json();
            });
        }

        function flash(el) {
            el.style.opacity = '1';
            setTimeout(function () { el.style.opacity = '0'; }, 1200);
        }

        // Карандашик рядом с полем: клик прячет текст и показывает инпут(ы) —
        // у даты доступа их несколько (дата + сумма + кнопки), у комментария один.
        document.querySelectorAll('[data-field-group]').forEach(function (group) {
            const view = group.querySelector('[data-view]');
            const editEls = group.querySelectorAll('[data-edit]');
            const toggle = group.querySelector('[data-field-edit-toggle]');
            toggle.addEventListener('click', function () {
                view.hidden = true;
                editEls.forEach(function (el) { el.hidden = false; });
                editEls[0].focus();
            });
        });

        const selectColorClasses = {
            gray:    ['bg-gray-100', 'text-gray-700', 'border-gray-300'],
            blue:    ['bg-blue-50', 'text-blue-700', 'border-blue-300'],
            amber:   ['bg-amber-50', 'text-amber-700', 'border-amber-300'],
            emerald: ['bg-emerald-50', 'text-emerald-700', 'border-emerald-300'],
            rose:    ['bg-rose-50', 'text-rose-700', 'border-rose-300'],
        };
        const allSelectColorClasses = Object.values(selectColorClasses).flat();

        function applySelectColor(select, color) {
            select.classList.remove.apply(select.classList, allSelectColorClasses);
            select.classList.add.apply(select.classList, selectColorClasses[color] || selectColorClasses.gray);
        }

        // Набор допустимых пунктов зависит от статуса (см.
        // User::crmStatusOptionsFor()) — после сохранения список <option>
        // может стать другим, не только текущее значение/цвет, поэтому
        // перестраиваем целиком по тому, что вернул сервер.
        function rebuildSelectOptions(select, options) {
            select.innerHTML = '';
            options.forEach(function (opt) {
                var el = document.createElement('option');
                el.value = opt.value;
                el.textContent = opt.label;
                el.disabled = opt.disabled;
                el.selected = opt.selected;
                select.appendChild(el);
            });
        }

        function removeRow(row) {
            row.style.transition = 'opacity .3s ease';
            row.style.opacity = '0';
            setTimeout(function () { row.remove(); }, 300);
        }

        // Активное/неактивное напоминание — два фиксированных набора классов,
        // а не одна "activated"-модификация: цвет всегда переустанавливаем
        // целиком (проще и надёжнее, чем remove/add по отдельности при
        // клонировании из <template> с уже готовым, но не обязательно верным
        // цветом по умолчанию).
        function reminderColorClasses(isActive) {
            return isActive ? ['border-amber-400', 'bg-amber-100'] : ['border-amber-200', 'bg-amber-50'];
        }

        const reminderRowTemplate = document.getElementById('crm-reminder-row-template');
        const reminderBodyRowTemplate = document.getElementById('crm-reminder-body-row-template');

        function buildReminderPopoverRow(r) {
            const row = reminderRowTemplate.content.firstElementChild.cloneNode(true);
            row.className = 'text-sm border rounded-lg px-2 py-1.5 ' + reminderColorClasses(r.is_active).join(' ');
            row.dataset.reminderId = r.id;
            row.querySelector('[data-reminder-date-label]').textContent = r.due_at_label;
            row.querySelector('[data-reminder-note-label]').textContent = r.note;
            row.querySelector('[data-reminder-edit-date]').value = r.due_at;
            row.querySelector('[data-reminder-edit-note]').value = r.note;
            return row;
        }

        function buildReminderBodyRow(r) {
            const row = reminderBodyRowTemplate.content.firstElementChild.cloneNode(true);
            row.className = 'flex items-center gap-2 border rounded-lg px-3 py-2 text-sm ' + reminderColorClasses(r.is_active).join(' ');
            row.dataset.reminderId = r.id;
            row.querySelector('[data-reminder-body-note]').textContent = r.note;
            row.querySelector('[data-reminder-body-date]').textContent = r.due_at_label;
            return row;
        }

        document.querySelectorAll('[data-student-row]').forEach(function (row) {
            const userId = row.dataset.userId;

            // Статус: селект со всеми состояниями сразу — при выборе сохраняет
            // и перекрашивается по ответу сервера. Сервер может вернуть НЕ тот
            // статус, что выбрали (например, выбрали "Записался", а у ученика
            // на деле есть активный доступ — crmStatus() всё равно посчитает
            // его "Активный ученик", приоритет реальных данных выше ручной
            // отметки) — поэтому значение селекта синхронизируем с ответом,
            // а не оставляем то, что выбрал админ.
            const stageSelect = row.querySelector('.crm-stage-select');
            let stageOriginal = stageSelect.value;
            stageSelect.addEventListener('change', function () {
                // "Отказался" переносит ученика в архив (пропадает из этого
                // списка) — спрашиваем явно, чтобы случайный клик по селекту
                // не увёл человека из вида молча.
                if (stageSelect.value === 'lost' && !confirm('Отметить ученика как "Отказался"? Он уйдёт из основного списка CRM в архив.')) {
                    stageSelect.value = stageOriginal;
                    return;
                }

                patch('/admin/crm/users/' + userId + '/checklist', {
                    stage: stageSelect.value || null,
                }).then(function (data) {
                    rebuildSelectOptions(stageSelect, data.status.options);
                    stageOriginal = stageSelect.value;
                    applySelectColor(stageSelect, data.status.color);

                    const isClosedNow = data.status.key === 'completed' || data.status.key === 'lost';
                    if (isClosedNow !== window.CRM_ARCHIVE_VIEW) {
                        removeRow(row);
                    }
                }).catch(function () {
                    stageSelect.value = stageOriginal;
                    alert('Не удалось сохранить, попробуйте ещё раз.');
                });
            });

            // Напоминания: колокольчик открывает поповер со списком всех
            // напоминаний ученика (правка/удаление) + форма добавления внизу.
            // Жёлтое поле в теле карточки — только просмотр (см.
            // reminder-body-row.blade.php), своих кнопок не имеет и
            // синхронизируется отсюда при любом CRUD в поповере.
            const reminderWidget = row.querySelector('[data-reminder-widget]');
            const reminderToggle = reminderWidget.querySelector('[data-reminder-toggle]');
            const reminderPopover = reminderWidget.querySelector('[data-reminder-popover]');
            const reminderPopoverList = reminderPopover.querySelector('[data-reminder-popover-list]');
            const reminderNewDate = reminderPopover.querySelector('[data-reminder-new-date]');
            const reminderNewNote = reminderPopover.querySelector('[data-reminder-new-note]');
            const reminderAddBtn = reminderPopover.querySelector('[data-reminder-add]');
            const reminderBodyList = row.querySelector('[data-reminder-body-list]');

            reminderToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                reminderPopover.hidden = !reminderPopover.hidden;
            });
            reminderPopover.addEventListener('click', function (e) { e.stopPropagation(); });
            document.addEventListener('click', function () {
                reminderPopover.hidden = true;
            });

            function syncReminderBodyRow(r) {
                const existing = reminderBodyList.querySelector('[data-reminder-id="' + r.id + '"]');
                if (existing) {
                    existing.className = 'flex items-center gap-2 border rounded-lg px-3 py-2 text-sm ' + reminderColorClasses(r.is_active).join(' ');
                    existing.querySelector('[data-reminder-body-note]').textContent = r.note;
                    existing.querySelector('[data-reminder-body-date]').textContent = r.due_at_label;
                } else {
                    reminderBodyList.appendChild(buildReminderBodyRow(r));
                }
                reminderBodyList.hidden = false;
            }

            function removeReminderBodyRow(id) {
                const existing = reminderBodyList.querySelector('[data-reminder-id="' + id + '"]');
                if (existing) existing.remove();
                if (!reminderBodyList.querySelector('[data-reminder-body-row]')) {
                    reminderBodyList.hidden = true;
                }
            }

            function wireReminderRow(popoverRow) {
                const id = popoverRow.dataset.reminderId;
                const view = popoverRow.querySelector('[data-view]');
                const edit = popoverRow.querySelector('[data-edit]');
                const editDate = popoverRow.querySelector('[data-reminder-edit-date]');
                const editNote = popoverRow.querySelector('[data-reminder-edit-note]');
                const editToggle = popoverRow.querySelector('[data-reminder-edit-toggle]');
                const saveBtn = popoverRow.querySelector('[data-reminder-save]');
                const cancelBtn = popoverRow.querySelector('[data-reminder-cancel]');
                const deleteBtn = popoverRow.querySelector('[data-reminder-delete]');

                editToggle.addEventListener('click', function () {
                    view.hidden = true;
                    edit.hidden = false;
                });

                // Отмена — откатываем к value-атрибуту (не .value), это
                // "исходное" значение инпута по HTML-спеке, не тронутое
                // вводом пользователя; после успешного сохранения ниже сами
                // же его обновляем через setAttribute, чтобы следующая
                // отмена откатывала уже к новому сохранённому состоянию.
                cancelBtn.addEventListener('click', function () {
                    editDate.value = editDate.getAttribute('value') || '';
                    editNote.value = editNote.getAttribute('value') || '';
                    edit.hidden = true;
                    view.hidden = false;
                });

                saveBtn.addEventListener('click', function () {
                    if (!editDate.value || !editNote.value) return;
                    patch('/admin/crm/reminders/' + id, {
                        due_at: editDate.value,
                        note: editNote.value,
                    }).then(function (data) {
                        const r = data.reminder;
                        popoverRow.querySelector('[data-reminder-date-label]').textContent = r.due_at_label;
                        popoverRow.querySelector('[data-reminder-note-label]').textContent = r.note;
                        editDate.setAttribute('value', r.due_at);
                        editNote.setAttribute('value', r.note);
                        popoverRow.className = 'text-sm border rounded-lg px-2 py-1.5 ' + reminderColorClasses(r.is_active).join(' ');
                        edit.hidden = true;
                        view.hidden = false;
                        syncReminderBodyRow(r);
                    }).catch(function () {
                        alert('Не удалось сохранить напоминание, попробуйте ещё раз.');
                    });
                });

                deleteBtn.addEventListener('click', function () {
                    if (!confirm('Удалить напоминание?')) return;
                    del('/admin/crm/reminders/' + id).then(function () {
                        removeRow(popoverRow);
                        removeReminderBodyRow(id);
                    }).catch(function () {
                        alert('Не удалось удалить напоминание, попробуйте ещё раз.');
                    });
                });
            }

            reminderPopoverList.querySelectorAll('[data-reminder-row]').forEach(wireReminderRow);

            reminderAddBtn.addEventListener('click', function () {
                if (!reminderNewDate.value || !reminderNewNote.value) return;
                postJson('/admin/crm/users/' + userId + '/reminders', {
                    due_at: reminderNewDate.value,
                    note: reminderNewNote.value,
                }).then(function (data) {
                    const r = data.reminder;
                    const newRow = buildReminderPopoverRow(r);
                    reminderPopoverList.appendChild(newRow);
                    wireReminderRow(newRow);
                    syncReminderBodyRow(r);
                    reminderNewDate.value = '';
                    reminderNewNote.value = '';
                }).catch(function () {
                    alert('Не удалось добавить напоминание, попробуйте ещё раз.');
                });
            });

            // Комментарий: открытие редактирования — общий обработчик
            // [data-field-group] выше (прячет data-view целиком, неважно,
            // был там readonly-текстарий или "Заметок нет"). Здесь только
            // сохранение по blur и переключение между "есть заметка"/"нет".
            const noteGroup = row.querySelector('.crm-note-input').closest('[data-field-group]');
            const noteViewText = noteGroup.querySelector('.crm-note-view');
            const noteEmptyText = noteGroup.querySelector('.crm-note-empty');
            const noteInput = noteGroup.querySelector('.crm-note-input');
            const noteSaved = noteGroup.querySelector('.crm-note-saved');
            let noteOriginal = noteInput.value;

            noteInput.addEventListener('blur', function () {
                const view = noteGroup.querySelector('[data-view]');
                if (noteInput.value === noteOriginal) {
                    noteInput.hidden = true;
                    view.hidden = false;
                    return;
                }
                patch('/admin/crm/users/' + userId + '/note', { note: noteInput.value })
                    .then(function () {
                        noteOriginal = noteInput.value;
                        if (noteInput.value) {
                            noteViewText.value = noteInput.value;
                            noteViewText.hidden = false;
                            noteEmptyText.hidden = true;
                        } else {
                            noteViewText.hidden = true;
                            noteEmptyText.hidden = false;
                        }
                        flash(noteSaved);
                        noteInput.hidden = true;
                        view.hidden = false;
                    })
                    .catch(function () {
                        alert('Не удалось сохранить комментарий, попробуйте ещё раз.');
                    });
            });

            // Дата доступа (+ необязательная сумма): правка по клику на
            // карандашик, сохранение — явной кнопкой (не по change/blur, раз
            // полей теперь два и надо забрать оба разом).
            row.querySelectorAll('[data-course-row]').forEach(function (courseRow) {
                const courseId = courseRow.dataset.courseId;
                const group = courseRow.querySelector('[data-field-group]');
                const view = group.querySelector('[data-view]');
                const dateText = view.querySelector('span');
                const dateInput = group.querySelector('.crm-access-input');
                const amountInput = group.querySelector('.crm-payment-amount');
                const saveBtn = group.querySelector('.crm-access-save');
                const cancelBtn = group.querySelector('.crm-access-cancel');
                const saved = group.querySelector('.crm-access-saved');
                const originalDate = dateInput.value;

                function revert() {
                    dateInput.hidden = true;
                    amountInput.hidden = true;
                    saveBtn.hidden = true;
                    cancelBtn.hidden = true;
                    view.hidden = false;
                }

                cancelBtn.addEventListener('click', function () {
                    dateInput.value = originalDate;
                    amountInput.value = '';
                    revert();
                });

                saveBtn.addEventListener('click', function () {
                    if (!dateInput.value) return;
                    patch('/admin/crm/users/' + userId + '/courses/' + courseId + '/access', {
                        access_until: dateInput.value,
                        amount_rub: amountInput.value || null,
                    }).then(function (data) {
                        amountInput.value = '';
                        flash(saved);
                        revert();
                        applyCourseState(data);
                    }).catch(function () {
                        alert('Не удалось сохранить, попробуйте ещё раз.');
                    });
                });

                // Ответ любого действия со строкой курса (дата, «Напомнил»,
                // «Оплатил», отмена платежа) — CrmCourseState::response():
                // блок оплаты приходит готовым HTML, поэтому обработчики ниже
                // делегированные — на courseRow, который не перерисовывается.
                function applyCourseState(data) {
                    dateText.textContent = 'до ' + data.access_until;
                    dateText.className = data.date_class;
                    // Оплата/дата возвращают приостановленную запись в active —
                    // метка "Просрочен" больше не нужна.
                    const suspendedBadge = courseRow.querySelector('[data-course-suspended]');
                    if (suspendedBadge && !data.suspended) suspendedBadge.remove();
                    const block = courseRow.querySelector('[data-course-billing]');
                    if (block && data.billing_html) block.outerHTML = data.billing_html;

                    // Оплата/дата могли автоматически поменять статус
                    // (например, из "Новый" в "Активный ученик") — обновляем
                    // селект тем же способом, что и при ручном выборе.
                    if (data.status) {
                        rebuildSelectOptions(stageSelect, data.status.options);
                        stageOriginal = stageSelect.value;
                        applySelectColor(stageSelect, data.status.color);

                        const isClosedNow = data.status.key === 'completed' || data.status.key === 'lost';
                        if (isClosedNow !== window.CRM_ARCHIVE_VIEW) {
                            removeRow(row);
                        }
                    }
                }

                const courseUrl = '/admin/crm/users/' + userId + '/courses/' + courseId;

                courseRow.addEventListener('change', function (e) {
                    const box = e.target;
                    if (box.matches('[data-mark-reminded]')) {
                        box.disabled = true;
                        patch(courseUrl + '/reminded', { reminded: box.checked })
                            .then(applyCourseState)
                            .catch(function () {
                                box.checked = !box.checked;
                                box.disabled = false;
                                alert('Не удалось сохранить, попробуйте ещё раз.');
                            });
                    } else if (box.matches('[data-mark-paid]')) {
                        // Галочка ставится только после «ОК» в окне (тогда блок
                        // перерисуется) — пока окно открыто или его закрыли, она снята.
                        box.checked = false;
                        const block = box.closest('[data-course-billing]');
                        openPaidDialog({
                            title: courseRow.dataset.courseTitle,
                            amount: block.dataset.defaultAmount,
                            lastHint: block.dataset.lastHint,
                            interval: block.dataset.interval,
                            baseline: block.dataset.baseline,
                            url: courseUrl + '/payments',
                            onDone: applyCourseState,
                        });
                    }
                });

                courseRow.addEventListener('click', function (e) {
                    const undo = e.target.closest('[data-payment-undo]');
                    if (!undo) return;
                    if (!confirm('Удалить этот платёж и вернуть прежнюю дату?\nУведомление ученику об оплате уже ушло — его не отозвать.')) return;
                    undo.disabled = true;
                    del('/admin/crm/payments/' + undo.dataset.paymentUndo)
                        .then(applyCourseState)
                        .catch(function () {
                            undo.disabled = false;
                            alert('Не удалось отменить платёж.');
                        });
                });
            });
        });

        // ── Окно «Оплатил» ────────────────────────────────────────────
        const paidDialog = document.getElementById('crm-paid-dialog');
        const paidForm = paidDialog.querySelector('form');
        const paidAmount = paidDialog.querySelector('[name=amount_rub]');
        const paidDate = paidDialog.querySelector('[name=paid_at]');
        const paidInterval = paidDialog.querySelector('[name=billing_interval_days]');
        const paidPreview = paidDialog.querySelector('[data-paid-preview]');
        const paidHint = paidDialog.querySelector('[data-paid-hint]');
        const paidNotify = paidDialog.querySelector('[data-paid-notify]');
        const paidError = paidDialog.querySelector('[data-paid-error]');
        const paidSubmit = paidDialog.querySelector('[data-paid-submit]');
        const MONTHS = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
        const MAX_CATCH_UP_DAYS = @json(\App\Service\BillingService::MAX_CATCH_UP_DAYS);
        let paidCtx = null;

        function isoDate(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        function parseIso(v) {
            const p = v.split('-');
            return new Date(+p[0], +p[1] - 1, +p[2]);
        }
        function addDays(d, n) {
            const r = new Date(d.getTime());
            r.setDate(r.getDate() + n);
            return r;
        }

        // Повторяет BillingService::nextDueAfterPayment() — только для
        // предпросмотра, итоговую дату считает сервер.
        function updatePaidPreview() {
            paidNotify.hidden = !(parseFloat(paidAmount.value) > 0);
            if (!paidDate.value) { paidPreview.textContent = '—'; return; }
            const paid = parseIso(paidDate.value);
            let base = paidCtx.baseline ? parseIso(paidCtx.baseline) : null;
            if (!base || addDays(base, MAX_CATCH_UP_DAYS) < paid) base = paid;
            const due = addDays(base, parseInt(paidInterval.value, 10));
            paidPreview.textContent = String(due.getDate()).padStart(2, '0') + ' ' + MONTHS[due.getMonth()] + ' ' + due.getFullYear();
        }

        function openPaidDialog(ctx) {
            paidCtx = ctx;
            paidDialog.querySelector('[data-paid-title]').textContent = ctx.title;
            paidAmount.value = ctx.amount;
            paidHint.textContent = ctx.lastHint ? 'Прошлая оплата: ' + ctx.lastHint : 'Оплат ещё не было — подставлена цена курса';
            const today = new Date();
            paidDate.value = isoDate(today);
            paidDate.max = isoDate(today);
            paidDate.min = isoDate(addDays(today, -60));
            paidInterval.value = ctx.interval === '14' ? '14' : '30';
            paidError.hidden = true;
            paidSubmit.disabled = false;
            updatePaidPreview();
            paidDialog.showModal();
            paidAmount.focus();
            paidAmount.select();
        }

        [paidAmount, paidDate, paidInterval].forEach(function (el) {
            el.addEventListener('input', updatePaidPreview);
            el.addEventListener('change', updatePaidPreview);
        });
        paidDialog.querySelector('[data-paid-cancel]').addEventListener('click', function () { paidDialog.close(); });
        paidDialog.addEventListener('click', function (e) { if (e.target === paidDialog) paidDialog.close(); });

        paidForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (paidSubmit.disabled || !paidCtx) return;
            if (paidAmount.value === '' || !paidDate.value) return;
            // Блокировка от двойного нажатия; сервер дополнительно отклоняет
            // такой же платёж в течение пары минут (409).
            paidSubmit.disabled = true;
            paidError.hidden = true;
            fetch(paidCtx.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    amount_rub: paidAmount.value,
                    paid_at: paidDate.value,
                    billing_interval_days: paidInterval.value,
                }),
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (body) {
                    if (!res.ok) throw new Error(body.message || 'Не удалось сохранить, попробуйте ещё раз.');
                    return body;
                });
            }).then(function (data) {
                paidDialog.close();
                paidCtx.onDone(data);
            }).catch(function (err) {
                paidError.textContent = err.message;
                paidError.hidden = false;
                paidSubmit.disabled = false;
            });
        });
    })();
    </script>
@endpush
