@extends('admin.layouts.main')

@section('content')
<div class="max-w-7xl mx-auto p-6 bg-white shadow rounded-lg">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Промокоды</h1>
        <a href="{{ route('admin.promos.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Создать промокод
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-2 pr-4">Код</th>
                    <th class="py-2 pr-4">Тип</th>
                    <th class="py-2 pr-4">Курс</th>
                    <th class="py-2 pr-4">Параметры</th>
                    <th class="py-2 pr-4">Окно действия</th>
                    <th class="py-2 pr-4">Лимит / Использ.</th>
                    <th class="py-2 pr-4">Статус</th>
                    <th class="py-2 pr-4">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($promos as $p)
                    <tr class="border-b align-top">
                        <td class="py-2 pr-4 font-mono whitespace-nowrap">{{ $p->code }}</td>

                        <td class="py-2 pr-4">
                            @if($p->kind === 'access')
                                <span class="px-2 py-1 rounded text-xs bg-indigo-100 text-indigo-700">Доступ</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Скидка</span>
                            @endif
                        </td>

                        <td class="py-2 pr-4">
                            {{ $p->course?->title ?? 'любой' }}
                        </td>

                        <td class="py-2 pr-4">
                            @if($p->kind === 'access')
                                <div>Длительность: <b>{{ $p->duration_days }}</b> дн.</div>
                            @else
                                @switch($p->discount_mode)
                                    @case('percent')
                                        <div>Скидка: <b>{{ $p->discount_percent }}%</b></div>
                                        @break
                                    @case('amount')
                                        <div>Минус: <b>{{ number_format($p->discount_value_cents/100, 2, ',', ' ') }} {{ $p->currency }}</b></div>
                                        @break
                                    @case('fixed_price')
                                        <div>Итоговая цена: <b>{{ number_format($p->discount_value_cents/100, 2, ',', ' ') }} {{ $p->currency }}</b></div>
                                        @break
                                    @case('free')
                                        <div><b>Бесплатно</b></div>
                                        @break
                                    @default
                                        <div class="text-gray-500">—</div>
                                @endswitch
                            @endif
                        </td>

                        <td class="py-2 pr-4 whitespace-nowrap">
                            {{ $p->starts_at ? $p->starts_at->format('d.m.Y H:i') : '—' }}
                            &nbsp;–&nbsp;
                            {{ $p->ends_at ? $p->ends_at->format('d.m.Y H:i') : '—' }}
                        </td>

                        <td class="py-2 pr-4 whitespace-nowrap">
                            {{ $p->max_uses ?? '∞' }} / {{ $p->used_count }}
                        </td>

                        <td class="py-2 pr-4">
                            <span class="px-2 py-1 rounded text-xs {{ $p->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $p->is_active ? 'Активен' : 'Выключен' }}
                            </span>
                        </td>

                        <td class="py-2 pr-4">
                            <div class="flex items-center gap-3">
                                {{-- Кнопка копирования ссылки активации для access-кодов, привязанных к конкретному курсу --}}
                                @if($p->kind === 'access' && $p->course_id)
                                    <button
                                        class="text-blue-600 hover:underline"
                                        onclick="copyActivation('{{ url('/promo/redeem') }}?code={{ $p->code }}')"
                                        type="button"
                                        title="Скопировать ссылку активации"
                                    >Скопировать ссылку</button>
                                @elseif($p->kind === 'access' && !$p->course_id)
                                    <span class="text-gray-500" title="Код для любого курса — укажите course_id в ссылке">
                                        Укажите course_id в ссылке
                                    </span>
                                @endif>

                                {{-- Тоггл активен/выключен --}}
                                <form action="{{ route('admin.promos.toggle', $p) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="text-indigo-600 hover:underline" type="submit">
                                        {{ $p->is_active ? 'Выключить' : 'Включить' }}
                                    </button>
                                </form>

                                {{-- (Опционально) Редактировать — если у тебя есть роут admin.promos.edit --}}
                                @if(Route::has('admin.promos.edit'))
                                    <a href="{{ route('admin.promos.edit', $p) }}" class="text-gray-700 hover:underline">Редактировать</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="py-6 text-gray-500" colspan="8">Пока нет промокодов</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $promos->links() }}</div>
</div>

<script>
function copyActivation(link) {
    navigator.clipboard.writeText(link)
        .then(() => {
            alert('Ссылка скопирована:\n' + link);
        })
        .catch(() => {
            // fallback
            const ta = document.createElement('textarea');
            ta.value = link;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); alert('Ссылка скопирована:\n' + link); }
            catch(e) {}
            document.body.removeChild(ta);
        });
}
</script>
@endsection
