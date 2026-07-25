{{-- Нижнее фиксированное меню платформы — показывается только на страницах
     ученика (см. проверку request()->routeIs('student.*') в layouts/main).
     Пока прототип: набор кнопок уточнится позже, здесь просто заглушки.

     ВАЖНО: десктопный вид сделан НЕ через Tailwind sm:-классы — на практике
     они почему-то не применялись в браузере (проверили: window.innerWidth
     верно возвращает 1536, обычные классы без sm: работают, но ни один
     sm:-вариант визуально не срабатывал, hard refresh не помог). Поэтому
     здесь обычный написанный вручную <style> (гарантированно уже в HTML,
     не зависит от Tailwind JIT/сборки) + JS, который переключает класс
     .is-desktop по window.innerWidth.

     На странице выполнения домашки (student.submissions.question) на ПК
     меню изначально скрыто — вместо него в правом нижнем углу кнопка
     "Раскрыть меню", по клику меню появляется на обычном месте. На
     мобилке для этой же страницы — без изменений, меню как везде. --}}
@php
    $isHomeworkSolvingPage = request()->routeIs('student.submissions.question');
@endphp
<style>
    #student-bottom-nav {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 40;
        background: #fff;
        border-top: 1px solid #e5e7eb;
        padding-bottom: env(safe-area-inset-bottom);
    }
    #student-bottom-nav-inner {
        display: flex;
    }
    #student-bottom-nav-inner a {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 16px 16px;
        color: #6b7280;
        text-decoration: none;
        transition: color .15s ease, background-color .15s ease;
    }
    #student-bottom-nav-inner a:hover {
        color: #b45309;
        background: #f9fafb;
    }
    #student-bottom-nav-inner a svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    #student-bottom-nav-inner a span {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: .02em;
        white-space: nowrap;
    }

    /* Десктопная "таблетка" — включается JS-ом через .is-desktop,
       а не через @media, раз медиазапросы у тебя не срабатывали.

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
    #student-bottom-nav.is-desktop {
        left: 50%;
        right: auto;
        bottom: 20px;
        transform: translateX(-50%);
        display: inline-block;
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
    /* Тонкий световой блик по верхней кромке — тот самый "стеклянный"
       акцент, который отличает материал Apple от обычного blur(). */
    #student-bottom-nav.is-desktop::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(180deg, rgba(255, 255, 255, .5), rgba(255, 255, 255, 0) 40%);
        pointer-events: none;
        z-index: -1;
    }
    #student-bottom-nav.is-desktop #student-bottom-nav-inner {
        display: flex;
        align-items: stretch;
        gap: 2px;
    }
    #student-bottom-nav.is-desktop #student-bottom-nav-inner a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 88px;
        text-align: center;
        padding: 12px 0;
        border-radius: 18px;
        transition: color .15s ease, background-color .2s ease, transform .15s ease;
    }
    #student-bottom-nav.is-desktop #student-bottom-nav-inner a:hover {
        color: #b45309;
        background: rgba(255, 255, 255, .55);
        transform: translateY(-2px);
    }
    #student-bottom-nav.is-desktop #student-bottom-nav-inner a:active {
        transform: translateY(0) scale(.96);
    }
    #student-bottom-nav.is-desktop #student-bottom-nav-inner a svg {
        margin: 0 auto;
        display: block;
    }
    #student-bottom-nav.is-desktop #student-bottom-nav-inner a span {
        display: block;
        margin-top: 4px;
    }

    /* Скрытое по умолчанию состояние на странице выполнения домашки (ПК) —
       переключается тем же JS, что и .is-desktop. */
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
        <a href="#">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5v-13z"></path>
                <path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5v-13z"></path>
            </svg>
            <span>Курсы</span>
        </a>
        <a href="{{ Route::has('student.homeworks.index') ? route('student.homeworks.index') : '#' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                <path d="M8 12l3 3 5-6"></path>
            </svg>
            <span>Домашки</span>
        </a>
        <a href="#">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="13" r="8"></circle>
                <path d="M12 9v4l3 2"></path>
                <path d="M9 2h6"></path>
            </svg>
            <span>Пробники</span>
        </a>
        <a href="{{ Route::has('student.profile.show') ? route('student.profile.show') : '#' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
            </svg>
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
        var isDesktop = window.innerWidth >= 640;
        nav.classList.toggle('is-desktop', isDesktop);

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
        // центрирования таблетки, см. .is-desktop). Если дать GSAP крутить
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
