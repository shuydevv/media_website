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
 * Персонажи вопросов (Лёха, Маша, Кира — config diagnostic.characters) —
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

    // Реплики маскота — ЗАГЛУШКИ, согласовать с автором (правила голоса
    // рыбы — config/fish_speech.php: без рода, не стыдим, коротко).
    var SAY = {
        hello: 'Привет! Я помогу понять, где ты уже крут в обществознании, а где можно добрать баллы 🐟\nЭто займёт минут 10.',
        grade: 'Для начала: ты в каком классе?',
        go: 'Поехали! Отвечай как знаешь — если сомневаешься, нажми «🎲 Наугад» перед ответом.',
        ok: ['Верно! ✅', 'Точно! ✅', 'Есть! ✅'],
        bad: ['Не совсем 🙃', 'Мимо, но это поправимо 🙃', 'Почти 🙃'],
        streak: '🔥 {n} подряд!',
        growth: '{block} — твоя точка роста 🎯\nИдём дальше!',
        target: 'Последний вопрос перед результатом: на сколько баллов хочешь сдать?',
        resultIntro: 'Готово! Вот что получилось 👇',
        improve: 'Хочешь улучшить свои знания?',
        improveYes: 'Отлично! Возвращаю тебя в чат — там тебе напишут 🙂',
        improveNo: 'Окей! Результат сохранился. Захочешь — пиши в чат бота.',
        open: 'Открой диагностику через бота в Telegram — по кнопке «Пройти диагностику».',
        authFail: 'Не получилось войти. Закрой это окно и открой диагностику из бота ещё раз.',
        error: 'Что-то пошло не так. Проверь интернет и попробуй ещё раз.',
    };

    var state = null;         // последний state от сервера
    var token = null;         // токен прохождения (X-Diag-Attempt)
    var shownBlock = -1;      // для какого блока уже показан вводный экран
    var ui = {};              // текущий вопрос: guess, ответ, время показа
    var pendingExplainMs = null;
    var shownProgress = -1;   // какой блок уже залит в прогрессе — чтобы новый заливался плавно
    var celebrated = false;   // конфетти — один раз на итог

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

    /** Аватар персонажа: картинка, если есть, иначе кружок с буквой его цвета. */
    function avatar(id, fallbackName) {
        var c = character(id);
        if (c && c.avatar) return el('img', { class: 'avatar', src: c.avatar, alt: '' });
        var name = c ? c.name : (fallbackName || '?');
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
        var img = who ? avatar(who) : mascot(mood || 'idle');
        var node = el('div', { class: 'say' }, [img, bubble]);
        var reveal = function () {
            bubble.className = 'bubble' + (tone ? ' ' + tone : '');
            bubble.textContent = text;
            if (extra) bubble.appendChild(extra);
            if (react) img.classList.add(react);
            node.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'nearest' });
        };
        if (reducedMotion) reveal(); else setTimeout(reveal, 450);
        return node;
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
        $screen.innerHTML = '';
        var i = 0;
        nodes.forEach(function (n) {
            if (!n) return;
            if (enter === 'slide') n.classList.add('enter');
            if (enter === 'rise') { n.classList.add('rise'); n.style.animationDelay = (i++ * 0.12) + 's'; }
            $screen.appendChild(n);
        });
        $bar.innerHTML = '';
        (barNodes || []).forEach(function (n) { if (n) $bar.appendChild(n); });
        window.scrollTo(0, 0);
    }

    function api(url, body) {
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };
        if (token) headers['X-Diag-Attempt'] = token;
        return fetch(url, { method: 'POST', headers: headers, body: JSON.stringify(body || {}), credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (data) { return { status: r.status, data: data }; });
            });
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
        var cls = function (i, at) { return i < at ? 'done' : (i === at ? 'now' : ''); };
        // Сначала — как было, следующим кадром — как стало: заливка едет.
        var from = shownProgress < 0 ? p.index : shownProgress;
        seg.innerHTML = '';
        for (var i = 0; i < p.total; i++) seg.appendChild(el('i', { class: cls(i, from) }));
        if (from !== p.index) {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { [].forEach.call(seg.children, function (s, i) { s.className = cls(i, p.index); }); });
            });
        }
        shownProgress = p.index;
        var block = p.blocks[p.index];
        document.getElementById('top-label').textContent = 'Блок ' + (p.index + 1) + ' из ' + p.total + (block ? ' · ' + block.title : '');
    }

    // ---------- экраны ----------

    function render(s) {
        state = s;
        token = s.attempt || token;
        renderProgress(s);
        if (s.screen === 'profile') return renderProfile(s);
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
                api(CFG.urls.profile, { grade: key }).then(function (r) {
                    if (r.status !== 200) return fail();
                    render(r.data);
                }, function () { fail(); });
            }, 'ghost'));
        });
        show([say(SAY.hello, null, 'happy'), say(SAY.grade, null, 'think'), grades], [], 'rise');
    }

    function renderBlockIntro(s) {
        var p = s.progress;
        var block = p.blocks[p.index];
        var first = p.index === 0;
        show([
            el('div', { class: 'center' }, [
                mascot('think', 'bob'),
                el('div', { class: 'muted', text: 'Блок ' + (p.index + 1) + ' из ' + p.total }),
                el('div', { class: 'big', text: block.title }),
            ]),
            first ? say(SAY.go) : null,
        ], [button('Поехали', function () { shownBlock = p.index; renderQuestion(s.question); })], 'rise');
    }

    function renderQuestion(q) {
        ui = { q: q, guess: false, shownAt: Date.now(), locked: false };
        var mech = RENDER[q.mechanic];
        var card = el('div', { class: 'card' });
        if (q.character) {
            card.appendChild(el('div', { class: 'character' }, [el('span', { class: 'avatar', text: q.character.charAt(0) }), q.character]));
        }
        if (q.skin === 'debate' && q.from) {
            card.appendChild(el('div', { class: 'speaker' }, [avatar(q.from), el('div', { class: 'bubble' }, [el('b', { text: nameOf(q.from) + ': ' }), q.prompt])]));
        } else if (q.mechanic !== 'swipe') {
            card.appendChild(el('p', { class: 'prompt', text: q.prompt }));
        }
        if (q.task) card.appendChild(el('p', { class: 'task', text: 'Задание: ' + q.task }));
        ui.card = card;
        ui.api = mech.build(q, card);

        var guess = el('button', { class: 'chip-guess', type: 'button', text: '🎲 Наугад', onclick: function () {
            if (ui.locked) return;
            ui.guess = !ui.guess;
            guess.classList.toggle('on', ui.guess);
            haptic();
        } });
        ui.submit = q.mechanic === 'swipe' ? null : button('Ответить', submit);
        if (ui.submit) ui.submit.disabled = true;
        show([card], [guess, ui.submit], 'slide');
        if (ui.api.ready) ui.api.ready();
    }

    function refreshSubmit() {
        if (ui.submit) ui.submit.disabled = !ui.api.complete();
    }

    function submit() {
        if (ui.locked || (ui.submit && !ui.api.complete())) return;
        ui.locked = true;
        if (ui.submit) ui.submit.disabled = true;
        var payload = {
            question_id: ui.q.id,
            answer: ui.api.value(),
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
        haptic(res.correct ? 'ok' : 'bad');
        ui.api.reveal(res.solution);
        // Свайп-карточка без рамки .card — анимируем саму карточку.
        var target = ui.card.querySelector('.swipe-card') || ui.card;
        play(target, res.correct ? 'flash-ok' : 'shake');

        // Мем — картинка из банка (поле meme: путь от корня сайта или https-адрес).
        var meme = typeof res.meme === 'string' && /^(https:\/\/|\/)/.test(res.meme) ? el('img', { class: 'meme', src: res.meme, alt: '' }) : null;
        // Говорит персонаж (тот, кто прав в «Кто прав?», или explain_by) — иначе рыба.
        var who = character(res.explain_by) ? res.explain_by : null;
        $screen.appendChild(sayTyping(
            who ? (res.correct ? '✅ ' : '❌ ') + nameOf(who) + ': ' + res.explain : (res.correct ? pick(SAY.ok) : pick(SAY.bad)) + '\n' + res.explain,
            res.correct ? 'ok' : 'bad',
            res.correct ? 'happy' : 'sad',
            res.correct ? 'hop' : 'tilt',
            meme,
            who
        ));
        if (res.correct && res.streak >= 3) toast(SAY.streak.replace('{n}', res.streak));

        var shownAt = Date.now();
        $bar.innerHTML = '';
        $bar.appendChild(button('Дальше', function () {
            pendingExplainMs = Date.now() - shownAt;
            if (res.block.closed_by_errors) return renderGrowth(res);
            render(res.state);
        }));
    }

    // Блок закрыт двумя ошибками — не «провал», а точка роста (ТЗ, 5.3).
    function renderGrowth(res) {
        $top.hidden = true;
        show([
            el('div', { class: 'center' }, [mascot('sad')]),
            say(SAY.growth.replace('{block}', res.block.title), null, 'think'),
        ], [button('Идём дальше', function () { render(res.state); })], 'rise');
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

    function renderResult(s, quiet) {
        var r = s.result;
        var nodes = [say(SAY.resultIntro, null, 'happy')];

        var score = el('div', { class: 'score', text: '~' + r.low + '–' + r.high });
        nodes.push(el('div', { class: 'card' }, [
            el('div', { class: 'muted', text: 'Сейчас ты можешь набрать' }),
            score,
            el('div', { class: 'muted', text: 'тестовых баллов ЕГЭ' }),
            r.target === null ? null : el('p', { text: r.near_target
                ? 'Ты рядом со своей целью ' + r.target + ' — дальше всё решает вторая часть.'
                : 'До твоей цели ' + r.target + ' не хватает ~' + r.to_target + ' ' + plural(r.to_target, 'балла', 'баллов', 'баллов') + '.' }),
        ]));

        // Оси радара — в порядке блоков теста, а не по потерям: так форма читается.
        var order = s.progress.blocks.map(function (b) { return b.key; });
        var axes = r.gains.slice().sort(function (a, b) { return order.indexOf(a.key) - order.indexOf(b.key); });
        nodes.push(el('div', { class: 'card' }, [el('p', { class: 'section-title', text: 'Твой профиль по блокам' }), radar(axes)]));

        var gains = r.gains.filter(function (g) { return g.gain > 0; });
        var max = gains.reduce(function (m, g) { return Math.max(m, g.gain); }, 1);
        var losses = el('div', { class: 'card' }, [el('p', { class: 'section-title', text: 'Где ты теряешь баллы' })]);
        gains.forEach(function (g) {
            losses.appendChild(el('div', { class: 'gain' }, [
                el('span', { text: g.title }),
                el('b', { text: '+' + g.gain }),
                el('div', { class: 'track' }, [el('i', { style: 'width:0', 'data-width': Math.round(g.gain / max * 100) + '%' })]),
            ]));
        });
        r.gains.filter(function (g) { return g.gain <= 0; }).forEach(function (g) {
            losses.appendChild(el('div', { class: 'muted', text: '✓ ' + g.title + ' — крепко' }));
        });
        nodes.push(losses);

        if (r.weak.length) {
            nodes.push(el('div', { class: 'card' }, [
                el('p', { class: 'section-title', text: 'Потенциал: ' + r.potential + '+' }),
                el('div', { text: 'Если подтянуть ' + (r.weak.length > 1 ? 'блоки ' : 'блок ')
                    + r.weak.map(function (t) { return '«' + t + '»'; }).join(' и ') + '.' }),
            ]));
        }
        if (r.guessed > 0) {
            nodes.push(el('div', { class: 'card', text: '🎲 ' + r.guessed + ' ' + plural(r.guessed, 'верный ответ', 'верных ответа', 'верных ответов')
                + ' ты дал наугад — в этих темах знания шаткие.' }));
        }
        nodes.push(el('div', { class: 'card muted', text: 'Вторую часть ЕГЭ мы не проверяли, а это около половины баллов.' }));

        var bar = [];
        if (s.improve) {
            nodes.push(say(s.improve === 'yes' ? SAY.improveYes : SAY.improveNo));
            bar.push(button('↺ Пройти ещё раз', function () { restart(false); }, 'ghost'));
        } else {
            nodes.push(say(SAY.improve));
            bar.push(button('Пока нет', function () { improve(false); }, 'ghost'));
            bar.push(button('Да, хочу', function () { improve(true); }));
            // Пройти заново можно и не отвечая на вопрос — итог этого раза сохранится.
            nodes.push(el('div', { class: 'center-link' }, [el('button', { class: 'link', type: 'button', text: '↺ Пройти заново', onclick: function () { restart(false); } })]));
        }
        show(nodes, bar, quiet ? null : 'rise');
        // Полоски потерь заливаются, когда их карточка уже появилась.
        setTimeout(function () {
            [].forEach.call($screen.querySelectorAll('.track i'), function (i) { i.style.width = i.getAttribute('data-width'); });
        }, quiet || reducedMotion ? 0 : 600);
        if (quiet) return;
        countUp(score, r.low, r.high);
        if (!celebrated) { celebrated = true; setTimeout(confetti, 700); }
    }

    /**
     * Радар по блокам: значение блока — по уровню (0–3 → 10/35/65/90%, ТЗ 2.4).
     * Длинные подписи — в две строки.
     */
    function radar(gains) {
        var NS = 'http://www.w3.org/2000/svg';
        var svg = function (tag, attrs) {
            var n = document.createElementNS(NS, tag);
            Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
            return n;
        };
        var share = [0.1, 0.35, 0.65, 0.9];
        var cx = 170, cy = 108, R = 78, n = gains.length;
        var point = function (i, k) {
            var a = -Math.PI / 2 + i * 2 * Math.PI / n;
            return [cx + Math.cos(a) * R * k, cy + Math.sin(a) * R * k];
        };
        var root = svg('svg', { viewBox: '0 0 340 210', class: 'radar', role: 'img', 'aria-label': 'Профиль по блокам' });
        [0.33, 0.66, 1].forEach(function (k) {
            root.appendChild(svg('polygon', { class: 'grid', points: gains.map(function (g, i) { return point(i, k).join(','); }).join(' ') }));
        });
        gains.forEach(function (g, i) {
            var end = point(i, 1);
            root.appendChild(svg('line', { class: 'grid', x1: cx, y1: cy, x2: end[0], y2: end[1] }));
            var at = point(i, 1.2);
            var anchor = Math.abs(at[0] - cx) < 8 ? 'middle' : (at[0] > cx ? 'start' : 'end');
            var label = svg('text', { x: at[0], y: at[1], 'text-anchor': anchor, 'dominant-baseline': 'middle' });
            var words = g.title.split(' ');
            var lines = g.title.length > 12 && words.length > 1
                ? [words.slice(0, Math.ceil(words.length / 2)).join(' '), words.slice(Math.ceil(words.length / 2)).join(' ')]
                : [g.title];
            lines.forEach(function (line, j) {
                var t = svg('tspan', { x: at[0], dy: j === 0 ? (lines.length > 1 ? '-0.6em' : '0') : '1.2em' });
                t.textContent = line;
                label.appendChild(t);
            });
            root.appendChild(label);
        });
        root.appendChild(svg('polygon', { class: 'shape', points: gains.map(function (g, i) { return point(i, share[g.level] || 0.1).join(','); }).join(' ') }));
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

    function improve(yes) {
        $bar.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        api(CFG.urls.improve, { yes: yes }).then(function (r) {
            if (r.status !== 200) return fail();
            state.improve = r.data.improve;
            renderResult(state, true);
            // В Telegram — обратно в чат: там дальше пишет Александр.
            if (inTelegram && yes) setTimeout(function () { tg.close(); }, 1500);
        }, function () { fail(); });
    }

    /**
     * «Начать заново»: посреди теста ($ask) — с подтверждением, прохождение
     * закрывается как брошенное; после итога — сразу, итог остаётся в истории.
     */
    function restart(ask) {
        var go = function () {
            api(CFG.urls.restart, { platform: tg ? tg.platform : null }).then(function (r) {
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
                    button('👎 ' + no, function () { choose(false); }, 'ghost'),
                    button(yes + ' 👍', function () { choose(true); }, 'ghost'),
                ]);
                card.appendChild(row);
                return {
                    complete: function () { return answer !== null; },
                    value: function () { return { value: answer }; },
                    reveal: function (sol) {
                        c.style.transform = '';
                        c.classList.add(sol.value === answer ? 'yes' : 'no');
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
                if (q.skin === 'odd') card.insertBefore(el('div', { class: 'task', text: 'Найди лишнее' }), card.lastChild);
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
                    reveal: function (sol) {
                        Object.keys(nodes).forEach(function (id) {
                            nodes[id].classList.remove('sel');
                            if (nodes[id].tagName === 'BUTTON') nodes[id].disabled = true;
                            if (id === String(sol.id)) { nodes[id].classList.add('ok'); if (q.skin !== 'who') mark(nodes[id], '✓'); }
                            else if (id === chosen) { nodes[id].classList.add('bad'); if (q.skin !== 'who') mark(nodes[id], '✗'); }
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
                var list = el('div', { class: 'options' + (q.skin === 'fragments' ? ' sheet' : '') });
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
                    reveal: function (sol) {
                        var right = {};
                        sol.ids.forEach(function (id) { right[String(id)] = true; });
                        Object.keys(nodes).forEach(function (id) {
                            var n = nodes[id];
                            n.disabled = true;
                            n.classList.remove('sel');
                            if (right[id] && chosen[id]) { n.classList.add('ok'); mark(n, '✓'); }
                            else if (chosen[id]) { n.classList.add('bad'); mark(n, '✗'); }
                            else if (right[id]) { n.classList.add('missed'); mark(n, q.skin === 'fragments' ? 'ошибка' : 'верно'); }
                        });
                    },
                };
            },
        },

        // Корзины: тап по карточке → тап по корзине; тап по разложенной — вернуть.
        // Оформление cases — карточки как сообщения людей (from на карточке).
        buckets: {
            build: function (q, card) {
                var placed = {};      // карточка → корзина
                var selected = null;
                var cards = {};
                var pool = el('div', { class: 'pool' });
                var zones = {};
                var redraw = function () {
                    pool.innerHTML = '';
                    Object.keys(zones).forEach(function (b) { zones[b].pool.innerHTML = ''; zones[b].node.classList.toggle('target', selected !== null); });
                    q.cards.forEach(function (c) {
                        var n = cards[c.id];
                        n.classList.toggle('sel', selected === c.id);
                        (placed[c.id] ? zones[placed[c.id]].pool : pool).appendChild(n);
                    });
                    refreshSubmit();
                };
                q.cards.forEach(function (c) {
                    cards[c.id] = el('button', { class: 'tag' + (c.from ? ' case' : ''), type: 'button', onclick: function (e) {
                        e.stopPropagation();
                        if (ui.locked) return;
                        if (placed[c.id]) { delete placed[c.id]; selected = null; }
                        else selected = selected === c.id ? null : c.id;
                        haptic();
                        redraw();
                    } }, c.from ? [el('b', { text: c.from }), el('span', { text: c.text })] : [c.text]);
                });
                var list = el('div', { class: 'buckets' });
                q.buckets.forEach(function (b) {
                    var inner = el('div', { class: 'pool' });
                    var node = el('div', { class: 'bucket', onclick: function () {
                        if (ui.locked || selected === null) return;
                        placed[selected] = b.id;
                        selected = null;
                        haptic();
                        redraw();
                    } }, [el('h4', { text: b.title }), inner]);
                    zones[b.id] = { node: node, pool: inner };
                    list.appendChild(node);
                });
                card.appendChild(el('div', { class: 'counter', text: 'Нажми на карточку, потом на группу' }));
                card.appendChild(pool);
                card.appendChild(list);
                redraw();
                return {
                    complete: function () { return Object.keys(placed).length === q.cards.length; },
                    value: function () { return { mapping: placed }; },
                    reveal: function (sol) {
                        var titles = {};
                        q.buckets.forEach(function (b) { titles[b.id] = b.title; });
                        Object.keys(zones).forEach(function (b) { zones[b].node.classList.remove('target'); });
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
                        row.appendChild(el('button', { class: 'tag' + (opt === word(id) ? ' sel' : ''), type: 'button', text: opt === byId[id].shown ? opt + ' (оставить)' : opt, onclick: function () {
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

                var post = el('div', { class: 'post' }, [
                    q.from ? el('div', { class: 'speech-head' }, [avatar(q.from), el('b', { text: nameOf(q.from) })]) : null,
                    text,
                ]);
                card.appendChild(post);
                card.appendChild(picker);
                card.appendChild(el('div', { class: 'counter', text: 'Нажми на подчёркнутое слово, чтобы заменить. Ошибок может быть несколько.' }));
                return {
                    complete: function () { return touched; },
                    value: function () { return { spots: chosen }; },
                    reveal: function (sol) {
                        picker.remove();
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

    if (tg) {
        try {
            tg.ready();
            tg.expand();
            if (inTelegram) document.documentElement.classList.add('tg');
        } catch (e) { /* старый клиент Telegram */ }
    }
    start(false);
})();
