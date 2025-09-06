@extends('layouts.main')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <div class="mb-6">
    <a href="{{ route('student.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">← Назад в дашборд</a>
  </div>

  <div class="mb-6">
    <h1 class="md:text-3xl text-2xl md:mb-3 mb-2 font-sans font-medium text-gray-900">{{ $course->title }}</h1>

    {{-- Сроки курса: надёжный вывод, даже если поля не кастятся к Carbon --}}
    @if(!empty($course->start_date) || !empty($course->end_date))
      <div class="mt-1 text-base text-gray-600">
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
  

  @if(!$nextSession)
    <div class="text-sm text-gray-600">Ближайшее занятие не запланировано.</div>
  @else
    @php
      $s = $nextSession;
      $lesson = $s->lesson; // может быть null
    @endphp
    <div class="rounded-2xl bg-blue-50 border border-blue-200 md:p-4 p-4">
      <div class="flex flex-col md:flex-row md:items-stretch md:gap-7 gap-4">
        {{-- Картинка урока (если есть) --}}
        @if($lesson && $lesson->image_url)
          <div class="w-full md:w-1/2">
            <div class="relative aspect-[16/10]">
              <img src="{{ $lesson->image_url }}" 
                   alt="" 
                   class="w-full h-full rounded-xl object-cover border border-blue-200">
                
              <h2 class="absolute top-3 left-3 bg-white/60 px-3 py-1 rounded-2xl text-xs md:text-base font-sans font-semibold text-blue-900 mb-3"><img class="inline-block md:mr-2 mr-1 md:w-auto w-4" src=" {{asset('/img/Return.svg')}} " alt="">Следующее занятие</h2>
            </div>
          </div>
        @endif

        <div class="w-full md:w-1/2 flex flex-col mt-0 md:mt-1">
          <div class="text-sm md:text-base tracking-wide opacity-60 text-blue-800">
            <img class="inline-block relative bottom-0.5 mr-1 w-4 h-4 md:w-5 md:h-5" 
                 src="{{ asset('img/Date_range.svg') }}" 
                 alt="Date">
            @if($s->display_date)
              {{ \Illuminate\Support\Carbon::parse($s->display_date)->translatedFormat('j F') }}
              @if($s->display_time) в {{ \Illuminate\Support\Str::substr($s->display_time, 0, 5) }} @endif
            @else
              Дата не указана
            @endif
          </div>

          <div class="mt-4 md:mt-5 md:mb-2 mb-1">
            @if($lesson)
              <a href="{{ route('student.lessons.show', $lesson) }}" 
                 class="text-3xl md:text-4xl tracking-wide font-medium text-blue-900">
                {{ $lesson->title }}
              </a>
            @else
              <div class="text-base md:text-lg font-semibold text-blue-900">Тема урока пока неизвестна</div>
            @endif
          </div>

          @if($lesson?->description)
            <div class="text-sm md:text-lg tracking-wide text-blue-800 mt-1">
              {{ $lesson->description }}
              {{-- <hr class="mt-8 mb-4 border-1 border-blue-200"> --}}
            </div>
          @endif

            {{-- эластичный «разделитель» свободного пространства:
       на десктопе растягиваем, на мобиле не мешаем естественному потоку --}}
          <div class="md:block hidden flex-1"></div>

          @if($lesson?->description)
            {{-- hr с адаптивными отступами: верх меньше, низ больше.
              clamp(min, preferred vw/vh, max) — чтобы красиво скейлилось --}}
            <hr class="border-1 border-blue-200 
                      mt-[clamp(6px,1.2vh,14px)] 
                      mb-[clamp(16px,3.5vh,36px)]">
          @endif
          

          @if($lesson?->id)
            <div class="mt-8 md:mt-10">
              <a href="{{ route('student.lessons.show', $lesson) }}"
                class="md:inline-block block text-center px-6 md:px-8 py-4 md:py-4 md:text-base text-base tracking-wide font-medium rounded-xl bg-zinc-800 border text-white hover:bg-zinc-900 transition">
                Перейти к уроку
              </a>
            </div>
          @endif
        </div>
      </div>
    </div>
  @endif
