@extends('admin.layouts.main')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Пользователь #{{ $user->id }}</h1>

    <div class="flex gap-2">
        <a href="{{ route('admin.user.edit', $user->id) }}"
           class="px-4 py-2 bg-zinc-900 text-white rounded-lg hover:bg-zinc-800">Изменить</a>

        @if($user->deleted_at)
            <form method="POST" action="{{ route('admin.user.restore', $user->id) }}">
                @csrf
                <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
                    Восстановить
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.user.delete', $user->id) }}"
                  onsubmit="return confirm('Точно пометить пользователя как удалённого?');">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700">
                    Удалить
                </button>
            </form>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg bg-rose-50 text-rose-800 px-4 py-3 text-sm">
        @foreach($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Карточка статусов -->
    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-black/5 p-5 space-y-4">
        <h2 class="text-lg font-medium">Статусы</h2>
        <div class="flex flex-wrap gap-2">
            @php
                $isAdmin = (string)$user->role === '1' || $user->role === 1;
            @endphp

            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $isAdmin ? 'bg-amber-50 text-amber-700' : 'bg-zinc-100 text-zinc-700' }}">
                Роль: {{ $isAdmin ? 'Админ' : 'Пользователь' }}
            </span>

            @if($user->deleted_at)
                <span class="inline-flex px-2 py-1 rounded-full text-xs bg-rose-50 text-rose-700">
                    Удалён {{ $user->deleted_at?->format('d.m.Y H:i') }}
                </span>
            @else
                <span class="inline-flex px-2 py-1 rounded-full text-xs bg-emerald-50 text-emerald-700">
                    Активен
                </span>
            @endif

            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $user->email_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-700' }}">
                Email: {{ $user->email_verified_at ? 'верифицирован' : 'не верифицирован' }}
            </span>

            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $user->phone_verified_at ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-700' }}">
                Телефон: {{ $user->phone_verified_at ? 'верифицирован' : 'не верифицирован' }}
            </span>
        </div>
    </div>

    <!-- Основные данные -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm ring-1 ring-black/5 p-5">
        <h2 class="text-lg font-medium mb-4">Профиль</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
                <div class="text-zinc-500">Имя (display)</div>
                <div class="text-zinc-900">{{ $user->name ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Email</div>
                <div class="text-zinc-900">{{ $user->email ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Телефон</div>
                <div class="text-zinc-900">{{ $user->phone ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Имя/Фамилия</div>
                <div class="text-zinc-900">
                    {{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '—' }}
                </div>
            </div>

            <div>
                <div class="text-zinc-500">Часовой пояс</div>
                <div class="text-zinc-900">{{ $user->timezone ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Язык</div>
                <div class="text-zinc-900">{{ strtoupper($user->locale ?? 'ru') }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Создан</div>
                <div class="text-zinc-900">{{ $user->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Обновлён</div>
                <div class="text-zinc-900">{{ $user->updated_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Email подтверждён</div>
                <div class="text-zinc-900">{{ $user->email_verified_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>
            <div>
                <div class="text-zinc-500">Телефон подтверждён</div>
                <div class="text-zinc-900">{{ $user->phone_verified_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Профиль заполнен</div>
                <div class="text-zinc-900">{{ $user->profile_completed_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </div>

            <div>
                <div class="text-zinc-500">Роль (raw)</div>
                <div class="text-zinc-900">{{ $user->role ?? '—' }}</div>
            </div>

            <div class="sm:col-span-2">
                <div class="text-zinc-500">Питомец (кличка)</div>
                <div class="text-zinc-900">
                    @if($user->fish_name)
                        {{ $user->fish_name }}
                    @else
                        @php
                            $fishService = app(\App\Service\FishFoodService::class);
                            $fishDefaultName = $fishService->levelName($fishService->levelFor((int) $user->fish_total_fed));
                        @endphp
                        <span class="text-zinc-500">не задано (сейчас показывается «{{ $fishDefaultName }}»)</span>
                    @endif
                </div>
            </div>

            <div class="sm:col-span-2">
                <div class="text-zinc-500">Пароль (hash, сокращённо)</div>
                <div class="text-zinc-900 font-mono break-all">
                    @if($user->password)
                        {{ substr($user->password, 0, 15) }}&hellip;
                    @else
                        —
                    @endif
                </div>
                <div class="text-xs text-zinc-500 mt-1">Полный хэш не показываем по соображениям безопасности.</div>
            </div>

            <div class="sm:col-span-2">
                <div class="text-zinc-500">remember_token</div>
                <div class="text-zinc-900 font-mono break-all">{{ $user->remember_token ?: '—' }}</div>
            </div>

            @if($user->deleted_at)
            <div class="sm:col-span-2">
                <div class="text-zinc-500">deleted_at</div>
                <div class="text-zinc-900">{{ $user->deleted_at?->format('d.m.Y H:i') }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="mt-6 bg-white rounded-2xl shadow-sm ring-1 ring-black/5 p-5">
    <h2 class="text-lg font-medium mb-4">Оплата курсов</h2>

    @forelse($enrollments as $course)
        @php
            $attachedPromo = $billing->attachedPromoCode($user, $course);
            $suggestedRub = number_format($billing->priceForEnrollment($user, $course) / 100, 2, '.', '');
        @endphp
        <div class="border rounded-xl p-4 mb-3">
            <div class="font-medium mb-2">{{ $course->title }}</div>
            <div class="text-xs text-zinc-500 mb-2">
                Следующий платёж: {{ optional($course->pivot->next_payment_due_at)->format('d.m.Y H:i') ?? '—' }}
            </div>

            <form method="POST" action="{{ route('admin.billing.autopay.update', [$user, $course]) }}" class="flex items-center gap-2 mb-3 text-xs">
                @csrf
                @method('PUT')
                <input type="hidden" name="enabled" value="{{ $course->pivot->autopay_enabled ? '0' : '1' }}">
                <span class="text-zinc-500">Автоплатёж:</span>
                <span class="{{ $course->pivot->autopay_enabled ? 'text-emerald-700' : 'text-zinc-500' }}">
                    {{ $course->pivot->autopay_enabled ? 'включён' : 'выключен' }}
                </span>
                <button class="underline text-blue-700">{{ $course->pivot->autopay_enabled ? 'выключить' : 'включить' }}</button>
                <span class="text-zinc-400">(пока без реального списания — только переключает уведомления)</span>
            </form>

            @if($attachedPromo)
                <div class="flex items-center gap-2 mb-3 text-xs">
                    <span class="text-zinc-500">Промокод:</span>
                    <span class="font-mono px-1.5 py-0.5 bg-zinc-100 rounded">{{ $attachedPromo->code }}</span>
                    <form method="POST" action="{{ route('admin.billing.promo.destroy', [$user, $course]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-rose-600 hover:underline">убрать</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('admin.billing.promo.store', [$user, $course]) }}" class="flex gap-2 items-end mb-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-zinc-500">Промокод</label>
                        <input type="text" name="code" class="border rounded px-2 py-1 text-sm" placeholder="код">
                    </div>
                    <button class="px-2 py-1 border rounded-lg text-xs hover:bg-gray-50">Подключить</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.billing.payments.store', [$user, $course]) }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <div>
                    <label class="block text-xs text-zinc-500">Сумма, ₽</label>
                    <input type="number" step="0.01" name="amount_rub" value="{{ $suggestedRub }}" class="border rounded px-2 py-1 w-28" required>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500">Периодичность</label>
                    <select name="billing_interval_days" class="border rounded px-2 py-1">
                        <option value="">оставить как есть</option>
                        <option value="14">14 дней</option>
                        <option value="30">30 дней</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs text-zinc-500">Заметка</label>
                    <input type="text" name="note" class="border rounded px-2 py-1 w-full" placeholder="напр. номер перевода">
                </div>
                <button class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">Записать платёж</button>
            </form>
        </div>
    @empty
        <p class="text-sm text-zinc-500">Активных записей на курсы нет.</p>
    @endforelse

    @if($payments->isNotEmpty())
        <table class="w-full text-xs mt-4">
            <thead><tr class="text-zinc-500 text-left"><th class="py-1">Дата</th><th>Курс</th><th>Сумма</th><th>Тип</th></tr></thead>
            <tbody>
            @foreach($payments as $p)
                <tr class="border-t">
                    <td class="py-1">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $p->course->title ?? '—' }}</td>
                    <td>{{ $p->is_promise ? '—' : number_format($p->amount_cents / 100, 2) . ' ₽' }}</td>
                    <td>{{ $p->method }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-8">
    <a href="{{ route('admin.user.index') }}" class="text-zinc-600 hover:text-zinc-900 underline">← Ко всем пользователям</a>
</div>
@endsection
