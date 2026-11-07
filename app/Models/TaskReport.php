<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Жалоба ученика «Ошибка в задании?» со станции «Задания». */
class TaskReport extends Model
{
    protected $fillable = ['user_id', 'task_id', 'message', 'status'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
