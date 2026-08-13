<?php

namespace App\Providers;

use App\Models\Course;
use App\Notifications\VerifyEmailWithCode;
use App\Policies\CoursePolicy;
use App\Service\EmailOtpService;
use App\Service\Sms\FakeSmsSender;
use App\Service\Sms\SmsSender;
use App\View\Composers\BillingBannerComposer;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function () {
            return new FakeSmsSender();
        });
    }

    protected $policies = [
        Course::class => CoursePolicy::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        View::composer('layouts.main', BillingBannerComposer::class);

        // Статус доставки кода на email для /auth/email/status (JS-поллинг на странице
        // ввода кода) — противоположный случай (окончательный провал джобы) ловит
        // VerifyEmailWithCode::failed(), тут только успешная отправка.
        Event::listen(function (NotificationSent $event) {
            if ($event->notification instanceof VerifyEmailWithCode && $event->notification->vid) {
                Cache::put(
                    app(EmailOtpService::class)->statusKey($event->notification->vid),
                    'sent',
                    now()->addMinutes(15)
                );
            }
        });
    }
}
