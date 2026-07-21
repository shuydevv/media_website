{{-- Колокольчик уведомлений в шапке — только на страницах ученика (см. guard
     в layouts/main.blade.php). Независим от partials/student-bottom-nav —
     тот компонент не трогаем. Счётчик считается прямо здесь, для текущего
     объёма это дёшево.

     Клик открывает выпадающую панель с последними уведомлениями (не переход
     на отдельную страницу) — сама панель позиционируется абсолютно
     относительно #site-header-row (layouts/main.blade.php), той же точки
     отсчёта, что и сам колокольчик, поэтому обёртывать их обоих в
     дополнительный div не нужно.

     Вся визуально-критичная стилизация — обычным <style>, не Tailwind-
     классами: в этом браузере часть Tailwind-классов (виделось на sm:-
     вариантах для нижнего меню, и на min-h-[56px]/top-1/2 здесь) почему-то
     не применяется. --}}
<style>
    #student-notification-bell {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
        color: #6b7280;
        text-decoration: none;
        transition: color .15s ease, background-color .15s ease;
        z-index: 20;
    }
    #student-notification-bell:hover {
        color: #b45309;
        background: #f9fafb;
    }
    #student-notification-bell svg {
        width: 20px;
        height: 20px;
    }
    #student-notification-bell .badge {
        position: absolute;
        top: -2px;
        right: -2px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 9999px;
        background: #d97706;
        color: #fff;
        font-size: 10px;
        font-weight: 500;
        line-height: 18px;
        text-align: center;
    }
    #notif-panel {
        display: none;
        position: absolute;
        top: calc(50% + 26px);
        right: 16px;
        width: 320px;
        max-width: calc(100vw - 32px);
        max-height: 420px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        z-index: 30;
    }
    #notif-panel.open {
        display: block;
    }
    #notif-panel-header {
        padding: 12px 14px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
        font-weight: 500;
        color: #111827;
    }
    #notif-panel-empty {
        padding: 24px 14px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
    }
    .notif-panel-item {
        position: relative;
        border-bottom: 1px solid #f3f4f6;
    }
    .notif-panel-item:last-child {
        border-bottom: none;
    }
    .notif-panel-item-form {
        margin: 0;
    }
    .notif-panel-item-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 10px 34px 10px 14px;
        background: none;
        border: none;
        cursor: pointer;
        font: inherit;
    }
    .notif-panel-item-btn:hover {
        background: #f9fafb;
    }
    .notif-panel-item.unread .notif-panel-item-btn {
        background: #fffbeb;
    }
    .notif-panel-item-title {
        font-size: 13px;
        font-weight: 500;
        color: #111827;
        margin-bottom: 2px;
    }
    .notif-panel-item-body {
        font-size: 12px;
        color: #6b7280;
    }
    .notif-panel-item-time {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 4px;
    }
    .notif-panel-item-delete-form {
        position: absolute;
        top: 8px;
        right: 8px;
        margin: 0;
    }
    .notif-panel-item-delete {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: #9ca3af;
        cursor: pointer;
        font-size: 15px;
        line-height: 1;
        padding: 0;
    }
    .notif-panel-item-delete:hover {
        background: #fee2e2;
        color: #dc2626;
    }
    #notif-panel-footer {
        padding: 10px 14px;
        border-top: 1px solid #f3f4f6;
    }
    #notif-panel-footer a {
        display: block;
        text-align: center;
        font-size: 13px;
        font-weight: 500;
        color: #b45309;
        text-decoration: none;
    }
    #notif-panel-footer a:hover {
        text-decoration: underline;
    }
</style>
@php
    $unreadNotificationsCount = auth()->user()->unreadNotifications()->count();
    $recentNotifications = auth()->user()->notifications()->latest()->take(5)->get();
@endphp
<a href="{{ route('student.notifications.index') }}" id="student-notification-bell" aria-label="Уведомления" onclick="return window.__toggleNotifPanel(event)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </svg>
    @include('partials.student-notification-badge', ['unreadCount' => $unreadNotificationsCount])
</a>

<div id="notif-panel">
    <div id="notif-panel-header">Уведомления</div>
    <div id="notif-panel-list">
        @forelse ($recentNotifications as $n)
            @php
                $data = $n->data;
                $isUnread = $n->read_at === null;
            @endphp
            <div class="notif-panel-item {{ $isUnread ? 'unread' : '' }}">
                <form method="POST" action="{{ route('student.notifications.markRead', $n->id) }}" class="notif-panel-item-form">
                    @csrf
                    <button type="submit" class="notif-panel-item-btn">
                        <div class="notif-panel-item-title">{{ $data['title'] ?? 'Уведомление' }}</div>
                        @if (!empty($data['body']))
                            <div class="notif-panel-item-body">{{ \Illuminate\Support\Str::limit($data['body'], 80) }}</div>
                        @endif
                        <div class="notif-panel-item-time">{{ $n->created_at->diffForHumans() }}</div>
                    </button>
                </form>
                <form method="POST" action="{{ route('student.notifications.destroy', $n->id) }}"
                      hx-delete="{{ route('student.notifications.destroy', $n->id) }}"
                      hx-target="closest .notif-panel-item"
                      hx-swap="outerHTML"
                      class="notif-panel-item-delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="notif-panel-item-delete" aria-label="Удалить уведомление">&times;</button>
                </form>
            </div>
        @empty
            <div id="notif-panel-empty">Уведомлений пока нет</div>
        @endforelse
    </div>
    <div id="notif-panel-footer">
        <a href="{{ route('student.notifications.index') }}">Посмотреть все</a>
    </div>
</div>

<script>
    window.__toggleNotifPanel = function (evt) {
        evt.preventDefault();
        var panel = document.getElementById('notif-panel');
        if (!panel) return false;
        panel.classList.toggle('open');
        return false;
    };
    document.addEventListener('click', function (evt) {
        var panel = document.getElementById('notif-panel');
        var bell = document.getElementById('student-notification-bell');
        if (!panel || !panel.classList.contains('open')) return;
        if (panel.contains(evt.target) || (bell && bell.contains(evt.target))) return;
        panel.classList.remove('open');
    });
    document.addEventListener('keydown', function (evt) {
        if (evt.key === 'Escape') {
            var panel = document.getElementById('notif-panel');
            if (panel) panel.classList.remove('open');
        }
    });
</script>
