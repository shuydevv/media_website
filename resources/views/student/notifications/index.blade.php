{{-- resources/views/student/notifications/index.blade.php
     Список уведомлений ученика — карточки в том же стиле, что и на странице
     "Домашки" (resources/views/student/homeworks/index.blade.php): белые
     rounded-2xl карточки, цветные бейджи по типу, амбер-акцент. --}}
@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900">Уведомления</h1>
        @if($notifications->contains(fn($n) => $n->read_at === null))
            <form method="POST" action="{{ route('student.notifications.markAllRead') }}">
                @csrf
                <button type="submit" class="text-sm text-amber-700 hover:underline">Прочитать всё</button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <x-ui.card class="text-zinc-600 text-center">
            Уведомлений пока нет
        </x-ui.card>
    @else
        <div class="flex flex-col gap-3">
            @php
                $badgeMap = [
                    'homework_graded'  => ['label' => 'Домашка', 'class' => 'bg-emerald-50 text-emerald-700'],
                    'homework_due'     => ['label' => 'Домашка', 'class' => 'bg-amber-50 text-amber-700'],
                    'payment_overdue'  => ['label' => 'Оплата', 'class' => 'bg-rose-50 text-rose-700'],
                    'payment_due'      => ['label' => 'Оплата', 'class' => 'bg-amber-50 text-amber-700'],
                    'promise_expiring' => ['label' => 'Оплата', 'class' => 'bg-rose-50 text-rose-700'],
                    'payment_confirmed'=> ['label' => 'Оплата', 'class' => 'bg-emerald-50 text-emerald-700'],
                    'lesson_soon'      => ['label' => 'Урок', 'class' => 'bg-blue-50 text-blue-700'],
                    'recording'        => ['label' => 'Урок', 'class' => 'bg-blue-50 text-blue-700'],
                    'enrolled'         => ['label' => 'Курс', 'class' => 'bg-emerald-50 text-emerald-700'],
                ];
            @endphp

            @foreach($notifications as $notification)
                @php
                    $data = $notification->data;
                    $badge = $badgeMap[$data['icon'] ?? ''] ?? ['label' => 'Уведомление', 'class' => 'bg-gray-100 text-gray-700'];
                    $isUnread = $notification->read_at === null;
                    $actionUrl = $data['action_url'] ?? null;
                @endphp

                <form method="POST" action="{{ route('student.notifications.markRead', $notification->id) }}">
                    @csrf
                    <x-ui.card-link :highlighted="$isUnread">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                @if($isUnread)
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                @endif
                                <span class="text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="font-medium text-zinc-900">{{ $data['title'] ?? 'Уведомление' }}</div>
                            @if(!empty($data['body']))
                                <div class="text-sm text-zinc-500 mt-0.5">{{ $data['body'] }}</div>
                            @endif
                        </div>
                        @if($actionUrl)
                            <span class="shrink-0 text-sm font-medium text-amber-700 whitespace-nowrap">Открыть →</span>
                        @endif
                    </x-ui.card-link>
                </form>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
