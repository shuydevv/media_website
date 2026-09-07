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
        <div class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="Поиск: имя, email, телефон…"
                class="flex-1 min-w-[220px] border rounded-lg px-3 py-2 input-focus sans text-sm"
            >
            <select name="status" class="border rounded-lg px-3 py-2 input-focus sans text-sm">
                <option value="">Все статусы</option>
                @foreach($statusOptions as $key => $opt)
                    <option value="{{ $key }}" {{ ($status ?? '') === $key ? 'selected' : '' }}>
                        {{ $opt['label'] }} ({{ $statusCounts[$key] ?? 0 }})
                    </option>
                @endforeach
            </select>
            <select name="sort" class="border rounded-lg px-3 py-2 input-focus sans text-sm">
                @foreach($sortOptions as $key => $label)
                    <option value="{{ $key }}" {{ ($sort ?? 'urgency') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1.5 border rounded-lg px-2 py-1.5">
                <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
                       class="input-focus sans text-sm border-0 p-0 w-[130px]" title="Регистрация с">
                <span class="text-zinc-300">–</span>
                <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
                       class="input-focus sans text-sm border-0 p-0 w-[130px]" title="Регистрация по">
            </div>
            <button class="rounded-lg px-3 py-2 border sans text-sm shrink-0">Искать</button>
            @if(!empty($q) || !empty($status) || !empty($dateFrom) || !empty($dateTo) || ($sort ?? 'urgency') !== 'urgency' || !empty($soonOnly))
                <a href="{{ route('admin.crm.index') }}" class="rounded-lg px-3 py-2 border sans text-sm text-zinc-500 shrink-0">Сброс</a>
            @endif
        </div>
        @if($soonOnly)
            <input type="hidden" name="soon" value="1">
        @endif
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
            <div class="flex-1 min-w-[280px]">
                <div class="sans text-xs text-zinc-400 uppercase tracking-wide mb-2">По статусам</div>
                <div class="flex flex-wrap gap-1.5">
                    @php
                        $badgeColorClasses = [
                            'gray'    => 'bg-gray-100 text-gray-700 border-gray-300',
                            'blue'    => 'bg-blue-50 text-blue-700 border-blue-300',
                            'amber'   => 'bg-amber-50 text-amber-700 border-amber-300',
                            'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-300',
                            'rose'    => 'bg-rose-50 text-rose-700 border-rose-300',
                        ];
                    @endphp
                    @foreach($statusOptions as $key => $opt)
                        @php
                            $isActiveBadge = ($status ?? '') === $key;
                            $target = $filterParams;
                            if ($isActiveBadge) {
                                unset($target['status']);
                            } else {
                                $target['status'] = $key;
                            }
                        @endphp
                        <a href="{{ route('admin.crm.index', $target) }}"
                           class="inline-flex items-center gap-1 px-2 py-1 rounded-full border text-xs sans-medium transition {{ $badgeColorClasses[$opt['color']] }} {{ $isActiveBadge ? 'ring-2 ring-offset-1 ring-zinc-400' : 'opacity-80 hover:opacity-100' }}">
                            {{ $opt['label'] }}
                            <span class="opacity-70">{{ $statusCounts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                    @php
                        $soonTarget = $filterParams;
                        if ($soonOnly) {
                            unset($soonTarget['soon']);
                        } else {
                            $soonTarget['soon'] = 1;
                        }
                    @endphp
                    <a href="{{ route('admin.crm.index', $soonTarget) }}"
                       title="Активный доступ, до истечения которого осталось не больше {{ \App\Models\User::CRM_SOON_THRESHOLD_DAYS }} дней"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-full border text-xs sans-medium transition {{ $badgeColorClasses['amber'] }} {{ $soonOnly ? 'ring-2 ring-offset-1 ring-zinc-400' : 'opacity-80 hover:opacity-100' }}">
                        Скоро истекает
                        <span class="opacity-70">{{ $soonCount }}</span>
                    </a>
                </div>
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
