<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Запись в истории ученика в CRM. Платежи сюда не пишутся — лента в
 * карточке склеивает эти события с payments (App\Support\CrmTimeline).
 */
class CrmEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'course_id', 'type', 'text', 'created_by_user_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function log(User $student, string $type, string $text, ?int $courseId = null): self
    {
        return self::create([
            'user_id' => $student->id,
            'course_id' => $courseId,
            'type' => $type,
            'text' => mb_substr($text, 0, 500),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
