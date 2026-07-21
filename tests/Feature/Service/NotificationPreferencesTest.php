<?php

namespace Tests\Feature\Service;

use App\Models\Course;
use App\Models\Homework;
use App\Models\User;
use App\Notifications\EnrolledInCourseNotification;
use App\Notifications\HomeworkDueSoonNotification;
use App\Notifications\NotificationPreferenceRegistry;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Нет отдельной тестовой БД, задействованные таблицы MyISAM — ручная очистка, см. BillingServiceTest. */
class NotificationPreferencesTest extends TestCase
{
    /** @var int[] */
    private array $createdUserIds = [];

    /** @var int[] */
    private array $createdCourseIds = [];

    /** @var int[] */
    private array $createdHomeworkIds = [];

    protected function tearDown(): void
    {
        if ($this->createdHomeworkIds !== []) {
            Homework::whereIn('id', $this->createdHomeworkIds)->delete();
        }
        if ($this->createdCourseIds !== []) {
            Course::whereIn('id', $this->createdCourseIds)->forceDelete();
        }
        if ($this->createdUserIds !== []) {
            User::whereIn('id', $this->createdUserIds)->delete();
        }
        DB::table('jobs')->truncate();
        parent::tearDown();
    }

    private function makeUser(?array $preferences = null): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_READER,
            'notification_preferences' => $preferences,
        ]);
        $this->createdUserIds[] = $user->id;
        return $user;
    }

    /** @test */
    public function a_notification_type_is_enabled_by_default_when_no_preference_is_stored()
    {
        $user = $this->makeUser(null);
        $this->assertTrue($user->wantsNotification(EnrolledInCourseNotification::SLUG));
    }

    /** @test */
    public function a_disabled_slug_is_respected()
    {
        $user = $this->makeUser([EnrolledInCourseNotification::SLUG => false]);
        $this->assertFalse($user->wantsNotification(EnrolledInCourseNotification::SLUG));
        // Остальные типы не затронуты выключением одного — дефолт остаётся true.
        $this->assertTrue($user->wantsNotification(HomeworkDueSoonNotification::SLUG));
    }

    /** @test */
    public function via_returns_no_channels_when_the_type_is_disabled()
    {
        $user = $this->makeUser([HomeworkDueSoonNotification::SLUG => false]);
        $course = Course::create(['title' => 'ТЕСТ (NotificationPreferencesTest)', 'description' => 'т', 'price_cents' => 0]);
        $this->createdCourseIds[] = $course->id;
        $homework = Homework::create(['title' => 'т', 'type' => 'homework', 'course_id' => $course->id, 'due_at' => now()->addHours(5)]);
        $this->createdHomeworkIds[] = $homework->id;

        $notification = new HomeworkDueSoonNotification($homework);

        $this->assertSame([], $notification->via($user));
    }

    /** @test */
    public function notify_dispatches_no_jobs_at_all_when_the_type_is_disabled()
    {
        $user = $this->makeUser([HomeworkDueSoonNotification::SLUG => false]);
        $course = Course::create(['title' => 'ТЕСТ (NotificationPreferencesTest)', 'description' => 'т', 'price_cents' => 0]);
        $this->createdCourseIds[] = $course->id;
        $homework = Homework::create(['title' => 'т', 'type' => 'homework', 'course_id' => $course->id, 'due_at' => now()->addHours(5)]);
        $this->createdHomeworkIds[] = $homework->id;

        DB::table('jobs')->truncate();
        $user->notify(new HomeworkDueSoonNotification($homework));

        $this->assertSame(0, DB::table('jobs')->count());
    }

    /** @test */
    public function registry_slugs_cover_exactly_the_nine_togglable_types()
    {
        $this->assertCount(9, NotificationPreferenceRegistry::all());
        $this->assertCount(9, array_unique(NotificationPreferenceRegistry::slugs()));
    }
}
