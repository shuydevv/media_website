@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <h1 class="text-xl font-semibold mb-4">Сданные работы</h1>

  <div class="space-y-2">
    @foreach($submissions as $s)
      <a href="{{ route('mentor.submissions.show', $s) }}" class="block rounded-xl border p-3 hover:bg-gray-50">
        <div class="text-sm text-gray-800">
          {{ $s->homework->title ?? 'Домашнее задание #'.$s->homework_id }}
          — {{ $s->user->name ?? 'Студент #'.$s->user_id }}
          — статус: {{ $s->status }}
        </div>
      </a>
    @endforeach
  </div>

  <div class="mt-4">
    {{ $submissions->links() }}
  </div>
</div>
@endsection
