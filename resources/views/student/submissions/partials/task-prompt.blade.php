{{-- resources/views/student/submissions/partials/task-prompt.blade.php
     Тело одного задания: пассаж/вопрос/таблица/картинка/варианты/соотнесение.
     Ожидает переменную $task. Поле ответа сюда не входит — это только «условие». --}}
@php
  $type         = $task->type ?? 'unknown';
  $questionText = \App\Support\Text::normalize($task->question_text ?? null);
  $passageText  = $task->passage_text ?? null;

  $storageUrl = function ($path) {
    if (!$path) return null;
    $isFull = \Illuminate\Support\Str::startsWith($path, ['http://','https://','/storage/','data:']);
    return $isFull ? $path : \Illuminate\Support\Facades\Storage::url($path);
  };
  $mediaUrl = $storageUrl($task->image_path ?? null);

  $rawOptions = $task->options ?? $task->image_auto_options ?? null;
  $options = [];
  if (is_array($rawOptions)) {
    $options = array_values(array_filter(array_map('trim', $rawOptions), fn($v) => $v !== '' && $v !== null));
  } elseif (is_string($rawOptions)) {
    $decoded = json_decode($rawOptions, true);
    if (is_array($decoded)) {
      $options = array_values(array_filter(array_map('trim', $decoded), fn($v) => $v !== '' && $v !== null));
    } else {
      $lines = preg_split("/\r\n|\r|\n/", $rawOptions);
      $options = array_values(array_filter(array_map('trim', $lines), fn($v) => $v !== '' && $v !== null));
    }
  }

  $normText = function ($s) {
    if ($s === null || $s === '') return [];
    $raw = (string) $s;
    $raw = preg_replace('/^\xEF\xBB\xBF/u', '', $raw);
    $raw = str_replace(["\r\n","\r"], "\n", $raw);
    $raw = str_replace("\xC2\xA0", ' ', $raw);
    $lines = preg_split('/\n/u', $raw);
    while ($lines && trim($lines[0]) === '') array_shift($lines);
    while ($lines && trim(end($lines)) === '') array_pop($lines);
    return array_values(array_filter($lines, fn($s) => trim($s) !== ''));
  };
@endphp

{{-- Текстовый фрагмент для text_with_questions --}}
@if($type === 'text_with_questions' && $passageText)
  <div class="mb-5 sm:mb-6 p-4 sm:p-5 rounded-xl bg-gray-50 border border-gray-200 leading-relaxed text-sm sm:text-base">
    @foreach($normText($passageText) as $p)
      <p class="mb-3 sm:mb-4 last:mb-0">{{ $p }}</p>
    @endforeach
  </div>
@endif

{{-- Вопрос / текст --}}
@if($questionText)
  <div class="text-sm md:text-base text-zinc-800 whitespace-pre-wrap mb-5 sm:mb-6">{{ $questionText }}</div>
@endif

{{-- Пассаж для развёрнутого ответа --}}
@if($type === 'written' && $passageText)
  <div class="mb-5 sm:mb-6 p-4 sm:p-5 bg-gray-50 border rounded-xl leading-relaxed text-sm sm:text-base">
    @foreach($normText($passageText) as $p)
      <p class="mb-3 sm:mb-4 last:mb-0">{{ $p }}</p>
    @endforeach
  </div>
@endif

