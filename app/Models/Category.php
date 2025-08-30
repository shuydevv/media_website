<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function tasks()
{
    return $this->hasMany(\App\Models\Task::class);
}

    protected $table = 'categories';
    protected $guarded = false; // позволяет изменять данные в таблице


}
