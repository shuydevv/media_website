<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

/**
 * Постоянная ссылка для входа без пароля: выпуск/перевыпуск/отключение в
 * Профиль → Аккаунт и сам вход по /enter/{token}. База — SQLite в памяти.
 */
class LoginLinkTest extends TestCase
{
    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';

    private function student(): User
    {
        return User::factory()->create(['role' => User::ROLE_READER]);
    }

    public function test_student_issues_link_and_profile_shows_it(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('student.profile.login-link.issue'))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->login_link_url);
        // В базе токен зашифрован, а хэш — не сам токен.
        $this->assertNotSame($user->login_token, $user->getRawOriginal('login_token'));
        $this->assertSame(hash('sha256', $user->login_token), $user->login_token_hash);

        $this->actingAs($user)->get(route('student.profile.show'))
            ->assertOk()
            ->assertSee($user->login_link_url, false);
    }

    public function test_link_logs_in_and_can_be_reused(): void
    {
        $user = $this->student();
        $token = $user->issueLoginToken();

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('auth.login-link', $token))
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->login_token_used_at);
        $this->assertSame('iPhone, Safari', $user->fresh()->login_token_used_device);

        auth()->logout();

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('auth.login-link', $token))
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_reissue_invalidates_old_link(): void
    {
        $user = $this->student();
        $old = $user->issueLoginToken();
        $user->issueLoginToken();

        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('auth.login-link', $old))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoked_link_does_not_work(): void
    {
        $user = $this->student();
        $token = $user->issueLoginToken();

        $this->actingAs($user)->delete(route('student.profile.login-link.revoke'));
        auth()->logout();

        $this->assertNull($user->fresh()->login_token_hash);
        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('auth.login-link', $token))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_preview_bot_is_not_logged_in(): void
    {
        $user = $this->student();
        $token = $user->issueLoginToken();

        $this->withHeader('User-Agent', 'TelegramBot (like TwitterBot)')
            ->get(route('auth.login-link', $token))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->login_token_used_at);
    }

    public function test_link_switches_from_another_open_account(): void
    {
        $owner = $this->student();
        $token = $owner->issueLoginToken();

        $this->actingAs($this->student())
            ->withHeader('User-Agent', self::BROWSER)
            ->get(route('auth.login-link', $token))
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($owner);
    }

    public function test_staff_cannot_issue_or_use_link(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('student.profile.login-link.issue'))->assertForbidden();

        // Даже если токен как-то оказался у админа (был учеником) — не пускаем.
        $token = $admin->issueLoginToken();
        auth()->logout();
        $this->withHeader('User-Agent', self::BROWSER)
            ->get(route('auth.login-link', $token))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
