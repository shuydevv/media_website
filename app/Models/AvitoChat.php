<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Чат Авито и его тема в админ-группе — см. Service\Avito\AvitoTopics. */
class AvitoChat extends Model
{
    protected $fillable = ['chat_id', 'user_id', 'client_name', 'topic_id'];

    protected $casts = [
        'user_id' => 'integer',
        'topic_id' => 'integer',
    ];

    public function account(): ?AvitoAccount
    {
        return AvitoAccount::where('avito_user_id', $this->user_id)->first();
    }
}
