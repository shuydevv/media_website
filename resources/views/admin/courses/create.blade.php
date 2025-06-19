@extends('admin.layouts.main')

@section('content')
<div class="max-w-3xl mx-auto p-6 bg-white shadow-md rounded-xl">
    <h1 class="text-2xl font-bold mb-6">Создать курс</h1>

    @if ($errors->any())
        <div class="mb-4 text-red-600">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.courses.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="mb-4">
            <label for="title" class="block font-medium text-sm">Название курса</label>
            <input type="text" name="title" id="title" class="w-full border rounded px-3 py-2" value="{{ old('title') }}" required>
        </div>

        <div class="mb-4">
            <label for="description" class="block font-medium text-sm">Описание</label>
            <textarea name="description" id="description" class="w-full border rounded px-3 py-2">{{ old('description') }}</textarea>
        </div>

        <div class="mb-4">
            <label for="category_id" class="block text-sm font-medium">Категория</label>
            <select name="category_id" id="category_id" class="w-full border rounded px-3 py-2">
                <option value="">-- Без категории --</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}"
                        {{ old('category_id', $course->category_id ?? '') == $category->id ? 'selected' : '' }}>
                        {{ $category->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-4">
            <div>
                <label for="start_date" class="block font-medium text-sm">Дата начала</label>
                <input type="date" name="start_date" id="start_date" class="w-full border rounded px-3 py-2" value="{{ old('start_date') }}" required>
            </div>
            <div>
                <label for="end_date" class="block font-medium text-sm">Дата окончания</label>
                <input type="date" name="end_date" id="end_date" class="w-full border rounded px-3 py-2" value="{{ old('end_date') }}" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="block font-medium text-sm mb-2">Расписание</label>

            <div id="schedule-wrapper">
                @php $oldSchedule = old('schedule', [0 => []]); @endphp
                @foreach ($oldSchedule as $i => $item)
                <div class="grid grid-cols-3 gap-4 mb-2 schedule-block relative border p-3 rounded-md bg-gray-50">
                    <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl leading-none remove-schedule">&times;</button>
                    <select name="schedule[{{ $i }}][day_of_week]" class="border rounded px-2 py-1" required>
                        <option value="">День недели</option>
                        @foreach (['Mon'=>'Пн','Tue'=>'Вт','Wed'=>'Ср','Thu'=>'Чт','Fri'=>'Пт','Sat'=>'Сб','Sun'=>'Вс'] as $code=>$name)
                            <option value="{{ $code }}" {{ old("schedule.$i.day_of_week") === $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    <input type="time" name="schedule[{{ $i }}][start_time]" class="border rounded px-2 py-1" value="{{ old("schedule.$i.start_time") }}" required>
                    <input type="number" name="schedule[{{ $i }}][duration_minutes]" class="border rounded px-2 py-1" placeholder="Длительность (мин)" value="{{ old("schedule.$i.duration_minutes") }}" min="1" required>
                </div>
                @endforeach
            </div>

            <button type="button" id="add-schedule" class="mt-2 text-blue-600 hover:underline text-sm">+ Добавить день</button>
        </div>

        <div class="mb-4"><label for="price" class="block text-sm font-medium">Цена</label>
            <input type="text" name="price" id="price" value="{{ old('price') }}" required class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4"><label for="old_price" class="block text-sm font-medium">Старая цена</label>
            <input type="text" name="old_price" id="old_price" value="{{ old('old_price') }}" class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4"><label for="content" class="block text-sm font-medium">Контент</label>
            <textarea name="content" id="content" class="w-full border rounded px-3 py-2">{{ old('content') }}</textarea>
        </div>

        <div class="mb-4"><label for="path" class="block text-sm font-medium">URL (Путь)</label>
            <input type="text" name="path" id="path" value="{{ old('path') }}" class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4"><label for="html_title" class="block text-sm font-medium">HTML Title</label>
            <input type="text" name="html_title" id="html_title" value="{{ old('html_title') }}" class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4"><label for="html_description" class="block text-sm font-medium">HTML Description</label>
            <input type="text" name="html_description" id="html_description" value="{{ old('html_description') }}" class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4"><label for="main_image" class="block text-sm font-medium">Обложка курса</label>
            <input type="file" name="main_image" id="main_image" class="w-full border rounded px-3 py-2">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Сохранить курс</button>
    </form>
</div>

<script>
    document.getElementById('add-schedule').addEventListener('click', function () {
        const wrapper = document.getElementById('schedule-wrapper');
        const index = wrapper.querySelectorAll('.schedule-block').length;

        const html = `
            <div class="grid grid-cols-3 gap-4 mb-2 schedule-block relative border p-3 rounded-md bg-gray-50">
                <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700 text-xl leading-none remove-schedule">&times;</button>
                <select name="schedule[${index}][day_of_week]" class="border rounded px-2 py-1" required>
                    <option value="">День недели</option>
                    <option value="Mon">Пн</option>
                    <option value="Tue">Вт</option>
                    <option value="Wed">Ср</option>
                    <option value="Thu">Чт</option>
                    <option value="Fri">Пт</option>
                    <option value="Sat">Сб</option>
                    <option value="Sun">Вс</option>
                </select>
                <input type="time" name="schedule[${index}][start_time]" class="border rounded px-2 py-1" required>
                <input type="number" name="schedule[${index}][duration_minutes]" class="border rounded px-2 py-1" placeholder="Длительность (мин)" min="1" required>
            </div>
        `;

        wrapper.insertAdjacentHTML('beforeend', html);
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-schedule')) {
            e.target.closest('.schedule-block').remove();
        }
    });
</script>
@endsection
