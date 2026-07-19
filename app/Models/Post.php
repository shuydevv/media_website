<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Post extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'posts';

    protected $fillable = [
        'title',
        'title2',
        'description',
        'content',
        'category_id',
        'path',
        'html_title',
        'html_description',
        'main_image',
    ];

    protected static function booted(): void
    {
        static::created(function (Post $post) {
            // Если path не задан — подставляем id
            if (blank($post->path)) {
                // чтобы не триггерить заново события и не ловить гонки — обновим напрямую
                DB::table('posts')->where('id', $post->id)->update([
                    'path' => (string) $post->id,
                ]);
                // Синхронизируем инстанс в памяти
                $post->path = (string) $post->id;
            }
        });
    }

    public function tags() {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }
    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function image() {
        return $this->hasMany(Image::class);
    }

    /**
     * Абсолютный URL обложки поста или null, если её нет. Раньше в разных
     * местах (список постов, "другие статьи" на странице поста) URL
     * собирали вручную по-разному — где-то через asset(), где-то просто
     * конкатенацией строки без него (что давало битую относительную
     * ссылку, резолвящуюся браузером от текущего пути, а не от корня
     * сайта), и без проверки на null. Теперь один источник правды.
     */
    public function getMainImageUrlAttribute(): ?string
    {
        return $this->main_image ? asset('storage/' . $this->main_image) : null;
    }
}
