{{-- Нижнее фиксированное меню платформы — показывается только на страницах
     ученика (см. проверку request()->routeIs('student.*') в layouts/main).
     Пока прототип: набор кнопок уточнится позже, здесь просто заглушки.

     Стилистика (стеклянная "таблетка") теперь одна и та же на мобилке и на
     ПК — раньше мобилка получала отдельный плоский белый бар на всю
     ширину. Вёрстка — обычный написанный вручную <style>, не Tailwind
     sm:-классы: на практике они почему-то не применялись в браузере
     (проверили: window.innerWidth верно возвращает 1536, обычные классы
     без sm: работают, но ни один sm:-вариант визуально не срабатывал, hard
     refresh не помог).

     JS по window.innerWidth здесь всё ещё нужен, но только для одной вещи:
     на странице выполнения домашки (student.submissions.question) на ПК
     меню изначально скрыто — вместо него в правом нижнем углу кнопка
     "Раскрыть меню", по клику меню появляется на обычном месте. На
     мобилке для этой же страницы — без изменений, меню как везде. --}}
@php
    $isHomeworkSolvingPage = request()->routeIs('student.submissions.question');
@endphp
<style>
    /* Одна и та же "стеклянная таблетка" на мобилке и на ПК — раньше
       мобилка получала отдельный плоский белый бар во всю ширину, а
       Apple-стекло было только в .is-desktop; по просьбе — стилистика
       единая. max-width/flex-shrink ниже нужны, чтобы 4 пункта меню не
       вылезали за край на узких телефонах (320-360px), сама таблетка при
       этом просто сжимается, а не переносится/скроллится.

       Стеклянный эффект в духе macOS/iPadOS (Dock, Control Center):
       - backdrop-filter с saturate — не просто блюр, а именно "фростед
         глас" (сатурация подсвечивает то, что под панелью, как у Apple);
       - полупрозрачный градиент сверху вниз вместо плоской заливки —
         на плоском rgba() стекло выглядит мутным листом бумаги, а не
         стеклом;
       - inset-тень сверху ("::before") — тонкий блик по верхнему краю,
         имитирующий отражение света на кромке стекла;
       - лёгкая внешняя тень — панель едва заметно приподнята над
         контентом, без "парения";
       - контур — как на iOS-таббаре (Today/Games/Apps/Arcade): яркая
         светлая обводка (сама "грань стекла", ловит свет) + тонкое
         свечение вокруг неё (мягкий белый ореол) + чуть заметное тёмное
         кольцо ровно по границе — оно нужно только чтобы светлая
         обводка не терялась на светлом фоне, само по себе почти не
         видно. */
    #student-bottom-nav {
        position: fixed;
        left: 50%;
        bottom: calc(20px + env(safe-area-inset-bottom));
        transform: translateX(-50%);
        z-index: 40;
        display: inline-block;
        max-width: calc(100vw - 24px);
        padding: 6px 10px;
        border: 1.5px solid rgba(255, 255, 255, .85);
        border-radius: 24px;
        background: linear-gradient(180deg, rgba(255, 255, 255, .78), rgba(255, 255, 255, .55));
        -webkit-backdrop-filter: blur(28px) saturate(180%);
        backdrop-filter: blur(28px) saturate(180%);
        box-shadow:
            0 0 0 1px rgba(0, 0, 0, .05),
            0 4px 16px rgba(0, 0, 0, .08),
            0 0 20px rgba(255, 255, 255, .45),
            inset 0 1px 1px rgba(255, 255, 255, .9),
            inset 0 0 0 1px rgba(255, 255, 255, .3);
        isolation: isolate;
    }
    /* Мобилке отступ снизу чуть меньше, чем ПК (20px) — обычный @media, не
       Tailwind sm:-класс, так что срабатывает нормально (см. историю
       проблемы с sm: выше). */
    @media (max-width: 639px) {
        #student-bottom-nav {
            bottom: calc(12px + env(safe-area-inset-bottom));
        }
    }
    /* Тонкий световой блик по верхней кромке — тот самый "стеклянный"
       акцент, который отличает материал Apple от обычного blur(). */
    #student-bottom-nav::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(180deg, rgba(255, 255, 255, .5), rgba(255, 255, 255, 0) 40%);
        pointer-events: none;
        z-index: -1;
    }
    #student-bottom-nav-inner {
        display: flex;
        align-items: stretch;
        gap: 2px;
    }
    #student-bottom-nav-inner a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1 1 0;
        min-width: 0;
        width: 88px;
        max-width: 88px;
        text-align: center;
        padding: 12px 4px;
        border-radius: 18px;
        color: #6b7280;
        text-decoration: none;
        transition: color .15s ease, background-color .2s ease, transform .15s ease;
    }
    #student-bottom-nav-inner a:hover {
        color: #b45309;
        background: rgba(255, 255, 255, .55);
        transform: translateY(-2px);
    }
    #student-bottom-nav-inner a:active {
        transform: translateY(0) scale(.96);
    }
    #student-bottom-nav-inner a svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin: 0 auto;
        display: block;
    }
    #student-bottom-nav-inner a span {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        font-weight: 500;
        letter-spacing: .02em;
        white-space: nowrap;
    }

    /* Скрытое по умолчанию состояние на странице выполнения домашки (ПК) —
       переключается JS через .is-collapsed. */
    #student-bottom-nav.is-collapsed {
        display: none;
    }
    #student-bottom-nav-reveal {
        display: none;
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 41;
        padding: 10px 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, .78), rgba(255, 255, 255, .55));
        -webkit-backdrop-filter: blur(28px) saturate(180%);
        backdrop-filter: blur(28px) saturate(180%);
        border: 1.5px solid rgba(255, 255, 255, .85);
        border-radius: 9999px;
        box-shadow:
            0 0 0 1px rgba(0, 0, 0, .05),
            0 4px 16px rgba(0, 0, 0, .08),
            0 0 20px rgba(255, 255, 255, .45),
            inset 0 1px 1px rgba(255, 255, 255, .9),
            inset 0 0 0 1px rgba(255, 255, 255, .3);
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: color .15s ease, background-color .2s ease, transform .15s ease;
    }
    #student-bottom-nav-reveal:hover {
        color: #b45309;
        background: linear-gradient(180deg, rgba(255, 255, 255, .85), rgba(255, 255, 255, .65));
        transform: translateY(-2px);
    }
    #student-bottom-nav-reveal.is-visible {
        display: block;
    }
