{{--
    Страница записи простого справочника: сводка полей + «Редактировать» /
    «Удалить».

    Параметры:
      $title       — заголовок (название записи)
      $backUrl, $backLabel — ссылка «назад» к списку
      $rows        — [подпись => значение] для сводки
      $editUrl     — URL формы редактирования
      $deleteUrl   — URL удаления (DELETE)
      $deleteConfirm — текст подтверждения удаления
      $publicUrl   — необязательная ссылка «открыть на сайте»
--}}
<div class="max-w-3xl">
    <x-ui.page-header :title="$title" :back="$backUrl" :back-label="$backLabel">
        <x-slot:actions>
            <x-ui.button href="{{ $editUrl }}" size="xs">Редактировать</x-ui.button>
            @if (! empty($publicUrl))
                <x-ui.button href="{{ $publicUrl }}" variant="secondary" size="xs" target="_blank" rel="noopener">Открыть на сайте</x-ui.button>
            @endif
            <x-ui.action-form :action="$deleteUrl" variant="danger-soft" :confirm="$deleteConfirm">Удалить</x-ui.action-form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($rows as $rowLabel => $rowValue)
                <div class="min-w-0">
                    <dt class="sans-medium text-xs uppercase tracking-wide text-zinc-400">{{ $rowLabel }}</dt>
                    <dd class="mt-0.5 text-sm text-zinc-900 break-words">{{ filled($rowValue) ? $rowValue : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>
</div>
