@extends('admin.crm.layout')

@section('title', 'CRM')

@section('content')
    @php
        $hasFilters = !empty($q) || !empty($status) || !empty($dateFrom) || !empty($dateTo) || ($sort ?? 'urgency') !== 'urgency' || !empty($soonOnly) || !empty($remindersOnly);
    @endphp

    <x-ui.page-header title="CRM">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.user.create') }}" size="xs">
                <x-icon name="plus" class="w-4 h-4" />
                Пользователь
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.tabs class="mb-4">
        <x-ui.tab href="{{ route('admin.crm.index') }}" :active="true">В работе</x-ui.tab>
        <x-ui.tab href="{{ route('admin.crm.archive') }}">Завершили / отказались</x-ui.tab>
    </x-ui.tabs>

    <form method="GET" class="mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.input name="q" type="search" value="{{ $q ?? '' }}" placeholder="Поиск: имя, email, телефон…" wrap="flex-1 min-w-[220px]" />
            <x-ui.button type="submit" size="xs">Искать</x-ui.button>
        </div>

        {{-- Остальные фильтры на телефоне свёрнуты под одну кнопку — иначе они
             занимают весь первый экран; раскрыты сами, если хоть один задан. --}}
        <button type="button" class="md:hidden inline-flex items-center gap-1.5 min-h-11 text-sm text-zinc-600"
                data-crm-filters-toggle aria-expanded="{{ $hasFilters ? 'true' : 'false' }}"
                onclick="var box = this.nextElementSibling; var hidden = box.classList.toggle('max-md:hidden'); this.setAttribute('aria-expanded', hidden ? 'false' : 'true');">
            Фильтры и сортировка
            <x-icon name="chevron-right" class="w-4 h-4 rotate-90" />
            @if($hasFilters)<x-ui.badge tone="blue">заданы</x-ui.badge>@endif
        </button>
        <div class="mt-1 md:mt-2 flex flex-wrap items-center gap-2 {{ $hasFilters ? '' : 'max-md:hidden' }}" data-crm-filters>
            <x-ui.select name="status" wrap="w-full sm:w-auto" aria-label="Статус">
                <option value="">Все статусы</option>
                @foreach($statusOptions as $key => $opt)
                    <option value="{{ $key }}" @selected(($status ?? '') === $key)>
                        {{ $opt['label'] }} ({{ $statusCounts[$key] ?? 0 }})
                    </option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="sort" wrap="w-full sm:w-auto" aria-label="Сортировка">
                @foreach($sortOptions as $key => $label)
                    <option value="{{ $key }}" @selected(($sort ?? 'urgency') === $key)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <x-ui.input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" title="Регистрация с" aria-label="Регистрация с" wrap="flex-1 sm:flex-none sm:w-40" />
                <span class="text-zinc-300">–</span>
                <x-ui.input type="date" name="date_to" value="{{ $dateTo ?? '' }}" title="Регистрация по" aria-label="Регистрация по" wrap="flex-1 sm:flex-none sm:w-40" />
            </div>
            @if($hasFilters)
                <x-ui.button href="{{ route('admin.crm.index') }}" variant="secondary" size="xs">Сброс</x-ui.button>
            @endif
        </div>
        @if($soonOnly)
            <input type="hidden" name="soon" value="1">
        @endif
        @if($remindersOnly)
            <input type="hidden" name="reminders" value="1">
        @endif
    </form>

    <x-ui.card class="mb-5">
        <div class="flex flex-wrap gap-x-6 sm:gap-x-8 gap-y-4">
            <x-ui.stat label="Пользователей">{{ $totalUsers }}</x-ui.stat>
            <x-ui.stat :label="'Доход за '.now()->translatedFormat('F')">{{ number_format($monthlyRevenueRub, 0, ',', ' ') }} ₽</x-ui.stat>
            <div class="flex-1 min-w-[260px]">
                <div class="sans-medium text-xs text-zinc-400 uppercase tracking-wide mb-2">По статусам</div>
                <div class="flex flex-wrap gap-1.5">
                    @php
                        $badgeColorClasses = [
                            'gray'    => 'bg-gray-100 text-gray-700 border-gray-300',
                            'blue'    => 'bg-blue-50 text-blue-700 border-blue-300',
                            'amber'   => 'bg-amber-50 text-amber-700 border-amber-300',
                            'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-300',
                            'rose'    => 'bg-rose-50 text-rose-700 border-rose-300',
                        ];
                        $chipClass = 'inline-flex items-center gap-1 px-2.5 min-h-9 md:min-h-7 rounded-full border text-xs sans-medium transition';
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
                           class="{{ $chipClass }} {{ $badgeColorClasses[$opt['color']] }} {{ $isActiveBadge ? 'ring-2 ring-offset-1 ring-zinc-400' : 'opacity-80 hover:opacity-100' }}">
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
                       class="{{ $chipClass }} {{ $badgeColorClasses['amber'] }} {{ $soonOnly ? 'ring-2 ring-offset-1 ring-zinc-400' : 'opacity-80 hover:opacity-100' }}">
                        Скоро истекает
                        <span class="opacity-70">{{ $soonCount }}</span>
                    </a>
                    @php
                        $remindersTarget = $filterParams;
                        if ($remindersOnly) {
                            unset($remindersTarget['reminders']);
                        } else {
                            $remindersTarget['reminders'] = 1;
                        }
                    @endphp
                    <a href="{{ route('admin.crm.index', $remindersTarget) }}"
                       title="Наступившие напоминания, поставленные в карточках учеников"
                       class="{{ $chipClass }} {{ $badgeColorClasses['amber'] }} {{ $remindersOnly ? 'ring-2 ring-offset-1 ring-zinc-400' : 'opacity-80 hover:opacity-100' }}">
                        Уведомления
                        <span class="opacity-70">{{ $reminderCount }}</span>
                    </a>
                </div>
            </div>
        </div>
    </x-ui.card>

    <div class="space-y-4">
        @forelse($students as $student)
            @include('admin.crm.partials.student-card', ['student' => $student, 'number' => $student->crmNumber])
        @empty
            <x-ui.empty>Ничего не найдено</x-ui.empty>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $students->links('pagination.ui') }}
    </div>
@endsection