</div>


  {{-- Прошедшие занятия (только с привязанным уроком) --}}
{{-- Прошедшие занятия (группировка по месяцам) --}}
<div id="past-sessions">
  {{-- <h2 class="text-3xl font-semibold font-sans text-gray-900 mb-3">Прошедшие занятия</h2> --}}

  @if(($pastByMonth ?? collect())->isEmpty())
    <div class="text-sm text-gray-600">
      Прошедших занятий пока нет.
      {{-- @if(($pastHiddenCount ?? 0) > 0)
        <div class="mt-1">
          Есть {{ $pastHiddenCount }} прошедш{{ $pastHiddenCount === 1 ? 'ая сессия' : ($pastHiddenCount < 5 ? 'ие сессии' : 'их сессий') }} без привязанного урока — они скрыты.
        </div>
      @endif --}}
    </div>
  @else
    <div class="space-y-6">
      @foreach($pastByMonth as $monthKey => $items)
        {{-- Заголовок месяца, с заглавной буквы и двоеточием --}}
        <div>
          <div class="flex justify-center">
            <div class="md:text-base tracking-wide text-lg border-2 rounded-2xl border-blue-100 inline-block py-1.5 px-4 font-normal text-blue-800 mt-6 md:mb-6 mb-4">
              <img class="inline-block relative bottom-1 mr-1" src="{{ asset('img/Date_range.svg') }}" alt="">
              {{ \Illuminate\Support\Str::ucfirst($monthKey) }}:
            </div>
          </div>


          <div class="md:gap-6 gap-4 grid sm:grid-cols-1 lg:grid-cols-3">
            @foreach($items as $s)
              @php
                $lesson = $s->lesson; // гарантированно есть
              @endphp
              <div class="rounded-2xl bg-gray-50 border border-gray-200 p-4">
                <div class="aspect-[16/10] gap-4">
                  {{-- Картинка урока (если есть) --}}
                  @if($lesson->image_url ?? false)
                    <img src="{{ $lesson->image_url }}" alt="cover" class="w-full h-full object-cover rounded-xl object-cover border border-gray-200">
                  @endif

                  {{-- Заголовок и ссылка на страницу урока --}}
                  <h3 class="text-xl sans font-medium mt-3 md:mb-2 mb-1 text-gray-900">
                    {{ $lesson->title }}
                  </h3>

                  <div class="flex-1">
                    {{-- Дата занятия: "2 сентября в 15:30" (без секунд, как договорились) --}}
                    <div class="text-base text-gray-600 bg-white">
                      {{-- Дата занятия: --}}
                      <img class="inline-block opacity-50 relative bottom-0.5 mr-1 w-4 h-4 md:w-5 md:h-5" 
                      src="{{ asset('img/Date_range.svg') }}" 
                      alt="Date">
                      @if($s->display_date)
                        {{ \Carbon\Carbon::parse($s->display_date)->locale('ru')->isoFormat('D MMMM') }}
                        @if($s->display_time) в {{ \Illuminate\Support\Str::substr($s->display_time, 0, 5) }} @endif
                      @else
                        Дата не указана
                      @endif
                    </div>


                    @if(Route::has('student.lessons.show'))
                      <a href="{{ route('student.lessons.show', $lesson) }}"
                        class="block mt-6 ml-auto text-center mr-auto w-full px-3 py-4 md:text-base text-base tracking-wide font-medium rounded-xl bg-zinc-800 border text-white hover:bg-zinc-900 transition">
                        Перейти к уроку
                      </a>
                    @endif

                    {{-- @if($lesson->description)
                      <div class="text-sm text-gray-700 mt-1">{{ $lesson->description }}</div>
                    @endif --}}
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
