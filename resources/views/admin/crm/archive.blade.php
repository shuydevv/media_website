@extends('admin.crm.layout')

@section('title', 'CRM — завершили/отказались')

@section('content')
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900">Завершили / отказались</h1>
            <p class="text-sm text-zinc-500 sans mt-1">Ученики, закрывшие цикл — не показываются в основном списке CRM.</p>
        </div>
        <a href="{{ route('admin.crm.index') }}" class="rounded-lg px-3 py-2 border sans text-sm text-zinc-600 hover:bg-zinc-50">
            ← Назад в CRM
        </a>
    </div>

    <form method="GET" class="mb-5">
        <div class="flex gap-2 max-w-md">
            <input
                type="text"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="Поиск: имя, email, телефон…"
                class="w-full border rounded-lg px-3 py-2 input-focus sans text-sm"
            >
            <button class="rounded-lg px-3 py-2 border sans text-sm shrink-0">Искать</button>
            @if(!empty($q))
                <a href="{{ route('admin.crm.archive') }}" class="rounded-lg px-3 py-2 border sans text-sm text-zinc-500 shrink-0">Сброс</a>
            @endif
        </div>
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
            <div class="bg-white border rounded-2xl shadow-sm px-5 py-10 text-center text-zinc-500 sans text-sm">Пока никого нет</div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $students->links() }}
    </div>
@endsection
