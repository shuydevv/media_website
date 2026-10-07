@extends('admin.layouts.main')

@section('title', 'Оповещения')

@section('content')
    <x-ui.page-header title="Оповещения">
        Баннер в шапке кабинета ученика — например, о переносе занятия.
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.announcements.create') }}" size="xs">
                <x-icon name="plus" class="w-4 h-4" />
                Создать оповещение
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-3 max-w-3xl">
        @forelse ($announcements as $announcement)
            @php
                $isExpired = $announcement->expires_at && $announcement->expires_at->isPast();
                $isLive = $announcement->is_active && !$isExpired;
            @endphp
            <x-ui.card :tone="$isLive ? 'white' : 'gray'">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    @if (!$announcement->is_active)
                        <x-ui.badge>Деактивировано</x-ui.badge>
                    @elseif ($isExpired)
                        <x-ui.badge>Истекло {{ $announcement->expires_at->format('d.m.Y H:i') }}</x-ui.badge>
                    @elseif ($announcement->expires_at)
                        <x-ui.badge tone="green">Активно до {{ $announcement->expires_at->format('d.m.Y H:i') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="green">Активно, без срока</x-ui.badge>
                    @endif
                    <span class="text-xs text-zinc-500">
                        {{ $announcement->all_students ? 'Все ученики' : $announcement->courses->pluck('title')->join(', ') }}
                    </span>
                </div>

                <p class="{{ $isLive ? 'text-zinc-900' : 'text-zinc-500' }} whitespace-pre-line break-words">{{ $announcement->message }}</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($isLive)
                        <x-ui.action-form :action="route('admin.announcements.deactivate', $announcement)" method="PATCH" variant="secondary">Деактивировать</x-ui.action-form>
                    @endif
                    <x-ui.action-form :action="route('admin.announcements.destroy', $announcement)" confirm="Удалить оповещение безвозвратно?">
                        <span class="text-apple-red-650">Удалить</span>
                    </x-ui.action-form>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty>Оповещений пока нет.</x-ui.empty>
        @endforelse
    </div>
@endsection
