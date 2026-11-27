<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageReaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['message_id', 'user_id', 'reaction_type_id'];
}
