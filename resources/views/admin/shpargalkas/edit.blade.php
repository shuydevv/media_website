@extends('admin.layouts.main')

@section('title', 'Редактирование шпаргалки')

@section('content')
<div class="max-w-4xl">
    <x-ui.page-header title="Редактировать шпаргалку" :back="route('admin.shpargalka.show', $shpargalka)" back-label="К шпаргалке" />

    <form action="{{ route('admin.shpargalka.update', $shpargalka->id) }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')

        @include('admin.shpargalkas._form', ['shpargalka' => $shpargalka, 'images' => $images])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Сохранить изменения</x-ui.button>
            <x-ui.button href="{{ route('admin.shpargalka.show', $shpargalka) }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
