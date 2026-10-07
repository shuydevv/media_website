@extends('admin.layouts.main')

@section('title', 'Редактирование упражнения')

@section('content')
@php
    // Какой тип открыть сразу: по тому, какие поля у упражнения заполнены.
    // Раньше все блоки были скрыты, пока заново не нажмёшь кнопку типа.
    $initialType = match (true) {
        filled($exercise->content_column_1_content) || filled($exercise->content_column_2_content) => 'btn_columns',
        filled($exercise->content_options) => 'btn_answers',
        default => 'btn_text',
    };
@endphp
<div class="max-w-4xl">
    <x-ui.page-header title="Редактировать упражнение" :back="route('admin.exercise.show', $exercise)" back-label="К упражнению" />

    <form action="{{ route('admin.exercise.update', $exercise->id) }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')

        @include('admin.exercises._form', ['exercise' => $exercise])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.exercise.show', $exercise) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>

<script src="{{ asset('/js/admin/tabs.js') }}"></script>
<script src="{{ asset('/js/admin/select_options.js') }}"></script>
<script>
    document.querySelector('.{{ $initialType }}').click();
</script>
@endsection
