@extends('admin.layouts.main')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white shadow rounded-lg">
    <h1 class="text-2xl font-bold mb-6">
        {{ isset($promoCode) ? 'Редактировать промокод' : 'Создать промокод' }}
    </h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ isset($promoCode) ? route('admin.promos.update', $promoCode) : route('admin.promos.store') }}">
        @csrf
        @if(isset($promoCode))
            @method('PUT')
        @endif

        {{-- Код --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Код промокода</label>
            <input type="text" name="code"
                   value="{{ old('code', $promoCode->code ?? '') }}"
                   class="w-full border rounded px-3 py-2">
            <p class="text-xs text-gray-500 mt-1">Можно оставить пустым при создании — сгенерируем автоматически.</p>
        </div>

        {{-- Курс (необязательно) --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Привязать к курсу</label>
            <select name="course_id" class="w-full border rounded px-3 py-2">
                <option value="">— любой курс —</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}"
                        {{ old('course_id', $promoCode->course_id ?? '') == $course->id ? 'selected' : '' }}>
                        {{ $course->title }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Тип промокода --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Тип промокода</label>
            <select name="kind" id="kind" class="w-full border rounded px-3 py-2" required>
                <option value="access" {{ old('kind', $promoCode->kind ?? 'access')==='access' ? 'selected' : '' }}>Доступ на период</option>
                <option value="discount" {{ old('kind', $promoCode->kind ?? '')==='discount' ? 'selected' : '' }}>Скидка/цена</option>
            </select>
        </div>

        {{-- ACCESS --}}
        <div id="block-access" class="mb-4">
            <label class="block text-sm font-medium">Длительность доступа (дней)</label>
            <input type="number" name="duration_days" min="1"
                   value="{{ old('duration_days', $promoCode->duration_days ?? 7) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        {{-- DISCOUNT --}}
        <div id="block-discount" class="mb-4 hidden">
            <label class="block text-sm font-medium">Режим скидки</label>
            <select name="discount_mode" id="discount_mode" class="w-full border rounded px-3 py-2">
                <option value="">— выберите —</option>
                <option value="percent" {{ old('discount_mode', $promoCode->discount_mode ?? '')==='percent' ? 'selected' : '' }}>Процент</option>
                <option value="amount" {{ old('discount_mode', $promoCode->discount_mode ?? '')==='amount' ? 'selected' : '' }}>Минус сумма</option>
                <option value="fixed_price" {{ old('discount_mode', $promoCode->discount_mode ?? '')==='fixed_price' ? 'selected' : '' }}>Зафиксированная цена</option>
                <option value="free" {{ old('discount_mode', $promoCode->discount_mode ?? '')==='free' ? 'selected' : '' }}>Бесплатно</option>
            </select>

            <div id="discount-percent" class="mt-3 hidden">
                <label class="block text-sm font-medium">Процент (1–100)</label>
                <input type="number" name="discount_percent" min="1" max="100"
                       value="{{ old('discount_percent', $promoCode->discount_percent ?? '') }}"
                       class="w-full border rounded px-3 py-2">
            </div>

            <div id="discount-value" class="mt-3 hidden">
                <label class="block text-sm font-medium">Сумма/Цена (в копейках)</label>
                <input type="number" name="discount_value_cents" min="0"
                       value="{{ old('discount_value_cents', $promoCode->discount_value_cents ?? '') }}"
                       class="w-full border rounded px-3 py-2">
            </div>

            <div id="discount-currency" class="mt-3 hidden">
                <label class="block text-sm font-medium">Валюта (например, RUB)</label>
                <input type="text" name="currency" maxlength="3"
                       value="{{ old('currency', $promoCode->currency ?? 'RUB') }}"
                       class="w-full border rounded px-3 py-2">
            </div>
        </div>

        {{-- Общие поля --}}
        <div class="mb-4">
            <label class="block text-sm font-medium">Дата начала действия</label>
            <input type="datetime-local" name="starts_at"
                   value="{{ old('starts_at', isset($promoCode->starts_at) ? $promoCode->starts_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">Дата окончания действия</label>
            <input type="datetime-local" name="ends_at"
                   value="{{ old('ends_at', isset($promoCode->ends_at) ? $promoCode->ends_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">Максимальное количество использований</label>
            <input type="number" name="max_uses" min="1"
                   value="{{ old('max_uses', $promoCode->max_uses ?? '') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-6">
            <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $promoCode->is_active ?? true) ? 'checked' : '' }}>
                <span class="ml-2">Активен</span>
            </label>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <a href="{{ route('admin.promos.index') }}" class="text-gray-600 hover:underline">Отмена</a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                {{ isset($promoCode) ? 'Сохранить' : 'Создать' }}
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const kind = document.getElementById('kind');
    const blockAccess = document.getElementById('block-access');
    const blockDiscount = document.getElementById('block-discount');
    const mode = document.getElementById('discount_mode');
    const percent = document.getElementById('discount-percent');
    const value = document.getElementById('discount-value');
    const currency = document.getElementById('discount-currency');

    function render() {
        const k = kind.value;
        blockAccess.classList.toggle('hidden', k !== 'access');
        blockDiscount.classList.toggle('hidden', k !== 'discount');

        const m = mode?.value;
        percent?.classList.toggle('hidden', !(k==='discount' && m==='percent'));
        value?.classList.toggle('hidden', !(k==='discount' && (m==='amount' || m==='fixed_price')));
        currency?.classList.toggle('hidden', !(k==='discount' && (m==='amount' || m==='fixed_price')));
    }

    kind.addEventListener('change', render);
    mode?.addEventListener('change', render);
    render();
});
</script>
@endsection
