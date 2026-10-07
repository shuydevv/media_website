@extends('admin.layouts.main')

@section('title', 'Новая шпаргалка')

@section('content')
<div class="max-w-4xl">
    <x-ui.page-header title="Создать шпаргалку" :back="route('admin.shpargalka.index')" back-label="Шпаргалки" />

    <form action="{{ route('admin.shpargalka.store') }}" method="post" enctype="multipart/form-data" class="space-y-4">
        @csrf

        @include('admin.shpargalkas._form', ['shpargalka' => null])

        <x-ui.form-actions>
            <x-ui.button type="submit" size="xs">Создать шпаргалку</x-ui.button>
            <x-ui.button href="{{ route('admin.shpargalka.index') }}" variant="ghost" size="xs">Отмена</x-ui.button>
        </x-ui.form-actions>
    </form>
</div>
@endsection
