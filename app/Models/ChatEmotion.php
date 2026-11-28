<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Эмоция ученика в чате трансляции: висит над лентой ограниченное время. */
class ChatEmotion extends Model
{
    protected $fillable = ['lesson_id', 'user_id', 'author_name', 'code', 'ends_at', 'withdrawn_at'];

    protected $casts = ['ends_at' => 'datetime', 'withdrawn_at' => 'datetime'];

    public function supports()
    {
        return $this->hasMany(ChatEmotionSupport::class, 'emotion_id');
    }
}
