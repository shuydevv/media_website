{{-- resources/views/student/dashboard.blade.php --}}
@extends('layouts.main') {{-- замени на свой layout, если другой --}}

@php
    use Illuminate\Support\Carbon;

    function money_fmt($cents, $cur='RUB') {
        return number_format(($cents ?? 0)/100, 2, ',', ' ') . ' ' . $cur;
    }
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold mb-6">Личный кабинет</h1>

    @if(session('success'))
        <div class="mb-4 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    <h2 class="text-xl font-semibold mb-4">Мои курсы</h2>

    @if($courses->isEmpty())
        <div class="p-6 rounded-xl border bg-white text-gray-600">
            Пока нет активных курсов. Если у вас есть промокод — активируйте его на странице
            <a href="{{ route('promo.redeem.form') }}" class="text-blue-600 underline">Активация промокода</a>.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($courses as $course)
                @php
                    $expiresAt = $course->pivot->expires_at ? Carbon::parse($course->pivot->expires_at) : null;
                    $expiresSoon = $expiresAt && $expiresAt->isAfter(now()) && $expiresAt->diffInDays(now()) <= 3;
                    $next = $course->nextSession;
                @endphp

                <div class="rounded-2xl border bg-white p-4 flex flex-col">
                    {{-- обложка, если есть --}}
                    @if(!empty($course->main_image))
                        <img src="{{ asset('storage/'.$course->main_image) }}" alt="{{ $course->title }}"
                             class="w-32 h-32 object-cover rounded-xl mb-3">
                    @endif

                    <h3 class="font-semibold text-lg mb-1">{{ $course->title }}</h3>
                    <p class="text-sm text-gray-600 line-clamp-2 mb-3">{{ $course->description }}</p>

                    {{-- статус и сроки --}}
                    <div class="text-sm space-y-1 mb-3">
                        <div>
                            <span class="text-gray-600">Статус:</span>
                            <span class="font-medium">Активен</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-gray-600">Доступ до:</span>
                            @if($expiresAt)
                                <span class="font-medium">{{ $expiresAt->format('d.m.Y H:i') }}</span>
                                @if($expiresSoon)
                                    <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-700">истекает скоро</span>
                                @endif
                            @else
                                <span class="text-gray-500">без ограничений</span>
                            @endif
                        </div>

@php
    $isLive = false;
    if ($next) {
        $start = \Illuminate\Support\Carbon::parse($next->date.' '.$next->start_time);
        $end   = \Illuminate\Support\Carbon::parse($next->date.' '.$next->end_time);
        // эфир: от начала и до 10 минут после конца
        $isLive = now()->between($start, $end->copy()->addMinutes(10));
    }
@endphp

<div class="flex items-start gap-2">
    <span class="text-gray-600 mt-0.5">Ближайшее занятие:</span>

    @if($next)
        <div class="space-y-2">
            <div class="font-medium">
                {{ \Illuminate\Support\Carbon::parse($next->date)->format('d.m.Y') }},
                {{ substr($next->start_time,0,5) }}–{{ substr($next->end_time,0,5) }}
            </div>

            @if($next->lesson)
                <div class="text-sm">
                    <div><span class="text-gray-600">Тема:</span> {{ $next->lesson->title ?? '—' }}</div>
                    @if($course->category)
                        <div><span class="text-gray-600">Предмет:</span> {{ $course->category->title }}</div>
                    @endif
                    @if(!empty($next->lesson->description))
                        <div class="text-gray-600 line-clamp-2">{{ $next->lesson->description }}</div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if($next->lesson->meet_link && $isLive)
                        <a href="{{ $next->lesson->meet_link }}" target="_blank"
                           class="px-3 py-1.5 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">
                            Перейти в эфир
                        </a>
                    @elseif($next->lesson->recording_link)
                        <a href="{{ $next->lesson->recording_link }}" target="_blank"
                           class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                            Смотреть запись
                        </a>
                    @endif

                    @if($next->lesson->notes_link)
                        <a href="{{ $next->lesson->notes_link }}" target="_blank"
                           class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">
                            Конспект
                        </a>
                    @endif
                </div>
            @else
                <div class="text-sm text-gray-500">урок пока не привязан</div>
            @endif
        </div>
    @else
        <span class="text-gray-500">нет</span>
    @endif
</div>

                    </div>

                    <div class="mt-auto flex gap-2">
                        @if(Route::has('student.courses.show'))
                            <a href="{{ route('student.courses.show', $course) }}"
                            class="inline-flex items-center px-3 py-1.5 text-sm rounded-xl bg-blue-100 border border-blue-200 text-blue-800 hover:bg-blue-200">
                            Перейти к курсу
                            </a>

                        @endif

                        @if(isset($expiresSoon) && $expiresSoon && Route::has('checkout.course.show'))
                            <a href="{{ route('checkout.course.show', $course) }}"
                               class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                Продлить доступ
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
