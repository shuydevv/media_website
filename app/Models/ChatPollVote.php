<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatPollVote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['poll_id', 'user_id', 'voter_name', 'choice'];

    protected $casts = ['choice' => 'boolean'];
}
