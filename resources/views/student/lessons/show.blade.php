@extends('layouts.main')

@section('content')
<div class="max-w-6xl mx-auto md:px-6 px-4 py-6">
  <a href="{{ route('student.courses.show', $course) }}" class="text-sm text-gray-500 hover:text-gray-700">← Назад к курсу</a>

  <h1 class="text-2xl md:text-4xl font-medium font-sans mt-6 mb-3">{{ $lesson->title }}</h1>
  @if($lesson->description)
    <p class="mt-2 md:mb-12 mb-8 text-gray-700 md:text-lg text-base">{{ $lesson->description }}</p>
  @endif

  @if ($lesson->recording_link)
  <div class="inline-block md:py-4 py-2 md:px-5 px-4 rounded-2xl md:text-xl text-lg md:mb-4 mb-4 bg-blue-50 border border-blue-200 text-blue-900"><img class="inline-block relative bottom-0.5 mr-2" src="{{asset('img/Camera.svg')}}" alt="">Запись трансляции</div>
  <div class="border-4 border-blue-100 rounded-xl" style="position: relative; padding-top: 56.25%; width: 100%"><iframe src="https://kinescope.io/embed/{{$lesson->recording_link}}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;" frameborder="0" allowfullscreen style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe></div>
  @endif

  @if ($lesson->short_class)
  <div class="inline-block p-4 px-5 rounded-2xl text-xl md:mb-4 mb-4 mt-12 bg-blue-50 border border-blue-200 text-blue-900"><img class="inline-block relative bottom-0.5 mr-2" src="{{asset('img/Camera.svg')}}" alt="">"Сок" — Выжимка урока</div>
  <div class="border-4 border-blue-100 rounded-xl" style="position: relative; padding-top: 56.25%; width: 100%"><iframe src="https://kinescope.io/embed/{{$lesson->short_class}}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;" frameborder="0" allowfullscreen style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe></div>
  @endif

  {{-- Здесь позже добавим материалы и домашку --}}
</div>

  @if ($lesson->meet_link)
  <div class="grid grid-cols-1 md:grid-cols-7 gap-4 md:px-12 px-4 mb-6">
   
    <div class="md:col-span-5">
      <div class="">
        <div class="border-4 rounded-2xl border-blue-200" style="position: relative; padding-top: 56.25%; width: 100%"><iframe src="https://kinescope.io/embed/{{$lesson->meet_link}}" allow="autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer; clipboard-write; screen-wake-lock;" frameborder="0" allowfullscreen style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;"></iframe></div>

      </div>
    </div>

   
    <div class="md:col-span-2">
      <iframe src="https://kinescope.io/chat/{{$lesson->meet_link}}"
              allow="fullscreen"
              frameborder="0"
              allowfullscreen
              class="w-full h-full min-h-[400px] md:min-h-full"></iframe>
    </div>
  </div>
  @endif

  <div class="max-w-6xl mx-auto mt-12 mb-16 px-4 md:px-6 flex flex-col md:flex-row gap-4 md:gap-6">
  {{-- Конспект --}}
  <div class="flex-1 bg-blue-50 border border-blue-200 rounded-2xl p-4 sm:p-6 flex flex-col sm:flex-row items-center sm:items-center gap-0 sm:gap-6">
    <img
      class="w-28 h-28 sm:w-36 sm:h-36 lg:w-48 lg:h-48 -scale-x-100 {{ $lesson->notes_link ? '' : 'opacity-40' }}"
      src="{{ asset('img/hand-holding-notes.webp') }}"
      alt="Иконка конспекта"
    >
    <div class="flex-1 text-center sm:text-left">
      <h3 class="text-xl sm:text-2xl font-medium text-blue-900 mb-10 sm:mb-4">
        {{ $lesson->notes_link ? 'Конспект урока' : 'Конспекта нет' }}
      </h3>

      @if ($lesson->notes_link)
        <a href="{{ $lesson->notes_link }}"
           class="block sm:inline-block w-full sm:w-auto text-center px-6 py-4 text-base tracking-wide font-medium rounded-xl border-2 bg-zinc-800 text-white border-zinc-800 hover:bg-zinc-900 transition">
          Скачать конспект
        </a>
      @else
        <p class="block w-full text-center px-6 py-4 text-base tracking-wide font-medium rounded-xl border-2 bg-blue-50 border-zinc-300 text-zinc-400">
          Скачивать нечего
        </p>
      @endif
    </div>
  </div>

  {{-- Домашка --}}
  <div class="flex-1 bg-blue-50 border border-blue-200 rounded-2xl p-4 sm:p-6 flex flex-col sm:flex-row items-center sm:items-center gap-0 sm:gap-6">
    <img
      class="w-40 h-40 sm:w-36 sm:h-36 lg:w-48 lg:h-48 -scale-x-100 {{ $lesson->homework ? '' : 'opacity-40' }}"
      src="{{ asset('img/homework-icon.webp') }}"
      alt="Иконка домашнего задания"
    >
    <div class="flex-1 text-center sm:text-left">
      @if ($lesson->homework)
        <h3 class="text-xl sm:text-2xl font-medium text-blue-900 mb-10 sm:mb-4">Домашнее задание</h3>

        @if(!empty($mySubmission))
          {{-- Есть попытка → показываем результаты --}}
          <a href="{{ route('student.submissions.show', $mySubmission) }}"
            class="block sm:inline-block w-full sm:w-auto text-center px-6 py-4 text-base tracking-wide font-medium rounded-xl bg-zinc-800 border-2 border-zinc-800 text-white hover:bg-zinc-900 transition">
            Смотреть результаты
          </a>
        @else
          {{-- Нет попыток → на форму сдачи --}}
          <a href="{{ route('student.submissions.create', $lesson->homework) }}"
            class="block sm:inline-block w-full sm:w-auto text-center px-6 py-4 text-base tracking-wide font-medium rounded-xl bg-zinc-800 border-2 border-zinc-800 text-white hover:bg-zinc-900 transition">
            Перейти к домашке
          </a>
        @endif
      @else
        <h3 class="text-xl sm:text-2xl font-medium text-blue-900 mb-10 sm:mb-3">Домашнего задания пока нет</h3>
        <p class="text-base text-blue-900/80">Возможно, оно будет позже. А может и не будет</p>
      @endif
    </div>
  </div>
</div>

@endsection
