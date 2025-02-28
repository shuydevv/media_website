<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shpargalka extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'shpargalkas';
    protected $guarded = false;

    public function post() {
        return $this->belongsTo(Post::class, 'category_id', 'id');
    }
    public function image() {
        return $this->hasMany(Image::class);
    }
}
