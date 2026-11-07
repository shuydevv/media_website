{{-- resources/views/student/dates/index.blade.php
     Главная тренажёра дат: три карточки активностей (повторение, новые даты,
     свободная тренировка) и карта событий и правлений по периодам. Устроена
     как student/terms/index; статусы — App\Service\Dates\DateTrainer::overview().
     Картинки активностей пока общие с тренажёром терминов. --}}
@extends('layouts.main')

@section('title', 'Тренажёр дат — Школа Полтавского')

@php
  $termWord = ['дата', 'даты', 'дат'];
  $review = $overview['review'];
  $learn = $overview['learn'];
  $free = $overview['free'];

  // Карточки активностей: подпись под заголовком и либо кнопка, либо
  // причина, по которой активность сейчас закрыта. Тон — как у рыбы
  // (config/fish_speech.php): дружелюбно, «мы», без рода; глагол не
  // согласуем с числом («1 дата ждут» — нельзя).
  // «Сегодня повторять нечего» само по себе ничего не говорит — показываем,
  // когда и сколько дат придёт на повторение.
  $plural = fn (int $n, array $forms) => \App\Support\Text::plural($n, $forms);
  $nextReview = $review['next'] ?? null;
  $nextReviewLabel = $nextReview
      ? 'Следующее — '.($nextReview['date']->isTomorrow() ? 'завтра' : $nextReview['date']->translatedFormat('j F'))
          .', '.$plural($nextReview['count'], $termWord)
      : null;

  $rows = [
    'review' => [
      'title' => 'Повторение',
      'note' => match ($review['status']) {
        'available' => 'Пора освежить '.$plural($review['count'], $termWord).' — пара минут, и они с тобой надолго. За это до 3 🍕'
            .(($review['carried'] ?? 0) > 0 ? ' Ещё '.$plural($review['carried'], $termWord).' — завтра' : ''),
        'in_progress' => 'Мы на середине — осталось '.$plural($review['count'], $termWord),
        'done' => ($review['carried'] ?? 0) > 0
            ? 'Сегодня повторили 👌 Завтра ещё '.$plural($review['carried'], $termWord)
            : 'Сегодня повторили 👌'.($nextReviewLabel ? ' '.$nextReviewLabel : ''),
        default => $nextReview
            ? 'Повторять пока нечего. Откроется '.($nextReview['date']->isTomorrow() ? 'завтра' : $nextReview['date']->translatedFormat('j F'))
                .' ('.$plural($nextReview['count'], $termWord).')'
            : 'Здесь будут даты, которые ты выучишь. Повторим их в нужный момент, чтобы не забылись',
      },
      'button' => ['available' => 'Повторить', 'in_progress' => 'Продолжить'][$review['status']] ?? null,
    ],
    'learn' => [
      'title' => 'Новые даты',
      'note' => match ($learn['status']) {
        'available' => 'Сегодня '.$plural($learn['count'], ['новая дата', 'новые даты', 'новых дат']).'. Выучи и заработай корм! 🍕',
        'in_progress' => 'Порция начата — давай доучим',
        'blocked_due' => 'Сначала повторим то, что уже выучено, — потом новые',
        'done_today' => 'Сегодняшняя порция выучена 🎉 Новые даты — завтра',
        default => 'Все даты выучены. Ты супер! 🏆',
      },
      'button' => ['available' => 'Учить', 'in_progress' => 'Продолжить'][$learn['status']] ?? null,
    ],
    'free' => [
      'title' => 'Свободная тренировка',
      'note' => match ($free['status']) {
        'available' => 'Пробегись по уже выученным датам. Корм не начисляется',
        'in_progress' => 'Круг в процессе: осталось '.$free['count'].' из '.$free['total'],
        'blocked_review' => 'Откроется после повторения — сначала главное',
        default => 'Откроется, когда выучишь первые даты',
      },
      'button' => ['available' => 'Тренироваться', 'in_progress' => 'Продолжить'][$free['status']] ?? null,
    ],
  ];

  // Первой стоит карточка, с которой ученику сейчас начинать: обычно это
  // повторение, но когда оно закрыто (нечего повторять или уже пройдено) —
  // новые даты.
  if ($rows['review']['button'] === null) {
      $rows = ['learn' => $rows['learn'], 'review' => $rows['review'], 'free' => $rows['free']];
  }
