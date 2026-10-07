{{--
    Общие поля формы курса — create.blade.php и edit.blade.php.
    $course — редактируемый курс или null при создании.
--}}
@php
    $course = $course ?? null;
    $weekdays = ['Mon' => 'Пн', 'Tue' => 'Вт', 'Wed' => 'Ср', 'Thu' => 'Чт', 'Fri' => 'Пт', 'Sat' => 'Сб', 'Sun' => 'Вс'];

    // Строки расписания: после ошибки валидации — то, что ввели (old), иначе
    // сохранённые шаблоны курса, иначе одна пустая строка.
    if (old('schedule') !== null) {
        $scheduleRows = collect(old('schedule'))->map(fn ($row) => [
            'day_of_week' => $row['day_of_week'] ?? '',
            'start_time' => $row['start_time'] ?? '',
            'duration_minutes' => $row['duration_minutes'] ?? '',
        ])->values();
    } elseif ($course) {
        $scheduleRows = $course->scheduleTemplates->map(fn ($item) => [
            'day_of_week' => $item->day_of_week,
            'start_time' => \Illuminate\Support\Str::of($item->start_time)->substr(0, 5)->toString(),
            'duration_minutes' => $item->duration_minutes,
        ])->values();
    } else {
        $scheduleRows = collect([['day_of_week' => '', 'start_time' => '', 'duration_minutes' => '']]);
    }
@endphp

<x-ui.card class="space-y-4">
    <x-ui.input name="title" label="Название курса" value="{{ old('title', $course?->title) }}" required />

    <x-ui.textarea name="description" label="Описание">{{ old('description', $course?->description) }}</x-ui.textarea>

    @if ($course)
        <x-ui.select name="category_id" label="Категория" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $course->category_id) == $category->id)>{{ $category->title }}</option>
            @endforeach
        </x-ui.select>
    @else
        <x-ui.select name="category_id" label="Категория">
            <option value="">— без категории —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->title }}</option>
            @endforeach
        </x-ui.select>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.input type="date" name="start_date" label="Дата начала" value="{{ old('start_date', $course?->start_date) }}" required />
        <x-ui.input type="date" name="end_date" label="Дата окончания" value="{{ old('end_date', $course?->end_date) }}" required />
    </div>
</x-ui.card>

<x-ui.card>
    <h2 class="sans-medium text-lg text-zinc-900">Расписание</h2>
    <p class="text-sm text-zinc-500 mt-1 mb-4">День недели, время начала и длительность занятия в минутах.</p>

    <div id="schedule-wrapper" class="space-y-2" data-next-index="{{ $scheduleRows->count() }}">
        @foreach ($scheduleRows as $i => $row)
            <div class="schedule-block flex flex-wrap items-center gap-2">
                <select name="schedule[{{ $i }}][day_of_week]" class="ui-input flex-1 min-w-[110px]" aria-label="День недели" required>
                    <option value="">День недели</option>
                    @foreach ($weekdays as $code => $weekdayName)
                        <option value="{{ $code }}" @selected($row['day_of_week'] === $code)>{{ $weekdayName }}</option>
                    @endforeach
                </select>
                <input type="time" name="schedule[{{ $i }}][start_time]" class="ui-input w-32" aria-label="Время начала" value="{{ $row['start_time'] }}" required>
                <input type="number" name="schedule[{{ $i }}][duration_minutes]" class="ui-input w-28" aria-label="Длительность, минут" placeholder="Минут" value="{{ $row['duration_minutes'] }}" min="1" inputmode="numeric" required>
                <button type="button" class="remove-schedule w-11 h-11 md:w-10 md:h-10 shrink-0 flex items-center justify-center rounded-lg text-zinc-400 hover:bg-apple-red-50 hover:text-apple-red-650" title="Убрать день" aria-label="Убрать день">
                    <x-icon name="x-close" class="w-4 h-4" />
                </button>
            </div>
        @endforeach
    </div>

    <x-ui.button id="add-schedule" variant="secondary" size="xs" class="mt-3">+ Добавить день</x-ui.button>

    {{-- Заготовка строки для JS: та же разметка, что отрисована сервером выше,
         __INDEX__ подменяется номером новой строки. --}}
    <template id="schedule-row-template">
        <div class="schedule-block flex flex-wrap items-center gap-2">
            <select name="schedule[__INDEX__][day_of_week]" class="ui-input flex-1 min-w-[110px]" aria-label="День недели" required>
                <option value="">День недели</option>
                @foreach ($weekdays as $code => $weekdayName)
                    <option value="{{ $code }}">{{ $weekdayName }}</option>
                @endforeach
            </select>
            <input type="time" name="schedule[__INDEX__][start_time]" class="ui-input w-32" aria-label="Время начала" required>
            <input type="number" name="schedule[__INDEX__][duration_minutes]" class="ui-input w-28" aria-label="Длительность, минут" placeholder="Минут" min="1" inputmode="numeric" required>
            <button type="button" class="remove-schedule w-11 h-11 md:w-10 md:h-10 shrink-0 flex items-center justify-center rounded-lg text-zinc-400 hover:bg-apple-red-50 hover:text-apple-red-650" title="Убрать день" aria-label="Убрать день">
                <x-icon name="x-close" class="w-4 h-4" />
            </button>
        </div>
    </template>
</x-ui.card>

<x-ui.card class="space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Цена</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-ui.input type="number" step="0.01" min="0" name="price_rub" label="Цена, ₽" inputmode="decimal" required
                    value="{{ old('price_rub', $course ? $course->price_cents / 100 : null) }}" />
        <x-ui.input type="number" step="0.01" min="0" name="old_price_rub" label="Старая цена, ₽" note="необязательно" inputmode="decimal"
                    value="{{ old('old_price_rub', $course && $course->old_price_cents !== null ? $course->old_price_cents / 100 : null) }}" />
    </div>
</x-ui.card>

<x-ui.card class="space-y-4">
    <h2 class="sans-medium text-lg text-zinc-900">Страница курса на сайте</h2>
    <x-ui.textarea name="content" label="Контент" rows="6">{{ old('content', $course?->content) }}</x-ui.textarea>
    <x-ui.input name="path" label="URL (путь)" value="{{ old('path', $course?->path) }}" />
    <x-ui.input name="html_title" label="HTML Title" value="{{ old('html_title', $course?->html_title) }}" />
    <x-ui.input name="html_description" label="HTML Description" value="{{ old('html_description', $course?->html_description) }}" />
    <x-ui.input type="file" name="main_image" label="Обложка курса" :hint="$course ? 'Оставьте пустым, чтобы не менять.' : null" />
</x-ui.card>

@push('page-scripts')
<script>
(function () {
    var wrapper = document.getElementById('schedule-wrapper');
    var template = document.getElementById('schedule-row-template').innerHTML;
    // Счётчик, а не «сколько строк сейчас»: после удаления строки её номер
    // не должен достаться новой, иначе две строки уйдут с одним индексом.
    var nextIndex = parseInt(wrapper.dataset.nextIndex, 10) || 0;

    document.getElementById('add-schedule').addEventListener('click', function () {
        wrapper.insertAdjacentHTML('beforeend', template.replace(/__INDEX__/g, nextIndex++));
    });

    wrapper.addEventListener('click', function (e) {
        var button = e.target.closest('.remove-schedule');
        if (button) button.closest('.schedule-block').remove();
    });
})();
</script>
@endpush
