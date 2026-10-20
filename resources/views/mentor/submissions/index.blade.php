@extends('layouts.main')

@php
  $queueChecked = $queue->where('status', 'checked');
  $queuePending = $queue->where('status', '!=', 'checked');

  // «Сначала с ИИ-черновиком»: такие работы проверять быстрее.
  $aiFirst = request()->boolean('ai_first');
  if ($aiFirst) {
      $queuePending = $queuePending->sortByDesc(fn ($s) => $s->aiDraftSummary()['ready'] > 0)->values();
  }
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

  {{-- ИИ-проверка через Claude Desktop: выгрузить порцию → проверить скиллом → загрузить результат --}}
  @if($aiDesktop)
    @include('mentor.submissions.partials.ai-desktop')
  @endif

  {{-- ИИ-проверка пачкой через API: все непроверенные письменные задания из очереди --}}
  @if($aiBatch)
    <div class="mb-6 rounded-2xl border border-purple-200 bg-purple-50 px-4 py-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-purple-900">
          <div class="font-medium">ИИ-проверка разом</div>
          @if($aiBatch['count'] > 0)
            <div>
              Готово к отправке: {{ $aiBatch['count'] }} зад. —
              @foreach($aiBatch['by_subject'] as $subject => $info)
                {{ $subject }}: {{ $info['count'] }}@if(!$info['has_rules']) <span class="text-amber-700">(по общим правилам)</span>@endif{{ !$loop->last ? ',' : '' }}
              @endforeach
            </div>
          @else
            <div>Новых письменных заданий для ИИ-проверки нет.</div>
          @endif
          @if($aiBatch['in_progress'] > 0)
            <div class="text-purple-700">В обработке: {{ $aiBatch['in_progress'] }} зад. — черновики появятся в работах, обычно в течение часа.</div>
          @endif
        </div>
        @if($aiBatch['count'] > 0)
          <form method="POST" action="{{ route('mentor.review.ai_batch') }}"
                onsubmit="return confirm('Отправить {{ $aiBatch['count'] }} зад. на ИИ-проверку?');">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700">
              Проверить разом
            </button>
          </form>
        @endif
      </div>
    </div>
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
            @include('mentor.submissions.partials.ai-badge', ['submission' => $s])
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
  <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="text-lg font-semibold">Доступные к проверке</h2>
    <a href="{{ request()->fullUrlWithQuery(['ai_first' => $aiFirst ? null : 1]) }}"
       class="text-sm {{ $aiFirst ? 'text-green-700 font-medium' : 'text-gray-600' }} underline">
      {{ $aiFirst ? '✓ Сначала с ИИ-черновиком' : 'Сначала с ИИ-черновиком' }}
    </a>
  </div>

  {{-- Pending --}}
  @forelse($queuePending as $s)
    <a href="{{ route('mentor.submissions.show', $s) }}" class="block rounded-2xl border border-gray-200 bg-white px-4 py-3 mb-2 hover:bg-gray-50">
      <div class="flex items-center justify-between">
        <div>
          <div class="font-medium">#{{ $s->id }} · {{ $s->homework->title ?? 'Домашка' }}</div>
          <div class="text-sm text-gray-600">
            Ученик: {{ $s->user->name ?? ('ID '.$s->user_id) }}
          </div>
          @if(($retrying[$s->user_id.':'.$s->homework_id] ?? 0) > $s->id)
            <div class="mt-1 inline-flex rounded-full bg-yellow-50 border border-yellow-200 px-2 py-0.5 text-xs text-yellow-800">
              Ученик перерешивает — новая попытка ещё не отправлена
            </div>
          @endif
          @include('mentor.submissions.partials.ai-badge', ['submission' => $s])
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
