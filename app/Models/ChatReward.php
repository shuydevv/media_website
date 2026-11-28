<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Корм за верный ответ в чате трансляции: команда (админ или куратор) отмечает
 * сообщение ученика. Одно сообщение — одна награда (уникальный message_id):
 * двое одному и тому же ответу корм не дают. Забрать — удалить строку
 * (корм списывается обратно, баланс не уходит в минус).
 */
class ChatReward extends Model
{
    protected $fillable = ['lesson_id', 'message_id', 'user_id', 'given_by', 'giver_name', 'corm'];
}
