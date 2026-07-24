@extends('layouts.main')

@php
    use App\Support\Money;
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="sans-medium text-2xl md:text-3xl mb-6 text-zinc-900">Оплата курсов</h1>

    @if (session('success'))
        <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    @if ($enrollments->isEmpty())
        <x-ui.card class="text-zinc-600 mb-4">
            Активных курсов нет.
        </x-ui.card>
    @else
        <div class="flex flex-col gap-4 mb-4">
            @foreach ($enrollments as $row)
                @php $course = $row['course']; @endphp
                <x-ui.card>
                    <div class="sans-medium text-lg text-zinc-900 mb-1">{{ $course->title }}</div>

                    @if (!$row['billingEnabled'])
                        <p class="text-sm text-zinc-500">Разовый доступ — не на регулярной оплате.</p>
                    @else
                        <p class="text-sm text-zinc-500 mb-1">
                            Следующий платёж:
                            {{ $row['nextDueAt'] ? $row['nextDueAt']->format('d.m.Y') : '—' }}
                            — {{ Money::format($row['priceCents']) }}
                        </p>

                        @if ($row['promoCode'])
                            <p class="text-sm text-emerald-700 mb-3">
                                Промокод <span class="font-mono px-1.5 py-0.5 bg-emerald-50 rounded">{{ $row['promoCode']->code }}</span> подключён и учитывается в каждом платеже.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('student.billing.update', $course) }}" class="flex flex-wrap items-center gap-3">
                            @csrf
                            <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                                <input type="radio" name="interval_days" value="30" {{ $row['intervalDays'] == 30 ? 'checked' : '' }}>
                                Раз в месяц
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                                <input type="radio" name="interval_days" value="14" {{ $row['intervalDays'] == 14 ? 'checked' : '' }}>
                                Раз в 2 недели
                            </label>
                            <x-ui.button type="submit" size="sm">
                                Сохранить
                            </x-ui.button>
                        </form>
                        <p class="text-xs text-zinc-400 mt-2 mb-4">Изменение вступит в силу со следующего платежа.</p>

                        @unless ($row['promoCode'])
                            <form method="POST" action="{{ route('student.billing.promo.apply', $course) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <div>
                                    <label class="block text-xs text-zinc-500 mb-1">Есть промокод?</label>
                                    <input type="text" name="code" class="border rounded px-2 py-1.5 text-sm" placeholder="Введите код">
                                </div>
                                <button type="submit" class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50 transition">
                                    Применить
                                </button>
                            </form>
                        @endunless
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif

    {{-- История платежей — по всем курсам сразу, не только активным. --}}
    <x-ui.card>
        <div class="sans-medium text-lg text-zinc-900 mb-4">История платежей</div>

        @if ($payments->isEmpty())
            <p class="text-sm text-zinc-500">Платежей пока нет.</p>
        @else
            <div class="flex flex-col divide-y divide-gray-100">
                @foreach ($payments as $payment)
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-zinc-900 truncate">
                                {{ $payment->course->title ?? 'Курс' }}
                            </div>
                            <div class="text-xs text-zinc-500">
                                {{ ($payment->paid_at ?? $payment->created_at)->format('d.m.Y H:i') }}
                                @if ($payment->is_promise)
                                    &middot; <span class="text-amber-700">Обещанный платёж</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm font-medium text-zinc-900">
                                {{ $payment->is_promise ? '—' : Money::format($payment->amount_cents, $payment->currency) }}
                            </div>
                            <div class="text-xs
                                @if ($payment->status === 'succeeded') text-emerald-600
                                @elseif ($payment->status === 'failed') text-rose-600
                                @else text-zinc-400
                                @endif">
                                @if ($payment->status === 'succeeded') Успешно
                                @elseif ($payment->status === 'failed') Не удалось
                                @elseif ($payment->status === 'pending') В обработке
                                @else {{ $payment->status }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
@endsection
