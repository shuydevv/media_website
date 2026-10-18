{{-- Вкладки раздела «Термины» в админке: фото, доступ, жалобы (со счётчиком
     неразобранных). --}}
@php
    $openReports = \App\Models\TermReport::whereNull('resolved_at')->count();
@endphp
<x-ui.tabs class="mb-5">
    <x-ui.tab href="{{ route('admin.terms.photos.index') }}" :active="request()->routeIs('admin.terms.photos.*')">Фото</x-ui.tab>
    <x-ui.tab href="{{ route('admin.terms.access.index') }}" :active="request()->routeIs('admin.terms.access.*')">Доступ</x-ui.tab>
    <x-ui.tab href="{{ route('admin.terms.reports.index') }}" :active="request()->routeIs('admin.terms.reports.*')">
        Жалобы
        @if($openReports)
            <span class="min-w-5 h-5 px-1.5 rounded-full bg-rose-500 text-white text-xs leading-5 text-center">{{ $openReports }}</span>
        @endif
    </x-ui.tab>
</x-ui.tabs>
