<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM') — Школа Полтавского</title>
    @vite('resources/css/app.css')
    <style>
        {{-- Как и /admin/design-system: список плотный и широкий, в 2/3-колонку
             admin.layouts.main он физически не помещается — свой минимальный каркас. --}}
        body { background: #F9FAFA; }
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
    </style>
</head>
<body class="text-zinc-800">

    <div class="px-6 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm bg-zinc-900 sticky top-0 z-10">
        <a href="{{ route('main.index') }}" class="text-white font-medium sans-medium">← Админ-панель</a>
        <a href="{{ route('admin.crm.index') }}" class="{{ request()->routeIs('admin.crm.index') ? 'text-white' : 'text-zinc-400 hover:text-white' }}">CRM</a>
        <a href="{{ route('admin.crm.archive') }}" class="{{ request()->routeIs('admin.crm.archive') ? 'text-white' : 'text-zinc-400 hover:text-white' }}">Завершили / отказались</a>
        <a href="{{ route('admin.user.index') }}" class="text-zinc-400 hover:text-white ml-auto">Пользователи (полный список)</a>
    </div>

    <div class="max-w-5xl mx-auto px-4 md:px-6 py-8">
        @yield('content')
    </div>

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

        document.querySelectorAll('[data-student-row]').forEach(function (row) {
            const userId = row.dataset.userId;

            // Статус: селект со всеми состояниями сразу — при выборе сохраняет
            // и перекрашивается по ответу сервера. Сервер может вернуть НЕ тот
            // статус, что выбрали (например, выбрали "Связались", а у ученика
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
                const lastPaymentEl = courseRow.querySelector('.crm-last-payment');
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
                        dateText.textContent = 'до ' + data.access_until;
                        dateText.className = 'sans text-zinc-700';
                        if (data.payment) {
                            lastPaymentEl.textContent = 'платёж: ' + data.payment.amount_rub + ' ₽ (' + data.payment.paid_at + ')';
                        }
                        amountInput.value = '';
                        flash(saved);
                        revert();

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
                    }).catch(function () {
                        alert('Не удалось сохранить, попробуйте ещё раз.');
                    });
                });
            });
        });
    })();
    </script>
</body>
</html>
