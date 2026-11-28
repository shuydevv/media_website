<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Быстрый опрос «Пон / Не пон» в чате трансляции. */
class ChatPoll extends Model
{
    protected $fillable = ['lesson_id', 'created_by', 'ends_at', 'closed_at'];

    protected $casts = [
        'ends_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function votes()
    {
        return $this->hasMany(ChatPollVote::class, 'poll_id');
    }

    public function isActive(): bool
    {
        return ! $this->closed_at && $this->ends_at->isFuture();
    }
}
