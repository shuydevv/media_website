@extends('layouts.main')

@php
    use App\Support\Money;
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold mb-6">Оплата курсов</h1>

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
        <div class="p-6 rounded-xl border bg-white text-gray-600">
            Активных курсов нет.
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($enrollments as $row)
                @php $course = $row['course']; @endphp
                <div class="bg-white border rounded-2xl p-5">
                    <div class="font-medium text-lg text-gray-900 mb-1">{{ $course->title }}</div>

                    @if (!$row['billingEnabled'])
                        <p class="text-sm text-gray-500">Разовый доступ — не на регулярной оплате.</p>
                    @else
                        <p class="text-sm text-gray-500 mb-1">
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
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="interval_days" value="30" {{ $row['intervalDays'] == 30 ? 'checked' : '' }}>
                                Раз в месяц
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="interval_days" value="14" {{ $row['intervalDays'] == 14 ? 'checked' : '' }}>
                                Раз в 2 недели
                            </label>
                            <button type="submit" class="px-3 py-2 rounded-lg bg-zinc-900 text-white text-sm hover:bg-zinc-800 transition">
                                Сохранить
                            </button>
                        </form>
                        <p class="text-xs text-gray-400 mt-2 mb-4">Изменение вступит в силу со следующего платежа.</p>

                        @unless ($row['promoCode'])
                            <form method="POST" action="{{ route('student.billing.promo.apply', $course) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Есть промокод?</label>
                                    <input type="text" name="code" class="border rounded px-2 py-1.5 text-sm" placeholder="Введите код">
                                </div>
                                <button type="submit" class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50 transition">
                                    Применить
                                </button>
                            </form>
                        @endunless
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
