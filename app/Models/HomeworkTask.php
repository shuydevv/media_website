<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HomeworkTask extends Model
{
    use HasFactory;
    protected $table = 'homework_tasks';

    protected $fillable = [
    'homework_id',
    'type',
    'question_text',
    'passage_text',
    'answer',
    'options',
    'matches',
    'table_content',
    'image_path',
    'task_number',
    'task_id',
    'order',
    'image_auto_options',
    'image_auto_strict',
    'order_matters',
    'left_title',
    'right_title',
    'max_score',
    'hint',
    'explanation',
    ];

    protected $casts = [
        'options'        => 'array',
        'table_content'  => 'array',
        'order_matters'  => 'boolean',
        'matches' => 'array',
        'table' => 'array',
        'image_auto_options' => 'array',
    ];

    /**
     * Единственный источник правды для "какие типы заданий проверяются
     * вручную куратором" — раньше этот список был продублирован в 6+ местах
     * (AutoGrader, SubmissionController, несколько Blade-view), а
     * isAutoGradable() ниже вместо этого сверялся с отдельным, независимым
     * набором TYPE_*-констант. Админ-форма сохраняла тип задания под
     * другими строками (например, 'multiple_choice' вместо 'test'), которые
     * не входили ни в один из списков TYPE_* — из-за этого isAutoGradable()
     * считал такие задания НЕ авто-проверяемыми и ученику показывался
     * textarea вместо квадратиков ответа, хотя тип был "Тест с вариантами".
     * Теперь и админ-форма, и эта проверка используют один и тот же список.
     */
    public const MANUAL_TYPES = ['written', 'image_written', 'image_manual'];

    /**
     * Поля содержания, которые при заполненном task_id читаются из связанного
     * банковского Task, а не из собственных колонок этой строки. Задание в
     * домашке бывает двух видов — «одноразовое» (task_id пуст, содержание в
     * своих колонках, как раньше) и «из банка» (task_id заполнен,
     * переиспользуемо) — оба видят один и тот же код прохождения/проверки
     * (AutoGrader, task-prompt.blade.php, question-region.blade.php и т.д.),
     * потому что для них разница между режимами не видна за пределами этого
     * метода.
     */
    private const BANK_PROXY_ATTRIBUTES = [
        'type', 'question_text', 'passage_text', 'options', 'matches',
        'table_content', 'image_path', 'answer', 'hint', 'max_score',
        'order_matters', 'image_auto_options', 'left_title', 'right_title',
        'number', 'explanation',
    ];

    public function getAttribute($key)
    {
        // Не $this->task_id — это снова уйдёт в __get()/getAttribute() и
        // рекурсивно вызовет этот же метод. task_id — обычная колонка без
        // приведения типов и мутаторов, поэтому безопасно взять её прямо из
        // сырых атрибутов.
        $taskId = $this->attributes['task_id'] ?? null;

        if ($taskId && in_array($key, self::BANK_PROXY_ATTRIBUTES, true)) {
            // max_score — единственное поле с обратным приоритетом: явное
            // переопределение баллов в ЭТОЙ домашке (своя колонка не пуста)
            // важнее значения по умолчанию из банка.
            if ($key === 'max_score') {
                $local = parent::getAttribute('max_score');
                if ($local !== null) {
                    return $local;
                }
            }

            $task = $this->task;
            if ($task) {
                $value = $task->getAttribute($key);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return parent::getAttribute($key);
    }

    public function isAutoGradable(): bool
    {
        return !in_array($this->type, self::MANUAL_TYPES, true);
    }

    /**
     * "number" (как у Task) читает ту же колонку, что и раньше валидировалась
     * под именем task_number, но нигде не сохранялась и не читалась — форма
     * задания в домашке (components/task-content-fields.blade.php) читает
     * это поле как data_get($task,'number') одинаково для Task и HomeworkTask,
     * переименовывать саму колонку в БД не требуется.
     */
    public function getNumberAttribute()
    {
        return $this->attributes['task_number'] ?? null;
    }

    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function task()
    {
        return $this->belongsTo(\App\Models\Task::class);
    }
}
