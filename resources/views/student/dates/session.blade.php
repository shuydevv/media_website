{{-- resources/views/student/dates/session.blade.php
     Каркас сессии тренажёра дат — тот же, что у тренажёра терминов
     (student/terms/session.blade.php), и тот же общий скрипт.

     Каркас сессии тренажёра. Всё содержимое — в partials/region, тот же
     партиал отдаётся htmx-запросам и подменяет #terms-app целиком.

     Скрипт ниже живёт вне #terms-app (как тосты в layouts/main), поэтому
     переживает подмену шага и может сравнить «было» и «стало»: по номеру
     карточки (data-key) решает, отбрасывать ли старую, по data-flash —
     какую анимацию проиграть. Вся анимация на GSAP; без него (не загрузился
     CDN) тренажёр работает так же, просто без движения. --}}
@extends('layouts.main')

@section('title', $kindLabel.' — Школа Полтавского')

{{-- Выход из сессии — стрелка «назад» в шапке, как на других страницах
     ученика (partials/student-back-button). Прогресс сохраняется по ходу
     сессии, так что уйти можно в любой момент. --}}
@section('back_url', route('student.dates.index'))

@section('content')
{{-- На телефоне и планшете место нижнего меню платформы занимает рыба с
     прогрессом (см. region.blade.php). Обычный <style>, а не классы на самом
     меню: оно общее для всех страниц ученика и рендерится в layouts/main.
     Граница та же, что у xl в Tailwind (1280px), — шире рыба стоит сбоку и
     меню не мешает. На итоге сессии меню тоже скрыто: это та же страница. --}}
<style>
  @media (max-width: 1279px) {
    /* Кнопка «Раскрыть меню» (та же схема, что при решении домашки, см.
       partials/student-bottom-nav) здесь тоже не нужна: на этих ширинах на
       месте меню полоска прогресса. На широком экране меню свёрнуто в эту
       кнопку, как в домашке. */
    #student-bottom-nav, #student-bottom-nav-reveal { display: none !important; }
    /* Вместе с меню убираем и место под него (pb-20 на body в layouts/main):
       иначе под итогом сессии оставалась пустая полоса. Место под рыбу во
       время заданий даёт отдельный отступ в region.blade.php. */
    body.student-portal { padding-bottom: 0 !important; }
  }
</style>
<div id="terms-app">
  @include('student.dates.partials.region')
</div>
@endsection

@push('page-scripts')
  @include('student.terms.partials.session-script')
@endpush
