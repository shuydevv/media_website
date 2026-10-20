{{--
  ИИ-проверка через Claude Desktop (только админ): ① выгрузить порцию →
  ② проверить в Claude навыком ege-homework-check → ③ загрузить результат.
  Данные — DesktopGradingExchange::stats() + версия правил + AiGradingStats.
--}}
@php
  $hasOpen  = $aiDesktop['open']->isNotEmpty();
  $report   = session('ai_report');
  $quality  = $aiDesktop['quality'];
  $pct      = fn ($part, $total) => $total ? (int) round($part * 100 / $total) : 0;
  $prompt   = 'Проверь эту выгрузку домашек навыком ege-homework-check';
  // Подсвечиваем шаг, который сейчас нужен: есть выгрузка без результата — ③, иначе ①.
  $stepCls  = fn (bool $active) => $active ? 'border-purple-400 ring-2 ring-purple-200 bg-white' : 'border-purple-100 bg-white/70';
@endphp

<section class="mb-6 rounded-2xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-900">
  <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
    <h2 class="text-base font-semibold">ИИ-проверка через Claude</h2>
    <div class="text-xs text-purple-700">
      Правила: версия <span class="font-mono">{{ $aiDesktop['rules_version'] }}</span> ·
      <a href="{{ route('mentor.review.ai_desktop.skill') }}" class="underline">Скачать навык</a>
    </div>
  </div>

  {{-- Отчёт о последней загрузке --}}
  @if($report)
    <div class="mb-3 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-green-900">
      <div class="font-medium">
        Загружено черновиков: {{ $report['imported'] }}
        @if(count($report['review'])) · «Проверьте сами»: {{ array_sum($report['review']) }} @endif
        @if(count($report['missing'])) · не вернулись: {{ count($report['missing']) }} (уйдут в следующую порцию) @endif
      </div>
      @if(count($report['review']))
        <div class="mt-1">
          Начните с этих работ:
          @foreach($report['review'] as $submissionId => $n)
            <a href="{{ route('mentor.submissions.show', $submissionId) }}" class="underline">#{{ $submissionId }}</a>@if($n > 1) ({{ $n }} зад.)@endif{{ !$loop->last ? ',' : '' }}
          @endforeach
        </div>
      @endif
      @if(count($report['skipped']))
        <details class="mt-1">
          <summary class="cursor-pointer select-none text-green-800">Пропущено: {{ count($report['skipped']) }}</summary>
          <ul class="mt-1 list-disc pl-5 text-xs">
            @foreach($report['skipped'] as $line)<li>{{ $line }}</li>@endforeach
          </ul>
        </details>
      @endif
    </div>
  @endif

  <div class="grid gap-3 md:grid-cols-3">
    {{-- ① Выгрузить --}}
    <div class="rounded-xl border p-3 {{ $stepCls(!$hasOpen) }}">
      <div class="mb-1 font-medium"><span class="mr-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-purple-600 text-xs text-white">1</span> Выгрузить порцию</div>
      @if($aiDesktop['tasks'] > 0)
        <div>Ждут: {{ $aiDesktop['tasks'] }} зад. в {{ $aiDesktop['submissions'] }} раб. · ≈ {{ $aiDesktop['portions'] }} порц.</div>
        <div class="mt-1 flex flex-wrap gap-1">
          @foreach($aiDesktop['by_subject'] as $subject => $n)
            <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs">{{ $subject }}: {{ $n }}</span>
          @endforeach
        </div>
        <form method="POST" action="{{ route('mentor.review.ai_desktop.export') }}" class="mt-2 flex flex-col gap-2">
          @csrf
          @if(count($aiDesktop['by_subject']) > 1)
            <select name="subject" class="rounded-lg border border-purple-200 px-2 py-1.5 text-sm">
              <option value="">Все предметы</option>
              @foreach($aiDesktop['by_subject'] as $subject => $n)
                <option value="{{ $subject }}">Только {{ $subject }}</option>
              @endforeach
            </select>
          @endif
          <button type="submit" class="rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700">
            Выгрузить ~{{ min($aiDesktop['tasks'], \App\Service\Homework\DesktopGradingExchange::PORTION) }} зад.
          </button>
        </form>
        <div class="mt-1 text-xs text-purple-700">Работа ученика всегда целиком.</div>
      @else
        <div class="text-purple-700">Новых письменных заданий нет.</div>
      @endif
    </div>

    {{-- ② Проверить в Claude --}}
    <div class="rounded-xl border p-3 border-purple-100 bg-white/70">
      <div class="mb-1 font-medium"><span class="mr-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-purple-600 text-xs text-white">2</span> Проверить в Claude</div>
      <div>Откройте Claude Desktop, перетащите файл выгрузки в чат и отправьте:</div>
      <div class="mt-2 flex items-center gap-2">
        <code class="ai-prompt flex-1 rounded-lg bg-purple-100 px-2 py-1 text-xs">{{ $prompt }}</code>
        <button type="button" class="ai-copy shrink-0 rounded-lg border border-purple-300 px-2 py-1 text-xs hover:bg-purple-100">Копировать</button>
      </div>
      <div class="mt-2 text-xs text-purple-700">Claude вернёт файл <span class="font-mono">homework-check-result-….json</span> — его на шаг 3.</div>
    </div>

    {{-- ③ Загрузить результат --}}
    <div class="rounded-xl border p-3 {{ $stepCls($hasOpen) }}">
      <div class="mb-1 font-medium"><span class="mr-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-purple-600 text-xs text-white">3</span> Загрузить результат</div>
      <form method="POST" action="{{ route('mentor.review.ai_desktop.import') }}" enctype="multipart/form-data" class="ai-drop-form">
        @csrf
        <label class="ai-dropzone flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-purple-300 px-3 py-5 text-center hover:bg-purple-50">
          <span class="ai-drop-text">Перетащите файл результата сюда<br><span class="text-xs text-purple-700">или нажмите, чтобы выбрать</span></span>
          <input type="file" name="result" accept=".json,application/json" class="sr-only">
        </label>
      </form>
    </div>
  </div>

  {{-- Выгрузки, ждущие результата --}}
  @if($hasOpen)
    <div class="mt-3 space-y-2">
      @foreach($aiDesktop['open'] as $open)
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
          <div>
            Ждёт результата: <span class="font-mono">{{ $open->external_id }}</span> ·
            {{ $open->request_count }} зад. · выгружено {{ $open->created_at->format('d.m H:i') }}
          </div>
          <div class="flex gap-2">
            <form method="POST" action="{{ route('mentor.review.ai_desktop.redownload', $open) }}">
              @csrf
              <button type="submit" class="rounded-lg border border-amber-300 px-2 py-1 text-xs hover:bg-amber-100">Скачать ещё раз</button>
            </form>
            <form method="POST" action="{{ route('mentor.review.ai_desktop.cancel', $open) }}"
                  onsubmit="return confirm('Отменить выгрузку? Задания вернутся в очередь на выгрузку.');">
              @csrf
              <button type="submit" class="rounded-lg border border-amber-300 px-2 py-1 text-xs hover:bg-amber-100">Отменить</button>
            </form>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- Качество ИИ: насколько куратор согласен с черновиком --}}
  <details class="mt-3 rounded-xl border border-purple-100 bg-white/70 px-3 py-2">
    <summary class="cursor-pointer select-none">
      Качество ИИ за 90 дней:
      @if($quality['total'])
        балл совпал с куратором в <b>{{ $pct($quality['score_match'], $quality['total']) }}%</b>,
        тексты без существенных правок — <b>{{ $pct($quality['text']['same'] + $quality['text']['cosmetic'], $quality['total']) }}%</b>
        ({{ $quality['total'] }} зад.)
      @else
        пока нет данных — появятся, когда кураторы начнут сохранять оценки по черновикам ИИ.
      @endif
    </summary>
    @if($quality['total'])
      <div class="mt-2 text-xs text-purple-800">
        Тексты: без правок {{ $quality['text']['same'] }} ·
        косметические {{ $quality['text']['cosmetic'] }} ·
        существенные {{ $quality['text']['substantial'] }} ·
        переписаны {{ $quality['text']['rewritten'] }}.
        Удалённая точка или исправленная буква правкой не считаются.
      </div>
      <table class="mt-2 text-xs">
        <thead><tr class="text-left text-purple-700"><th class="pr-4">№ задания</th><th class="pr-4">Заданий</th><th>Балл совпал</th></tr></thead>
        <tbody>
          @foreach($quality['by_number'] as $number => $row)
            @php($p = $pct($row['score_match'], $row['total']))
            <tr>
              <td class="pr-4">{{ $number }}</td>
              <td class="pr-4">{{ $row['total'] }}</td>
              <td class="{{ $row['total'] >= 5 && $p < 70 ? 'font-semibold text-red-700' : '' }}">{{ $p }}%</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="mt-1 text-xs text-purple-700">Красным — номера, где ИИ часто ошибается (от 5 заданий и совпадение ниже 70%): их правила стоит уточнить.</div>
    @endif
  </details>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ② Копировать текст для чата Claude
  document.querySelectorAll('.ai-copy').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.parentElement.querySelector('.ai-prompt').textContent.trim();
      try {
        await navigator.clipboard.writeText(text);
      } catch (e) {
        const range = document.createRange();
        range.selectNodeContents(btn.parentElement.querySelector('.ai-prompt'));
        getSelection().removeAllRanges();
        getSelection().addRange(range);
        document.execCommand('copy');
      }
      const old = btn.textContent;
      btn.textContent = 'Скопировано';
      setTimeout(() => (btn.textContent = old), 1500);
    });
  });

  // ③ Загрузка результата: выбор или перетаскивание файла — сразу отправка
  document.querySelectorAll('.ai-drop-form').forEach((form) => {
    const zone = form.querySelector('.ai-dropzone');
    const input = form.querySelector('input[type=file]');
    const send = () => {
      if (!input.files.length) return;
      form.querySelector('.ai-drop-text').textContent = 'Загружаю ' + input.files[0].name + '…';
      zone.classList.add('opacity-60', 'pointer-events-none');
      form.submit();
    };
    input.addEventListener('change', send);
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
      e.preventDefault();
      zone.classList.add('bg-purple-100', 'border-purple-500');
    }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
      e.preventDefault();
      zone.classList.remove('bg-purple-100', 'border-purple-500');
    }));
    zone.addEventListener('drop', (e) => {
      if (!e.dataTransfer.files.length) return;
      input.files = e.dataTransfer.files;
      send();
    });
  });
});
</script>
