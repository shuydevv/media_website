@extends('layouts.main')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-4">Новая запись (критерии)</h1>

  @if(session('error'))
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2">{{ session('error') }}</div>
  @endif

  <form method="post" action="{{ route('admin.tasks.store') }}" class="space-y-4">
    @csrf

    <div>
      <label class="block text-sm font-medium mb-1">Категория</label>
      <select name="category_id" class="w-full border rounded-lg px-3 py-2" required>
        <option value="">Выберите категорию</option>
        @foreach($categories as $cat)
          <option value="{{ $cat->id }}" @selected(old('category_id')==$cat->id)>{{ $cat->title }}</option>
        @endforeach
      </select>
      @error('category_id')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Номер в экзамене</label>
      <input name="number" value="{{ old('number') }}" class="w-full border rounded-lg px-3 py-2">
      @error('number')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Критерии</label>
      <textarea name="criteria" rows="8" class="w-full border rounded-lg px-3 py-2" placeholder='Критерии' required>{{ old('criteria') }}</textarea>
      @error('criteria')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">AI-шаблон «Обоснование баллов» (опционально)</label>
      <textarea name="ai_rationale_template" rows="4" class="w-full border rounded-lg px-3 py-2" placeholder="Короткая заготовка для поля «Обоснование баллов»">{{ old('ai_rationale_template') }}</textarea>
      @error('ai_rationale_template')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Комментарий (рекомендации для проверяющего)</label>
      <textarea name="comment" rows="4" class="w-full border rounded-lg px-3 py-2" placeholder="На что обратить внимание при проверке, примеры частых ошибок...">{{ old('comment') }}</textarea>
      @error('comment')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>

    <div class="flex items-center gap-2">
      <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Сохранить</button>
      <a href="{{ route('admin.tasks.index') }}" class="px-4 py-2 rounded-lg border hover:bg-gray-50">Отмена</a>
    </div>
  </form>
</div>
@endsection
