{{--
    Список простого справочника (категории, разделы, темы, тэги, посты,
    шпаргалки, упражнения): «#id — название», клик ведёт на страницу записи.

    Параметры:
      $title       — заголовок страницы
      $items       — коллекция записей (нужны id и title)
      $showRoute   — имя маршрута страницы записи
      $createRoute — имя маршрута создания
      $createLabel — подпись кнопки создания
      $emptyText   — текст пустого состояния
      $meta        — необязательный callable($item): строка мелким шрифтом под названием
--}}
@php $meta = $meta ?? null; @endphp

<x-ui.page-header :title="$title">
    <x-slot:actions>
        <x-ui.button href="{{ route($createRoute) }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            {{ $createLabel }}
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

@if ($items->isEmpty())
    <x-ui.empty>{{ $emptyText }}</x-ui.empty>
@else
    <div class="max-w-3xl">
        {{-- Быстрый фильтр по названию — прямо в браузере, без запроса; появляется, когда список длиннее экрана. --}}
        @if ($items->count() > 10)
            <x-ui.input type="search" placeholder="Найти по названию…" wrap="mb-3" aria-label="Найти по названию" data-simple-filter />
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden divide-y divide-zinc-100" data-simple-list>
            @foreach ($items as $item)
                <a href="{{ route($showRoute, $item) }}" class="flex items-center gap-3 px-4 py-3 min-h-12 hover:bg-zinc-50 transition" data-simple-row>
                    <span class="w-10 shrink-0 text-xs text-zinc-400">#{{ $item->id }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-zinc-900 break-words" data-simple-title>{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $item->title)), 140) ?: 'Без названия' }}</span>
                        @if ($meta && ($metaText = $meta($item)))
                            <span class="block text-xs text-zinc-500 mt-0.5">{{ $metaText }}</span>
                        @endif
                    </span>
                    <x-icon name="chevron-right" class="w-4 h-4 shrink-0 text-zinc-300" />
                </a>
            @endforeach
        </div>
        <p class="hidden mt-3 text-sm text-zinc-500" data-simple-none>Ничего не найдено</p>
    </div>

    @if ($items->count() > 10)
        <script>
        (function () {
            var input = document.querySelector('[data-simple-filter]');
            var rows = document.querySelectorAll('[data-simple-row]');
            var none = document.querySelector('[data-simple-none]');
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                var shown = 0;
                rows.forEach(function (row) {
                    var match = row.textContent.toLowerCase().indexOf(q) !== -1;
                    row.style.display = match ? '' : 'none';
                    if (match) shown++;
                });
                none.classList.toggle('hidden', shown > 0);
            });
        })();
        </script>
    @endif
@endif
