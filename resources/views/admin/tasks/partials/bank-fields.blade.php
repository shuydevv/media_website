{{-- Поля, специфичные для банка заданий (не часть содержания задания,
     поэтому не входят в <x-task-content-fields> — тот же компонент
     используется и в конструкторе домашки, где категории/номера нет).
     Номер в экзамене редактируется прямо в шапке карточки содержания
     (task-content-fields.blade.php) — тем же бейджем, что показывает
     student/submissions/show.blade.php, только с инпутом внутри. --}}
@php
  $old = fn ($key, $default = null) => old($key, data_get($task, $key, $default));
@endphp
<div>
  <label class="block text-sm font-medium mb-1">Категория</label>
  <select name="category_id" class="task-category-select w-full border rounded-lg px-3 py-2" required>
    <option value="">Выберите категорию</option>
    @foreach($categories as $cat)
      <option value="{{ $cat->id }}" @selected($old('category_id') == $cat->id)>{{ $cat->title }}</option>
    @endforeach
  </select>
  @error('category_id')<div class="text-red-600 text-sm mt-1">{{ $message }}</div>@enderror
</div>
