<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Гость чата трансляции по гостевой ссылке урока (не пользователь сайта). */
class ChatGuest extends Model
{
    protected $fillable = ['lesson_id', 'name', 'ip'];
}
