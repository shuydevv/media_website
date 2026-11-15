@extends('admin.crm.layout')

@section('title', ($tab ?? 'archive') === 'excluded' ? 'CRM — не учитываются' : 'CRM — архив')

@section('content')
    @php
        $tab = $tab ?? 'archive';
        $listRoute = $tab === 'excluded' ? route('admin.crm.excluded') : route('admin.crm.archive');
    @endphp
    <x-ui.page-header title="CRM">
        @if($tab === 'excluded')
            Свои и тестовые аккаунты: доступ к курсам сохранён, но в статусы, счётчики и доход они не идут.
        @else
            Ученики, закрывшие цикл, — в основном списке CRM они не показываются.
        @endif
    </x-ui.page-header>

    @include('admin.crm.partials.tabs', ['active' => $tab])

    <form method="GET" class="mb-5 flex flex-wrap gap-2 max-w-xl">
        <x-ui.input name="q" type="search" value="{{ $q ?? '' }}" placeholder="Поиск: имя, email, телефон…" wrap="flex-1 min-w-[200px]" data-crm-live-search autocomplete="off" />
        @if(!empty($q))
            <x-ui.button href="{{ $listRoute }}" variant="secondary" size="xs">Сброс</x-ui.button>
        @endif
    </form>

    <div data-crm-results>
    @if(!empty($lostReasons) && $lostReasons->isNotEmpty() && empty($q))
        <x-ui.card class="mb-5">
            <div class="sans-medium text-xs text-zinc-400 uppercase tracking-wide mb-2">Почему отказываются</div>
            <div class="flex flex-wrap gap-x-5 gap-y-1 sans text-sm text-zinc-700">
                @foreach($lostReasons as $key => $count)
                    <span>{{ \App\Models\User::CRM_LOST_REASONS[$key] ?? 'Без причины (до 15.11.2026)' }}: <span class="sans-medium">{{ $count }}</span></span>
                @endforeach
            </div>
        </x-ui.card>
    @endif
    <div class="space-y-4">
        @forelse($students as $student)
            @include('admin.crm.partials.student-card', ['student' => $student])
        @empty
            <x-ui.empty>Пока никого нет</x-ui.empty>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $students->links('pagination.ui') }}
    </div>
    </div>
@endsection
