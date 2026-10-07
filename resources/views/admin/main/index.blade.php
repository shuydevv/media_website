@extends('admin.layouts.main')

@section('title', 'Главная')

@section('content')
    <x-ui.page-header title="Главная">
        {{ now()->translatedFormat('l, j F') }}
    </x-ui.page-header>

    {{-- Что требует внимания — каждая плитка ведёт в раздел с уже включённым фильтром. --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
        <x-ui.card-link href="{{ route('admin.crm.index', ['reminders' => 1]) }}" :highlighted="$dueReminders->isNotEmpty()">
            <x-ui.stat label="Напоминания">
                {{ $dueReminders->count() }}
                <x-slot:sub>наступили в CRM</x-slot:sub>
            </x-ui.stat>
        </x-ui.card-link>
        <x-ui.card-link href="{{ route('mentor.submissions.index') }}" :highlighted="$pendingReviewCount > 0">
            <x-ui.stat label="На проверке">
                {{ $pendingReviewCount }}
                <x-slot:sub>работ ждут куратора</x-slot:sub>
            </x-ui.stat>
        </x-ui.card-link>
        <x-ui.card-link href="{{ route('admin.crm.index', ['status' => 'past_due']) }}" :highlighted="$pastDueCount > 0">
            <x-ui.stat label="Просрочена оплата">
                {{ $pastDueCount }}
                <x-slot:sub>учеников</x-slot:sub>
            </x-ui.stat>
        </x-ui.card-link>
        <x-ui.card-link href="{{ route('admin.crm.index', ['soon' => 1]) }}" :highlighted="$soonCount > 0">
            <x-ui.stat label="Скоро истекает">
                {{ $soonCount }}
                <x-slot:sub>доступ в ближайшие {{ \App\Models\User::CRM_SOON_THRESHOLD_DAYS }} дн.</x-slot:sub>
            </x-ui.stat>
        </x-ui.card-link>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
        <x-ui.card>
            <div class="flex items-baseline justify-between gap-3 mb-3">
                <h2 class="sans-medium text-lg text-zinc-900">Напоминания на сегодня</h2>
                <a href="{{ route('admin.crm.index', ['reminders' => 1]) }}" class="text-sm text-zinc-500 hover:text-zinc-900 shrink-0">Все в CRM</a>
            </div>
            @forelse ($dueReminders->take(8) as $row)
                @php
                    $student = $row['student'];
                    $studentName = trim(($student->first_name ?? '').' '.($student->last_name ?? '')) ?: ($student->name ?: 'Ученик #'.$student->id);
                @endphp
                <a href="{{ route('admin.crm.index', ['q' => $student->email ?: $studentName]) }}"
                   class="flex items-start gap-3 py-2.5 border-t border-zinc-100 first:border-t-0 hover:bg-zinc-50 -mx-2 px-2 rounded-lg">
                    <x-icon name="bell-01" class="w-4 h-4 mt-0.5 shrink-0 text-apple-orange-500" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-zinc-900">{{ $row['reminder']->note }}</span>
                        <span class="block text-xs text-zinc-500 mt-0.5">{{ $studentName }} · {{ \App\Support\CrmDate::format($row['reminder']->due_at) }}</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-zinc-500">Наступивших напоминаний нет.</p>
            @endforelse
            @if ($dueReminders->count() > 8)
                <p class="text-xs text-zinc-500 mt-2">и ещё {{ $dueReminders->count() - 8 }}</p>
            @endif
        </x-ui.card>

        <x-ui.card>
            <div class="flex items-baseline justify-between gap-3 mb-3">
                <h2 class="sans-medium text-lg text-zinc-900">Ближайшие занятия</h2>
                <a href="{{ route('admin.sessions.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900 shrink-0">Все сессии</a>
            </div>
            @forelse ($sessions as $session)
                @php $sessionDate = \Illuminate\Support\Carbon::parse($session->date); @endphp
                <div class="flex items-start gap-3 py-2.5 border-t border-zinc-100 first:border-t-0">
                    <div class="w-16 shrink-0">
                        <div class="text-sm text-zinc-900">{{ $sessionDate->isToday() ? 'сегодня' : ($sessionDate->isTomorrow() ? 'завтра' : $sessionDate->translatedFormat('j M')) }}</div>
                        <div class="text-xs text-zinc-500">{{ substr((string) $session->start_time, 0, 5) }}</div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm text-zinc-900 truncate">{{ $session->lesson?->title ?: 'Урок не создан' }}</div>
                        <div class="text-xs text-zinc-500 truncate">{{ $session->course?->title ?? '—' }}</div>
                    </div>
                    @if ($session->lesson)
                        <a href="{{ route('admin.lessons.edit', $session->lesson->id) }}" class="text-zinc-400 hover:text-zinc-900 shrink-0 p-1 -m-1" title="Открыть урок">
                            <x-icon name="edit-02" class="w-4 h-4" />
                        </a>
                    @else
                        <x-ui.badge tone="orange">нет урока</x-ui.badge>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-500">На ближайшую неделю занятий не запланировано.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <h2 class="sans-medium text-lg text-zinc-900 mb-3">Школа сейчас</h2>
            <div class="grid grid-cols-2 gap-4">
                <x-ui.stat label="Активных учеников">{{ $activeCount }}</x-ui.stat>
                <x-ui.stat label="Заявки в работе">{{ $leadCount }}</x-ui.stat>
                <x-ui.stat :label="'Доход за '.now()->translatedFormat('F')" class="col-span-2">{{ number_format($monthlyRevenueRub, 0, ',', ' ') }} ₽</x-ui.stat>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="sans-medium text-lg text-zinc-900 mb-3">Быстрые действия</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <x-ui.button href="{{ route('admin.user.create') }}" variant="secondary" size="xs" block><x-icon name="plus" class="w-4 h-4" />Пользователь</x-ui.button>
                <x-ui.button href="{{ route('admin.homeworks.create') }}" variant="secondary" size="xs" block><x-icon name="plus" class="w-4 h-4" />Домашка</x-ui.button>
                <x-ui.button href="{{ route('admin.lessons.create') }}" variant="secondary" size="xs" block><x-icon name="plus" class="w-4 h-4" />Урок</x-ui.button>
                <x-ui.button href="{{ route('admin.tasks.create') }}" variant="secondary" size="xs" block><x-icon name="plus" class="w-4 h-4" />Задание в банк</x-ui.button>
                <x-ui.button href="{{ route('admin.announcements.create') }}" variant="secondary" size="xs" block><x-icon name="plus" class="w-4 h-4" />Оповещение</x-ui.button>
                <x-ui.button href="{{ route('admin.promos.create') }}" variant="secondary" size="xs" block><x-icon name="plus" class="w-4 h-4" />Промокод</x-ui.button>
            </div>
        </x-ui.card>
    </div>
@endsection
