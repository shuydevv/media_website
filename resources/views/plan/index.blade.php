@extends('layouts.main')
@section('title')
Планы ЕГЭ по обществознанию — Школа Полтавского
@endsection
@section('description')
Готовые планы для ЕГЭ по обществознанию, разбитые по разделам и темам курса.
@endsection
@section('content')
        <div class="container mx-auto max-w-screen-md md:mt-20 mt-12 md:mb-20 mb-16 px-3">
            <a class="noclass sans text-sm text-zinc-500 hover:text-zinc-900" href="{{ route('post.index') }}">← Все статьи</a>
            <h1 class="md:text-3xl text-2xl font-medium md:mt-6 mt-4 md:mb-6 mb-4 tracking-wide text-zinc-900"><span class="sans">Планы ЕГЭ по обществознанию</span></h1>
            <x-text text="Готовые планы по разделам и темам курса — для подготовки к заданию с планом и для повторения." />

            <div class="md:mt-14 mt-10">
                @if ($groups->isEmpty())
                    <p class="sans text-zinc-500">Планов пока нет.</p>
                @else
                    @include('plan._list', ['groups' => $groups])
                @endif
            </div>
        </div>

        <x-material></x-material>
        <x-footer />
@endsection
