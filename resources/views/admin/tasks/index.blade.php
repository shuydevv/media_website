@extends('layouts.main')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Банк критериев (Task)</h1>
    <a href="{{ route('admin.tasks.create') }}" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Создать</a>
  </div>

  @foreach (['success'=>'green','error'=>'red'] as $k=>$c)
    @if(session($k))
      <div class="mb-4 rounded-xl border border-{{ $c }}-200 bg-{{ $c }}-50 text-{{ $c }}-800 px-3 py-2">
        {{ session($k) }}
      </div>
    @endif
  @endforeach

  <form method="get" class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-2">
    <input name="search" value="{{ $filters['search'] ?? '' }}" class="border rounded-lg px-3 py-2" placeholder="Поиск (категория/номер)">
    <select name="category_id" class="border rounded-lg px-3 py-2">
      <option value="">Категория</option>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? null)==$cat->id)>{{ $cat->title }}</option>
      @endforeach
    </select>
    <input name="number" value="{{ $filters['number'] ?? '' }}" class="border rounded-lg px-3 py-2" placeholder="Номер">
    <button class="px-4 py-2 rounded-lg border hover:bg-gray-50">Фильтр</button>
  </form>

  <div class="bg-white rounded-2xl border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-gray-600">
        <tr>
          <th class="text-left px-4 py-2">ID</th>
          <th class="text-left px-4 py-2">Категория</th>
          <th class="text-left px-4 py-2">Номер</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($tasks as $t)
          <tr class="border-t">
            <td class="px-4 py-2">#{{ $t->id }}</td>
            <td class="px-4 py-2">{{ $t->category?->title ?? '—' }}</td>
            <td class="px-4 py-2">{{ $t->number ?? '—' }}</td>
            <td class="px-4 py-2 text-right">
              <a href="{{ route('admin.tasks.show', $t) }}" class="text-blue-600 hover:underline">Открыть</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Пусто</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $tasks->withQueryString()->links() }}</div>
</div>
@endsection
