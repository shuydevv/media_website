/*
 * Диагностика по обществознанию — страница Mini App (resources/views/diagnostic/app.blade.php).
 * ТЗ — docs/diagnostic/. Сервер — источник правды (Diagnostic\DiagnosticController,
 * DiagnosticRunner): страница рисует экран из state, шлёт ответ, показывает
 * пояснение и следующий экран из ответа сервера. Правильный ответ приходит
 * только после ответа (solution).
 *
 * Экраны: profile → [вводный экран блока] → question → feedback → … →
 * target → result. Механики вопросов — RENDER ниже (swipe, single, multi,
 * buckets, fix), у каждой: нарисовать, собрать ответ, подсветить решение.
 * Персонажи вопросов (кальмар Кеша, черепаха Душнила, фугу Пыша — config diagnostic.characters) —
 * CFG.characters; пояснение после ответа может говорить персонаж (explain_by).
 *
 * Движение — сдержанное, по одному короткому на событие: карточка вопроса
 * въезжает, верно — вспышка и рыба подпрыгивает, неверно — встряска и рыба
 * сочувствует, перед репликой — «печатает…», серия — плашка вверху, итог
 * раскрывается по частям с радаром и одним залпом конфетти. Стили — в
 * app.blade.php; с «уменьшить движение» в системе всё показывается сразу.
 */
