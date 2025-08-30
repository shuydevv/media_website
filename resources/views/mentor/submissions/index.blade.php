@extends('layouts.main')

@php
  $queueChecked = $queue->where('status', 'checked');
  $queuePending = $queue->where('status', '!=', 'checked');
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-6">Домашки к проверке</h1>

  @if(session('error'))
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2">{{ session('error') }}</div>
  @endif
  @if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-800 px-3 py-2">{{ session('success') }}</div>
  @endif

  {{-- Пропущенные задания (видит только админ) --}}
@php
  $isAdmin = (int)auth()->user()->role === \App\Models\User::ROLE_ADMIN;
@endphp

@if($isAdmin)
  <div class="mt-10">
    <h2 class="text-lg font-semibold mb-3">С пропущенными заданиями</h2>

    @forelse($skipped as $s)
      @php
        $per = $s->per_task_results ?? [];
        $skippedCount = 0;
        foreach ($per as $row) {
          if (!empty($row['skipped'])) $skippedCount++;
        }
      @endphp

      <a href="{{ route('mentor.submissions.show', $s) }}"
         class="block rounded-2xl border border-red-200 bg-white px-4 py-3 mb-2 hover:bg-red-50">
        <div class="flex items-center justify-between">
          <div>
            <div class="font-medium">#{{ $s->id }} · {{ $s->homework->title ?? 'Домашка' }}</div>
            <div class="text-sm text-gray-600">
              Ученик: {{ $s->user->name ?? ('ID '.$s->user_id) }}
            </div>
          </div>
          <div class="text-sm text-red-600">
            Пропущено: {{ $skippedCount }}
          </div>
        </div>
      </a>
    @empty
      <div class="text-sm text-gray-500">Нет работ с пропущенными заданиями.</div>
    @endforelse
  </div>
@endif


  {{-- Мои (залоченные мной) --}}
  <div class="mb-8 mt-8">
    <h2 class="text-lg font-semibold mb-3">Мои текущие проверки</h2>
    @forelse($mine as $s)
      <a href="{{ route('mentor.submissions.show', $s) }}" class="block rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 mb-2 hover:bg-amber-100">
        <div class="flex items-center justify-between">
          <div>
            <div class="font-medium">#{{ $s->id }} · {{ $s->homework->title ?? 'Домашка' }}</div>
            <div class="text-sm text-gray-600">
              Ученик: {{ $s->user->name ?? ('ID '.$s->user_id) }}
            </div>
          </div>
          <div class="text-sm text-amber-700">
            Проверить до: {{ optional($s->lock_expires_at)->format('H:i') }}
          </div>
        </div>
      </a>
    @empty
      <div class="text-sm text-gray-500">Нет активных проверок.</div>
    @endforelse
  </div>

{{-- Очередь доступных --}}
<div>
  <h2 class="text-lg font-semibold mb-3">Доступные к проверке</h2>

  {{-- Pending --}}
  @forelse($queuePending as $s)
    <a href="{{ route('mentor.submissions.show', $s) }}" class="block rounded-2xl border border-gray-200 bg-white px-4 py-3 mb-2 hover:bg-gray-50">
      <div class="flex items-center justify-between">
        <div>
          <div class="font-medium">#{{ $s->id }} · {{ $s->homework->title ?? 'Домашка' }}</div>
          <div class="text-sm text-gray-600">
            Ученик: {{ $s->user->name ?? ('ID '.$s->user_id) }}
          </div>
        </div>
        <div class="text-sm text-gray-500">Статус: {{ $s->status }}</div>
      </div>
    </a>
  @empty
    <div class="text-sm text-gray-500">Очередь пуста.</div>
  @endforelse

  {{-- Checked под спойлером --}}
  @if($queueChecked->count())
    <details class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-3">
      <summary class="cursor-pointer select-none text-sm text-gray-700">
        Проверенные работы ({{ $queueChecked->count() }})
      </summary>
      <div class="mt-3 space-y-2">
        @foreach($queueChecked as $s)
          <a href="{{ route('mentor.submissions.show', $s) }}" class="block rounded-xl border border-gray-200 bg-white px-4 py-2 hover:bg-gray-50">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-medium">#{{ $s->id }} · {{ $s->homework->title ?? 'Домашка' }}</div>
                <div class="text-sm text-gray-600">
                  Ученик: {{ $s->user->name ?? ('ID '.$s->user_id) }}
                </div>
              </div>
              <div class="text-sm text-gray-500">Статус: {{ $s->status }}</div>
            </div>
          </a>
        @endforeach
      </div>
    </details>
  @endif
</div>

</div>
@endsection
