<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Админ-форма создания/редактирования домашки сохраняла тип задания под
 * значениями 'multiple_choice' / 'text_based' / 'image_written', которые
 * нигде в остальном коде (HomeworkTask::isAutoGradable(), вопрос-визард
 * ученика, автопроверка) не распознавались — из-за этого, например, задания
 * типа "Тест с вариантами" ошибочно считались непроверяемыми автоматически
 * и ученику показывался textarea вместо квадратиков ответа. Приводим уже
 * сохранённые записи к тем же строкам, которые теперь пишет форма.
 */
return new class extends Migration
{
    private const MAP = [
        'multiple_choice' => 'test',
        'text_based'       => 'text_with_questions',
        'image_written'    => 'image_manual',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('homework_tasks')->where('type', $old)->update(['type' => $new]);
        }
    }

    public function down(): void
    {
        // Необратимо: 'test'/'text_with_questions'/'image_manual' уже
        // использовались (например, сидером демо-курса) до этой миграции,
        // так что откат по значению задел бы и те записи, которых up() не
        // трогал. Ничего не делаем, чтобы не испортить данные откатом.
    }
};
