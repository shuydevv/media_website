<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Кусок эфира между сигналами Kinescope «эфир пошёл» и «эфир закончился». */
class LessonLiveSegment extends Model
{
    protected $fillable = ['lesson_id', 'started_at', 'ended_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
