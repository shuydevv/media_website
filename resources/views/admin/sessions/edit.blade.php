@extends('admin.layouts.main')

@section('content')
<div class="max-w-xl mx-auto p-6 bg-white rounded-xl shadow">
    <h1 class="text-xl font-semibold mb-6">Редактировать занятие</h1>

    @if (session('success'))
        <div class="mb-4 text-green-600 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.sessions.update', $session) }}">
        @csrf
        @method('PUT')

        {{-- Дата --}}
        <div class="mb-4">
            <label for="date" class="block text-sm font-medium">Дата</label>
            <input
                type="date"
                name="date"
                id="date"
                class="w-full border rounded px-3 py-2"
                value="{{ old('date', $session->date) }}"
                required
            >
        </div>

        {{-- Время начала --}}
        <div class="mb-4">
            <label for="start_time" class="block text-sm font-medium">Время начала</label>
            <input
                type="time"
                name="start_time"
                id="start_time"
                class="w-full border rounded px-3 py-2"
                value="{{ old('start_time', substr($session->start_time, 0, 5)) }}"
                required
            >
        </div>

        {{-- Длительность, минут --}}
        <div class="mb-4">
            <label for="duration_minutes" class="block text-sm font-medium">Длительность (мин)</label>
            <input
                type="number"
                min="1"
                name="duration_minutes"
                id="duration_minutes"
                class="w-full border rounded px-3 py-2"
                value="{{ old('duration_minutes', $session->duration_minutes) }}"
                required
            >
            {{-- Необязательный подсказчик конечного времени (клиентский расчёт) --}}
            <p id="end-time-hint" class="text-xs text-gray-500 mt-1"></p>
        </div>

        {{-- Статус занятия --}}
        <div class="mb-6">
            <label for="status" class="block text-sm font-medium">Статус занятия</label>
            <select name="status" id="status" class="w-full border rounded px-3 py-2">
                <option value="active" {{ old('status', $session->status) === 'active' ? 'selected' : '' }}>Активное</option>
                <option value="cancelled" {{ old('status', $session->status) === 'cancelled' ? 'selected' : '' }}>Отменено</option>
            </select>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Сохранить изменения
            </button>
            <a href="{{ route('admin.sessions.index') }}" class="text-gray-600 hover:underline">Назад к списку</a>
        </div>
    </form>
</div>

{{-- Небольшой скрипт для подсказки времени окончания на клиенте (не влияет на бэкенд) --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const startInput = document.getElementById('start_time');
    const durInput   = document.getElementById('duration_minutes');
    const hint       = document.getElementById('end-time-hint');

    function pad(n){ return String(n).padStart(2,'0'); }

    function updateHint() {
        const start = startInput.value; // "HH:MM"
        const dur   = parseInt(durInput.value, 10);
        if (!start || !dur || isNaN(dur)) { hint.textContent = ''; return; }

        const [h, m] = start.split(':').map(Number);
        const total  = h * 60 + m + dur;
        const hh     = Math.floor(total / 60) % 24;
        const mm     = total % 60;

        hint.textContent = 'Окончание: ' + pad(hh) + ':' + pad(mm);
    }

    startInput.addEventListener('input', updateHint);
    durInput.addEventListener('input', updateHint);
    updateHint();
});
</script>
@endsection
