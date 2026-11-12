<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Личность тренажёра личностей по истории. Наполняется только импортом
 * docs/personalities/personalities.json (App\Service\Persons\PersonImporter) —
 * руками в базе не правится, следующая заливка перезапишет.
 */
class Person extends Model
{
    protected $table = 'persons';

    /** Уровни — поле level файла базы. */
    public const LEVELS = [
        1 => 'База — нужно знать всем',
        2 => 'Средний уровень',
        3 => 'Продвинутый — на высокий балл',
    ];

    public const ROLES = ['R', 'M', 'S', 'C', 'K', 'O', 'H'];

    public const ROLE_RULER = 'R';

    protected $fillable = [
        'slug', 'name', 'description', 'association', 'related_text', 'related_slugs', 'era', 'role',
        'level', 'sort_order', 'answer', 'image_path', 'image_meta', 'is_active',
    ];

    protected $casts = [
        'related_slugs' => 'array',
        'image_meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        // Демо-ученику — только лёгкий набор из config/demo.php.
        return \App\Service\Demo\DemoMode::restrict($query->where('is_active', true), 'persons', 'slug');
    }

    /**
     * Уменьшенные копии портрета (делает persons:import): папка => сторона в px.
     * thumbs — для мелких мест до 32 px (карта, чипы), cards — для страницы
     * «Все личности» (карточки ~200 px). Чётко на экранах с двойной плотностью.
     */
    public const SIZES = ['thumbs' => 64, 'cards' => 256];

    public function portraitUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public static function thumbPathFor(string $imagePath, string $kind = 'thumbs'): string
    {
        return dirname($imagePath).'/'.$kind.'/'.basename($imagePath);
    }

    /** Миниатюра 64 px; если её нет — полный портрет. */
    public function thumbUrl(): ?string
    {
        return $this->sizedUrl('thumbs');
    }

    /** Копия 256 px для карточек; если её нет — полный портрет. */
    public function cardUrl(): ?string
    {
        return $this->sizedUrl('cards');
    }

    private function sizedUrl(string $kind): ?string
    {
        if (!$this->image_path) {
            return null;
        }
        $path = self::thumbPathFor($this->image_path, $kind);

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : $this->portraitUrl();
    }

    /** Инициалы для заглушки без портрета. */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim(preg_replace('/\(.*?\)/u', '', $this->name)));
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters);
    }
}
