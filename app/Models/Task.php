<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Банк заданий — единая сущность содержания задания. Используется тремя
 * способами: (1) переиспользуется в домашках через HomeworkTask.task_id,
 * (2) самостоятельное прорешивание в личном кабинете (task_attempts), (3)
 * публичная SEO-страница, если is_public — см. student/tasks/* и exercise/*.
 * Критерии проверки и баллы за задание НЕ хранятся здесь: и то, и другое —
 * свойство НОМЕРА в экзамене (category_id, number), общее для всех
 * заданий с этим номером — см. TaskCriteria. criteria_override — редкое
 * исключение, когда у конкретного задания критерии отличаются от общих
 * для номера; для баллов такого исключения нет (см. getMaxScoreAttribute()).
 */
class Task extends Model
{
    protected $fillable = [
        'category_id',
        'number',
        'criteria_override',
        'type',
        'question_text',
        'passage_text',
        'options',
        'matches',
        'table_content',
        'image_path',
        'image_auto_options',
        'order_matters',
        'left_title',
        'right_title',
        'answer',
        'hint',
        'is_public',
    ];

    protected $casts = [
        'options'             => 'array',
        'matches'              => 'array',
        'table_content'        => 'array',
        'image_auto_options'   => 'array',
        'order_matters'        => 'boolean',
        'is_public'            => 'boolean',
    ];

    // Тот же список ролей, что и у HomeworkTask — единственный источник
    // истины не дублируем, ссылаемся на него напрямую.
    public function isAutoGradable(): bool
    {
        return !in_array($this->type, HomeworkTask::MANUAL_TYPES, true);
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    public function homeworkTasks()
    {
        return $this->hasMany(\App\Models\HomeworkTask::class);
    }

    /** Уже использованные номера в банке — подсказки в datalist поля "№ в ЕГЭ",
     *  общие для формы банка и конструктора домашки. */
    public static function distinctNumbers(): array
    {
        return self::query()
            ->whereNotNull('number')
            ->distinct()
            ->orderByRaw('CAST(number AS UNSIGNED), number')
            ->pluck('number')
            ->all();
    }

    // Общий для всех заданий с этим (category_id, number) набор критериев.
    public function resolvedCriteriaRecord(): ?TaskCriteria
    {
        return TaskCriteria::where('category_id', $this->category_id)
            ->where('number', $this->number)
            ->first();
    }

    // Текст критериев с учётом редкого точечного переопределения на самом задании.
    public function getResolvedCriteriaAttribute(): ?string
    {
        return $this->criteria_override ?: $this->resolvedCriteriaRecord()?->criteria;
    }

    // Баллы — не своя колонка, а значение из общих критериев номера
    // (редактируется на странице критериев, не в форме содержания).
    // HomeworkTask::getAttribute() читает max_score именно через это —
    // аксессор перехватывает магический доступ раньше, чем сработал бы
    // (несуществующий) сырой атрибут, так что прокси в HomeworkTask ничего
    // не заметил.
    public function getMaxScoreAttribute(): int
    {
        return $this->resolvedCriteriaRecord()?->max_score ?? 1;
    }
}
