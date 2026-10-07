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
  <label class="ui-label">Категория</label>
  <select name="category_id" class="task-category-select ui-input" required>
    <option value="">Выберите категорию</option>
    @foreach($categories as $cat)
      <option value="{{ $cat->id }}" @selected($old('category_id') == $cat->id)>{{ $cat->title }}</option>
    @endforeach
  </select>
  @error('category_id')<div class="ui-error">{{ $message }}</div>@enderror
</div>

{{-- Тема — те же Раздел/Тема, что у упражнений и планов. Необязательна:
     нужна только для разбивки слабых мест по темам в отчёте по ученику
     (/admin/users/{id}); без неё задание учитывается лишь по номеру в ЕГЭ. --}}
<div>
  <label class="ui-label">Тема <span class="ui-label-note">(необязательно)</span></label>
  <select name="topic_id" class="ui-input">
    <option value="">Без темы</option>
    @foreach(\App\Models\Section::with(['topics', 'category:id,title'])->orderBy('category_id')->orderBy('id')->get() as $section)
      <optgroup label="{{ $section->category?->title }} — {{ $section->title }}">
        @foreach($section->topics as $topic)
          <option value="{{ $topic->id }}" @selected($old('topic_id') == $topic->id)>{{ $topic->title }}</option>
        @endforeach
      </optgroup>
    @endforeach
  </select>
  @error('topic_id')<div class="ui-error">{{ $message }}</div>@enderror
</div>
