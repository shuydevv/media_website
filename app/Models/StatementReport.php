<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Жалоба ученика «Ошибка в карточке?». */
class StatementReport extends Model
{
    protected $fillable = ['user_id', 'statement_id', 'message', 'status'];

    public function statement()
    {
        return $this->belongsTo(Statement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
