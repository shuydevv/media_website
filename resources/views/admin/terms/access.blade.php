{{-- resources/views/admin/terms/access.blade.php
     Кому открыт тренажёр терминов. Автоматическое правило (курсы
     обществознания) здесь только показывается; дополнительно — курсы и
     отдельные ученики (см. Admin\Terms\AccessController, TermAccess). --}}
@extends('admin.layouts.main')

@section('title', 'Доступ к тренажёру терминов')

@section('content')
<x-ui.page-header title="Тренажёр терминов">
    Задание 18 ЕГЭ по обществознанию.
</x-ui.page-header>

@include('admin.terms._tabs')

<x-ui.card class="mb-6">
    <h2 class="sans-medium text-base text-zinc-900 mb-1">Открыт автоматически</h2>
    <p class="text-sm text-zinc-500 mb-4">
        Ученикам с активным доступом к курсу, у которого в названии категории есть «{{ $keyword }}». Админы и кураторы видят тренажёр всегда.
    </p>
    @forelse($autoCourses as $course)
        <div class="text-sm text-zinc-800 py-1">
            {{ $course->title }} <span class="text-zinc-400">· {{ $course->category->title }}</span>
        </div>
    @empty
        <div class="text-sm text-amber-700">Сейчас ни один курс не подходит. Проверь название категории курса обществознания или привяжи курс ниже.</div>
    @endforelse
</x-ui.card>

<x-ui.card class="mb-6">
    <h2 class="sans-medium text-base text-zinc-900 mb-1">Дополнительные курсы</h2>
    <p class="text-sm text-zinc-500 mb-4">Ученики этих курсов с активным доступом тоже видят тренажёр.</p>

    @foreach($linkedCourses as $course)
        <div class="flex items-center justify-between gap-3 py-1.5 border-b border-gray-100 last:border-0">
            <div class="text-sm text-zinc-800 min-w-0 truncate">
                {{ $course->title }}@if($course->category)<span class="text-zinc-400"> · {{ $course->category->title }}</span>@endif
            </div>
            <x-ui.action-form :action="route('admin.terms.access.courses.destroy', $course)" confirm="Отвязать курс «{{ $course->title }}» от тренажёра?">Отвязать</x-ui.action-form>
        </div>
    @endforeach

    @if($otherCourses->isNotEmpty())
        <form method="POST" action="{{ route('admin.terms.access.courses.store') }}" class="flex flex-wrap items-end gap-3 {{ $linkedCourses->isNotEmpty() ? 'mt-4' : '' }}">
            @csrf
            <x-ui.select name="course_id" wrap="flex-1 min-w-56">
                @foreach($otherCourses as $course)
                    <option value="{{ $course->id }}">{{ $course->title }}@if($course->category) · {{ $course->category->title }}@endif</option>
                @endforeach
            </x-ui.select>
            <x-ui.button type="submit" variant="primary" size="xs">Привязать</x-ui.button>
        </form>
    @endif
</x-ui.card>

<x-ui.card>
    <h2 class="sans-medium text-base text-zinc-900 mb-1">Отдельные ученики</h2>
    <p class="text-sm text-zinc-500 mb-4">Тренажёр открыт, даже если у ученика нет подходящих курсов.</p>

    <form method="POST" action="{{ route('admin.terms.access.users.store') }}" class="flex flex-wrap items-start gap-3 mb-4">
        @csrf
        <x-ui.input type="email" name="email" placeholder="email ученика" value="{{ old('email') }}" wrap="flex-1 min-w-56" />
        <x-ui.button type="submit" variant="primary" size="xs">Открыть</x-ui.button>
    </form>

    @if($grants->isEmpty())
        <x-ui.empty>Пока никому не открыт отдельно.</x-ui.empty>
    @else
        <x-ui.table>
            <thead>
                <tr><th>Ученик</th><th>Открыл</th><th>Когда</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($grants as $grant)
                    <tr>
                        <td data-primary>{{ $grant->name }} <span class="text-zinc-400">· {{ $grant->email }}</span></td>
                        <td data-label="Открыл">{{ $grant->granted_by ?? '—' }}</td>
                        <td data-label="Когда">{{ \Illuminate\Support\Carbon::parse($grant->created_at)->format('d.m.Y') }}</td>
                        <td data-actions>
                            <div class="ui-table-actions">
                                <x-ui.action-form :action="route('admin.terms.access.users.destroy', $grant->id)" confirm="Закрыть личный доступ: {{ $grant->name }}?">Закрыть</x-ui.action-form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    @endif
</x-ui.card>
@endsection
