<?php

namespace App\Providers;
use App\Models\Course;
use App\Policies\CoursePolicy;

use App\Service\Sms\SmsSender;
use App\Service\Sms\FakeSmsSender;

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
        //
    }
}
