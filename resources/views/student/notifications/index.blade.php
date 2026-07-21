{{-- resources/views/student/notifications/index.blade.php
     Список уведомлений ученика — карточки в том же стиле, что и на странице
     "Домашки" (resources/views/student/homeworks/index.blade.php): белые
     rounded-2xl карточки, цветные бейджи по типу, амбер-акцент. --}}
@extends('layouts.main')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    <div class="flex items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-semibold">Уведомления</h1>
        @if($notifications->contains(fn($n) => $n->read_at === null))
            <form method="POST" action="{{ route('student.notifications.markAllRead') }}">
                @csrf
                <button type="submit" class="text-sm text-amber-700 hover:underline">Прочитать всё</button>
            </form>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="p-6 rounded-2xl border bg-white text-gray-600 text-center">
            Уведомлений пока нет
        </div>
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
                    <button type="submit" class="w-full text-left flex items-start justify-between gap-4 rounded-2xl border bg-white p-4 hover:border-amber-300 hover:shadow-sm transition {{ $isUnread ? 'border-amber-200 bg-amber-50/40' : '' }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                @if($isUnread)
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                @endif
                                <span class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="font-medium text-gray-900">{{ $data['title'] ?? 'Уведомление' }}</div>
                            @if(!empty($data['body']))
                                <div class="text-sm text-gray-500 mt-0.5">{{ $data['body'] }}</div>
                            @endif
                        </div>
                        @if($actionUrl)
                            <span class="shrink-0 text-sm font-medium text-amber-700 whitespace-nowrap">Открыть →</span>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
