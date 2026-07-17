@extends('layouts.main')

@section('content')
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
  <h1 class="text-xl sm:text-2xl font-medium mb-2">{{ $homework->title ?? 'Домашнее задание' }}</h1>
  <p class="text-sm text-gray-500 mb-6">Проверьте ответы перед отправкой. Пока работа не отправлена, можно вернуться к любому вопросу.</p>

  @if ($errors->any())
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
      <ul class="list-disc list-inside">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="rounded-2xl border border-gray-200 bg-white divide-y">
    @foreach($tasks as $i => $t)
      @php $st = $statusOf($t); @endphp
      <a href="{{ route('student.submissions.question', [$submission, $i + 1]) }}"
         class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
        <span class="text-sm sm:text-base text-gray-800">Вопрос {{ $i + 1 }}</span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badgeClasses[$st] }}">
          {{ $labels[$st] }}
        </span>
      </a>
    @endforeach
  </div>

  <div class="mt-8">
    @if($allAnswered)
      <form method="POST" action="{{ route('student.submissions.finish.submit', $submission) }}">
        @csrf
        <button type="submit" class="px-5 py-3 rounded-xl bg-blue-600 text-white hover:bg-blue-700 text-sm sm:text-base">
          Завершить и отправить работу
        </button>
      </form>
    @else
      <button type="button" disabled class="px-5 py-3 rounded-xl bg-gray-200 text-gray-500 cursor-not-allowed text-sm sm:text-base">
        Сначала ответьте на все вопросы
      </button>
    @endif
  </div>
</div>
@endsection