(function () {
    'use strict';

    var CFG = window.DIAG;
    var tg = window.Telegram && window.Telegram.WebApp;
    var inTelegram = !!(tg && tg.initData);
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    var $screen = document.getElementById('screen');
    var $bar = document.getElementById('bar');
    var $top = document.getElementById('top');

    // Реплики ведущей-акулы — ЗАГЛУШКИ, согласовать с автором (правила голоса
    // маскота — config/fish_speech.php: без рода, не стыдим, коротко).
    var SAY = {
        hello: 'Привет! Я помогу понять, где ты уже крут в обществознании, а где можно добрать баллы 🦈\nЭто займёт минут 10.',
        grade: 'Для начала: ты в каком классе?',
        go: 'Поехали! Если не знаешь ответ — жми «Не знаю».',
        skip: ['Ничего страшного — разберём 👇', 'Это нормально — вот как правильно 👇'],
        ok: ['Верно! ✅', 'Точно! ✅', 'Есть! ✅'],
        bad: ['Не совсем 🙃', 'Мимо, но это поправимо 🙃', 'Почти 🙃'],
        streak: '🔥 {n} подряд!',
        target: 'Последний вопрос перед результатом: на сколько баллов хочешь сдать?',
        // Итог — по типу результата (renderResult). result_strong — слова автора.
        result_strong: 'Ты хорошо знаешь базовую теорию! Вот столько ты сможешь набрать, при условии, что умеешь решать задания второй части и знаешь критерии 👇',
        result_medium: 'Хорошая база! Есть пара точек роста — и ты на {potential}+ 💪',
        result_weak: 'Есть над чем поработать — и это быстро закрывается 💪',
        improve_weak: 'Хочешь подтянуть {blocks}?',
        improve_strong: 'Хочешь улучшить свой балл?',
        improveYes: 'Отлично! Возвращаю тебя в чат — там тебе напишут 🙂',
        improveNo: 'Окей! Результат сохранился. Захочешь — пиши в чат бота.',
        open: 'Открой диагностику через бота в Telegram — по кнопке «Пройти диагностику».',
        authFail: 'Не получилось войти. Закрой это окно и открой диагностику из бота ещё раз.',
        error: 'Что-то пошло не так. Проверь интернет и попробуй ещё раз.',
        level: 'А какой у тебя сейчас уровень?',
        // Начальный тест (ТЗ, 5.8): провалиться нельзя — после каждого вопроса
        // разбор, ошибка — «новое для тебя», без красного и баллов.
        basic_go: 'Тут нельзя ошибиться по-настоящему 🙂 После каждого вопроса — короткий разбор. Не знаешь — жми «Не знаю — покажи».',
        basic_ok: '✅ Ты это знаешь!',
        basic_new: '🌱 Новое для тебя',
        basic_result: 'Ты знаешь больше, чем думал 🙂',
        basic_result_low: 'Отличное начало — теперь видно, с чего стартовать 🙂',
        basic_upsell: 'Похоже, ты знаешь больше, чем начальный уровень 😏 Хочешь узнать свой балл ЕГЭ? Пройди тест посложнее.',
        improve_basic: 'Хочешь, подскажу, с чего начать?',
    };

    // Статусы тем на карте начального теста — по уровню блока (0–3).
    var BASIC_MAP = [
        // Тест проверяет только базовые понятия — «знаешь право» не говорим.
        ['🗺', 'начнём знакомство'], ['🗺', 'начнём знакомство'], ['🌱', 'часть базы знакома'], ['✅', 'базовые понятия знаешь'],
    ];

    var state = null;         // последний state от сервера
    var token = null;         // токен прохождения (X-Diag-Attempt)
    var shownBlock = -1;      // для какого блока уже показан вводный экран
    var ui = {};              // текущий вопрос: guess, ответ, время показа
    var pendingExplainMs = null;
    var shownProgress = -1;   // какой блок уже залит в прогрессе — чтобы новый заливался плавно
    var celebrated = false;   // конфетти — один раз на итог
    var autoTimer = null;     // автопереход: с заставки блока и после верного ответа
    var autoSkip = null;      // тап по экрану — сразу дальше

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- утилиты ----------

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') node.className = attrs[k];
                else if (k === 'text') node.textContent = attrs[k];
                else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2), attrs[k]);
                else if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) node.setAttribute(k, attrs[k]);
            });
        }
        (children || []).forEach(function (c) {
            if (c !== null && c !== undefined) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return node;
    }

    function basic() { return !!state && state.track === 'basic'; }

    // В начальном тесте никто не грустит: вместо «ошибся» — обычное лицо.
    function sadMood() { return basic() ? 'idle' : 'sad'; }

    function pick(list) { return list[Math.floor(Math.random() * list.length)]; }

    function plural(n, one, few, many) {
        var m10 = n % 10, m100 = n % 100;
        if (m10 === 1 && m100 !== 11) return one;
        if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
        return many;
    }

    function haptic(kind) {
        // Вне Telegram и в клиентах старше 6.1 вибрации нет — и предупреждений в консоль тоже.
        if (!inTelegram || !tg.HapticFeedback || !tg.isVersionAtLeast || !tg.isVersionAtLeast('6.1')) return;
        try {
            if (kind === 'ok' || kind === 'bad') tg.HapticFeedback.notificationOccurred(kind === 'ok' ? 'success' : 'error');
            else tg.HapticFeedback.selectionChanged();
        } catch (e) { /* старый клиент Telegram */ }
    }

    function mascot(mood, cls) {
        return el('img', { src: (CFG.mascots && CFG.mascots[mood]) || CFG.mascot, alt: '', class: cls || null });
    }

    function character(id) {
        return (id && CFG.characters && CFG.characters[id]) || null;
    }

    function nameOf(id, fallback) {
        var c = character(id);
        return c ? c.name : (fallback || '');
    }

    /**
     * Аватар персонажа: арт по эмоции (idle — обычный, happy — радуется,
     * sad — ошибся), если есть; иначе эмодзи (или буква) на кружке его цвета.
     */
    function avatar(id, fallbackName, mood) {
        var c = character(id);
        if (c && c.avatars) return el('img', { class: 'avatar', src: c.avatars[mood] || c.avatars.idle, alt: '', 'data-who': id });
        var name = c ? c.name : (fallbackName || '?');
        // Без кружка: эмодзи (пока нет арта) — сам по себе; буква — на кружке его цвета.
        if (c && c.emoji) return el('span', { class: 'avatar emoji', text: c.emoji });
        return el('span', { class: 'avatar', style: c && c.color ? 'background:' + c.color : null, text: name.charAt(0) });
    }

    function say(text, tone, mood) {
        return el('div', { class: 'say' }, [
            mascot(mood || 'idle'),
            el('div', { class: 'bubble' + (tone ? ' ' + tone : ''), text: text }),
        ]);
    }

    /**
     * Реплика маскота после короткого «печатает…» — как в чате. Узел
     * возвращается сразу (место в ленте занято), текст появляется через
     * delay мс; extra — что дописать в пузырь (мем).
     */
    function sayTyping(text, tone, mood, react, extra, who) {
        var bubble = el('div', { class: 'bubble' }, [el('span', { class: 'typing' }, [el('i'), el('i'), el('i')])]);
        var img = who ? avatar(who, null, mood) : mascot(mood || 'idle');
        var node = el('div', { class: 'say' }, [img, bubble]);
        var reveal = function () {
            // Успели уйти на следующий экран за время «печатает…» — ни текста,
            // ни прокрутки: иначе новый вопрос открылся бы прокрученным вниз.
            if (!node.isConnected) return;
            bubble.className = 'bubble' + (tone ? ' ' + tone : '');
            bubble.textContent = text;
            if (extra) bubble.appendChild(extra);
            if (react) img.classList.add(react);
            // Экран плавно уезжает в самый низ — к реплике.
            window.scrollTo({ top: document.documentElement.scrollHeight, behavior: reducedMotion ? 'auto' : 'smooth' });
        };
        if (reducedMotion) reveal(); else setTimeout(reveal, 450);
        return node;
    }

    /**
     * Через ms (плюс delay — пока «печатает…») сам вызвать go; тап по экрану —
     * сразу. Полоска .countdown в нижней панели бежит синхронно.
     */
    function autoAdvance(go, ms, delay) {
        cancelAuto();
        var once = function () { cancelAuto(); go(); };
        autoSkip = once;
        $screen.addEventListener('click', once);
        autoTimer = setTimeout(once, ms + delay);
    }

    function cancelAuto() {
        clearTimeout(autoTimer);
        if (autoSkip) { $screen.removeEventListener('click', autoSkip); autoSkip = null; }
    }

    function countdownBar(ms, delay) {
        return el('div', { class: 'countdown' }, [el('i', { style: 'animation-duration:' + ms + 'ms;animation-delay:' + delay + 'ms' })]);
    }

    function toast(text) {
        var t = el('div', { class: 'toast', text: text });
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 1900);
    }

    /** Повторно проиграть CSS-анимацию на узле (класс уже мог стоять). */
    function play(node, cls) {
        node.classList.remove(cls);
        void node.offsetWidth;
        node.classList.add(cls);
    }

    function button(text, onclick, cls) {
        return el('button', { class: 'btn' + (cls ? ' ' + cls : ''), type: 'button', text: text, onclick: onclick });
    }

    /** enter — как появляется экран: 'slide' (вопрос), 'rise' (по очереди снизу) или ничего. */
    function show(nodes, barNodes, enter) {
        // Новый экран отменяет отложенный автопереход (например, «Заново» во время отсчёта).
        cancelAuto();
        $screen.innerHTML = '';
        var i = 0;
        nodes.forEach(function (n) {
            if (!n) return;
            if (enter === 'slide') n.classList.add('enter');
            if (enter === 'rise') { n.classList.add('rise'); n.style.animationDelay = (i++ * 0.12) + 's'; }
            if (enter) {
                n.addEventListener('animationend', function done(ev) {
                    if (ev.target !== n) return;
                    n.classList.remove('enter', 'rise');
                    n.style.animationDelay = '';
                    n.removeEventListener('animationend', done);
                });
            }
            $screen.appendChild(n);
        });
        $bar.innerHTML = '';
        (barNodes || []).forEach(function (n) { if (n) $bar.appendChild(n); });
        window.scrollTo(0, 0);
    }

    function api(url, body) {
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };
        if (token) headers['X-Diag-Attempt'] = token;
        var where = url.replace(/^.*\/api\//, '');
        return fetch(url, { method: 'POST', headers: headers, body: JSON.stringify(body || {}), credentials: 'same-origin' })
            .then(function (r) {
                // 409 — устаревший ответ, 403 — открыли не из Telegram: это не поломки.
                if (r.status >= 500 || r.status === 419 || r.status === 422 || r.status === 404) report('api', where + ' → ' + r.status);
                return r.json().catch(function () { return {}; }).then(function (data) { return { status: r.status, data: data }; });
            }, function (e) {
                report('network', where + ': ' + (e && e.message ? e.message : 'нет сети'));
                throw e;
            });
    }

    function report(kind, message, extra) {
        if (window.diagReport) window.diagReport(kind, message, extra);
    }

    function fail(text) {
        $top.hidden = true;
        show([say(text || SAY.error)], [button('Попробовать ещё раз', function () { location.reload(); })]);
    }

    // ---------- прогресс ----------

    function renderProgress(s) {
        var p = s.progress;
        var inTest = s.screen === 'question';
        $top.hidden = !inTest;
        if (!inTest) return;
        var seg = document.getElementById('segments');
        // Позиция — блок + доля отвеченных в нём (из 4 возможных): линия идёт с
        // каждым ответом, ракушка — одна, на конце заливки.
        var at = p.index + Math.min(1, (p.answered || 0) / (p.max || 4));
        var paint = function (pos) {
            [].forEach.call(seg.children, function (s, i) {
                var fill = Math.max(0, Math.min(1, pos - i));
                s.className = fill >= 1 ? 'done' : '';
                s.style.setProperty('--fill', (fill * 100) + '%');
                var shell = s.querySelector('b');
                if (shell) shell.remove();
                if (i === Math.min(p.total - 1, Math.floor(pos))) s.appendChild(el('b', { text: '🐚' }));
            });
        };
        // Сначала — как было, следующим кадром — как стало: заливка едет.
        var from = shownProgress < 0 ? at : shownProgress;
        seg.innerHTML = '';
        for (var i = 0; i < p.total; i++) seg.appendChild(el('i'));
        paint(from);
        if (from !== at) requestAnimationFrame(function () { requestAnimationFrame(function () { paint(at); }); });
        shownProgress = at;
        var block = p.blocks[p.index];
        document.getElementById('top-label').textContent = (basic() ? 'Тема ' : 'Блок ') + (p.index + 1) + ' из ' + p.total + (block ? ' · ' + block.title : '');
    }

    // ---------- экраны ----------

    function render(s) {
        state = s;
        token = s.attempt || token;
        CFG.attempt = token;
        if (s.track) document.documentElement.setAttribute('data-track', s.track);
        else document.documentElement.removeAttribute('data-track');
        renderProgress(s);
        if (s.screen === 'profile') return renderProfile(s);
        if (s.screen === 'level') return renderLevel(s);
        if (s.screen === 'question') {
            if (shownBlock !== s.progress.index) return renderBlockIntro(s);
            return renderQuestion(s.question);
        }
        if (s.screen === 'target') return renderTarget(s);
        if (s.screen === 'result') return renderResult(s);
    }

    function renderProfile(s) {
        var grades = el('div', { class: 'grades' });
        Object.keys(s.grades).forEach(function (key) {
            grades.appendChild(button(s.grades[key], function () {
                haptic();
                // Один ответ: на медленной сети второй тап не шлёт второй запрос.
                grades.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                api(CFG.urls.profile, { grade: key }).then(function (r) {
                    if (r.status !== 200) return fail();
                    render(r.data);
                }, function () { fail(); });
            }, 'choice'));
        });
        // Варианты — сразу под вопросом акулы, как ответные кнопки в чате.
        grades.classList.add('answers');
        show([say(SAY.hello, null, 'happy'), say(SAY.grade, null, 'think'), grades], [], 'rise');
    }

    // «Какой у тебя сейчас уровень?» — подсказка на кнопке: люди плохо оценивают себя.
    function renderLevel(s) {
        var list = el('div', { class: 'levels' });
        Object.keys(s.tracks).forEach(function (key) {
            var t = s.tracks[key];
            list.appendChild(el('div', {}, [el('button', { class: 'btn choice level-btn', type: 'button', text: t.title, onclick: function () {
                haptic();
                list.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                api(CFG.urls.level, { track: key }).then(function (r) {
                    if (r.status !== 200) return fail();
                    render(r.data);
                }, function () { fail(); });
            } }), el('small', { text: t.hint })]));
        });
        show([say(SAY.level, null, 'think'), list], [], 'rise');
    }

    function renderBlockIntro(s) {
        var p = s.progress;
        var block = p.blocks[p.index];
        var first = p.index === 0;
        // Без лишнего клика: заставка держится немного и сама переходит к
        // заданию; на первом блоке дольше — там совет про «Не знаю». Тап по
        // экрану — сразу дальше. Полоска внизу показывает, сколько осталось.
        var ms = first ? 3500 : 1800;
        var go = function () {
            shownBlock = p.index;
            renderQuestion(s.question);   // show() внутри снимет таймер и тап
        };
        show([
            el('div', { class: 'center' }, [
                // Маленький праздник: прошлый блок позади.
                p.index > 0 ? el('div', { class: 'done-chip', text: '✓ «' + p.blocks[p.index - 1].title + '» пройден' }) : null,
                mascot(p.index > 0 ? 'happy' : 'think', 'bob'),
                el('div', { class: 'muted', text: (basic() ? 'Тема ' : 'Блок ') + (p.index + 1) + ' из ' + p.total }),
                el('div', { class: 'big', text: block.title }),
            ]),
            first ? say(basic() ? SAY.basic_go : SAY.go) : null,
        ], [countdownBar(ms, 0)], 'rise');
        autoAdvance(go, ms, 0);
    }

    function renderQuestion(q) {
        ui = { q: q, guess: false, shownAt: Date.now(), locked: false };
        var mech = RENDER[q.mechanic];
        // Без карточки-контейнера: вопрос один на экране, лишняя рамка только
        // отнимает ширину. Плашки — у того, на что нажимают.
        var card = el('div', { class: 'q' });
        if (q.character) {
            card.appendChild(el('div', { class: 'character' }, [el('span', { class: 'avatar', text: q.character.charAt(0) }), q.character]));
        }
        if (q.skin === 'debate' && q.from) {
            card.appendChild(el('div', { class: 'speaker' }, [avatar(q.from), el('div', { class: 'bubble' }, [el('b', { text: nameOf(q.from) + ': ' }), q.prompt])]));
        } else {
            // На каждом вопросе — кто-то из наших: персонаж уже есть в «Кто прав?»,
            // чате, посте и ответе на ЕГЭ; в остальных — персонаж вопроса (from)
            // или акула рядом с вопросом.
            var hasFace = q.skin === 'who' || q.skin === 'chat' || q.skin === 'fragments' || (q.mechanic === 'fix' && q.from);
            var face = q.from ? avatar(q.from) : mascot('think', 'host');
            // «Найди лишнее» — прямо в формулировке: отдельной плашкой его не замечали.
            var text = q.mechanic === 'swipe' ? (hasFace ? null : 'Правда или нет?')
                : q.skin === 'odd' ? q.prompt.replace(/[.:]?\s*$/, '. Найди лишнее') : q.prompt;
            if (text !== null) card.appendChild(hasFace ? el('p', { class: 'prompt', text: text })
                : el('div', { class: 'q-host' }, [face, el('p', { class: 'prompt', text: text })]));
        }
        if (q.task) card.appendChild(el('p', { class: 'task', text: 'Задание: ' + q.task }));
        ui.card = card;
        ui.api = mech.build(q, card);

        // «Не знаю» — пропуск: сразу на сервер, засчитывается как ноль баллов.
        var guess = el('button', { class: 'chip-guess', type: 'button', text: basic() ? '🤔 Не знаю — покажи' : '🤷 Не знаю', onclick: function () {
            if (ui.locked) return;
            ui.guess = true;
            haptic();
            submit(true);
        } });
        ui.submit = q.mechanic === 'swipe' ? null : button('Ответить', function () { submit(false); });
        if (ui.submit) ui.submit.disabled = true;
        show([card], [guess, ui.submit], 'slide');
        if (ui.api.ready) ui.api.ready();
    }

    function refreshSubmit() {
        if (ui.submit) ui.submit.disabled = !ui.api.complete();
    }

    function submit(skip) {
        // Пропуск — только явное submit(true) от «Не знаю»: обработчик клика
        // передаёт событие, и оно не должно превращать ответ в пропуск.
        skip = skip === true;
        if (ui.locked || (!skip && ui.submit && !ui.api.complete())) return;
        ui.locked = true;
        if (ui.submit) ui.submit.disabled = true;
        var payload = {
            question_id: ui.q.id,
            question_hash: ui.q.hash || null,
            answer: skip ? { skip: true } : ui.api.value(),
            guess: ui.guess,
            answer_ms: Date.now() - ui.shownAt,
            explain_ms: pendingExplainMs,
        };
        api(CFG.urls.answer, payload).then(function (r) {
            if (r.status === 409 && r.data.state) { shownBlock = r.data.state.progress.index; return render(r.data.state); }
            if (r.status !== 200) { ui.locked = false; refreshSubmit(); return fail(); }
            pendingExplainMs = null;
            renderFeedback(r.data);
        }, function () { ui.locked = false; refreshSubmit(); fail(); });
    }

    function renderFeedback(res) {
        var soft = basic();
        haptic(res.correct ? 'ok' : (res.skipped || soft ? null : 'bad'));
        // «Не знаю» — задание само становится решённым (верное положение, зелёным):
        // не ругаем, показываем, как надо.
        ui.api.reveal(res.solution, !!res.skipped);
        // Верно — вспышка у свайп-карточки или у верных плашек; неверно — встряска вопроса.
        if (res.correct) {
            var lit = ui.card.querySelector('.swipe-card') ? [ui.card.querySelector('.swipe-card')] : [].slice.call(ui.card.querySelectorAll('.ok'));
            lit.forEach(function (n) { play(n, 'flash-ok'); });
        } else if (!res.skipped && !soft) {
            play(ui.card, 'shake');
        }

        // Мем — картинка из банка (поле meme: путь от корня сайта или https-адрес).
        var meme = typeof res.meme === 'string' && /^(https:\/\/|\/)/.test(res.meme) ? el('img', { class: 'meme', src: res.meme, alt: '' }) : null;
        // Говорит персонаж (тот, кто прав в «Кто прав?», или explain_by) — иначе рыба.
        // Персонаж вопроса (from) до ответа — обычный, после — по тому, прав ли он сам:
        // утверждение в чате верно, в споре он прав; в посте и ответе на ЕГЭ — ошибки.
        var q = ui.q;
        var fromRight = q.from ? (q.mechanic === 'swipe' ? res.solution.value === true : q.skin === 'debate') : null;
        if (q.from) [].forEach.call(ui.card.querySelectorAll('img.avatar[data-who="' + q.from + '"]'), function (img) {
            var c = character(q.from);
            if (c && c.avatars) img.src = c.avatars[fromRight ? 'happy' : sadMood()];
        });
        // Объясняющий: правый в «Кто прав?» и персонаж вопроса — по своей правоте; другой — обычный.
        var who = character(res.explain_by) ? res.explain_by : null;
        var whoMood = q.skin === 'who' ? 'happy' : (who === q.from ? (fromRight ? 'happy' : sadMood()) : 'idle');
        var mood = who ? whoMood : (res.correct ? 'happy' : (soft ? 'think' : 'sad'));
        var text = soft
            ? (res.correct ? SAY.basic_ok : SAY.basic_new) + '\n' + (who ? nameOf(who) + ': ' : '') + res.explain
            : who ? (res.correct ? '✅ ' : '❌ ') + nameOf(who) + ': ' + res.explain : (res.correct ? pick(SAY.ok) : pick(res.skipped ? SAY.skip : SAY.bad)) + '\n' + res.explain;
        // Мини-урок: «📌 Запомни» из банка (поле remember).
        if (res.remember) text += '\n\n📌 Запомни: ' + res.remember;
        $screen.appendChild(sayTyping(
            text,
            res.correct ? 'ok' : (res.skipped ? null : 'bad'),
            mood,
            res.correct ? 'hop' : 'tilt',
            meme,
            who
        ));
        if (res.correct && res.streak >= (soft ? 2 : 3)) toast(SAY.streak.replace('{n}', res.streak));

        var shownAt = Date.now();
        var next = function () {
            pendingExplainMs = Date.now() - shownAt;
            render(res.state);
        };
        $bar.innerHTML = '';
        if (res.correct && !soft) {
            // Верно — без лишнего клика: пара секунд прочитать (по длине текста)
            // и сам переход; отсчёт — после «печатает…». Тап — сразу дальше.
            var ms = Math.min(5000, Math.max(2500, 1200 + text.length * 35));
            var typing = reducedMotion ? 0 : 450;
            $bar.appendChild(countdownBar(ms, typing));
            autoAdvance(next, ms, typing);
        } else {
            // Неверно (а в начальном тесте — всегда) — разбор читают сколько нужно, дальше — кнопкой.
            $bar.appendChild(button('Дальше', next));
        }
    }

    function renderTarget(s) {
        var value = s.default_target || 80;
        var out = el('div', { class: 'target-value', text: String(value) });
        var range = el('input', { type: 'range', min: '40', max: '100', step: '5', value: String(value) });
        range.addEventListener('input', function () { value = +range.value; out.textContent = String(value); haptic(); });
        var send = function (target) {
            $bar.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
            api(CFG.urls.target, { target: target }).then(function (r) {
                if (r.status !== 200) return fail();
                render(r.data);
            }, function () { fail(); });
        };
        show([say(SAY.target, null, 'think'), el('div', { class: 'card' }, [out, range])], [
            button('Пока не знаю', function () { send(null); }, 'ghost'),
            button('Показать результат', function () { send(value); }),
        ], 'rise');
    }

    /**
     * Итог — под тип результата (ТЗ, раздел 7):
     *   сильный (нет блоков с прибавкой) — «хорошо знаешь базовую теорию», балл —
     *     при условии, что умеешь вторую часть; кнопка «Хочу разобраться с этим»;
     *   средний / слабый — вывод акулы, точки роста с «+N баллов», вопрос «Хочешь
     *     подтянуть право и экономику?».
     * Сверху вниз: вывод → балл и шкала (сейчас · цель · потенциал) → сильные
     * стороны чипами → точки роста → профиль по блокам (свёрнут) → вопрос.
     * Без карточек: блоки разделены отступами и заголовками.
     */
    function renderResult(s, quiet) {
        if (s.track === 'basic') return renderBasicResult(s, quiet);
        var r = s.result;
        var strong = r.weak.length === 0;
        var mid = Math.round((r.low + r.high) / 2);
        var kind = strong ? 'strong' : (mid < 50 ? 'weak' : 'medium');
        var headline = SAY['result_' + kind].replace('{potential}', r.potential);
        var nodes = [say(headline, null, kind === 'weak' ? 'think' : 'happy')];

        // Цель — над баллом: сразу видно, сколько до неё.
        var score = el('div', { class: 'score', text: '~' + r.low + '–' + r.high });
        nodes.push(el('div', { class: 'hero' }, [
            r.target === null ? null : el('div', { class: 'goal', text: '🎯 Твоя цель — ' + r.target }),
            score,
            el('div', { class: 'muted', text: 'тестовых баллов ЕГЭ' + (strong ? '' : ' — сейчас') }),
            r.target === null ? null : el('p', { class: 'hero-note', text: r.near_target
                ? 'Ты рядом со своей целью 💪'
                : 'До цели не хватает ~' + r.to_target + ' ' + plural(r.to_target, 'балла', 'баллов', 'баллов') }),
            scale(r, strong ? 'ты' : 'сейчас'),
        ]));

        // Профиль по блокам — сразу под баллом: смотрится эффектно. Оси — в порядке блоков теста.
        var order = s.progress.blocks.map(function (b) { return b.key; });
        var axes = r.gains.slice().sort(function (a, b) { return order.indexOf(a.key) - order.indexOf(b.key); });
        nodes.push(section('Профиль по блокам', [radar(axes)]));

        // Сильные блоки видно на профиле — отдельные плашки не нужны.
        // Точки роста: блоки с «+N баллов»; дошёл до сложного, но с ошибками
        // (точность ниже 80%) — «стоит повторить»; у сильного — ещё вторая часть.
        var gains = r.gains.filter(function (g) { return g.gain > 0; });
        var review = r.gains.filter(function (g) { return g.gain <= 0 && g.accuracy !== undefined && g.accuracy < 0.8; });
        var max = gains.reduce(function (m, g) { return Math.max(m, g.gain); }, 1);
        var growth = gains.map(function (g) {
            return el('div', { class: 'gain' }, [
                el('span', { text: g.title }),
                el('b', { text: '+' + g.gain }),
                el('div', { class: 'track' }, [el('i', { style: 'width:0', 'data-width': Math.round(g.gain / max * 100) + '%' })]),
            ]);
        });
        review.forEach(function (g) {
            growth.push(el('div', { class: 'gain review' }, [
                el('span', { text: g.title }),
                el('small', { text: 'были ошибки — стоит повторить' }),
            ]));
        });
        growth.push(el('div', { class: strong ? 'gain-part2' : 'muted small', text: strong
            ? 'Вторая часть ЕГЭ и критерии — тест их не проверяет, а это около половины баллов.'
            : 'И вторая часть ЕГЭ — её тест не проверяет, а это около половины баллов.' }));
        nodes.push(section(strong ? 'Что проверить дальше' : 'Точки роста', growth));


        var bar = [];
        if (s.improve) {
            nodes.push(say(s.improve === 'yes' ? SAY.improveYes : SAY.improveNo));
            bar.push(button('↺ Пройти ещё раз', function () { restart(false); }, 'ghost'));
        } else {
            // Вопрос и ответы — в конце страницы, под репликой: прочитал итог —
            // видишь, о чём спрашивают. Нижняя панель пустая.
            nodes.push(say(strong ? SAY.improve_strong : SAY.improve_weak.replace('{blocks}', joinAnd(r.weak_acc || r.weak)), null, 'think'));
            nodes.push(el('div', { class: 'grades answers improve-answers' }, [
                button('Пока нет', function () { improve(false); }, 'ghost'),
                button('Да, хочу', function () { improve(true); }),
            ]));
            // Пройти заново можно и не отвечая на вопрос — итог этого раза сохранится.
            nodes.push(el('div', { class: 'center-link' }, [el('button', { class: 'link', type: 'button', text: '↺ Пройти заново', onclick: function () { restart(false); } })]));
        }
        show(nodes, bar, quiet ? null : 'rise');
        // Полоски заливаются, когда их блок уже появился.
        setTimeout(function () {
            [].forEach.call($screen.querySelectorAll('.track i, .scale .band'), function (i) { i.style.width = i.getAttribute('data-width'); });
        }, quiet || reducedMotion ? 0 : 600);
        if (quiet) return;
        countUp(score, r.low, r.high);
        if (!celebrated) { celebrated = true; setTimeout(confetti, 700); }
    }

    /**
     * Итог начального теста (ТЗ, 5.8): без баллов. Сверху вниз: вывод акулы и
     * «верно N из M» → значки похвалы → карта-острова (тап — что узнал в
     * теме) → реплики персонажей → путь до ЕГЭ → (сильному — тест посложнее)
     * → «Хочешь, подскажу, с чего начать?».
     */
    function renderBasicResult(s, quiet) {
        var r = s.result;
        var nodes = [say(r.correct >= r.total / 2 ? SAY.basic_result : SAY.basic_result_low, null, 'happy')];
        nodes.push(el('div', { class: 'hero' }, [
            el('div', { class: 'score', text: r.correct + ' из ' + r.total }),
            el('div', { class: 'muted', text: 'верных ответов' }),
        ]));
        nodes.push(el('div', { class: 'badges' }, r.badges.map(function (b) {
            return el('div', { class: 'badge' }, [el('span', { class: 'badge-icon', text: b.icon }), el('span', { text: b.text })]);
        })));
        nodes.push(section('Карта предмета', [islands(r.map)]));
        nodes.push(section('Что говорит команда', r.voices.map(function (v) {
            return el('div', { class: 'say voice' }, [avatar(v.who, null, 'happy'), el('div', { class: 'bubble' }, [el('b', { text: nameOf(v.who) + ': ' }), v.text])]);
        })));
        nodes.push(section('Путь до ЕГЭ', [el('p', { text: r.horizon }), route(r)]));

        var bar = [];
        if (r.upsell) {
            nodes.push(say(SAY.basic_upsell, null, 'happy'));
            nodes.push(el('div', { class: 'center-link' }, [el('button', { class: 'link', type: 'button', text: '📚 Пройти тест посложнее', onclick: function () { restart(false, 'middle'); } })]));
        }
        if (s.improve) {
            nodes.push(say(s.improve === 'yes' ? SAY.improveYes : SAY.improveNo));
            bar.push(button('↺ Пройти ещё раз', function () { restart(false); }, 'ghost'));
        } else {
            nodes.push(say(SAY.improve_basic, null, 'think'));
            nodes.push(el('div', { class: 'grades answers improve-answers' }, [
                button('Пока нет', function () { improve(false); }, 'ghost'),
                button('Да, хочу', function () { improve(true); }),
            ]));
            nodes.push(el('div', { class: 'center-link' }, [el('button', { class: 'link', type: 'button', text: '↺ Пройти заново', onclick: function () { restart(false); } })]));
        }
        show(nodes, bar, quiet ? null : 'rise');
        if (!quiet && !celebrated) { celebrated = true; setTimeout(confetti, 700); }
    }

    // Острова в море — темы. Цвет и туман — по числу верных из 3; точки ●●○ —
    // сами ответы. Тап — карточка ниже: «📌 Запомни» вопросов этой темы.
    var ISLE_AT = [[4, 4], [54, 0], [28, 34], [2, 66], [56, 62]];
    var ISLE_ART = '<svg viewBox="0 0 120 64" aria-hidden="true"><ellipse class="shoal" cx="60" cy="50" rx="56" ry="12"/>'
        + '<path class="land" d="M14 50 C20 28 44 16 62 18 C82 20 100 32 106 50 Z"/>'
        + '<path class="palm" d="M70 22 C70 14 72 8 76 4 M76 4 C68 2 62 6 60 10 M76 4 C84 0 90 4 92 8"/></svg>';

    function islands(map) {
        var info = el('div', { class: 'isle-info' });
        var sea = el('div', { class: 'sea' });
        var buttons = [];
        var select = function (i) {
            var b = map[i];
            var st = BASIC_MAP[b.level] || BASIC_MAP[0];
            buttons.forEach(function (n, j) { n.classList.toggle('sel', j === i); });
            info.innerHTML = '';
            info.appendChild(el('h4', { text: st[0] + ' ' + b.title + ' — ' + st[1] }));
            info.appendChild(el('p', { class: 'muted small', text: 'Что ты узнал в этой теме:' }));
            b.facts.forEach(function (f) {
                info.appendChild(el('div', { class: 'fact' }, [el('span', { text: f.correct ? '✅' : '📌' }), el('span', { text: f.text })]));
            });
        };
        map.forEach(function (b, i) {
            var dots = '';
            for (var k = 0; k < (b.asked || 3); k++) dots += k < b.level ? '●' : '○';
            var art = el('span', { class: 'isle-art' });
            art.innerHTML = ISLE_ART;
            var at = ISLE_AT[i] || [0, 0];
            var n = el('button', { class: 'isle lvl-' + b.level, type: 'button', style: 'left:' + at[0] + '%;top:' + at[1] + '%', onclick: function () { haptic(); select(i); } }, [
                art, el('span', { class: 'isle-name', text: b.title }), el('span', { class: 'isle-dots', text: dots }),
            ]);
            buttons.push(n);
            sea.appendChild(n);
        });
        select(0);
        return el('div', {}, [sea, info]);
    }

    // Путь до ЕГЭ: кораблик «сейчас» → вехи → флаг «ЕГЭ через ~N месяцев».
    function route(r) {
        var stops = [['🚤', r.steps[0]]].concat(r.steps.slice(1, -1).map(function (t) { return ['⛵', t]; }));
        stops.push(['🏁', r.steps[r.steps.length - 1] + (r.months ? ' · ЕГЭ через ~' + r.months + ' ' + plural(r.months, 'месяц', 'месяца', 'месяцев') : '')]);
        return el('div', { class: 'route' }, stops.map(function (st) {
            return el('div', { class: 'stop' }, [el('span', { class: 'stop-icon', text: st[0] }), el('span', { text: st[1] })]);
        }));
    }

    function section(title, children) {
        return el('div', { class: 'res-sec' }, [el('h3', { text: title })].concat(children));
    }

    function joinAnd(list) {
        return list.length > 1 ? list.slice(0, -1).join(', ') + ' и ' + list[list.length - 1] : (list[0] || '');
    }

    /**
     * Шкала 0–100: полоса — диапазон «сейчас», чёрточка — цель, кружок —
     * потенциал (если он выше). Подписи, что оказались рядом, — во второй строке.
     */
    function scale(r, nowLabel) {
        var pos = function (v) { return Math.max(0, Math.min(100, v)) + '%'; };
        var rows = [];   // в каждой строке — где уже стоят подписи
        var bar = el('div', { class: 'scale' }, [
            el('i', { class: 'band', style: 'left:' + pos(r.low) + ';width:0', 'data-width': (r.high - r.low) + '%' }),
        ]);
        // Подпись — в первую строку, где рядом ничего нет («потенциал 66+» шириной около
        // четверти шкалы); три подписи рядом — в три строки.
        var label = function (at, text, cls) {
            var row = 0;
            while (rows[row] && rows[row].some(function (m) { return Math.abs(m - at) < 24; })) row++;
            (rows[row] = rows[row] || []).push(at);
            bar.appendChild(el('span', { class: 'lbl' + (cls ? ' ' + cls : ''), style: 'left:' + pos(Math.max(6, Math.min(94, at))) + ';top:' + (20 + row * 16) + 'px', text: text }));
            bar.style.marginBottom = (44 + (rows.length - 1) * 16) + 'px';
        };
        label((r.low + r.high) / 2, nowLabel);
        if (r.target !== null) {
            bar.appendChild(el('i', { class: 'tick', style: 'left:' + pos(r.target) }));
            label(r.target, 'цель ' + r.target);
        }
        if (r.weak.length && r.potential > r.high) {
            bar.appendChild(el('i', { class: 'dot', style: 'left:' + pos(r.potential) }));
            label(r.potential, 'потенциал ' + r.potential + '+', 'pot');
        }
        return bar;
    }

    /**
     * Радар по блокам: значение — точность блока с весом по сложности
     * (BlockRun::accuracy): ошибки видны, даже если дошёл до сложного уровня.
     * У вершин — точки, у подписей — проценты; слабые блоки — цветом ошибки.
     * Длинные подписи — в две строки.
     */
    function radar(gains) {
        var NS = 'http://www.w3.org/2000/svg';
        var svg = function (tag, attrs) {
            var n = document.createElementNS(NS, tag);
            Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
            return n;
        };
        var value = function (g) { return Math.max(0.06, Math.min(1, g.accuracy !== undefined ? g.accuracy : [0.1, 0.35, 0.65, 0.9][g.level])); };
        var cx = 170, cy = 134, R = 80, n = gains.length;
        var point = function (i, k) {
            var a = -Math.PI / 2 + i * 2 * Math.PI / n;
            return [cx + Math.cos(a) * R * k, cy + Math.sin(a) * R * k];
        };
        var root = svg('svg', { viewBox: '0 0 340 246', class: 'radar', role: 'img', 'aria-label': 'Профиль по блокам' });
        [0.33, 0.66, 1].forEach(function (k) {
            root.appendChild(svg('polygon', { class: 'grid', points: gains.map(function (g, i) { return point(i, k).join(','); }).join(' ') }));
        });
        gains.forEach(function (g, i) {
            var end = point(i, 1);
            root.appendChild(svg('line', { class: 'grid', x1: cx, y1: cy, x2: end[0], y2: end[1] }));
            var at = point(i, 1.2);
            var anchor = Math.abs(at[0] - cx) < 8 ? 'middle' : (at[0] > cx ? 'start' : 'end');
            var label = svg('text', { x: at[0], y: at[1], 'text-anchor': anchor, 'dominant-baseline': 'middle', class: g.gain > 0 ? 'weak' : null });
            var words = g.title.split(' ');
            var lines = g.title.length > 12 && words.length > 1
                ? [words.slice(0, Math.ceil(words.length / 2)).join(' '), words.slice(Math.ceil(words.length / 2)).join(' ')]
                : [g.title];
            lines.push(Math.round(value(g) * 100) + '%');
            // Подпись над верхней вершиной целиком выше неё (иначе процент ложится на точку),
            // под нижней — ниже, сбоку — по центру.
            var first = anchor !== 'middle' ? -(lines.length - 1) * 0.6 : (at[1] < cy ? -(lines.length - 1) * 1.2 : 0);
            lines.forEach(function (line, j) {
                var t = svg('tspan', { x: at[0], dy: j === 0 ? first + 'em' : '1.2em', class: j === lines.length - 1 ? 'pct' : null });
                t.textContent = line;
                label.appendChild(t);
            });
            root.appendChild(label);
        });
        var shape = svg('g', { class: 'shape-g' });
        shape.appendChild(svg('polygon', { class: 'shape', points: gains.map(function (g, i) { return point(i, value(g)).join(','); }).join(' ') }));
        gains.forEach(function (g, i) {
            var p = point(i, value(g));
            shape.appendChild(svg('circle', { class: 'vertex' + (g.gain > 0 ? ' weak' : ''), cx: p[0], cy: p[1], r: 4.5 }));
        });
        root.appendChild(shape);
        return root;
    }

    /** Один короткий залп конфетти на итоге. */
    function confetti() {
        if (reducedMotion) return;
        var colors = ['#2f6fed', '#1f9d55', '#f5a524', '#e5484d', '#8e4ec6'];
        var box = el('div', { class: 'confetti' });
        for (var i = 0; i < 26; i++) {
            box.appendChild(el('i', { style: 'left:' + Math.round(Math.random() * 100) + '%;background:' + colors[i % colors.length]
                + ';--dx:' + Math.round(Math.random() * 120 - 60) + 'px;--rot:' + Math.round(Math.random() * 720 - 360) + 'deg'
                + ';animation-delay:' + (Math.random() * 0.25).toFixed(2) + 's' }));
        }
        document.body.appendChild(box);
        setTimeout(function () { box.remove(); }, 2000);
    }

    function countUp(node, low, high) {
        var start = Date.now(), dur = 900;
        (function frame() {
            var t = Math.min(1, (Date.now() - start) / dur);
            var e = 1 - Math.pow(1 - t, 3);
            node.textContent = '~' + Math.round(low * e) + '–' + Math.round(high * e);
            if (t < 1) requestAnimationFrame(frame); else haptic('ok');
        })();
    }

    /**
     * Ответ на «Хочешь улучшить?»: как после верного ответа в задании — экран
     * уезжает вниз к реплике акулы, полоска отсчёта, потом (на «Да» в Telegram)
     * — обратно в чат, где дальше пишет Александр.
     */
    function improve(yes) {
        [].forEach.call($screen.querySelectorAll('.improve-answers button'), function (b) { b.disabled = true; });
        api(CFG.urls.improve, { yes: yes }).then(function (r) {
            if (r.status !== 200) return fail();
            state.improve = r.data.improve;
            [].forEach.call($screen.querySelectorAll('.center-link'), function (n) { n.remove(); });
            var text = state.improve === 'yes' ? SAY.improveYes : SAY.improveNo;
            $screen.appendChild(sayTyping(text, null, 'happy', 'hop'));
            var ms = 3000, typing = reducedMotion ? 0 : 450;
            $bar.innerHTML = '';
            $bar.appendChild(countdownBar(ms, typing));
            autoAdvance(function () {
                if (inTelegram && state.improve === 'yes') return tg.close();
                renderResult(state, true);
            }, ms, typing);
        }, function () { fail(); });
    }

    /**
     * «Начать заново»: посреди теста ($ask) — с подтверждением, прохождение
     * закрывается как брошенное; после итога — сразу, итог остаётся в истории.
     */
    /** track — сразу в этот тест с тем же классом («Пройди тест посложнее»). */
    function restart(ask, track) {
        var go = function () {
            api(CFG.urls.restart, { platform: tg ? tg.platform : null, track: track || null }).then(function (r) {
                if (r.status !== 200) return fail();
                shownBlock = -1;
                shownProgress = -1;
                celebrated = false;
                render(r.data);
            }, function () { fail(); });
        };
        if (!ask) return go();
        var question = 'Начать диагностику заново? Ответы этого раза не сохранятся.';
        if (inTelegram && tg.showConfirm && tg.isVersionAtLeast && tg.isVersionAtLeast('6.2')) {
            tg.showConfirm(question, function (ok) { if (ok) go(); });
        } else if (window.confirm(question)) {
            go();
        }
    }

    // ---------- механики ----------

    function optionButton(text, onclick, extra) {
        return el('button', { class: 'opt' + (extra ? ' ' + extra : ''), type: 'button', onclick: onclick }, [text]);
    }

    function mark(node, text) {
        node.appendChild(el('span', { class: 'mark', text: text }));
    }

    var RENDER = {
        // Карточка «верно/неверно»: свайп вправо — верно, влево — неверно.
        // Оформление chat — утверждение приходит сообщением персонажа (from).
        swipe: {
            build: function (q, card) {
                card.classList.remove('card');
                var chat = q.skin === 'chat' && q.from;
                var c = el('div', { class: 'swipe-card' + (chat ? ' chat' : '') }, chat
                    ? [el('div', { class: 'speech-head' }, [avatar(q.from), el('b', { text: nameOf(q.from) })]), el('div', { text: q.prompt })]
                    : [q.prompt]);
                var zone = el('div', { class: 'swipe-zone' }, [c]);
                var answer = null;
                var yes = chat ? 'Это правда' : 'Верно';
                var no = chat ? 'Это не так' : 'Неверно';
                var choose = function (v) {
                    if (ui.locked) return;
                    answer = v;
                    c.style.transform = 'translateX(' + (v ? 30 : -30) + 'px) rotate(' + (v ? 3 : -3) + 'deg)';
                    submit();
                };
                var x0 = null, dx = 0;
                c.addEventListener('pointerdown', function (e) { if (ui.locked) return; x0 = e.clientX; dx = 0; c.classList.add('drag'); c.setPointerCapture(e.pointerId); });
                c.addEventListener('pointermove', function (e) {
                    if (x0 === null) return;
                    dx = e.clientX - x0;
                    c.style.transform = 'translateX(' + dx + 'px) rotate(' + dx / 20 + 'deg)';
                    c.classList.toggle('yes', dx > 60);
                    c.classList.toggle('no', dx < -60);
                });
                var end = function () {
                    if (x0 === null) return;
                    x0 = null;
                    c.classList.remove('drag', 'yes', 'no');
                    if (Math.abs(dx) > 90) choose(dx > 0); else c.style.transform = '';
                };
                c.addEventListener('pointerup', end);
                c.addEventListener('pointercancel', end);

                var hint = el('div', { class: 'swipe-hint' }, [el('span', { text: '← ' + no }), el('span', { text: yes + ' →' })]);
                card.appendChild(zone);
                card.appendChild(hint);
                var row = el('div', { class: 'grades' }, [
                    button('👎 ' + no, function () { choose(false); }, 'choice'),
                    button(yes + ' 👍', function () { choose(true); }, 'choice'),
                ]);
                card.appendChild(row);
                return {
                    complete: function () { return answer !== null; },
                    value: function () { return { value: answer }; },
                    reveal: function (sol, skipped) {
                        c.style.transform = '';
                        c.classList.add(skipped || sol.value === answer ? 'yes' : 'no');
                        row.remove();
                        hint.remove();
                        card.appendChild(el('p', { class: 'muted', text: 'Правильно: ' + (sol.value ? yes : no).toLowerCase() }));
                    },
                };
            },
        },

        // Один из N: обычный, мини-кейс (character), «Найди лишнее» (odd), «Кто прав?» (who).
        single: {
            build: function (q, card) {
                var chosen = null;
                var nodes = {};
                var list = el('div', { class: 'options' });
                var items = q.skin === 'who' ? q.speakers : q.options;
                items.forEach(function (o) {
                    var node;
                    var onclick = function () {
                        if (ui.locked) return;
                        chosen = o.id;
                        Object.keys(nodes).forEach(function (id) { nodes[id].classList.toggle('sel', id === chosen); });
                        haptic();
                        refreshSubmit();
                    };
                    if (q.skin === 'who') {
                        node = el('div', { class: 'speaker', onclick: onclick }, [
                            avatar(o.who, o.name),
                            el('div', { class: 'bubble' }, [el('b', { text: nameOf(o.who, o.name) + ': ' }), o.text]),
                        ]);
                    } else {
                        node = optionButton(o.text, onclick);
                    }
                    nodes[o.id] = node;
                    list.appendChild(node);
                });
                card.appendChild(list);
                if (q.skin === 'who') card.appendChild(el('div', { class: 'counter', text: 'Нажми на того, кто прав' }));
                return {
                    complete: function () { return chosen !== null; },
                    value: function () { return { id: chosen }; },
                    reveal: function (sol, skipped) {
                        if (skipped) chosen = String(sol.id);
                        Object.keys(nodes).forEach(function (id) {
                            nodes[id].classList.remove('sel');
                            if (nodes[id].tagName === 'BUTTON') nodes[id].disabled = true;
                            if (id === String(sol.id)) { nodes[id].classList.add('ok'); if (q.skin !== 'who') mark(nodes[id], '✓'); }
                            else if (id === chosen) { nodes[id].classList.add('bad'); if (basic()) mark(nodes[id], 'твой ответ'); else if (q.skin !== 'who') mark(nodes[id], '✗'); }
                        });
                        // «Кто прав?»: правый радуется, остальные — «ошибся».
                        if (q.skin === 'who') items.forEach(function (o) {
                            var img = nodes[o.id].querySelector('img.avatar');
                            var c = character(o.who);
                            if (img && c && c.avatars) img.src = c.avatars[String(o.id) === String(sol.id) ? 'happy' : sadMood()];
                        });
                    },
                };
            },
        },

        // Множество: все верные суждения, «N из M» (pick), «Помоги выиграть спор»
        // (debate — просьба персонажа над вариантами), «Ты эксперт ЕГЭ»
        // (fragments — ответ персонажа, отметить, за что снять баллы).
        multi: {
            build: function (q, card) {
                var chosen = {};
                var nodes = {};
                var count = function () { return Object.keys(chosen).length; };
                var counter = el('div', { class: 'counter' });
                var updateCounter = function () {
                    counter.textContent = q.pick
                        ? 'Выбрано ' + count() + ' из ' + q.pick
                        : (q.skin === 'fragments' ? 'Снимаешь баллы за: ' + count() : 'Выбери все верные · выбрано ' + count());
                };
                if (q.skin === 'fragments' && q.from) {
                    card.appendChild(el('div', { class: 'speech-head' }, [avatar(q.from), el('b', { text: nameOf(q.from) + ' отвечает:' })]));
                }
                var list = el('div', { class: 'options' + (q.skin === 'fragments' ? ' sheet' : ''),
                    style: q.skin === 'fragments' && character(q.from) ? '--quote:' + character(q.from).color : null });
                q.options.forEach(function (o) {
                    var node = optionButton(o.text, function () {
                        if (ui.locked) return;
                        if (chosen[o.id]) delete chosen[o.id];
                        else if (!q.pick || count() < q.pick) chosen[o.id] = true;
                        else return haptic('bad');
                        node.classList.toggle('sel', !!chosen[o.id]);
                        haptic();
                        updateCounter();
                        refreshSubmit();
                    }, q.skin === 'fragments' ? 'fragment' : '');
                    nodes[o.id] = node;
                    list.appendChild(node);
                });
                card.appendChild(list);
                updateCounter();
                card.appendChild(counter);
                return {
                    complete: function () { return q.pick ? count() === q.pick : count() > 0; },
                    value: function () { return { ids: Object.keys(chosen) }; },
                    reveal: function (sol, skipped) {
                        var right = {};
                        sol.ids.forEach(function (id) { right[String(id)] = true; });
                        if (skipped) chosen = right;
                        Object.keys(nodes).forEach(function (id) {
                            var n = nodes[id];
                            n.disabled = true;
                            n.classList.remove('sel');
                            if (right[id] && chosen[id]) { n.classList.add('ok'); mark(n, '✓'); }
                            else if (chosen[id]) { n.classList.add('bad'); if (!basic()) mark(n, '✗'); }
                            else if (right[id]) { n.classList.add('missed'); mark(n, q.skin === 'fragments' ? 'ошибка' : 'верно'); }
                        });
                    },
                };
            },
        },

        // Корзины: карточку перетаскивают пальцем в группу (или тап по карточке,
        // потом по группе — запасной способ). У каждой карточки своё место в
        // пуле: взятая оставляет пустую рамку того же размера, остальные не
        // сдвигаются — не надо заново искать глазами, где какая. Вынутая из
        // группы возвращается на своё место.
        // Оформление cases — карточки как сообщения людей (from на карточке).
        buckets: {
            build: function (q, card) {
                var placed = {};      // карточка → корзина
                var order = [];       // в каком порядке карточки легли в корзины
                var selected = null;  // запасной способ: выбранная тапом карточка
                var cards = {};
                var slots = {};
                var zones = {};
                var pool = el('div', { class: 'pool slots' });

                var redraw = function () {
                    Object.keys(zones).forEach(function (b) {
                        zones[b].pool.innerHTML = '';
                        zones[b].node.classList.toggle('target', selected !== null);
                    });
                    q.cards.forEach(function (c) {
                        cards[c.id].classList.toggle('sel', selected === c.id);
                        slots[c.id].classList.toggle('empty', !!placed[c.id]);
                        if (!placed[c.id] && cards[c.id].parentNode !== slots[c.id]) slots[c.id].appendChild(cards[c.id]);
                    });
                    order.forEach(function (id) { zones[placed[id]].pool.appendChild(cards[id]); });
                    refreshSubmit();
                };
                // bucket null — домой, на своё место в пуле.
                var put = function (id, bucket) {
                    order = order.filter(function (x) { return x !== id; });
                    if (bucket) { placed[id] = bucket; order.push(id); } else delete placed[id];
                    selected = null;
                    haptic();
                    redraw();
                };
                var zoneAt = function (x, y) {
                    for (var hit = document.elementFromPoint(x, y); hit && hit !== document.body; hit = hit.parentNode) {
                        if (hit.getAttribute && hit.getAttribute('data-bucket')) return hit.getAttribute('data-bucket');
                    }
                    return null;
                };
                var hover = function (bucket) {
                    Object.keys(zones).forEach(function (b) { zones[b].node.classList.toggle('over', b === bucket); });
                };

                // Тянешь карточку к краю экрана — страница сама едет: нижние группы
                // на телефоне за краем.
                var edgeScroll = function (d) {
                    if (!d || !d.moved) return;
                    var edge = 80, dy = 0;
                    if (d.y > window.innerHeight - edge) dy = Math.ceil((d.y - (window.innerHeight - edge)) / 6);
                    else if (d.y < edge) dy = -Math.ceil((edge - d.y) / 6);
                    if (dy) { window.scrollBy(0, dy); hover(zoneAt(d.x, d.y)); }
                    d.raf = requestAnimationFrame(function () { edgeScroll(d.alive ? d : null); });
                };

                q.cards.forEach(function (c) {
                    var n = el('button', { class: 'tag drag' + (c.from ? ' case' : ''), type: 'button' },
                        c.from ? [el('b', { text: c.from }), el('span', { text: c.text })] : [c.text]);
                    // На телефоне карточку берёт долгое нажатие: обычное движение пальцем
                    // листает страницу. Мышью — сразу, как потянул.
                    // За пальцем едет копия в <body>, а не сама карточка: у position: fixed
                    // внутри анимированного контейнера свой отсчёт координат, и карточка
                    // прыгала вниз-вправо. Копия стоит ровно там, где была карточка, и
                    // сдвигается на смещение пальца; отпустил — плавно ложится на место.
                    var drag = null;
                    var lift = function (e) {
                        var r = n.getBoundingClientRect();
                        n.classList.remove('armed');
                        drag.moved = true;
                        drag.rect = r;
                        drag.ghost = n.cloneNode(true);
                        drag.ghost.classList.add('dragging');
                        drag.ghost.style.cssText = 'left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;height:' + r.height + 'px';
                        document.body.appendChild(drag.ghost);
                        n.style.visibility = 'hidden';
                        selected = null;
                        // Место карточки — пустой рамкой сразу, ещё до того, как отпустили.
                        if (!placed[c.id]) slots[c.id].classList.add('empty');
                        drag.alive = true;
                        haptic();
                        follow(e.clientX, e.clientY);
                        edgeScroll(drag);
                    };
                    var follow = function (x, y) {
                        drag.x = x;
                        drag.y = y;
                        drag.ghost.style.transform = 'translate3d(' + (x - drag.x0) + 'px,' + (y - drag.y0) + 'px,0) rotate(-2deg) scale(1.04)';
                        hover(zoneAt(x, y));
                    };
                    n.addEventListener('pointerdown', function (e) {
                        if (ui.locked) return;
                        drag = { x0: e.clientX, y0: e.clientY, moved: false, mouse: e.pointerType === 'mouse' };
                        n.setPointerCapture(e.pointerId);
                        if (drag.mouse) return;
                        var d = drag;
                        d.timer = setTimeout(function () {
                            if (drag !== d) return;
                            d.armed = true;
                            n.classList.add('armed');
                            lift({ clientX: d.x0, clientY: d.y0 });
                        }, 300);
                    });
                    n.addEventListener('pointermove', function (e) {
                        if (!drag) return;
                        if (!drag.moved) {
                            if (Math.abs(e.clientX - drag.x0) + Math.abs(e.clientY - drag.y0) < 8) return;
                            // Пальцем повёл раньше, чем засчиталось долгое нажатие, — это прокрутка.
                            if (!drag.mouse) { clearTimeout(drag.timer); drag = null; return; }
                            lift(e);
                        }
                        follow(e.clientX, e.clientY);
                    });
                    // Пока тянешь, страница сама не листается — только у края (edgeScroll).
                    n.addEventListener('touchmove', function (e) { if (drag && drag.moved) e.preventDefault(); }, { passive: false });
                    n.addEventListener('contextmenu', function (e) { e.preventDefault(); });
                    // Долгое нажатие на тексте не должно его выделять (iOS) — иначе карточка не берётся.
                    n.addEventListener('selectstart', function (e) { e.preventDefault(); });
                    var drop = function (e, cancelled) {
                        if (!drag) return;
                        var d = drag;
                        drag = null;
                        clearTimeout(d.timer);
                        if (!d.moved) return;   // не тянули — это тап, его разберёт click
                        d.alive = false;
                        cancelAnimationFrame(d.raf);
                        n.suppressClick = true;
                        hover(null);
                        var target = cancelled ? placed[c.id] || null : zoneAt(e.clientX, e.clientY);
                        // Взял и отпустил на месте — карточка остаётся, где была.
                        if (!cancelled && !target && placed[c.id] && Math.abs(e.clientX - d.x0) + Math.abs(e.clientY - d.y0) < 8) target = placed[c.id];
                        put(c.id, target);
                        // Копия долетает до нового места карточки и исчезает.
                        var to = n.getBoundingClientRect();
                        var ghost = d.ghost;
                        var land = function () { ghost.remove(); n.style.visibility = ''; };
                        if (reducedMotion) return land();
                        ghost.classList.add('landing');
                        ghost.style.transform = 'translate3d(' + (to.left - d.rect.left) + 'px,' + (to.top - d.rect.top) + 'px,0)';
                        ghost.style.width = to.width + 'px';
                        ghost.style.height = to.height + 'px';
                        setTimeout(land, 190);
                    };
                    n.addEventListener('pointerup', function (e) { drop(e, false); });
                    n.addEventListener('pointercancel', function (e) { drop(e, true); });
                    n.addEventListener('click', function (e) {
                        e.stopPropagation();
                        if (n.suppressClick) { n.suppressClick = false; return; }
                        if (ui.locked) return;
                        // Выбрана другая карточка — тап по лежащей в группе кладёт выбранную
                        // в ту же группу, а не выкидывает лежащую.
                        if (placed[c.id] && selected !== null && selected !== c.id) return put(selected, placed[c.id]);
                        if (placed[c.id]) return put(c.id, null);
                        selected = selected === c.id ? null : c.id;
                        haptic();
                        redraw();
                    });
                    cards[c.id] = n;
                    slots[c.id] = el('div', { class: 'slot' }, [n]);
                    pool.appendChild(slots[c.id]);
                });

                var list = el('div', { class: 'buckets' });
                q.buckets.forEach(function (b) {
                    var inner = el('div', { class: 'pool' });
                    var node = el('div', { class: 'bucket', 'data-bucket': b.id, onclick: function () {
                        if (ui.locked || selected === null) return;
                        put(selected, b.id);
                    } }, [el('h4', { text: b.title }), inner]);
                    zones[b.id] = { node: node, pool: inner };
                    list.appendChild(node);
                });
                card.appendChild(pool);
                card.appendChild(list);
                redraw();
                return {
                    // Место карточки — её размер на экране: пустая рамка потом его держит.
                    ready: function () {
                        q.cards.forEach(function (c) {
                            slots[c.id].style.width = cards[c.id].offsetWidth + 'px';
                            slots[c.id].style.height = cards[c.id].offsetHeight + 'px';
                        });
                    },
                    complete: function () { return Object.keys(placed).length === q.cards.length; },
                    value: function () { return { mapping: placed }; },
                    reveal: function (sol, skipped) {
                        var titles = {};
                        q.buckets.forEach(function (b) { titles[b.id] = b.title; });
                        if (skipped) {
                            placed = {};
                            order = [];
                            q.cards.forEach(function (c) { placed[c.id] = sol.mapping[c.id]; order.push(c.id); });
                            selected = null;
                            redraw();
                        }
                        Object.keys(zones).forEach(function (b) { zones[b].node.classList.remove('target', 'over'); });
                        q.cards.forEach(function (c) {
                            var n = cards[c.id];
                            n.disabled = true;
                            var ok = placed[c.id] === sol.mapping[c.id];
                            n.classList.add(ok ? 'ok' : 'bad');
                            if (!ok) n.appendChild(el('span', { class: 'fix', text: '→ ' + titles[sol.mapping[c.id]] }));
                        });
                    },
                };
            },
        },

        // «Найди и исправь»: пост персонажа, спорные слова подчёркнуты одинаково —
        // какие из них ошибочны, не видно. Тап по слову → выбор из похожих
        // терминов (текущее слово среди них — «оставить как есть»).
        fix: {
            build: function (q, card) {
                var chosen = {};       // место → слово, только изменённые
                var touched = false;
                var open = null;
                var spots = {};
                var byId = {};
                q.spots.forEach(function (s) { byId[s.id] = s; });
                var word = function (id) { return chosen[id] !== undefined ? chosen[id] : byId[id].shown; };

                var picker = el('div', { class: 'picker', hidden: 'hidden' });
                var openPicker = function (id) {
                    if (ui.locked) return;
                    open = open === id ? null : id;
                    Object.keys(spots).forEach(function (k) { spots[k].classList.toggle('sel', k === open); });
                    picker.innerHTML = '';
                    if (open === null) { picker.hidden = true; return; }
                    picker.appendChild(el('div', { class: 'counter', text: 'Чем заменить?' }));
                    var row = el('div', { class: 'pool' });
                    byId[id].options.forEach(function (opt) {
                        row.appendChild(el('button', { class: 'tag' + (opt === word(id) ? ' sel' : ''), type: 'button', text: opt, onclick: function () {
                            if (opt === byId[id].shown) delete chosen[id]; else chosen[id] = opt;
                            touched = true;
                            spots[id].textContent = word(id);
                            spots[id].classList.toggle('changed', chosen[id] !== undefined);
                            haptic();
                            openPicker(id);   // закрыть
                            refreshSubmit();
                        } }));
                    });
                    picker.appendChild(row);
                    picker.hidden = false;
                    haptic();
                };

                var text = el('div', { class: 'post-text' });
                q.text.split(/(\{\{\w+\}\})/).forEach(function (part) {
                    var m = part.match(/^\{\{(\w+)\}\}$/);
                    if (!m || !byId[m[1]]) return text.appendChild(document.createTextNode(part));
                    var id = m[1];
                    spots[id] = el('span', { class: 'spot', role: 'button', tabindex: '0', text: byId[id].shown, onclick: function () { openPicker(id); } });
                    text.appendChild(spots[id]);
                });

                var post = el('div', { class: 'post', style: character(q.from) ? '--quote:' + character(q.from).color : null }, [
                    q.from ? el('div', { class: 'speech-head' }, [avatar(q.from), el('b', { text: nameOf(q.from) })]) : null,
                    text,
                ]);
                card.appendChild(post);
                card.appendChild(picker);
                card.appendChild(el('div', { class: 'counter', text: 'Нажми на подчёркнутое слово, чтобы заменить. Ошибок может быть несколько.' }));
                return {
                    complete: function () { return touched; },
                    value: function () { return { spots: chosen }; },
                    reveal: function (sol, skipped) {
                        picker.remove();
                        if (skipped) Object.keys(spots).forEach(function (id) {
                            if (sol.spots[id] !== byId[id].shown) chosen[id] = sol.spots[id]; else delete chosen[id];
                            spots[id].textContent = word(id);
                        });
                        Object.keys(spots).forEach(function (id) {
                            var ok = word(id) === sol.spots[id];
                            spots[id].classList.remove('sel', 'changed');
                            spots[id].classList.add(ok ? 'ok' : 'bad');
                            if (!ok) spots[id].appendChild(el('span', { class: 'fix', text: ' → ' + sol.spots[id] }));
                        });
                    },
                };
            },
        },
    };

    // ---------- старт ----------

    function start(restart) {
        api(CFG.urls.session, {
            init_data: inTelegram ? tg.initData : null,
            platform: tg ? tg.platform : null,
            restart: !!restart,
        }).then(function (r) {
            if (r.status === 403) return fail(r.data.error === 'auth' ? SAY.authFail : SAY.open);
            if (r.status !== 200) return fail();
            render(r.data);
        }, function () { fail(); });
    }

    document.getElementById('restart').addEventListener('click', function () { restart(true); });

    // Ночь — по теме Telegram (в нём) или системы: свой фон и тёмные плашки.
    var applyNight = function () {
        var night = inTelegram ? tg.colorScheme === 'dark' : !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('night', night);
        // Шапка и низ Telegram — цвета фона, чтобы Mini App не обрезался полосой чужого цвета.
        if (inTelegram && tg.isVersionAtLeast && tg.isVersionAtLeast('6.1')) {
            var bg = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim();
            try { tg.setHeaderColor(bg); tg.setBackgroundColor(bg); } catch (e) { /* старый клиент */ }
            if (tg.setBottomBarColor && tg.isVersionAtLeast('7.10')) { try { tg.setBottomBarColor(bg); } catch (e) { /* старый клиент */ } }
        }
    };
    applyNight();
    if (inTelegram && tg.onEvent) tg.onEvent('themeChanged', applyNight);

    if (tg) {
        try {
            tg.ready();
            tg.expand();
            // Перетаскивание карточек вниз не должно сворачивать Mini App.
            if (tg.disableVerticalSwipes && tg.isVersionAtLeast && tg.isVersionAtLeast('7.7')) tg.disableVerticalSwipes();
            if (inTelegram) document.documentElement.classList.add('tg');
        } catch (e) { /* старый клиент Telegram */ }
    }
    start(false);
})();
