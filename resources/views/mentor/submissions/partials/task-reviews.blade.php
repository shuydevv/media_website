{{-- Очередь ответов второй части со станции «Задания» (WrittenPractice::queue). --}}
@if($taskReviews->isNotEmpty())
  <div class="mb-6 rounded-2xl border border-blue-200 bg-white p-4">
    <div class="flex items-center justify-between gap-3 mb-3">
      <h2 class="text-lg font-semibold">Задания из тренажёра</h2>
      <span class="text-sm text-gray-500">{{ $taskReviews->count() }} ждут проверки</span>
    </div>
    <div class="divide-y divide-gray-100">
      @foreach($taskReviews as $r)
        <a href="{{ route('mentor.task-reviews.show', $r) }}" class="flex items-center gap-3 py-2 hover:bg-gray-50 rounded-lg px-2 -mx-2">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 font-bold text-gray-700">{{ (int) $r->task->number }}</span>
          <span class="flex-1 min-w-0">
            <span class="block font-medium text-gray-900 truncate">{{ $r->user->name ?? 'Ученик #'.$r->user_id }}</span>
            <span class="block text-sm text-gray-500 truncate">{{ \Illuminate\Support\Str::limit($r->task->question_text, 110) }}</span>
          </span>
          <span class="text-sm text-gray-500 whitespace-nowrap">
            @if($r->locked_by && (int) $r->locked_by === (int) auth()->id()) у вас · @endif
            {{ $r->sent_at?->diffForHumans() }}
          </span>
        </a>
      @endforeach
    </div>
  </div>
@endif
