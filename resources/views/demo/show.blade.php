{{-- resources/views/demo/show.blade.php
     Скрытая страница управления демо-режимом (DemoController). Видна
     только демо-ученику. Не ученическая: сюда ведущий заходит перед
     показом или между частями, на экране покупателя её можно не открывать. --}}
@extends('layouts.main')

@section('title', 'Демо-режим — Школа Полтавского')
@section('back_url', route('student.dashboard'))

@section('content')
<div class="max-w-xl mx-auto px-4 py-6 space-y-4">
    <h1 class="text-2xl font-semibold text-zinc-900">Демо-режим</h1>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 space-y-3">
        <h2 class="text-lg font-semibold text-zinc-900">Сейчас</h2>
        <div class="text-sm text-zinc-700 space-y-1">
            <div>Рыба: {{ $fishLevelName }}, уровень {{ $fishLevel }}</div>
            <div>Корм на балансе: {{ (int) $user->fish_corm_balance }}</div>
            @if ($toNextLevel !== null)
                <div>До следующего уровня: скормить ещё {{ $toNextLevel }}</div>
            @endif
            <div>Ждут повторения сегодня: {{ $summary['due'] }}</div>
        </div>
        <div class="flex flex-wrap gap-2 pt-1">
            @foreach ($summary['learned'] as $label => $count)
                <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs text-zinc-700">{{ $label }}: {{ $count }}</span>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 space-y-3">
        <h2 class="text-lg font-semibold text-zinc-900">Наступило завтра</h2>
        <p class="text-sm text-zinc-600">
            Сдвигает весь прогресс на день назад: изученное вчера ждёт повторения,
            раунды с кормом и бонус за визит снова доступны. Можно нажимать несколько раз.
        </p>
        <form method="POST" action="{{ route('demo.next-day') }}">
            @csrf
            <button type="submit" class="rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                Наступило завтра
            </button>
        </form>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 space-y-3">
        <h2 class="text-lg font-semibold text-zinc-900">Начать заново</h2>
        <p class="text-sm text-zinc-600">
            Стирает прогресс и возвращает стартовое состояние. То же самое
            происходит само при выходе из аккаунта.
        </p>
        <form method="POST" action="{{ route('demo.reset') }}" onsubmit="return confirm('Сбросить весь демо-прогресс?')">
            @csrf
            <button type="submit" class="rounded-xl border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-zinc-50">
                Сбросить сейчас
            </button>
        </form>
    </section>

    <a href="{{ route('student.dashboard') }}" class="inline-block text-sm text-zinc-600 underline">В кабинет</a>
</div>
@endsection
