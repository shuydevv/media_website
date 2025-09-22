@extends('layouts.main') {{-- или твой общий layout --}}

@section('content')
<div class="max-w-md sm:mx-auto mx-2 p-6 bg-white rounded shadow mt-12">
    <h1 class="md:text-2xl text-xl font-bold sans mb-6">Активация промокода</h1>

    @if (session('success'))
        <div class="mb-3 text-green-600 text-sm">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-sm">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('promo.redeem') }}" method="POST">
        @csrf
        <div class="mb-4">
            <label for="code" class="block mb-1">Введите промокод</label>
            <input type="text" name="code" id="code" class="border rounded w-full px-3 py-2" required>
        </div>

        {{-- Если коды у тебя «для любого курса», можно дать выбрать курс --}}
        @if(!empty($courses ?? null))
            <div class="mb-4">
                <label class="block mb-1">Курс</label>
                <select name="course_id" class="border rounded w-full px-3 py-2">
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}">{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 mt-4 rounded">
            Активировать
        </button>
    </form>
</div>
@endsection
