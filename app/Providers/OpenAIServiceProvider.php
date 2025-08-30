<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Service\OpenAIService;

class OpenAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIService::class, fn() => new OpenAIService());
    }
}
