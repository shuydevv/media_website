@extends('admin.layouts.main')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold mb-6">Сессии</h1>

    {{-- Флеш-сообщения --}}
    @if (session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif


<form method="GET" action="{{ route('admin.sessions.index') }}" class="mb-4 grid md:grid-cols-4 gap-4 items-end">
    {{-- Курс --}}
    <div>
        <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Курс</label>
        <select name="course_id" id="course_id" class="border rounded px-3 py-2 w-full"
                onchange="this.form.submit()">
            <option value="">Все курсы</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>
                    {{ $course->title }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Статус --}}
    <div>
        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Статус</label>
        <select name="status" id="status" class="border rounded px-3 py-2 w-full"
                onchange="this.form.submit()">
            <option value="">Все статусы</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Активные</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Отменённые</option>
        </select>
    </div>

    {{-- Дата занятия --}}
    <div>
        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Дата</label>
        <input type="date" name="date" id="date"
               value="{{ request('date') }}"
               class="border rounded px-3 py-2 w-full"
               onchange="this.form.submit()">
    </div>

    {{-- Кнопки --}}
    <div>
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded">Применить</button>
        @if(request()->hasAny(['course_id','date','status']))
            <a href="{{ route('admin.sessions.index') }}" class="ml-2 text-sm text-gray-600 underline">Сбросить</a>
        @endif
    </div>
</form>



    {{-- Таблица занятий --}}
    <div class="overflow-x-auto bg-white shadow rounded-lg">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="text-left px-4 py-3">Дата</th>
                    <th class="text-left px-4 py-3">Начало</th>
                    <th class="text-left px-4 py-3">Длительность</th>
                    <th class="text-left px-4 py-3">Курс</th>
                    <th class="text-left px-4 py-3">Статус</th>
                    <th class="text-left px-4 py-3">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3">{{ $session->date }}</td>
                        <td class="px-4 py-3">{{ $session->start_time }}</td>
                        <td class="px-4 py-3">{{ $session->duration_minutes }} мин</td>
                        <td class="px-4 py-3">{{ $session->course->title ?? '-' }}</td>
                        <td class="px-4 py-3 capitalize text-gray-700">{{ $session->status }}</td>
                        <td class="px-4 py-3 flex gap-2">
                            <a href="{{ route('admin.sessions.edit', $session->id) }}" class="text-blue-600 hover:underline">Редактировать</a>
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3 text-gray-500" colspan="6">Сессий не найдено.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Пагинация --}}
    <div class="mt-6">
        {{ $sessions->appends(request()->query())->links() }}
    </div>
</div>

{{-- JS: поиск занятий по курсу/дате + подсказка времени окончания --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterCourse = document.getElementById('filter_course_id');
    const filterDate   = document.getElementById('filter_date');
    const filterBtn    = document.getElementById('filter_btn');
    const list         = document.getElementById('found_sessions');

    const courseSelect = document.getElementById('course_id');
    const dateInput    = document.getElementById('date');

    function renderSessions(items) {
        list.innerHTML = '';
        if (!items || !items.length) {
            const li = document.createElement('li');
            li.textContent = 'Ничего не найдено';
            li.className = 'text-gray-500';
            list.appendChild(li);
            return;
        }
        items.forEach(s => {
            const li = document.createElement('li');
            const time = (s.start_time || '').slice(0,5) + (s.end_time ? '–' + s.end_time.slice(0,5) : '');
            const dur = s.duration_minutes ? ` (${s.duration_minutes} мин)` : '';
            li.textContent = `${s.date} • ${time}${dur} • ${s.status === 'cancelled' ? 'отменено' : 'активно'}`;
            list.appendChild(li);
        });
    }

    function fetchSessions() {
        const cid = filterCourse.value;
        let url = `/admin/api/courses/${cid}/sessions`;
        const d = filterDate.value;
        if (d) url += `?date=${encodeURIComponent(d)}`;

        fetch(url)
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => renderSessions(data))
            .catch(() => renderSessions([]));
    }

    // синхронизируем выбранный курс фильтра с формой (удобно)
    filterCourse.addEventListener('change', () => {
        courseSelect.value = filterCourse.value;
        fetchSessions();
    });

    filterDate.addEventListener('change', fetchSessions);
    filterBtn.addEventListener('click', fetchSessions);

    // Инициализация: выставим в фильтре тот же курс, что и в форме
    filterCourse.value = courseSelect.value;
    // Если дату уже ввели в форме — подставим её в фильтр
    if (dateInput.value) filterDate.value = dateInput.value;

    // Первая загрузка
    fetchSessions();
});
</script>
@endsection