{{-- Таблица --}}
@if($type === 'table')
  @php
    $tableRaw = $task->table_content ?? null;
    if (is_string($tableRaw)) {
      $decoded = json_decode($tableRaw, true);
      $table = is_array($decoded) ? $decoded : [];
    } elseif (is_array($tableRaw)) {
      $table = $tableRaw;
    } else {
      $table = [];
    }

    $cols = is_array($table['cols'] ?? null) ? $table['cols'] : [];
    $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
    if (empty($cols) && !empty($rows) && is_array($rows[0] ?? null)) {
      $cols = array_map(fn($i) => 'Колонка '.($i+1), range(0, count($rows[0])-1));
    }

    $blanks = is_array($table['blanks'] ?? null) ? $table['blanks'] : [];
    $blankMap = [];
    foreach ($blanks as $b) {
      if (isset($b['r'], $b['c'])) $blankMap[$b['r'].'_'.$b['c']] = $b['key'] ?? '';
    }
  @endphp

  <div class="overflow-auto rounded-xl border border-gray-100 mb-5 sm:mb-6">
    <table class="min-w-full border-collapse">
      @if(!empty($cols))
        <thead class="bg-gray-50">
          <tr>
            @foreach($cols as $c)
              <th class="border border-gray-200 px-3 py-2 sm:py-3 text-left text-xs sm:text-sm font-medium text-zinc-700">{{ $c }}</th>
            @endforeach
          </tr>
        </thead>
      @endif
      <tbody>
        @forelse($rows as $rIdx => $row)
          <tr class="odd:bg-white">
            @foreach((array)$row as $cIdx => $cell)
              @php
                $k = $rIdx.'_'.$cIdx;
                $isBlank = array_key_exists($k, $blankMap);
                $badge = $isBlank ? ($blankMap[$k] ?: '') : '';
              @endphp
              <td class="px-3 py-2 sm:py-3 align-top border border-gray-200">
                @if($isBlank)
                  <div class="inline-flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-amber-50 border border-amber-200 text-amber-700 text-[10px] sm:text-xs font-medium">{{ $badge }}</span>
                    <span class="text-zinc-500 text-xs sm:text-sm">— заполнить</span>
                  </div>
                @else
                  <div class="text-sm sm:text-[15px] text-zinc-800 whitespace-pre-wrap">{{ (string)$cell }}</div>
                @endif
              </td>
            @endforeach
          </tr>
        @empty
          <tr><td class="px-3 py-3 text-xs sm:text-sm text-zinc-500">Таблица не задана</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endif

{{-- Картинка: типу положена картинка, но её могли не загрузить (например,
     ещё не заведена или это тестовые данные) — тогда вместо пустого места
     показываем ту же заглушку, что и для урока/курса без обложки (см.
     student/courses/show.blade.php). --}}
@if(in_array($type, ['image_auto','image_manual']))
  <div class="mb-5 sm:mb-6">
    @if($mediaUrl)
      <img src="{{ $mediaUrl }}" alt="" class="w-full max-h-[360px] sm:max-h-[380px] object-contain rounded-xl border">
    @else
      <div class="w-full h-48 sm:h-56 rounded-xl border border-gray-200 bg-gray-100 flex items-center justify-center text-gray-300">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-12 h-12">
          <rect x="3" y="3" width="18" height="18" rx="2"></rect>
          <circle cx="8.5" cy="8.5" r="1.5"></circle>
          <path d="M21 15l-5-5L5 21"></path>
        </svg>
      </div>
    @endif
  </div>
@endif

{{-- Варианты --}}
@if(!empty($options))
  <div class="mb-5 sm:mb-6 text-zinc-900 text-sm sm:text-base flex flex-col flex-wrap gap-2 sm:gap-3 items-start">
    @foreach($options as $opt)
      <div class="px-2 sm:px-3 py-0.5 sm:py-1 rounded-lg border border-gray-200 bg-gray-50">{{ $opt }}</div>
    @endforeach
  </div>
@endif

{{-- Соотнесение --}}
@if($type === 'matching')
  @php
    $left  = [];
    $right = [];
    if (!empty($task->matches['left'])) {
      $left = is_array($task->matches['left'])
        ? $task->matches['left']
        : preg_split("/\r\n|\r|\n/", (string)$task->matches['left']);
    }
    if (!empty($task->matches['right'])) {
      $right = is_array($task->matches['right'])
        ? $task->matches['right']
        : preg_split("/\r\n|\r|\n/", (string)$task->matches['right']);
    }
    $letters = ['А','Б','В','Г','Д','Е','Ж','З','И','К','Л','М'];
  @endphp
  <div class="grid md:grid-cols-2 gap-4 sm:gap-6 mb-5 sm:mb-6">
    <div class="rounded-xl border bg-white">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-zinc-700">{{ $task->left_title ?? 'Левая колонка' }}</div>
      <div class="divide-y">
        @forelse($left as $iL => $val)
          <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-zinc-500 mr-2">{{ $letters[$iL] ?? ($iL+1) }}.</span> {{ $val }}
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-zinc-500">Нет элементов</div>
        @endforelse
      </div>
    </div>
    <div class="rounded-xl border bg-white">
      <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm font-medium text-zinc-700">{{ $task->right_title ?? 'Правая колонка' }}</div>
      <div class="divide-y">
        @forelse($right as $iR => $val)
          <div class="px-3 py-2 sm:py-3 text-sm sm:text-base">
            <span class="text-zinc-500 mr-2">{{ $iR+1 }}.</span> {{ $val }}
          </div>
        @empty
          <div class="px-3 py-2 sm:py-3 text-xs sm:text-sm text-zinc-500">Нет элементов</div>
        @endforelse
      </div>
    </div>
  </div>
@endif
