<?php

namespace App\Providers;
use App\Models\Submission;
use App\Policies\SubmissionPolicy;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Course::class   => \App\Policies\CoursePolicy::class,
        \App\Models\Lesson::class   => \App\Policies\LessonPolicy::class,
        \App\Models\Homework::class => \App\Policies\HomeworkPolicy::class,
        Submission::class => SubmissionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
