<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title')</title>
        <meta name="description" content="@yield('description')" />
        <style>

        </style>

        <!-- Styles -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
        @vite('resources/css/app.css')

        <!-- htmx: AJAX-переходы без перезагрузки страницы -->
        <script src="https://unpkg.com/htmx.org@1.9.12" defer></script>

        <!-- GSAP: анимация попапа с результатом проверки -->
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
        <script>
            document.addEventListener('htmx:configRequest', function (evt) {
                var token = document.querySelector('meta[name="csrf-token"]');
                if (token) {
                    evt.detail.headers['X-CSRF-TOKEN'] = token.content;
                }
            });
        </script>


<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=104147441', 'ym');

    ym(104147441, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/104147441" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->



    </head>
    <body class="{{ request()->routeIs('student.*') ? 'pb-20' : '' }}">
        <div class="relative border-b ">
            {{-- Обычный <style>, не Tailwind-классы (max-w-6xl/p-4 и т.п.) —
                 в этом браузере часть Tailwind-классов ненадёжно
                 применяется, уже несколько раз ловили на этом хедере и на
                 нижнем меню. #site-header-row задаёт и колонку контента (та
                 же ширина, что max-w-6xl в остальном сайте), и реальную
                 высоту хедера — без неё колокольчику не по чему
                 центрироваться (и он, и лого — position: absolute, высоту
                 строки не формируют). --}}
            <style>
                #site-header-row {
                    position: relative;
                    max-width: 72rem;
                    margin: 0 auto;
                    padding: 16px;
                }
            </style>
            <div id="site-header-row">

                {{-- <div class="relative blue-bg rounded-full flex grow-0 self-center">
                    <h3 class=" p-2 pl-5 pr-8 grow-0 font-medium text-white tracking-wide">Все карты для ЕГЭ по истории!</h3>
                    <img class="absolute" style="right: -16px; width: 40px; height: 40px;" src="/img/map.png" alt="123" srcset="">
                </div> --}}
                <a class="inline-block" href="{{route('index')}}"><h2 class="font-oktyabrina leading-none md:text-[26px] md:mt-0 md:mb-0 text-xl tracking-wide absolute left-0 right-0 ml-0 mr-0 py-2 bottom-1 text-center antialiased text-zinc-800">Школа Полтавского</h2></a>
                @hasSection('back_url')
                    @include('partials.student-back-button')
                @endif
                @if(auth()->check() && request()->routeIs('student.*'))
                    @include('partials.student-notification-bell')
                @endif
                {{-- <ul class="gap-10 md:flex hidden">
                    <li style="color: rgb(217 119 6);" class="tracking-wide"></li>
                    <li class="tracking-wide"><span><img class="inline-block mr-1 b-4 w-5" src="{{asset('img/person.svg')}}" alt="alt" srcset=""> Войти</span><span></span></li>
                </ul> --}}
            </div>
        </div>
        <div class="relative">
            {{-- <div class="md:flex hidden justify-center md:mb-8 mb-4 p-4 border w-full z-10 items-center flex-wrap grow-0"> --}}
                {{-- <ul class="gap-16 md:flex hidden">

                    <a class="tracking-wide" href="{{route('main.repetitor')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Курсы</li></a>
                    <a class="tracking-wide" href="{{route('exercise.index')}}"><li class="hover:text-amber-700 transition-all tracking-wide">Упражнения</li></a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600">Выйти</button>
                    </form>
                </ul> --}}
            {{-- </div> --}}
        </div>
        @include('partials.billing-banner')
        @yield('content')
        @yield('scripts')
        @stack('page-scripts')

        @if(request()->routeIs('student.*'))
            @include('partials.student-bottom-nav')
        @endif

        {{-- Тост-уведомления (например, «Верно!» после авто-перехода на следующий вопрос).
             Живёт вне #wizard-app, чтобы не зависеть от того, что сейчас подменено htmx. --}}
        <div id="toast-root" class="fixed top-4 left-1/2 -translate-x-1/2 z-[60] flex flex-col items-center gap-2 pointer-events-none"></div>
        <script>
        (function () {
            function submissionIdFromUrl() {
                var m = location.pathname.match(/\/student\/submissions\/(\d+)/);
                return m ? m[1] : 'na';
            }

            function streakKey() {
                return 'streak:' + submissionIdFromUrl();
            }

            // Серия верных ответов подряд прервалась — сбрасывает счётчик.
            // Дёргается из попапа с неверным/частично верным результатом.
            window.__resetAnswerStreak = function () {
                try { sessionStorage.setItem(streakKey(), '0'); } catch (e) {}
            };

            // Короткие синтезированные звуки (без аудиофайлов и библиотек):
            // растущий "дзинь" на верный ответ, короткий низкий "бип" на неверный/частично верный.
            var audioCtx = null;
            function getAudioCtx() {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return null;
                if (!audioCtx) audioCtx = new Ctx();
                if (audioCtx.state === 'suspended') audioCtx.resume().catch(function () {});
                return audioCtx;
            }

            window.__playSound = function (type) {
                try {
                    var ctx = getAudioCtx();
                    if (!ctx) return;

                    var now = ctx.currentTime;
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    if (type === 'ok') {
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(600, now);
                        osc.frequency.exponentialRampToValueAtTime(900, now + 0.15);
                        gain.gain.setValueAtTime(0.0001, now);
                        gain.gain.exponentialRampToValueAtTime(0.15, now + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.25);
                        osc.start(now);
                        osc.stop(now + 0.26);
                    } else {
                        osc.type = 'square';
                        osc.frequency.setValueAtTime(180, now);
                        gain.gain.setValueAtTime(0.0001, now);
                        gain.gain.exponentialRampToValueAtTime(0.08, now + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.18);
                        osc.start(now);
                        osc.stop(now + 0.19);
                    }
                } catch (e) {}
            };

            // Уведомление построено так же, как попап с результатом проверки:
            // маскот-кружок сверху, подпись снизу — просто без кнопок и само закрывается.
            function spawnToast(message, opts) {
                opts = opts || {};
                var root = document.getElementById('toast-root');
                if (!root) return;

                var el = document.createElement('div');
                el.className = 'toast-item pointer-events-auto bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.07)] px-5 py-4 w-[220px] flex flex-col items-center gap-2';

                var mascot = null;
                if (opts.icon) {
                    mascot = document.createElement('div');
                    mascot.className = 'w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center';
                    var img = document.createElement('img');
                    img.src = opts.icon;
                    img.alt = '';
                    img.className = 'w-12 h-12';
                    mascot.appendChild(img);
                    el.appendChild(mascot);
                }

                var badge = document.createElement('div');
                badge.className = 'rounded-xl px-3 py-1.5 text-sm font-medium text-center w-full ' + (opts.badge || 'bg-emerald-50 text-emerald-800');
                badge.textContent = message;
                el.appendChild(badge);

                root.appendChild(el);

                var gsapOk = typeof window.gsap !== 'undefined';

                if (gsapOk) {
                    var tl = gsap.timeline();
                    tl.fromTo(el,
                        { autoAlpha: 0, y: -16, scale: .85 },
                        { autoAlpha: 1, y: 0, scale: 1, duration: .35, ease: 'back.out(1.9)' }
                    );
                    if (mascot) {
                        tl.fromTo(mascot,
                            { scale: .5, rotate: -8, autoAlpha: 0 },
                            { scale: 1, rotate: 0, autoAlpha: 1, duration: .45, ease: 'elastic.out(1, .5)' },
                            '-=0.2'
                        );
                    }
                } else {
                    requestAnimationFrame(function () { el.classList.add('toast-in'); });
                }

                setTimeout(function () {
                    if (gsapOk) {
                        gsap.to(el, {
                            autoAlpha: 0, y: -10, scale: .92, duration: .25, ease: 'power2.in',
                            onComplete: function () { el.remove(); },
                        });
                    } else {
                        el.classList.add('toast-out');
                        setTimeout(function () { el.remove(); }, 300);
                    }
                }, opts.duration || 2500);
            }

            document.body.addEventListener('toast', function (evt) {
                var message = (evt.detail && evt.detail.message) || 'Готово';
                spawnToast(message, { icon: '{{ asset('img/like.svg') }}', badge: 'bg-emerald-50 text-emerald-800' });
                window.__playSound('ok');

                // Стрик верных ответов подряд — чисто косметическая штука на клиенте,
                // на баллы и сохранение ответов никак не влияет.
                try {
                    var key = streakKey();
                    var streak = (parseInt(sessionStorage.getItem(key), 10) || 0) + 1;
                    sessionStorage.setItem(key, String(streak));

                    if (streak === 3 || streak === 5) {
                        setTimeout(function () {
                            spawnToast('🔥 ' + streak + ' подряд!', { icon: '{{ asset('img/cool.svg') }}', badge: 'bg-amber-50 text-amber-800', duration: 4000 });
                        }, 350);
                    }
                } catch (e) {}
            });

            // Сетевая ошибка / нет ответа от сервера — без этого htmx просто молча
            // ничего не делает, и непонятно, сработал ли клик по кнопке.
            function notifyNetworkError() {
                spawnToast('Не удалось отправить ответ. Проверьте соединение и попробуйте ещё раз.', {
                    badge: 'bg-rose-50 text-rose-800',
                    duration: 4000,
                });
            }
            document.body.addEventListener('htmx:responseError', notifyNetworkError);
            document.body.addEventListener('htmx:sendError', notifyNetworkError);
        })();
        </script>

        @auth
        {{-- Раз в 10 минут проверяем, жива ли сессия на сервере: если её выбило
             лимитом одновременных входов (см. App\Listeners\EnforceSessionLimit),
             редиректим на логин сразу, не дожидаясь обычной навигации. --}}
        <script>
        setInterval(function () {
            fetch('{{ route('session.heartbeat') }}', { headers: { 'Accept': 'application/json' } })
                .then(function (res) {
                    if (res.status === 401) {
                        window.location.href = '{{ route('login') }}?expired=1';
                    }
                })
                .catch(function () {});
        }, 10 * 60 * 1000);
        </script>
        @endauth
    </body>
</html>