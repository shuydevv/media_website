@extends('layouts.main')

@php
    function money_fmt($cents, $cur='RUB') {
        return number_format($cents/100, 2, ',', ' ') . ' ' . $cur;
    }
@endphp

@section('content')
<div class="max-w-2xl mx-auto p-6 bg-white rounded-xl shadow">
    <h1 class="text-2xl font-semibold mb-4">Оплата курса</h1>

    <div class="mb-6 border rounded-lg p-4">
        <div class="text-lg font-medium">{{ $course->title }}</div>
        <div class="text-gray-600">{{ $course->description }}</div>

        <div class="mt-3 text-sm">
            <div>Базовая цена: <b>{{ money_fmt($baseCents, $baseCurrency) }}</b></div>
            @if(isset($appliedPromo) && $appliedPromo)
                <div class="mt-1">
                    Применён промокод <span class="font-mono px-2 py-0.5 bg-gray-100 rounded">{{ $appliedPromo->code }}</span> —
                    @if($appliedPromo->discount_mode === 'percent')
                        скидка {{ $appliedPromo->discount_percent }}%
                    @elseif($appliedPromo->discount_mode === 'amount')
                        минус {{ money_fmt($appliedPromo->discount_value_cents, $baseCurrency) }}
                    @elseif($appliedPromo->discount_mode === 'fixed_price')
                        фиксированная цена {{ money_fmt($appliedPromo->discount_value_cents, $baseCurrency) }}
                    @elseif($appliedPromo->discount_mode === 'free')
                        бесплатно
                    @endif
                </div>
            @endif
        </div>

        <div class="mt-3 text-xl">
            Итог к оплате: <span class="font-bold">{{ money_fmt($finalCents, $baseCurrency) }}</span>
        </div>
    </div>

    {{-- ошибки --}}
    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    {{-- форма ввода промокода --}}
    <form method="POST" action="{{ route('checkout.course.apply', $course) }}" class="flex gap-2 items-end">
        @csrf
        <div class="flex-1">
            <label class="block text-sm font-medium">Промокод</label>
            <input type="text" name="code" value="{{ old('code', $inputCode ?? '') }}"
                   class="w-full border rounded px-3 py-2" placeholder="Введите код">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded">Применить</button>
    </form>

    {{-- заглушка оплаты --}}
    <div class="mt-6">
        <button class="px-4 py-2 bg-blue-600 text-white rounded" onclick="alert('Здесь будет оплата. Итог: {{ money_fmt($finalCents, $baseCurrency) }}')">
            Перейти к оплате
        </button>
        <p class="text-xs text-gray-500 mt-2">Платёжная интеграция подключается позже. Сейчас мы лишь применяем промокод и считаем итоговую цену.</p>
    </div>
</div>
@endsection
