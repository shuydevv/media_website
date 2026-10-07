@extends('admin.layouts.main')

@section('title', isset($promoCode) ? 'Редактирование промокода' : 'Новый промокод')

@section('content')
<div class="max-w-2xl">
    <x-ui.page-header :title="isset($promoCode) ? 'Редактировать промокод' : 'Создать промокод'" :back="route('admin.promos.index')" back-label="Промокоды" />

    <form method="POST" action="{{ isset($promoCode) ? route('admin.promos.update', $promoCode) : route('admin.promos.store') }}" class="space-y-4">
        @csrf
        @if(isset($promoCode))
            @method('PUT')
        @endif

        <x-ui.card class="space-y-4">
            <x-ui.input name="code" label="Код промокода" value="{{ old('code', $promoCode->code ?? '') }}" autocomplete="off" class="font-mono"
                        hint="Можно оставить пустым при создании — сгенерируем автоматически." />

            <x-ui.select name="course_id" label="Привязать к курсу">
                <option value="">— любой курс —</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id', $promoCode->course_id ?? '') == $course->id)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select name="kind" id="kind" label="Тип промокода" required>
                <option value="access" @selected(old('kind', $promoCode->kind ?? 'access') === 'access')>Доступ на период</option>
                <option value="discount" @selected(old('kind', $promoCode->kind ?? '') === 'discount')>Скидка/цена</option>
            </x-ui.select>

            {{-- ACCESS --}}
            <div id="block-access">
                <x-ui.input type="number" name="duration_days" label="Длительность доступа, дней" min="1" inputmode="numeric"
                            value="{{ old('duration_days', $promoCode->duration_days ?? 7) }}" />
            </div>

            {{-- DISCOUNT --}}
            <div id="block-discount" class="hidden space-y-4">
                <x-ui.select name="discount_mode" id="discount_mode" label="Режим скидки">
                    <option value="">— выберите —</option>
                    <option value="percent" @selected(old('discount_mode', $promoCode->discount_mode ?? '') === 'percent')>Процент</option>
                    <option value="amount" @selected(old('discount_mode', $promoCode->discount_mode ?? '') === 'amount')>Минус сумма</option>
                    <option value="fixed_price" @selected(old('discount_mode', $promoCode->discount_mode ?? '') === 'fixed_price')>Зафиксированная цена</option>
                    <option value="free" @selected(old('discount_mode', $promoCode->discount_mode ?? '') === 'free')>Бесплатно</option>
                </x-ui.select>

                <div id="discount-percent" class="hidden">
                    <x-ui.input type="number" name="discount_percent" label="Процент (1–100)" min="1" max="100" inputmode="numeric"
                                value="{{ old('discount_percent', $promoCode->discount_percent ?? '') }}" />
                </div>

                <div id="discount-value" class="hidden">
                    <x-ui.input type="number" name="discount_value_cents" label="Сумма/цена, в копейках" min="0" inputmode="numeric"
                                value="{{ old('discount_value_cents', $promoCode->discount_value_cents ?? '') }}" />
                </div>

                <div id="discount-currency" class="hidden">
                    <x-ui.input name="currency" label="Валюта" hint="Например, RUB" maxlength="3"
                                value="{{ old('currency', $promoCode->currency ?? 'RUB') }}" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-4">
            <h2 class="sans-medium text-lg text-zinc-900">Ограничения</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input type="datetime-local" name="starts_at" label="Начало действия"
                            value="{{ old('starts_at', isset($promoCode->starts_at) ? $promoCode->starts_at->format('Y-m-d\TH:i') : '') }}" />
                <x-ui.input type="datetime-local" name="ends_at" label="Окончание действия"
                            value="{{ old('ends_at', isset($promoCode->ends_at) ? $promoCode->ends_at->format('Y-m-d\TH:i') : '') }}" />
            </div>

            <x-ui.input type="number" name="max_uses" label="Максимум использований" note="пусто — без лимита" min="1" inputmode="numeric"
                        value="{{ old('max_uses', $promoCode->max_uses ?? '') }}" />

            <x-ui.checkbox name="is_active" :checked="(bool) old('is_active', $promoCode->is_active ?? true)">Активен</x-ui.checkbox>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">{{ isset($promoCode) ? 'Сохранить' : 'Создать' }}</x-ui.button>
            <x-ui.button href="{{ route('admin.promos.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
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
