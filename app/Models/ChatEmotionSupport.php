<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatEmotionSupport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['emotion_id', 'user_id', 'supporter_name'];
}
