{{-- ИИ-статус работы в списке: есть ли черновик ИИ / ждёт ли проверки. Без значка — ИИ работу не проверял. --}}
@php($ai = $submission->aiDraftSummary())
@if($ai['ready'] > 0)
  <div class="mt-1 inline-flex rounded-full bg-green-50 border border-green-200 px-2 py-0.5 text-xs text-green-800">
    ИИ-черновик: {{ $ai['ready'] }} из {{ $ai['total'] }}
    @if($ai['waiting'] > 0) · в очереди: {{ $ai['waiting'] }} @endif
  </div>
@elseif($ai['waiting'] > 0)
  <div class="mt-1 inline-flex rounded-full bg-purple-50 border border-purple-200 px-2 py-0.5 text-xs text-purple-800">
    Ждёт ИИ-проверки
  </div>
@endif
