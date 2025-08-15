@extends('layouts.main')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <div class="mb-6">
    <a href="{{ route('student.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Назад в дашборд</a>
  </div>

  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ $course->title }}</h1>

    {{-- Сроки курса: надёжный вывод, даже если поля не кастятся к Carbon --}}
    @if(!empty($course->start_date) || !empty($course->end_date))
      <div class="mt-1 text-sm text-gray-600">
        @php
          $fmt = function($v) {
            try { return $v ? \Illuminate\Support\Carbon::parse($v)->format('d.m.Y') : null; } catch (\Throwable $e) { return $v; }
          };
        @endphp
        Дата: {{ $fmt($course->start_date) ?? '—' }} — {{ $fmt($course->end_date) ?? '—' }}
      </div>
    @endif

    @if(optional($course->category)->name)
      <div class="mt-1 text-sm text-gray-600">Категория: {{ $course->category->name }}</div>
    @endif
  </div>

  {{-- Ближайшая сессия --}}
  <div id="next-session" class="mb-10">
    <h2 class="text-lg font-semibold text-blue-900 mb-3">Ближайшее занятие</h2>

    @if(!$nextSession)
      <div class="text-sm text-gray-600">Ближайшая сессия не запланирована.</div>
    @else
      @php
        $s = $nextSession;
        $lesson = $s->lesson; // может быть null
      @endphp
      <div class="rounded-2xl bg-blue-50 border border-blue-200 p-4">
        <div class="flex items-start gap-4">
          {{-- Картинка урока (если есть) --}}
        @if($lesson && $lesson->image_url)
            <img src="{{ $lesson->image_url }}" alt="" class="w-24 h-24 rounded-xl object-cover border border-blue-200">

          @endif

          <div class="flex-1">
            <div class="text-sm text-blue-800">
            
            @if($s->display_date)
                {{ \Illuminate\Support\Carbon::parse($s->display_date)->translatedFormat('j F') }}
                @if($s->display_time) в {{ \Illuminate\Support\Str::substr($s->display_time, 0, 5) }} @endif
            @else
                Дата не указана
            @endif
            </div>






            <div class="mt-1">
              @if($lesson)
                <a href="{{ route('student.lessons.show', $lesson) }}" class="text-lg font-semibold text-blue-900 hover:underline">
                  {{ $lesson->title }}
                </a>
              @else
                <div class="text-lg font-semibold text-blue-900">Тема урока пока неизвестна</div>
              @endif
            </div>

            @if($lesson?->description)
              <div class="text-sm text-blue-800 mt-1">{{ $lesson->description }}</div>
            @endif
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- Прошедшие занятия (только с привязанным уроком) --}}
{{-- Прошедшие занятия (группировка по месяцам) --}}
<div id="past-sessions">
  <h2 class="text-lg font-semibold text-gray-900 mb-3">Прошедшие занятия</h2>

  @if(($pastByMonth ?? collect())->isEmpty())
    <div class="text-sm text-gray-600">
      Прошедших занятий с материалами пока нет.
      @if(($pastHiddenCount ?? 0) > 0)
        <div class="mt-1">
          Есть {{ $pastHiddenCount }} прошедш{{ $pastHiddenCount === 1 ? 'ая сессия' : ($pastHiddenCount < 5 ? 'ие сессии' : 'их сессий') }} без привязанного урока — они скрыты.
        </div>
      @endif
    </div>
  @else
    <div class="space-y-6">
      @foreach($pastByMonth as $monthKey => $items)
        {{-- Заголовок месяца, с заглавной буквы и двоеточием --}}
        <div>
          <div class="text-base font-semibold text-gray-900 mb-2">
            {{ \Illuminate\Support\Str::ucfirst($monthKey) }}:
          </div>

          <div class="space-y-3">
            @foreach($items as $s)
              @php
                $lesson = $s->lesson; // гарантированно есть
              @endphp
              <div class="rounded-2xl bg-gray-50 border border-gray-200 p-4">
                <div class="flex items-start gap-4">
                  {{-- Картинка урока (если есть) --}}
                  @if($lesson->image_url ?? false)
                    <img src="{{ $lesson->image_url }}" alt="" class="w-20 h-20 rounded-xl object-cover border border-gray-200">
                  @endif

                  <div class="flex-1">
                    {{-- Дата занятия: "2 сентября в 15:30" (без секунд, как договорились) --}}
                    <div class="text-sm text-gray-600">
                      Дата занятия:
                      @if($s->display_date)
                        {{ \Carbon\Carbon::parse($s->display_date)->locale('ru')->isoFormat('D MMMM') }}
                        @if($s->display_time) в {{ \Illuminate\Support\Str::substr($s->display_time, 0, 5) }} @endif
                      @else
                        не указана
                      @endif
                    </div>

                    {{-- Заголовок и ссылка на страницу урока --}}
                    <a href="{{ route('student.lessons.show', $lesson) }}" class="text-lg font-semibold text-gray-900 hover:underline">
                      {{ $lesson->title }}
                    </a>

                    @if($lesson->description)
                      <div class="text-sm text-gray-700 mt-1">{{ $lesson->description }}</div>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

@endsection
