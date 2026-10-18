{{-- resources/views/admin/terms/reports.blade.php
     Жалобы учеников из квиза по признакам (см. Admin\Terms\ReportController).
     Признаки правятся в docs/terms/egkh_terms_18.json и заливаются
     php artisan terms:import — здесь только разбор. --}}
@extends('admin.layouts.main')

@section('title', 'Жалобы — тренажёр терминов')

@section('content')
<x-ui.page-header title="Тренажёр терминов">
    Задание 18 ЕГЭ по обществознанию.
</x-ui.page-header>

@include('admin.terms._tabs')

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <x-ui.tabs>
        <x-ui.tab href="{{ route('admin.terms.reports.index') }}" :active="!$resolved">Новые</x-ui.tab>
        <x-ui.tab href="{{ route('admin.terms.reports.index', ['tab' => 'resolved']) }}" :active="$resolved">Разобранные</x-ui.tab>
    </x-ui.tabs>
    <p class="text-sm text-zinc-500">Жалобы из квиза «Какой признак подходит?». Правка признаков — в базе терминов и <code>terms:import</code>.</p>
</div>

@if($reports->isEmpty())
    <x-ui.empty>{{ $resolved ? 'Разобранных жалоб пока нет.' : 'Новых жалоб нет.' }}</x-ui.empty>
@else
    <div class="space-y-4">
        @foreach($reports as $report)
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <div class="sans-medium text-base text-zinc-900">
                            {{ $report->term?->title }}@if($report->term?->qualifier)<span class="text-zinc-400"> · {{ $report->term->qualifier }}</span>@endif
                        </div>
                        <div class="text-xs text-zinc-500 mt-0.5">
                            {{ $report->created_at->format('d.m.Y H:i') }} · {{ $report->user?->name ?? 'ученик удалён' }}
                            @if(!$resolved && ($sameFeature[$report->feature_id] ?? 0) > 1)
                                · <span class="text-rose-600 font-medium">на этот признак жалоб: {{ $sameFeature[$report->feature_id] }}</span>
                            @endif
                        </div>
                    </div>
                    <x-ui.action-form :action="route('admin.terms.reports.update', $report)" method="PATCH" variant="secondary">
                        {{ $resolved ? 'Вернуть в новые' : 'Разобрано' }}
                    </x-ui.action-form>
                </div>

                @if($report->comment)
                    <div class="rounded-xl bg-amber-50 border border-amber-100 px-3 py-2 text-sm text-zinc-800 mb-3">«{{ $report->comment }}»</div>
                @endif

                <div class="text-xs uppercase tracking-wide text-zinc-400 mb-1.5">Что видел ученик</div>
                <ul class="space-y-1.5 text-sm">
                    <li class="flex gap-2">
                        <span class="shrink-0 text-emerald-600 font-medium w-20">Верный</span>
                        <span class="text-zinc-800">{{ $report->feature?->text }}</span>
                    </li>
                    @foreach($report->distractor_ids as $id)
                        @php($d = $distractors[$id] ?? null)
                        <li class="flex gap-2">
                            <span class="shrink-0 text-zinc-400 w-20">Чужой</span>
                            <span class="text-zinc-600">
                                {{ $d?->text ?? 'признак удалён' }}
                                @if($d?->term)<span class="text-zinc-400"> — {{ $d->term->title }}@if($d->term->qualifier) · {{ $d->term->qualifier }}@endif</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endforeach
    </div>

    <div class="mt-6">{{ $reports->links() }}</div>
@endif
@endsection