</style>

@if($isHomeworkSolvingPage)
    <button id="student-bottom-nav-reveal" type="button">Раскрыть меню</button>
@endif

<nav id="student-bottom-nav" @if($isHomeworkSolvingPage) data-collapsible="1" @endif>
    <div id="student-bottom-nav-inner">
        <a href="{{ Route::has('student.dashboard') ? route('student.dashboard') : '#' }}">
            <x-icon name="book-open-01" />
            <span>Курсы</span>
        </a>
        <a href="{{ Route::has('student.homeworks.index') ? route('student.homeworks.index') : '#' }}">
            <x-icon name="clipboard-check" />
            <span>Домашки</span>
        </a>
        <a href="{{ Route::has('student.mocks.index') ? route('student.mocks.index') : '#' }}">
            <x-icon name="clock" />
            <span>Пробники</span>
        </a>
        <a href="{{ Route::has('student.profile.show') ? route('student.profile.show') : '#' }}">
            <x-icon name="user-01" />
            <span>Профиль</span>
        </a>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var nav = document.getElementById('student-bottom-nav');
    if (!nav) return;

    var inner = document.getElementById('student-bottom-nav-inner');
    var navLinks = nav.querySelectorAll('#student-bottom-nav-inner a');
    var revealBtn = document.getElementById('student-bottom-nav-reveal');
    var collapsible = nav.dataset.collapsible === '1';
    var revealed = false;

    function applyLayout() {
        // Визуально меню теперь одинаковое на любой ширине — isDesktop
        // нужен только для того, чтобы решить, скрывать ли его по
        // умолчанию на странице выполнения домашки (см. ниже).
        var isDesktop = window.innerWidth >= 640;

        // Скрыто по умолчанию только на ПК, только на странице выполнения
        // домашки, и только пока пользователь сам не нажал "Раскрыть меню".
        // На мобилке (isDesktop === false) — всегда как обычно.
        var shouldCollapse = collapsible && isDesktop && !revealed;
        nav.classList.toggle('is-collapsed', shouldCollapse);

        if (revealBtn) {
            revealBtn.classList.toggle('is-visible', shouldCollapse);
        }
    }

    function revealWithAnimation() {
        var gsapOk = typeof window.gsap !== 'undefined';

        // У <nav> уже есть свой CSS-transform (translateX(-50%) для
        // центрирования таблетки, см. #student-bottom-nav). Если дать GSAP крутить
        // y/scale прямо на ней, он перечитает и подменит этот transform —
        // центрирование может съехать. Поэтому autoAlpha (не трогает
        // transform) — на <nav>, а пружинистые y/scale — на вложенном
        // #student-bottom-nav-inner, у которого своего transform нет.
        if (!gsapOk) {
            nav.classList.remove('is-collapsed');
            return;
        }

        gsap.set(nav, { autoAlpha: 0 });
        gsap.set(inner, { y: 26, scale: 0.85, transformOrigin: '50% 100%' });
        nav.classList.remove('is-collapsed');

        gsap.timeline()
            .to(nav, { autoAlpha: 1, duration: 0.25, ease: 'power1.out' })
            .to(inner, { y: 0, scale: 1, duration: 0.6, ease: 'back.out(1.8)' }, '<')
            .fromTo(navLinks,
                { autoAlpha: 0, y: 12 },
                { autoAlpha: 1, y: 0, duration: 0.35, stagger: 0.07, ease: 'power2.out' },
                '-=0.35'
            );
    }

    if (revealBtn) {
        revealBtn.addEventListener('click', function () {
            if (revealed) return;
            revealed = true;

            var gsapOk = typeof window.gsap !== 'undefined';
            if (gsapOk) {
                gsap.to(revealBtn, {
                    scale: 0.7,
                    autoAlpha: 0,
                    duration: 0.22,
                    ease: 'power2.in',
                    onComplete: function () { revealBtn.classList.remove('is-visible'); },
                });
            } else {
                revealBtn.classList.remove('is-visible');
            }

            revealWithAnimation();
        });
    }

    applyLayout();
    window.addEventListener('resize', applyLayout);
});
</script>
