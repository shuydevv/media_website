<?php

namespace App\Notifications;

/**
 * Единый список переключаемых типов уведомлений — источник правды для
 * страницы профиля (резюме слаг + подпись + группа). Сами SLUG-константы
 * живут на соответствующих классах уведомлений и используются в их via()
 * (см. User::wantsNotification()) — здесь только для отображения.
 *
 * Транзакционные/security-уведомления (сброс пароля, подтверждение email)
 * сюда намеренно не входят — их нельзя отключить.
 */
class NotificationPreferenceRegistry
{
    public static function all(): array
    {
        return [
            ['slug' => HomeworkGradedNotification::SLUG, 'group' => 'Домашки', 'label' => 'Домашка проверена'],
            ['slug' => HomeworkDueSoonNotification::SLUG, 'group' => 'Домашки', 'label' => 'Скоро дедлайн домашки'],
            ['slug' => PaymentOverdueNotification::SLUG, 'group' => 'Оплата', 'label' => 'Доступ приостановлен из-за просрочки'],
            ['slug' => PaymentDueSoonNotification::SLUG, 'group' => 'Оплата', 'label' => 'Скоро оплата'],
            ['slug' => PromisedPaymentExpiringNotification::SLUG, 'group' => 'Оплата', 'label' => 'Обещанный платёж скоро истекает'],
            ['slug' => PaymentConfirmedNotification::SLUG, 'group' => 'Оплата', 'label' => 'Платёж зафиксирован'],
            ['slug' => LessonStartingSoonNotification::SLUG, 'group' => 'Уроки', 'label' => 'Урок скоро начнётся'],
            ['slug' => LessonRecordingAvailableNotification::SLUG, 'group' => 'Уроки', 'label' => 'Появилась запись урока'],
            // Скрыт из формы настроек в профиле — не показываем чекбокс,
            // но само уведомление продолжает работать по дефолту (включено),
            // см. ProfileController::updateNotifications() — скрытые слаги
            // не трогаются при сохранении остальных чекбоксов.
            ['slug' => EnrolledInCourseNotification::SLUG, 'group' => 'Курс', 'label' => 'Зачисление на курс', 'hidden' => true],
        ];
    }

    /** Только то, что реально показываем чекбоксами на странице профиля. */
    public static function visible(): array
    {
        return array_values(array_filter(self::all(), fn (array $type) => empty($type['hidden'])));
    }

    /** Все известные слаги (в т.ч. скрытые) — для тестов/подсчёта. */
    public static function slugs(): array
    {
        return array_column(self::all(), 'slug');
    }

    /** Слаги, которые реально редактируются формой — для фильтрации ввода перед сохранением. */
    public static function visibleSlugs(): array
    {
        return array_column(self::visible(), 'slug');
    }
}
