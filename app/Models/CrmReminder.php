<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmReminder extends Model
{
    protected $fillable = ['user_id', 'created_by_user_id', 'due_at', 'note', 'notified_at'];

    protected $casts = [
        'due_at' => 'date',
        'notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * "Наступило" — due_at (хранится как полночь дня) уже в прошлом, то
     * есть сегодня или раньше. Не пересчитывается отдельным cron-полем —
     * тот же принцип, что и у User::crmStatus()/crmExpiresSoon(), считаем
     * на лету от текущего времени.
     */
    public function isActive(): bool
    {
        return $this->due_at->isPast();
    }
}
