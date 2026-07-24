{{-- resources/views/exercise/index.blade.php
     Публичный банк заданий — список Task с is_public=1. --}}
@extends('layouts.main')

@section('title')Банк заданий — Школа Полтавского@endsection
@section('description')Бесплатные задания для подготовки к ЕГЭ и ОГЭ по истории и обществознанию с разбором ответов.@endsection

@section('content')
<div class="container mx-auto max-w-screen-lg md:mt-16 mt-10 md:mb-16 mb-10 px-3">
  <h1 class="md:text-3xl text-2xl font-medium md:mb-6 mb-4 tracking-wide text-zinc-900">Банк заданий</h1>

  <form method="get" class="flex flex-wrap gap-2 mb-8">
    <a href="{{ request()->fullUrlWithQuery(['page' => null, 'category_id' => null]) }}"
       class="{{ !request('category_id') ? 'bg-zinc-900 text-white border-zinc-900' : '' }} text-sm px-4 py-2 border-2 rounded-full">Все</a>
    @foreach($categories as $cat)
      <a href="{{ request()->fullUrlWithQuery(['page' => null, 'category_id' => $cat->id]) }}"
         class="{{ (int) request('category_id') === $cat->id ? 'bg-zinc-900 text-white border-zinc-900' : '' }} text-sm px-4 py-2 border-2 rounded-full">{{ $cat->title }}</a>
    @endforeach
  </form>

  @if($tasks->isEmpty())
    <p class="text-zinc-600">Заданий пока нет — загляните позже.</p>
  @else
    <div class="grid gap-4">
      @foreach($tasks as $task)
        <a href="{{ route('exercise.show', $task) }}" class="block px-6 py-6 bg-white border border-gray-200 rounded-2xl hover:border-blue-300 transition">
          <div class="text-xs text-zinc-500 mb-2">{{ $task->category?->title ?? 'Без категории' }} @if($task->number) · № {{ $task->number }} @endif</div>
          <div class="sans text-lg text-zinc-800 leading-relaxed">{{ \Illuminate\Support\Str::limit(strip_tags((string) $task->question_text), 140) ?: 'Без текста' }}</div>
        </a>
      @endforeach
    </div>

    <div class="mt-8">{{ $tasks->links() }}</div>
  @endif
</div>

<x-material></x-material>
<x-footer />
@endsection
