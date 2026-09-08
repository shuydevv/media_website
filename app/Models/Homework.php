<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homeworks';
    protected $fillable = ['title', 'description', 'type', 'course_id', 'lesson_id', 'attempts_allowed', 'due_at', 'mock_number',];

    /**
     * Длительность пробника всегда фиксированная (3ч30м) — не поле в БД и не
     * настройка в админке, единственный источник истины для таймера.
     */
    public const MOCK_TIME_LIMIT_MINUTES = 210;

    protected $casts = [
        'due_at' => 'datetime', // удобно форматировать
    ];

    public function tasks()
    {
        return $this->hasMany(HomeworkTask::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class); // Связь с курсом
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions()
    {
        return $this->hasMany(\App\Models\Submission::class);
    }

    public function unlocks()
    {
        return $this->hasMany(HomeworkUnlock::class);
    }

    /**
     * Админ точечно открыл доступ этому ученику в обход
     * isLessonBeforeEnrollment() — см. Admin\Homework\Unlock\StoreController/
     * DestroyController и миграцию create_homework_unlocks_table.
     */
    public function isUnlockedFor(User $user): bool
    {
        return $this->unlocks()->where('user_id', $user->id)->exists();
    }

    /**
     * У ученика уже есть попытка сдачи этой домашки — второе (после
     * isUnlockedFor()) исключение из isLessonBeforeEnrollment(). Дата урока
     * и дата зачисления — эвристика "мог ли ученик вообще узнать об этой
     * домашке", а не запрет постфактум: если submission уже существует,
     * значит в момент сдачи доступ был и работа реальна (проверена/оценена)
     * — прятать её из-за более поздней правки enrolled_at или из-за самого
     * факта, что isLessonBeforeEnrollment() появился в проекте позже, чем
     * ученик сдал домашку, нельзя.
     */
    public function hasSubmissionFrom(User $user): bool
    {
        return $this->submissions()->where('user_id', $user->id)->exists();
    }

    /**
     * Урок, к которому привязана домашка, ещё не наступил — до этого момента
     * ученик вообще не должен знать о существовании домашки (не в расписании,
     * не в списке домашек, и напрямую по ссылке зайти тоже нельзя). Если
     * домашка ни к какому уроку не привязана, или у урока не определена
     * дата/время сессии — ничего не можем утверждать, поэтому не прячем.
     */
    public function isLessonUpcoming(): bool
    {
        $session = $this->lesson?->courseSession;

        return $session !== null
            && $session->start_date_time !== null
            && now()->lt($session->start_date_time);
    }

    /**
     * Урок, к которому привязана домашка, прошёл ДО того, как ученик был
     * зачислён на курс (домашка осталась от прошлого потока/до его
     * регистрации) — как и с ещё не наступившим уроком (isLessonUpcoming()),
     * ученик о такой домашке вообще не должен знать. Сравниваем с
     * courseEnrolledAt(), а не users.created_at напрямую — тот же принцип,
     * что и в isOverdueFor(), чтобы ориентироваться на дату подключения
     * именно к ЭТОМУ курсу, а не на дату регистрации в системе вообще.
     *
     * Админ может точечно снять этот запрет для конкретного ученика —
     * например, попросили досдать домашку за прошлый поток — см.
     * isUnlockedFor()/HomeworkUnlock. Второе исключение — уже существующий
     * submission (см. hasSubmissionFrom()): само это правило появилось в
     * проекте позже, чем часть учеников успела сдать домашки, которые под
     * него подпадают (урок за пару дней до формальной даты зачисления —
     * например, из-за задержки между тем, когда ученик реально получил
     * доступ, и моментом, когда это записалось в courseEnrolledAt()).
     * Ретроактивно прятать уже оценённую работу из-за правила, которого не
     * было в момент её сдачи, нельзя. Оба исключения проверяем последними —
     * лишние запросы (unlocks/submissions) нужны только когда без них было
     * бы 404.
     */
    public function isLessonBeforeEnrollment(User $user): bool
    {
        $session = $this->lesson?->courseSession;
        if ($session === null || $session->start_date_time === null) {
            return false;
        }

        $enrolledAt = $user->courseEnrolledAt($this->course_id);
        $isBeforeEnrollment = $enrolledAt !== null && $session->start_date_time->lt($enrolledAt);

        return $isBeforeEnrollment && !$this->isUnlockedFor($user) && !$this->hasSubmissionFrom($user);
    }

    /**
     * Число разрешённых попыток сдачи — 2 по умолчанию (столбец
     * attempts_allowed и заведён с default(2), см. миграцию add_score_and_
     * attempts), 0/null трактуем так же, а не как "безлимит": такого режима
     * в продукте нет. Единственный источник истины — раньше
     * SubmissionController::create() и submissions/show.blade.php считали
     * это по-разному (один разрешал безлимит, другой давал ровно 2), из-за
     * чего "Перерешать работу" могла быть недоступна, хотя бэкенд ещё
     * разрешил бы попытку.
     */
    public static function normalizeAttemptsAllowed($raw): int
    {
        $value = (int) ($raw ?? 0);

        return $value > 0 ? $value : 2;
    }

    public function attemptsAllowed(): int
    {
        return self::normalizeAttemptsAllowed($this->attempts_allowed);
    }

    /**
     * Просрочена ли домашка ДЛЯ ЭТОГО ученика. Дедлайн сам по себе ничего не
     * решает: если он наступил ДО того, как ученик был зачислён на курс
     * (например, домашка осталась от прошлого потока), это не его вина и не
     * "провал" — такую домашку не помечаем просроченной, иначе она мгновенно
     * выглядит проваленной сразу после зачисления. Единственный источник
     * истины для всех мест, где раньше независимо дублировалось сравнение
     * due_at с now() (список домашек, дашборд, значок на уроке, финализация
     * попытки).
     */
    public function isOverdueFor(User $user): bool
    {
        if ($this->due_at === null || now()->isBefore($this->due_at)) {
            return false;
        }

        $enrolledAt = $user->courseEnrolledAt($this->course_id);

        return $enrolledAt === null || $enrolledAt->lte($this->due_at);
    }
}
