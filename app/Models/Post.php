<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'topic_id',
    ];

    /**
     * Планы ЕГЭ по обществознанию — это обычные посты с этим тегом. Живут на
     * /plans/{path}, а не в общей ленте /posts. Раньше планы определялись
     * в разных местах по-разному (где-то по тегу, где-то по категории
     * «Планы по обществознанию») — теперь только по тегу, через этот класс.
     */
    public const PLAN_TAG = 'Планы';

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

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id', 'id');
    }

    public function scopePlans(Builder $query): Builder
    {
        return $query->whereHas('tags', fn ($tags) => $tags->where('title', self::PLAN_TAG));
    }

    /** Всё, кроме планов, — для ленты /posts и «Других статей». */
    public function scopeArticles(Builder $query): Builder
    {
        return $query->whereDoesntHave('tags', fn ($tags) => $tags->where('title', self::PLAN_TAG));
    }

    public function isPlan(): bool
    {
        return $this->tags->contains('title', self::PLAN_TAG);
    }

    /** Публичный адрес: /plans/{path} для планов, /posts/{path} для остального. */
    public function getUrlAttribute(): string
    {
        return $this->isPlan()
            ? route('plan.show', ['post' => $this->path])
            : route('post.show', ['post' => $this->path]);
    }

    /** Заголовок целиком — title и title2 в карточках выводятся раздельно. */
    public function getFullTitleAttribute(): string
    {
        return trim(rtrim(trim((string) $this->title), '.') . '. ' . trim((string) $this->title2), ' .');
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
