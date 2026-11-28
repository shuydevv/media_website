<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Статус ученика в чате: lesson_id — на урок, null — навсегда. Один на ученика. */
class ChatStatus extends Model
{
    public const COLORS = ['blue', 'green', 'amber', 'pink', 'violet'];

    protected $fillable = ['user_id', 'lesson_id', 'text', 'color', 'set_by'];
}
