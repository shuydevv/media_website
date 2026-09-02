<?php

namespace App\Models;

use App\Notifications\SendVerifyWithQueueNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    // SoftDeletes;

    const ROLE_ADMIN  = 1;
    const ROLE_READER = 2;
    const ROLE_MENTOR = 3;

    public static function getRoles()
    {
        return [
            self::ROLE_ADMIN  => 'Админ',
            self::ROLE_READER => 'Пользователь',
            self::ROLE_MENTOR => 'Куратор',
        ];
    }

    public function courses()
    {
        return $this->belongsToMany(\App\Models\Course::class, 'course_user')
            ->withPivot([
                'status', 'enrolled_at', 'expires_at', 'source', 'payment_id', 'promo_code',
                'billing_interval_days', 'next_payment_due_at', 'autopay_enabled',
                'promised_payment_expires_at', 'promised_payment_used_at', 'reminder_sent_at',
            ])
            ->withTimestamps();
    }

    public function hasActiveEnrollment($course): bool
    {
        $courseId = is_object($course) ? $course->id : (int) $course;

        return $this->courses()
            ->wherePivot('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where('courses.id', $courseId)
            ->exists();
    }

    public function submissions()
    {
        return $this->hasMany(\App\Models\Submission::class);
    }

    /**
     * Статус для /admin/crm — 'key' машинный (для фильтров/JS), 'label'/'color'
     * для отображения. Намеренно НЕ отдельное хранимое поле — "активный"/
     * "просрочен"/"заморожен"/"завершил" считаются из уже загруженной
     * pivot-коллекции courses() (без доп. запросов, в отличие от
     * BillingService::hasAccess(), который бьёт в БД по одному курсу за раз —
     * на списке из многих учеников это была бы N+1), остальное — из ручного
     * crm_stage. Приоритет (сверху вниз, первое совпадение побеждает):
     * Отказался → Активный → Просрочена оплата → Заморожен → Завершил курс →
     * Пробный/Связались (crm_stage) → Новый. Активный стоит выше просрочки/
     * заморозки специально: если у ученика два курса и один из них исправно
     * оплачивается, это не тот случай, который надо показывать как проблемный.
     */
    public function crmStatus(): array
    {
        if ($this->crm_stage === 'lost') {
            return ['key' => 'lost', 'label' => 'Отказался', 'color' => 'rose'];
        }

        $hasActive = false;
        $hasPastDue = false;
        $hasFrozen = false;
        $hasCompleted = false;

        foreach ($this->courses as $course) {
            $pivot = $course->pivot;

            if ($pivot->status === 'completed') {
                $hasCompleted = true;
                continue;
            }

            if ($pivot->status === 'suspended') {
                $hasFrozen = true;
                continue;
            }

            if ($pivot->status !== 'active') {
                continue;
            }

            // courses() не объявляет ->using(CourseUser::class), поэтому $pivot —
            // обычный Illuminate\...\Pivot без кастов CourseUser::$casts: даты
            // приходят сырыми строками из БД, парсим вручную вместо ->isPast().
            if ($pivot->billing_interval_days === null) {
                // Ручное/промо: между истечением expires_at и ночным прогоном
                // enrollments:expire (переведёт в suspended) есть короткое
                // окно, когда status ещё 'active', но доступа уже нет — это не
                // "просрочка" в смысле биллинга, отдельного флага не заводим,
                // просто не считаем активным.
                if ($pivot->expires_at === null || !\Carbon\Carbon::parse($pivot->expires_at)->isPast()) {
                    $hasActive = true;
                }
                continue;
            }

            $dueOk = $pivot->next_payment_due_at && !\Carbon\Carbon::parse($pivot->next_payment_due_at)->isPast();
            $promiseOk = $pivot->promised_payment_expires_at && !\Carbon\Carbon::parse($pivot->promised_payment_expires_at)->isPast();

            if ($dueOk || $promiseOk) {
                $hasActive = true;
            } else {
                $hasPastDue = true;
            }
        }

        if ($hasActive) {
            return ['key' => 'active', 'label' => 'Активный ученик', 'color' => 'emerald'];
        }

        if ($hasPastDue) {
            return ['key' => 'past_due', 'label' => 'Просрочена оплата', 'color' => 'rose'];
        }

        if ($hasFrozen) {
            return ['key' => 'frozen', 'label' => 'Заморожен', 'color' => 'amber'];
        }

        if ($hasCompleted) {
            return ['key' => 'completed', 'label' => 'Завершил курс', 'color' => 'blue'];
        }

        if ($this->crm_stage === 'trial_done') {
            return ['key' => 'trial_done', 'label' => 'Пробный урок пройден', 'color' => 'gray'];
        }

        if ($this->crm_stage === 'contacted') {
            return ['key' => 'contacted', 'label' => 'Связались', 'color' => 'gray'];
        }

        return ['key' => 'new', 'label' => 'Новый', 'color' => 'gray'];
    }

    /**
     * Ушёл из основного списка /admin/crm на отдельную "архивную" страницу
     * (Admin\Crm\ArchiveController) — цикл с этим учеником для менеджера
     * закрыт, держать его среди тех, с кем ещё работают, только мешает.
     */
    public function isClosedInCrm(): bool
    {
        return in_array($this->crmStatus()['key'], ['completed', 'lost'], true);
    }

    /**
     * Порядок сортировки /admin/crm — не по алфавиту/дате, а по срочности:
     * кому просрочили оплату или кто заморожен, нужно увидеть раньше, чем
     * тех, у кого и так всё в порядке (активные). Меньше число — выше в
     * списке. new/contacted/trial_done в одной группе — все ждут действия
     * менеджера примерно одинаково, порядок между ними отдельно не важен.
     */
    public function crmSortPriority(): int
    {
        return match ($this->crmStatus()['key']) {
            'past_due' => 0,
            'frozen' => 1,
            'new', 'contacted', 'trial_done' => 2,
            'active' => 3,
            default => 4,
        };
    }

    /**
     * Полный словарь статусов для селекта в /admin/crm (порядок = порядок
     * в выпадающем списке). 'selectable' => false — вычисляется системой,
     * руками через селект не выставляется (можно увидеть текущим значением,
     * но не выбрать) — иначе можно было бы "назначить" ученику активный
     * доступ, которого у него на самом деле нет.
     */
    public static function crmStatusOptions(): array
    {
        return [
            'new'        => ['label' => 'Новый', 'color' => 'gray', 'selectable' => true],
            'contacted'  => ['label' => 'Связались', 'color' => 'gray', 'selectable' => true],
            'trial_done' => ['label' => 'Пробный урок пройден', 'color' => 'gray', 'selectable' => true],
            'active'     => ['label' => 'Активный ученик', 'color' => 'emerald', 'selectable' => false],
            'past_due'   => ['label' => 'Просрочена оплата', 'color' => 'rose', 'selectable' => false],
            'frozen'     => ['label' => 'Заморожен', 'color' => 'amber', 'selectable' => false],
            'completed'  => ['label' => 'Завершил курс', 'color' => 'blue', 'selectable' => false],
            'lost'       => ['label' => 'Отказался', 'color' => 'rose', 'selectable' => true],
        ];
    }

    /**
     * Какие опции реально показать в селекте — зависит от текущего статуса,
     * не всегда все восемь сразу. Пока ученик в ручной "домашней" воронке
     * (new/contacted/trial_done/lost — ни одна не завязана на реальные
     * курс/оплату) — видны все четыре, можно свободно переключаться между
     * ними. Как только статус определяется реальными данными (active/
     * past_due/frozen/completed) — единственный осмысленный ручной вариант
     * это "отказался"; показывать остальные три "домашних" не нужно, они
     * всё равно ничего не изменят (crmStatus() пересчитает по реальным
     * данным заново, см. её приоритет).
     */
    public static function crmStatusOptionsFor(string $currentKey): array
    {
        $all = self::crmStatusOptions();
        $manualPipeline = ['new', 'contacted', 'trial_done', 'lost'];

        $keys = in_array($currentKey, $manualPipeline, true)
            ? $manualPipeline
            : [$currentKey, 'lost'];

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $all[$key];
        }

        return $result;
    }

    /**
     * crmStatus() + готовый набор опций селекта для текущего статуса — для
     * JSON-ответов ChecklistController/AccessController. Опции нужны в
     * ответе, а не только цвет/лейбл: набор пунктов в селекте зависит от
     * статуса (crmStatusOptionsFor()), и после сохранения фронт должен
     * перестроить сам список <option>, а не только перекрасить — иначе,
     * например, после перехода в "Активный ученик" в селекте так и
     * останутся "Новый"/"Связались"/"Пробный урок пройден", которых там
     * уже не должно быть.
     */
    public function crmStatusPayload(): array
    {
        $status = $this->crmStatus();

        $options = [];
        foreach (self::crmStatusOptionsFor($status['key']) as $key => $opt) {
            $options[] = [
                'value' => $key === 'new' ? '' : $key,
                'label' => $opt['label'],
                'disabled' => !$opt['selectable'],
                'selected' => $key === $status['key'],
            ];
        }
        $status['options'] = $options;

        return $status;
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    public function taskAttempts()
    {
        return $this->hasMany(\App\Models\TaskAttempt::class);
    }

    /**
     * Кандидаты на чистку от ботов: ни email, ни телефон не подтверждены и
     * нигде не оставили реального следа (курс, оплата, домашка, попытка
     * задания) — см. Admin/User/BotsPreviewController. Намеренно не опираемся
     * на "последний вход", такого поля в схеме нет, а сессии в БД сами
     * протухают по session.lifetime, так что это не надёжная история входов.
     */
    public function scopeBotCandidates($query)
    {
        return $query->where('role', self::ROLE_READER)
            ->whereNull('email_verified_at')
            ->whereNull('phone_verified_at')
            ->doesntHave('courses')
            ->doesntHave('submissions')
            ->doesntHave('payments')
            ->doesntHave('taskAttempts');
    }

    /**
     * Скрывает из /admin/crm самозарегистрировавшихся, но так и не
     * подтвердивших почту/телефон — это боты или бросившие регистрацию на
     * середине, а не реальные лиды (created_by_admin_id проставляется
     * только в Admin\User\StoreController — см. её комментарий). Ученика,
     * приглашённого админом, видно всегда, даже пока он не перешёл по
     * ссылке — это реальный лид, а не мусор, скрывать его нельзя.
     */
    public function scopeVisibleInCrm($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('created_by_admin_id')
                ->orWhereNotNull('email_verified_at')
                ->orWhereNotNull('phone_verified_at');
        });
    }

    /**
     * Момент зачисления на конкретный курс (для "домашка выложена раньше,
     * чем ученик подключился" — см. Homework::isOverdueFor()). Приоритет:
     * course_user.enrolled_at → course_user.created_at (может быть пусто у
     * старых/тестовых записей) → users.created_at как крайний фоллбек.
     * Мемоизируем по course_id, чтобы не дёргать запрос на каждую домашку
     * курса при переборе списка.
     */
    private array $courseEnrolledAtCache = [];

    public function courseEnrolledAt(int $courseId): ?\Illuminate\Support\Carbon
    {
        if (array_key_exists($courseId, $this->courseEnrolledAtCache)) {
            return $this->courseEnrolledAtCache[$courseId];
        }

        $pivot = $this->courses()->where('courses.id', $courseId)->first()?->pivot;
        $value = $pivot?->enrolled_at ?? $pivot?->created_at;

        return $this->courseEnrolledAtCache[$courseId]
            = ($value ? \Illuminate\Support\Carbon::parse($value) : $this->created_at);
    }

    /**
     * Проверка: админ.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Проверка: куратор.
     */
    public function isMentor(): bool
    {
        return $this->role === self::ROLE_MENTOR;
    }

    /**
     * Проверка: студент/обычный пользователь.
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_READER;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'first_name', 'last_name',
        'password',
        'role',
        'phone','phone_verified_at','timezone','locale','created_by_admin_id',
        'crm_stage', 'crm_note',
        'fish_corm_balance', 'fish_total_fed', 'fish_last_active_date', 'fish_milestones',
        'fish_name', 'fish_background', 'fish_unlocked_backgrounds',
        'fish_accessory', 'fish_unlocked_accessories',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'profile_completed_at' => 'datetime',
        'notification_preferences' => 'array',
        'fish_last_active_date' => 'date',
        'fish_milestones' => 'array',
        'fish_unlocked_backgrounds' => 'array',
        'fish_unlocked_accessories' => 'array',
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new SendVerifyWithQueueNotification());
    }

        public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Хочет ли пользователь получать уведомление данного типа (слаг —
     * см. App\Notifications\NotificationPreferenceRegistry). Отсутствие
     * ключа = включено по умолчанию (opt-out, а не opt-in) — так
     * существующим пользователям ничего не нужно мигрировать.
     */
    public function wantsNotification(string $slug): bool
    {
        return (bool) ($this->notification_preferences[$slug] ?? true);
    }

    /**
     * Тот же паттерн, что и Lesson::getImageUrlAttribute() — 'avatar'
     * хранит путь на диске public, отдаём готовый URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (Str::startsWith($this->avatar, ['http://', 'https://', '/storage/', 'data:'])) {
            return $this->avatar;
        }

        return Storage::url($this->avatar);
    }
}