@endphp

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

  {{-- Корм на ПК — в одной строке с заголовком, по центру по высоте; на
       телефоне — под подзаголовком, иначе он отжимает заголовок и тот
       переносится на две строки. Поэтому два экземпляра, а не один. --}}
  <div class="mb-5">
    <div class="flex items-center justify-between gap-3">
      <h1 class="sans-medium text-2xl md:text-3xl text-zinc-900 min-w-0">Тренажёр дат</h1>
      <div class="hidden md:flex shrink-0 text-sm text-zinc-600 items-center gap-1">
        Корм:
        <img src="{{ auth()->user()->fish_food_icon_url }}" alt="" class="w-5 h-5 inline-block">
        <span class="font-medium text-zinc-900">{{ $cormBalance }}</span>
      </div>
    </div>
    <div class="mt-2 text-sm text-zinc-500">События и правители с годами — для хронологии и всех заданий на даты</div>
    <div class="md:hidden mt-2 text-sm text-zinc-600 flex items-center gap-1">
      Корм:
      <img src="{{ auth()->user()->fish_food_icon_url }}" alt="" class="w-5 h-5 inline-block">
      <span class="font-medium text-zinc-900">{{ $cormBalance }}</span>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-3 mb-6">
    @foreach($rows as $kind => $row)
      @php
        $status = $overview[$kind]['status'];
        $isDone = in_array($status, ['done', 'done_today', 'all_learned']);
        // Свои сцены тренажёра дат (с Душнилой) — public/img/trainers/dates/.
        // WebP рядом с JPG — легче; JPG — запасной.
        $base = 'img/trainers/dates/'.['review' => 'repeating', 'learn' => 'new_exercise', 'free' => 'free_session'][$kind];
        $file = collect([$base.'.webp', $base.'.jpg'])->first(fn ($f) => file_exists(public_path($f)));
        $image = $file ? asset($file) : null;
      @endphp
      <x-ui.card class="flex flex-col min-w-0">
        <div class="aspect-[16/9] rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center mb-4">
          @if($image)
            <img src="{{ $image }}" alt="" class="w-full h-full object-cover">
          @else
            <x-icon name="image-01" class="w-8 h-8 text-zinc-300" />
          @endif
        </div>

        <div class="sans-medium text-lg text-zinc-900">{{ $row['title'] }}</div>
        <div class="text-sm text-zinc-500 mt-1">{{ $row['note'] }}</div>

        {{-- mt-auto прижимает кнопку к низу: подписи разной длины, а кнопки
             трёх карточек должны стоять на одной линии. --}}
        <div class="mt-auto pt-4">
          @if($row['button'])
            <form method="POST" action="{{ route('student.dates.start', $kind) }}">
              @csrf
              <x-ui.button type="submit" size="sm" block :variant="$kind === 'free' ? 'game' : 'game-main'">{{ $row['button'] }}</x-ui.button>
            </form>
          @else
            <div class="flex items-center justify-center gap-2 rounded-lg bg-gray-50 px-4 py-3 text-sm {{ $isDone ? 'text-emerald-700' : 'text-zinc-400' }}">
              <x-icon name="{{ $isDone ? 'check-circle' : 'lock-01' }}" class="w-4 h-4 shrink-0" />
              {{ $isDone ? 'Готово' : 'Пока закрыто' }}
            </div>
          @endif
        </div>
      </x-ui.card>
    @endforeach
  </div>

  @if($weakItems->isNotEmpty())
    {{-- Даты, последняя проверка которых закончилась ошибкой. --}}
    <x-ui.card class="mb-6">
      <div class="sans-medium text-xs uppercase tracking-wide text-zinc-400 mb-2">Стоит повторить</div>
      <div class="flex flex-wrap gap-2">
        @foreach($weakItems as $weak)
          <a href="{{ route('student.dates.show', $weak) }}"
             class="inline-block rounded-lg border border-zinc-200 bg-gray-50 px-3 py-1.5 text-sm text-zinc-800 hover:bg-gray-100">
            <span class="text-blue-700 tabular-nums">{{ $weak->yearsLabel() }}</span> · {{ $weak->title }}
          </a>
        @endforeach
      </div>
    </x-ui.card>
  @endif

  @foreach($levels as $level => $levelData)
    @php $locked = $levelData['locked']; @endphp
    <div class="flex items-baseline justify-between gap-3 mb-3 mt-10 md:mt-12">
      <h2 class="sans-medium text-lg md:text-xl text-zinc-900">
        Уровень {{ $level }} · {{ $levelData['label'] }}
        @if($locked)
          <x-icon name="lock-01" class="inline-block w-4 h-4 text-zinc-400 relative bottom-0.5 ml-1" />
        @endif
      </h2>
      <div class="text-sm text-zinc-500 whitespace-nowrap">{{ $levelData['learned'] }} из {{ $levelData['total'] }}</div>
    </div>

    @if($locked)
      <div class="text-sm text-zinc-500 mb-3">Откроется, когда изучишь все даты предыдущего уровня.</div>
    @endif

    {{-- Год показываем только у изученного: у неизученного он был бы
         подсказкой к будущему вопросу. --}}
    <div class="space-y-4">
      @foreach($levelData['eras'] as $eraNumber => $eraItems)
        <x-ui.card>
          <div class="{{ $locked ? 'opacity-60' : '' }}">
          <div class="flex items-baseline justify-between gap-3 mb-3">
            <div class="sans-medium text-sm text-zinc-500">{{ $eras[$eraNumber] ?? 'Период '.$eraNumber }}</div>
            <div class="text-xs text-zinc-400 whitespace-nowrap">{{ $eraItems->filter(fn ($t) => $progress->has($t->id))->count() }} из {{ $eraItems->count() }}</div>
          </div>
          <div class="grid gap-x-8 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($eraItems as $eraItem)
              @php $row = $progress->get($eraItem->id); @endphp
              <div class="flex items-center justify-between gap-3 min-w-0">
                @if($row)
                  <a href="{{ route('student.dates.show', $eraItem) }}" class="min-w-0 text-sm text-zinc-900 hover:underline truncate">
                    <span class="text-blue-700 tabular-nums">{{ $eraItem->yearsLabel() }}</span> · {{ $eraItem->title }}
                  </a>
                  <span class="shrink-0 flex gap-1" title="Шаг {{ $row->step }} из {{ $maxStep }}">
                    @for($i = 1; $i <= $maxStep; $i++)
                      <span class="w-1.5 h-1.5 rounded-full {{ $i <= $row->step ? 'bg-blue-500' : 'bg-gray-200' }}"></span>
                    @endfor
                  </span>
                @else
                  <span class="min-w-0 text-sm text-zinc-400 truncate">
                    {{ $eraItem->isReign() ? 'Правление: ' : '' }}{{ $eraItem->title }}
                  </span>
                @endif
              </div>
            @endforeach
          </div>
          </div>
        </x-ui.card>
      @endforeach
    </div>
  @endforeach

</div>
@endsection
