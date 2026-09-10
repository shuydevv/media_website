@extends('admin.layouts.main')

@section('content')
    <div>
        <h1 class="text-xl sans mb-4">Оповещения</h1>

        <a href="{{route('admin.announcements.create')}}"><button class="mb-6 p-2 px-4 bg-zinc-200 hover:bg-zinc-300">Создать оповещение</button></a>

        @forelse ($announcements as $announcement)
            @php
                $isExpired = $announcement->expires_at && $announcement->expires_at->isPast();
                $isLive = $announcement->is_active && !$isExpired;
            @endphp
            <div class="mt-4 p-3 border {{ $isLive ? '' : 'opacity-50' }}">
                <p class="text-zinc-900">{{ $announcement->message }}</p>
                <p class="mt-2 text-sm text-zinc-500">
                    {{ $announcement->all_students ? 'Все ученики' : $announcement->courses->pluck('title')->join(', ') }}
                </p>
                <p class="mt-1 text-sm text-zinc-500">
                    @if (!$announcement->is_active)
                        Деактивировано
                    @elseif ($isExpired)
                        Истекло {{ $announcement->expires_at->format('d.m.Y H:i') }}
                    @elseif ($announcement->expires_at)
                        Активно до {{ $announcement->expires_at->format('d.m.Y H:i') }}
                    @else
                        Активно, без срока
                    @endif
                </p>
                <div class="mt-3 flex gap-4">
                    @if ($isLive)
                        <form action="{{route('admin.announcements.deactivate', $announcement)}}" method="post">
                            @csrf
                            @method('patch')
                            <button type="submit" class="text-sm text-zinc-600 hover:opacity-50">Деактивировать</button>
                        </form>
                    @endif
                    <form action="{{route('admin.announcements.destroy', $announcement)}}" method="post"
                          onsubmit="return confirm('Удалить оповещение безвозвратно?')">
                        @csrf
                        @method('delete')
                        <button type="submit" class="text-sm text-red-600 hover:opacity-50">Удалить</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="mt-2 text-zinc-400">Оповещений пока нет.</p>
        @endforelse
    </div>
@endsection
