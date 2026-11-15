{{--
    x-ui.page-header — шапка страницы админки: ссылка «назад», заголовок (H1 из
    шкалы шрифтов, см. /admin/design-system), пояснение и действия справа.
    На телефоне действия переносятся под заголовок.

    Пропы:
      title:     текст H1
      back:      URL ссылки «назад» (необязательно)
      backLabel: подпись ссылки (по умолчанию «Назад»)
    Слоты:
      default — пояснение под заголовком
      actions — кнопки справа
--}}
@props([
    'title',
    'back' => null,
    'backLabel' => 'Назад',
])

<div {{ $attributes->merge(['class' => 'mb-5 md:mb-6']) }}>
    @if ($back)
        <a href="{{ $back }}" data-smart-back class="inline-flex items-center gap-1 -ml-1 mb-1 min-h-9 text-sm text-zinc-500 hover:text-zinc-900">
            <x-icon name="chevron-left" class="w-4 h-4 shrink-0" />
            <span data-smart-back-label>{{ $backLabel }}</span>
        </a>
        @once
        {{-- «Назад» ведёт туда, откуда пришли (CRM с фильтрами, главная…),
             а не всегда в заданный раздел. Точка входа запоминается на
             вкладку (sessionStorage) по пути страницы: возврат с дочерней
             страницы (…/edit после сохранения) её не перезаписывает, и
             пары «A → B → A» не зацикливаются. Нет данных — обычная ссылка. --}}
        <script>
        (function () {
            var link = document.querySelector('[data-smart-back]');
            if (!link) return;
            var here = location.pathname;
            var key = 'smart-back:' + here;
            var store;
            try { store = window.sessionStorage; store.getItem(key); } catch (e) { return; }
            var ref = null;
            try { ref = document.referrer ? new URL(document.referrer) : null; } catch (e) {}
            if (ref && ref.origin === location.origin && ref.pathname !== here
                && ref.pathname.indexOf(here + '/') !== 0) {
                var refBack = store.getItem('smart-back:' + ref.pathname);
                var loops = false;
                try { loops = refBack && new URL(refBack).pathname === here; } catch (e) {}
                if (!loops) store.setItem(key, ref.href);
            }
            var target = store.getItem(key);
            if (!target) return;
            var targetUrl = new URL(target);
            var defaultUrl = new URL(link.href, location.href);
            if (targetUrl.pathname + targetUrl.search === defaultUrl.pathname + defaultUrl.search) return;
            link.href = target;
            if (targetUrl.pathname !== defaultUrl.pathname) {
                link.querySelector('[data-smart-back-label]').textContent = 'Назад';
            }
            link.addEventListener('click', function (e) {
                // Тот же адрес, что в истории, — шагаем назад: браузер вернёт
                // и прокрутку списка.
                if (document.referrer === target && history.length > 1) {
                    e.preventDefault();
                    history.back();
                }
            });
        })();
        </script>
        @endonce
    @endif
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
        <div class="min-w-0">
            <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900 break-words">{{ $title }}</h1>
            @if (trim((string) $slot) !== '')
                <div class="mt-1.5 text-sm text-zinc-500 max-w-3xl">{{ $slot }}</div>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
