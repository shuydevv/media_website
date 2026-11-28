@extends('admin.layouts.main')

@section('title', 'Редактирование урока')

@section('content')
<div class="max-w-3xl">
    <x-ui.page-header title="Редактирование урока" :back="route('admin.lessons.index')" back-label="Уроки" />

    <form action="{{ route('admin.lessons.update', $lesson->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        {{-- Привязка к сессии не редактируется --}}
        <x-ui.card tone="gray">
            <x-ui.stat label="Сессия" size="md">{{ $lesson->session->date }} — {{ $lesson->session->course->title }}</x-ui.stat>
        </x-ui.card>

        @include('admin.lessons.partials.form-fields', ['lesson' => $lesson])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Обновить урок</x-ui.button>
            <x-ui.button href="{{ route('admin.lessons.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>

    @if ($lesson->meet_link)
        @php $guestToken = \App\Models\LessonChat::where('lesson_id', $lesson->id)->value('guest_token'); @endphp
        <x-ui.card class="mt-6 space-y-3">
            <h2 class="sans-medium text-lg text-zinc-900">Гостевая ссылка на эфир</h2>
            <p class="text-sm text-zinc-600">
                Для тех, кто хочет прийти на урок без регистрации: по ссылке открывается только этот урок —
                эфир, а после эфира запись с чатом. Гость пишет в чат с плашкой «Гость».
            </p>
            @if ($guestToken)
                <div class="flex flex-wrap gap-2 items-center">
                    <input id="guest-link" readonly value="{{ url('/live/'.$guestToken) }}"
                           class="flex-1 min-w-0 rounded-lg border border-gray-200 px-3 py-2 text-sm bg-zinc-50">
                    <x-ui.button type="button" size="xs" variant="secondary"
                                 onclick="navigator.clipboard.writeText(document.getElementById('guest-link').value); this.textContent='Скопировано'">Скопировать</x-ui.button>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.lessons.guest-link', $lesson) }}"
                  @if ($guestToken) onsubmit="return confirm('Старая ссылка перестанет работать. Сменить?')" @endif>
                @csrf
                <x-ui.button type="submit" size="xs" :variant="$guestToken ? 'ghost' : 'primary'">{{ $guestToken ? 'Сменить ссылку' : 'Создать гостевую ссылку' }}</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
@endsection
