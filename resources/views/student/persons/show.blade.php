{{-- resources/views/student/persons/show.blade.php
     Карточка изученной личности — справочник с карты. Чтение не считается
     повторением и на расписание не влияет. --}}
@extends('layouts.main')

@section('title', $person->name.' — Школа Полтавского')

@section('content')
@include('student.persons.partials.styles')
<div class="max-w-3xl mx-auto px-3 sm:px-4 py-5 sm:py-6">
  <x-ui.page-header :title="$eras[$person->era] ?? 'Личность'" :back="route('student.persons.index')" backLabel="К тренажёру" />

  <div class="rounded-2xl border border-gray-200 bg-white card-surface p-4 sm:p-6">
    @include('student.persons.partials.person-card', ['person' => $person, 'eras' => $eras])

    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between gap-3 text-sm text-zinc-500">
      <span>Следующее повторение: {{ $row->due_on->isPast() && !$row->due_on->isToday() ? 'ждёт сейчас' : ($row->due_on->isToday() ? 'сегодня' : $row->due_on->translatedFormat('j F')) }}</span>
      <span class="flex gap-1" title="Шаг {{ $row->step }} из {{ $maxStep }}">
        @for($i = 1; $i <= $maxStep; $i++)
          <span class="w-1.5 h-1.5 rounded-full {{ $i <= $row->step ? 'bg-blue-500' : 'bg-gray-200' }}"></span>
        @endfor
      </span>
    </div>
  </div>
</div>
@endsection
