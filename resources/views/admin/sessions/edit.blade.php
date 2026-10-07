@extends('admin.layouts.main')

@section('title', 'Редактирование занятия')

@section('content')
<div class="max-w-2xl">
    <x-ui.page-header title="Редактировать занятие" :back="route('admin.sessions.index')" back-label="Сессии" />

    <form method="POST" action="{{ route('admin.sessions.update', $session) }}">
        @csrf
        @method('PUT')

        <x-ui.card class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.input type="date" name="date" id="date" label="Дата" value="{{ old('date', $session->date) }}" required />
                <x-ui.input type="time" name="start_time" id="start_time" label="Время начала" value="{{ old('start_time', substr($session->start_time, 0, 5)) }}" required />
                <x-ui.input type="number" name="duration_minutes" id="duration_minutes" label="Длительность, минут" min="1" inputmode="numeric"
                            value="{{ old('duration_minutes', $session->duration_minutes) }}" required />
            </div>
            {{-- Подсказка времени окончания считается на клиенте, на бэкенд не влияет --}}
            <p id="end-time-hint" class="ui-hint -mt-2 empty:hidden"></p>

            <x-ui.select name="status" label="Статус занятия">
                <option value="active" @selected(old('status', $session->status) === 'active')>Активное</option>
                <option value="cancelled" @selected(old('status', $session->status) === 'cancelled')>Отменено</option>
            </x-ui.select>
        </x-ui.card>

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.sessions.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>

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
