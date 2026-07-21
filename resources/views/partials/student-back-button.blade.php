{{-- Стрелка "назад" в шапке — рендерится только если конкретная страница
     задала @section('back_url', ...) (см. layouts/main.blade.php). Каждая
     страница сама решает, куда вести (дашборд, страница курса и т.п.) — не
     history.back(), поэтому это обычная ссылка: работает без JS, открывается
     в новой вкладке, доступна с клавиатуры.

     Тот же приём с обычным <style> вместо Tailwind-классов и position:
     absolute + top: 50% / translateY, что и у колокольчика
     (partials/student-notification-bell.blade.php) — тот же браузер, та же
     ненадёжность части Tailwind-классов в этом хедере. Левая сторона
     #site-header-row, колокольчик — правая, друг другу не мешают. --}}
<style>
    #student-back-button {
        position: absolute;
        left: 16px;
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
        color: #3f3f46;
        text-decoration: none;
        transition: color .15s ease, background-color .15s ease;
        z-index: 20;
    }
    #student-back-button:hover {
        color: #18181b;
        background: #f9fafb;
    }
</style>
<a id="student-back-button" href="@yield('back_url')" aria-label="Назад">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
        <path d="M15 18l-6-6 6-6"></path>
    </svg>
</a>
