@extends('admin.layouts.main')

@section('title', 'Контакты бота')

@section('content')
@php
    $stageTone = ['not_opened' => 'gray', 'opened' => 'orange', 'watched' => 'blue', 'rated' => 'purple', 'wrote' => 'green', 'blocked' => 'red'];
@endphp

<x-ui.page-header title="Telegram-бот">
    Все, кто запускал бота, — и те, у кого в группе ещё нет темы (ничего не писали). Нажмите на человека, чтобы увидеть всю его историю.
</x-ui.page-header>

@include('admin.telegram._tabs', ['active' => 'contacts'])

@php
    $isLesson = $bot === \App\Service\Telegram\TelegramBots::LESSON;
    $botLabels = ['lesson' => 'Бот пробного урока', 'diag' => '🧪 Бот диагностики'];
@endphp
<x-ui.tabs class="mb-3">
    @foreach ($botLabels as $key => $label)
        <x-ui.tab href="{{ route('admin.telegram.contacts.index', array_filter(['bot' => $key === 'lesson' ? null : $key, 'q' => $q])) }}" :active="$bot === $key">
            {{ $label }} <span class="text-zinc-400 tabular-nums">{{ $botCounts[$key] ?? 0 }}</span>
        </x-ui.tab>
    @endforeach
</x-ui.tabs>

<div class="flex flex-wrap gap-2 mb-3">
    @foreach (['' => 'Все'] + ($isLesson ? \App\Models\TelegramContact::STAGES : []) as $key => $label)
        <a href="{{ route('admin.telegram.contacts.index', array_filter(['bot' => $isLesson ? null : $bot, 'stage' => $key, 'q' => $q])) }}"
           class="inline-flex items-center gap-1.5 px-3 min-h-9 rounded-lg text-sm border transition
                  {{ $stage === $key ? 'bg-zinc-800 border-zinc-800 text-white' : 'bg-white border-zinc-200 text-zinc-700 hover:border-zinc-400' }}">
            {{ $label }} <span class="{{ $stage === $key ? 'text-zinc-300' : 'text-zinc-400' }} tabular-nums">{{ $counts[$key] }}</span>
        </a>
    @endforeach
</div>

<form method="get" class="flex flex-wrap items-end gap-2 mb-4">
    @if ($stage !== '') <input type="hidden" name="stage" value="{{ $stage }}"> @endif
    @if (! $isLesson) <input type="hidden" name="bot" value="{{ $bot }}"> @endif
    <x-ui.input name="q" size="sm" placeholder="Имя, @логин или метка" value="{{ $q }}" wrap="w-full sm:w-72" />
    <x-ui.button type="submit" size="xs" variant="secondary">Найти</x-ui.button>
    @if ($q !== '')
        <x-ui.button href="{{ route('admin.telegram.contacts.index', array_filter(['bot' => $isLesson ? null : $bot, 'stage' => $stage])) }}" size="xs" variant="ghost">Сбросить</x-ui.button>
    @endif
    @if ($userIds !== '')
        <x-ui.button type="button" size="xs" variant="secondary" data-copy-ids="{{ $userIds }}"
                     title="Все id под текущим фильтром — для рассылки: ответьте на карточку в «📣 Рассылка» словом «только» и вставьте их">
            Скопировать id ({{ $counts[$stage] }})
        </x-ui.button>
    @endif
</form>

@if ($contacts->isEmpty())
    <x-ui.empty>Никого нет</x-ui.empty>
@else
    <x-ui.table>
        <thead>
            <tr><th>Кто</th><th>Этап</th><th>{{ $isLesson ? 'Уроки' : 'Диагностика' }}</th><th>Дальше</th><th>Старт</th></tr>
        </thead>
        <tbody>
            @foreach ($contacts as $c)
                @php $next = $c->nextScheduled(); @endphp
                <tr>
                    <td data-primary>
                        <a href="{{ route('admin.telegram.contacts.show', $c) }}" class="text-blue-600 hover:underline">{{ $c->displayName() }}</a>
                        @if ($c->username && ! str_starts_with($c->displayName(), '@'))
                            <span class="text-zinc-400 text-sm">{{ '@'.$c->username }}</span>
                        @endif
                        <div class="text-xs text-zinc-400 font-mono">
                            <span class="select-all text-zinc-500" title="Telegram id">{{ $c->telegram_user_id }}</span> · {{ $c->source ?? 'без метки' }}
                        </div>
                    </td>
                    <td data-label="Этап">
                        <span>
                            @if ($isLesson)
                                <x-ui.badge :tone="$stageTone[$c->stageKey()]">{{ \App\Models\TelegramContact::STAGES[$c->stageKey()] }}</x-ui.badge>
                            @else
                                <span title="статус темы">{{ $c->statusEmoji() }}</span>
                            @endif
                            @if ($c->awaitingReply()) <x-ui.badge tone="orange">ждёт ответа</x-ui.badge> @endif
                        </span>
                    </td>
                    <td data-label="{{ $isLesson ? 'Уроки' : 'Диагностика' }}">
                        @include($isLesson ? 'admin.telegram.contacts._lessons' : 'admin.telegram.contacts._diagnostic', ['c' => $c])
                    </td>
                    <td data-label="Дальше" class="text-xs md:text-sm">
                        @if ($next)
                            {{ $next[0] }} <span class="text-zinc-400 md:whitespace-nowrap">— {{ $next[1]->format('d.m H:i') }}</span>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </td>
                    <td data-label="Старт" class="md:whitespace-nowrap text-sm">{{ $c->started_at->format('d.m H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    <div class="mt-4">{{ $contacts->links() }}</div>
@endif

<script>
document.addEventListener('click', function (e) {
    var button = e.target.closest('[data-copy-ids]');
    if (!button) return;

    var ids = button.getAttribute('data-copy-ids');
    var label = button.textContent;
    var done = function () {
        button.textContent = 'Скопировано ✓';
        setTimeout(function () { button.textContent = label; }, 1500);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(ids).then(done).catch(function () { window.prompt('Скопируйте id:', ids); });
        return;
    }
    // http без TLS (локально) — clipboard API недоступен.
    var area = document.createElement('textarea');
    area.value = ids;
    document.body.appendChild(area);
    area.select();
    try { document.execCommand('copy'); done(); } catch (err) { window.prompt('Скопируйте id:', ids); }
    area.remove();
});
</script>
@endsection
