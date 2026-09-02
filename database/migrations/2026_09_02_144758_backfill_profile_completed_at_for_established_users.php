<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * profile_completed_at нигде не проставлялся до сих пор — у всех
     * существующих пользователей он NULL, включая давно активных
     * платящих учеников. EnsureProfileCompleted (новый глобальный гейт)
     * без этого бэкфилла заблокировал бы их всех на онбординге при
     * следующем визите. Грандфазерим (проставляем "уже завершил") любого,
     * у кого есть надёжный признак: подтверждённый email/телефон (значит
     * реально проходил какой-то вход), зачисление на курс, оплата, сданная
     * домашка, попытка задания, или это не студент (админ/куратор).
     *
     * НЕ используем "first_name заполнен" как признак — раньше это было
     * надёжно (имя мог задать только сам ученик на онбординге), но с тех
     * пор как админ стал заполнять first_name прямо при создании аккаунта
     * (Admin\User\StoreController), это перестало значить "прошёл
     * онбординг". С этим признаком бэкфилл грандфазерил свежесозданных,
     * ещё не приглашённых учеников — их ссылка-приглашение затем считалась
     * "уже использованной" (EmailAuthController::inviteLogin() проверяет
     * profile_completed_at) при первом же клике.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('profile_completed_at')
            ->where(function ($query) {
                $query->whereNotNull('email_verified_at')
                    ->orWhereNotNull('phone_verified_at')
                    ->orWhere('role', '!=', 2) // не студент (админ/куратор) — гейт их и так не трогает
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')->from('course_user')
                            ->whereColumn('course_user.user_id', 'users.id');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')->from('payments')
                            ->whereColumn('payments.user_id', 'users.id');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')->from('submissions')
                            ->whereColumn('submissions.user_id', 'users.id');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')->from('task_attempts')
                            ->whereColumn('task_attempts.user_id', 'users.id');
                    });
            })
            ->update(['profile_completed_at' => now()]);
    }

    /**
     * Данные, не схема — какие строки бэкфилл затронул, а какие уже были
     * заполнены, после выполнения неразличимо. Откат не имеет смысла.
     */
    public function down(): void
    {
    }
};
