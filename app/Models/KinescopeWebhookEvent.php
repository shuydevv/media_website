<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Журнал уведомлений Kinescope — для отладки и разбора сбоев чата трансляций. */
class KinescopeWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event_type', 'lesson_id', 'action', 'payload'];
}
