@extends('admin.crm.layout')

@section('title', 'CRM')

@section('content')
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900">CRM</h1>
        <a href="{{ route('admin.user.create') }}"
           class="rounded-lg px-4 py-2 bg-zinc-900 text-white hover:bg-zinc-800 transition sans-medium text-sm">
            + Пользователь
        </a>
    </div>

    <form method="GET" class="mb-5">
        <div class="flex gap-2 max-w-md">
            <input
                type="text"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="Поиск: имя, email, телефон…"
                class="w-full border rounded-lg px-3 py-2 input-focus sans text-sm"
            >
            <button class="rounded-lg px-3 py-2 border sans text-sm shrink-0">Искать</button>
            @if(!empty($q))
                <a href="{{ route('admin.crm.index') }}" class="rounded-lg px-3 py-2 border sans text-sm text-zinc-500 shrink-0">Сброс</a>
            @endif
        </div>
    </form>

    @if(session('success'))
        <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 sans">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white border rounded-2xl shadow-sm p-4 md:p-5 mb-5">
        <div class="flex flex-wrap gap-x-8 gap-y-4">
            <div>
                <div class="sans text-xs text-zinc-400 uppercase tracking-wide">Всего пользователей</div>
                <div class="sans-medium text-2xl text-zinc-900 mt-0.5">{{ $totalUsers }}</div>
            </div>
            <div>
                <div class="sans text-xs text-zinc-400 uppercase tracking-wide">Доход за {{ now()->translatedFormat('F') }}</div>
                <div class="sans-medium text-2xl text-zinc-900 mt-0.5">{{ number_format($monthlyRevenueRub, 0, ',', ' ') }} ₽</div>
            </div>
            {{-- <div class="flex-1 min-w-[280px]">
                <div class="sans text-xs text-zinc-400 uppercase tracking-wide mb-2">Ученики по курсам</div>
                <div class="overflow-x-auto">
                    <table class="text-sm sans w-full">
                        <thead>
                            <tr class="text-left text-zinc-400 text-xs">
                                <th class="font-normal pr-4 pb-1">Курс</th>
                                <th class="font-normal pb-1">Учеников</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse($courseStats as $courseStat)
                                <tr>
                                    <td class="pr-4 py-1 text-zinc-800">{{ $courseStat['title'] }}</td>
                                    <td class="py-1 text-zinc-600">{{ $courseStat['students'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="py-1 text-zinc-400">Курсов нет</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div> --}}
        </div>
    </div>

    <div class="space-y-4">
        @forelse($students as $student)
            @include('admin.crm.partials.student-card', ['student' => $student, 'number' => $student->crmNumber])
        @empty
            <div class="bg-white border rounded-2xl shadow-sm px-5 py-10 text-center text-zinc-500 sans text-sm">Ничего не найдено</div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $students->links() }}
    </div>
@endsection
