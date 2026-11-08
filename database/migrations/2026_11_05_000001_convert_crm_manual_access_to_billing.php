<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Записи, которым админ проставлял дату/оплату через /admin/crm, раньше
 * оставались "ручными" (billing_interval_days пусто, дата в expires_at) — и
 * по окончании периода enrollments:expire переводил их в suspended. Теперь
 * AccessController сразу переводит такие записи на биллинг; здесь — то же
 * для уже существующих: признак — есть платёж с note 'CRM:%' (его пишет
 * только AccessController). Замороженные этим путём возвращаются в active —
 * с прошедшей датой оплаты это "Просрочена оплата" (доступа нет), админ
 * просто переставляет дату.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('course_user')
            ->whereNull('billing_interval_days')
            ->whereNotNull('expires_at')
            ->whereIn('status', ['active', 'suspended'])
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.course_user_id', 'course_user.id')
                    ->where('payments.method', 'manual')
                    ->where('payments.note', 'like', 'CRM:%');
            })
            ->pluck('id');

        foreach ($ids as $id) {
            $row = DB::table('course_user')->where('id', $id)->first();

            DB::table('course_user')->where('id', $id)->update([
                'status' => 'active',
                'billing_interval_days' => 30,
                'next_payment_due_at' => $row->expires_at,
                'expires_at' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Данные не откатываем: прежнее состояние (suspended без следа
        // курса в CRM) и было ошибкой.
    }
};
