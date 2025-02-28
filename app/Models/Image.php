<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Image extends Model
{
    use HasFactory;
    protected $fillable = [
        'shpargalka_id',
        'post_id',
        'name',
        'original_name',

    ];
    public function post() {
        return $this->belongsTo(Post::class);
    }
}
