<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Запись в журнале обратной связи ученику — см. миграцию
 * create_student_feedback_table.
 */
class StudentFeedback extends Model
{
    protected $table = 'student_feedback';

    public const KINDS = [
        'voice' => 'Голосовое',
        'text' => 'Сообщение',
        'call' => 'Созвон',
    ];

    protected $fillable = ['user_id', 'author_id', 'kind', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
