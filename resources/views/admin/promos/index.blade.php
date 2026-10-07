@extends('admin.layouts.main')

@section('title', 'Промокоды')

@section('content')
<x-ui.page-header title="Промокоды">
    <x-slot:actions>
        <x-ui.button href="{{ route('admin.promos.create') }}" size="xs">
            <x-icon name="plus" class="w-4 h-4" />
            Создать промокод
        </x-ui.button>
    </x-slot:actions>
</x-ui.page-header>

@if($promos->isEmpty())
    <x-ui.empty>Пока нет промокодов</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr>
                <th>Код</th>
                <th>Тип</th>
                <th>Курс</th>
                <th>Параметры</th>
                <th>Окно действия</th>
                <th>Лимит / использ.</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($promos as $p)
                <tr>
                    <td data-primary class="font-mono md:whitespace-nowrap">{{ $p->code }}</td>

                    <td data-label="Тип">
                        <span><x-ui.badge :tone="$p->kind === 'access' ? 'indigo' : 'orange'">{{ $p->kind === 'access' ? 'Доступ' : 'Скидка' }}</x-ui.badge></span>
                    </td>

                    <td data-label="Курс">{{ $p->course?->title ?? 'любой' }}</td>

                    <td data-label="Параметры">
                        <span>
                            @if($p->kind === 'access')
                                {{ $p->duration_days }} дн. доступа
                            @else
                                @switch($p->discount_mode)
                                    @case('percent')
                                        скидка {{ $p->discount_percent }}%
                                        @break
                                    @case('amount')
                                        минус {{ number_format($p->discount_value_cents/100, 2, ',', ' ') }} {{ $p->currency }}
                                        @break
                                    @case('fixed_price')
                                        цена {{ number_format($p->discount_value_cents/100, 2, ',', ' ') }} {{ $p->currency }}
                                        @break
                                    @case('free')
                                        бесплатно
                                        @break
                                    @default
                                        —
                                @endswitch
                            @endif
                        </span>
                    </td>

                    <td data-label="Окно действия" class="text-xs md:text-sm">
                        <span>
                            {{ $p->starts_at ? $p->starts_at->format('d.m.Y H:i') : '—' }}
                            –
                            {{ $p->ends_at ? $p->ends_at->format('d.m.Y H:i') : '—' }}
                        </span>
                    </td>

                    <td data-label="Лимит / использовано" class="md:whitespace-nowrap">{{ $p->max_uses ?? '∞' }} / {{ $p->used_count }}</td>

                    <td data-label="Статус">
                        <span><x-ui.badge :tone="$p->is_active ? 'green' : 'gray'">{{ $p->is_active ? 'Активен' : 'Выключен' }}</x-ui.badge></span>
                    </td>

                    <td data-actions>
                        <div class="ui-table-actions md:justify-end -ml-3.5 md:ml-0">
                            {{-- Ссылка активации — только для access-кодов, привязанных к конкретному курсу --}}
                            @if($p->kind === 'access' && $p->course_id)
                                <x-ui.button variant="ghost" size="xs" title="Скопировать ссылку активации"
                                             data-copy-link="{{ url('/promo/redeem') }}?code={{ urlencode($p->code) }}">Ссылка</x-ui.button>
                            @elseif($p->kind === 'access' && !$p->course_id)
                                <span class="px-3.5 text-xs text-zinc-500" title="Код для любого курса — укажите course_id в ссылке">нужен course_id в ссылке</span>
                            @endif

                            <x-ui.action-form :action="route('admin.promos.toggle', $p)" method="POST">{{ $p->is_active ? 'Выключить' : 'Включить' }}</x-ui.action-form>

                            <x-ui.button href="{{ route('admin.promos.edit', $p) }}" variant="ghost" size="xs">Изменить</x-ui.button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>
@endif

<div class="mt-4">{{ $promos->links('pagination.ui') }}</div>

<script>
document.addEventListener('click', function (e) {
    var button = e.target.closest('[data-copy-link]');
    if (!button) return;

    var link = button.getAttribute('data-copy-link');
    var label = button.textContent;
    var done = function () {
        button.textContent = 'Скопировано ✓';
        setTimeout(function () { button.textContent = label; }, 1500);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(link).then(done).catch(function () { window.prompt('Скопируйте ссылку:', link); });
        return;
    }
    // http без TLS (локально) — clipboard API недоступен.
    var area = document.createElement('textarea');
    area.value = link;
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); done(); } catch (err) { window.prompt('Скопируйте ссылку:', link); }
    area.remove();
});
</script>
@endsection
