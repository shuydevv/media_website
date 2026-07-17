{{-- resources/views/student/submissions/partials/finish-region.blade.php
     Содержимое #wizard-app для обзора перед отправкой. Рендерится и как часть
     полной страницы (finish.blade.php), и как htmx-фрагмент (без layout). --}}
@php
  $statusOf = function ($t) use ($answers, $perTask) {
    if (!array_key_exists($t->id, $answers)) return 'unanswered';
    if (!$t->isAutoGradable()) return 'saved';
    return $perTask[$t->id]['status'] ?? 'saved';
  };

  $labels = [
    'unanswered' => 'Не отвечен',
    'saved'      => 'Сохранён (на проверке куратора)',
    'ok'         => 'Верно',
    'partial'    => 'Частично верно',
    'fail'       => 'Неверно',
  ];

  $badgeClasses = [
    'unanswered' => 'bg-gray-100 text-gray-600',
    'saved'      => 'bg-blue-50 text-blue-700',
    'ok'         => 'bg-emerald-50 text-emerald-700',
    'partial'    => 'bg-amber-50 text-amber-700',
    'fail'       => 'bg-rose-50 text-rose-700',
  ];
@endphp

<div class="max-w-3xl mx-auto px-3 sm:px-4 py-5 sm:py-6">
  <h1 class="text-xl sm:text-2xl font-medium mb-2"><span class="sans">{{ $homework->title ?? 'Домашнее задание' }}</span></h1>
  <p class="text-sm text-gray-500 mb-6">Проверьте ответы перед отправкой. Пока работа не отправлена, можно вернуться к любому вопросу.</p>

  @if (!empty($error))
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      {{ $error }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc list-inside">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="rounded-2xl border border-gray-200 bg-white divide-y">
    @foreach($tasks as $i => $t)
      @php $st = $statusOf($t); @endphp
      <a href="{{ route('student.submissions.question', [$submission, $i + 1]) }}"
         hx-get="{{ route('student.submissions.question', [$submission, $i + 1]) }}"
         hx-target="#wizard-app"
         hx-swap="innerHTML"
         hx-push-url="true"
         class="relative flex items-center justify-between px-4 py-3 hover:bg-gray-50">
        <span class="btn-label text-sm sm:text-base text-gray-800">Вопрос {{ $i + 1 }}</span>
        <span class="btn-spinner">
          <svg class="animate-spin h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badgeClasses[$st] }}">
          {{ $labels[$st] }}
        </span>
      </a>
    @endforeach
  </div>

  <div class="mt-8">
    @if($allAnswered)
      <form method="POST" action="{{ route('student.submissions.finish.submit', $submission) }}"
            hx-post="{{ route('student.submissions.finish.submit', $submission) }}"
            hx-target="#wizard-app"
            hx-swap="innerHTML">
        @csrf
        <button type="submit" class="relative inline-flex items-center justify-center px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
          <span class="btn-label">Завершить и отправить работу</span>
          <span class="btn-spinner">
            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
          </span>
        </button>
      </form>
    @else
      <button type="button" disabled class="px-5 py-3 rounded-xl bg-gray-200 text-gray-500 cursor-not-allowed text-sm sm:text-base">
        Сначала ответьте на все вопросы
      </button>
    @endif
  </div>
</div>
