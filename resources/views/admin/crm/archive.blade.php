@extends('admin.crm.layout')

@section('title', 'CRM — архив')

@section('content')
    <x-ui.page-header title="CRM">
        Ученики, закрывшие цикл, — в основном списке CRM они не показываются.
    </x-ui.page-header>

    <x-ui.tabs class="mb-4">
        <x-ui.tab href="{{ route('admin.crm.index') }}">В работе</x-ui.tab>
        <x-ui.tab href="{{ route('admin.crm.archive') }}" :active="true">Завершили / отказались</x-ui.tab>
    </x-ui.tabs>

    <form method="GET" class="mb-5 flex flex-wrap gap-2 max-w-xl">
        <x-ui.input name="q" type="search" value="{{ $q ?? '' }}" placeholder="Поиск: имя, email, телефон…" wrap="flex-1 min-w-[200px]" />
        <x-ui.button type="submit" size="xs">Искать</x-ui.button>
        @if(!empty($q))
            <x-ui.button href="{{ route('admin.crm.archive') }}" variant="secondary" size="xs">Сброс</x-ui.button>
        @endif
    </form>

    @php $total = $students->total(); @endphp

    <div class="space-y-4">
        @forelse($students as $student)
            @php
                $position = $students->firstItem() + $loop->index;
                $number = $total - $position + 1;
            @endphp
            @include('admin.crm.partials.student-card', ['student' => $student, 'number' => $number])
        @empty
            <x-ui.empty>Пока никого нет</x-ui.empty>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $students->links('pagination.ui') }}
    </div>
@endsection
