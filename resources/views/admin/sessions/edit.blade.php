@extends('admin.layouts.main')

@section('content')
<div class="max-w-xl mx-auto p-6 bg-white rounded-xl shadow">
    <h1 class="text-xl font-semibold mb-6">Редактировать занятие</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.sessions.update', $session->id) }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="block text-sm font-medium">Дата</label>
            <input type="date" name="date" value="{{ old('date', $session->date) }}" class="w-full border rounded px-3 py-2" required>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">Время начала</label>
            {{-- <input type="time" name="start_time" value="{{ old('start_time', $session->start_time) }}" class="w-full border rounded px-3 py-2" required> --}}
            <input type="time" name="start_time" value="{{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }}" class="w-full border rounded px-3 py-2" required>

        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium">Длительность (в минутах)</label>
            <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $session->duration_minutes) }}" class="w-full border rounded px-3 py-2" min="1" required>
        </div>

        <div class="mb-4">
            <label for="status" class="block text-sm font-medium">Статус занятия</label>
            <select name="status" id="status" class="w-full border rounded px-3 py-2">
                <option value="active" {{ $session->status === 'active' ? 'selected' : '' }}>Активное</option>
                <option value="cancelled" {{ $session->status === 'cancelled' ? 'selected' : '' }}>Отменено</option>
            </select>
        </div>


        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Сохранить изменения</button>
    </form>
</div>
@endsection