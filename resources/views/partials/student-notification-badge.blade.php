{{-- Счётчик непрочитанных на колокольчике — отдельный partial, чтобы
     NotificationController::destroy() мог вернуть его же как htmx OOB-фрагмент
     и обновить бейдж в хедере без перезагрузки страницы (см.
     partials/student-notification-bell.blade.php). --}}
@php $oob = $oob ?? false; @endphp
<span id="notif-badge-wrapper"@if($oob) hx-swap-oob="true"@endif>
    @if($unreadCount > 0)
        <span class="badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
    @endif
</span>
