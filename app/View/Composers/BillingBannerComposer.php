<?php

namespace App\View\Composers;

use App\Service\BillingService;
use Illuminate\View\View;

class BillingBannerComposer
{
    public function __construct(private BillingService $billing)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();
        if (!$user) {
            $view->with(['billingDueSoon' => collect(), 'billingOverdue' => collect(), 'billingPromised' => collect()]);
            return;
        }

        $courses = $user->courses()->wherePivot('status', 'active')->get();

        // При автоплатеже "скоро оплата" не требует действия от пользователя —
        // баннер про это не показываем, предупреждаем только письмом (см.
        // app/Console/Commands/SendBillingReminders.php). А вот баннер о
        // просрочке/приостановке доступа нужен всегда, автоплатёж это или нет —
        // именно он предлагает оплатить вручную, если списание не прошло.
        $dueSoon = $courses
            ->reject(fn ($course) => $this->billing->isAutopayEnabled($user, $course))
            ->map(fn ($course) => ['course' => $course, 'daysLeft' => $this->billing->dueInDays($user, $course)])
            ->filter(fn ($row) => $row['daysLeft'] !== null && $row['daysLeft'] >= 0 && $row['daysLeft'] <= 2);

        // Пока обещанный платёж в силе, доступ фактически не закрыт — вместо
        // баннера "доступ приостановлен" показываем отдельный баннер с
        // обратным отсчётом до конца отсрочки.
        $promised = $courses
            ->filter(fn ($course) => $this->billing->isPromiseActive($user, $course))
            ->map(fn ($course) => ['course' => $course, 'daysLeft' => $this->billing->promiseDaysLeft($user, $course)]);

        $overdue = $courses->filter(fn ($course) => $this->billing->isPastDue($user, $course)
            && !$this->billing->isPromiseActive($user, $course));

        $view->with(['billingDueSoon' => $dueSoon, 'billingOverdue' => $overdue, 'billingPromised' => $promised]);
    }
}
