{{--
    Пагинация админки: {{ $items->links('pagination.ui') }}. Дефолтная
    vendor/pagination/tailwind.blade.php — нетронутый шаблон из коробки
    Laravel, визуально не связанный с остальным интерфейсом (см. «Пагинация»
    на /admin/design-system). На телефоне — «назад / страница N из M /
    вперёд» с кнопками под палец, номера страниц — только на десктопе.
--}}
@if ($paginator->hasPages())
    @php
        $btn = 'inline-flex items-center justify-center min-w-11 min-h-11 md:min-w-9 md:min-h-9 px-3 rounded-lg text-sm transition';
        $idle = 'text-zinc-700 hover:bg-zinc-100';
        $off = 'text-zinc-300 cursor-default';
    @endphp
    <nav role="navigation" aria-label="Страницы" class="flex items-center justify-between gap-2">
        @if ($paginator->onFirstPage())
            <span class="{{ $btn }} {{ $off }}" aria-disabled="true"><x-icon name="chevron-left" class="w-4 h-4" /><span class="ml-1">Назад</span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $btn }} {{ $idle }}"><x-icon name="chevron-left" class="w-4 h-4" /><span class="ml-1">Назад</span></a>
        @endif

        <div class="md:hidden text-sm text-zinc-500">
            {{ $paginator->currentPage() }} из {{ $paginator->lastPage() }}
        </div>

        <div class="hidden md:flex items-center gap-1">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $btn }} {{ $off }}">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $btn }} bg-zinc-900 text-white font-medium">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $btn }} {{ $idle }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $btn }} {{ $idle }}"><span class="mr-1">Вперёд</span><x-icon name="chevron-right" class="w-4 h-4" /></a>
        @else
            <span class="{{ $btn }} {{ $off }}" aria-disabled="true"><span class="mr-1">Вперёд</span><x-icon name="chevron-right" class="w-4 h-4" /></span>
        @endif
    </nav>
@endif
