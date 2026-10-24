{{-- Состояние диагностики контакта бота diag: последнее прохождение. $c — TelegramContact. --}}
@php $attempt = $c->latestDiagnostic(); @endphp
@if (! $attempt)
    <span class="text-xs md:text-sm text-zinc-400">не открывал</span>
@else
    <div class="text-xs md:text-sm space-y-0.5">
        {{-- Строки уже экранированы (DiagnosticSummary — тот же текст, что в карточке темы). --}}
        @foreach (\App\Service\Diagnostic\DiagnosticSummary::attemptLines($attempt) as $line)
            <div>{!! $line !!}</div>
        @endforeach
    </div>
@endif
