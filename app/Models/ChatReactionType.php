<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Реакция чата: стандартная (анимация Noto — public/img/chat-reactions/noto/
 * <code>.webp, статичная — <code>.svg) или своя, загруженная админом
 * (webp/gif/lottie/png в storage/app/public/chat-reactions). Не удаляется —
 * выключается: поставленные раньше реакции должны оставаться видны.
 */
class ChatReactionType extends Model
{
    public const FORMATS = ['webp', 'gif', 'lottie', 'png'];

    protected $fillable = ['name', 'noto_code', 'emoji', 'file_path', 'format', 'placement', 'sort', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Для браузера: что показывать в выборе, на чипе и в анимации. */
    public function toClient(): array
    {
        if ($this->format === 'noto') {
            $base = asset('img/chat-reactions/noto/'.$this->noto_code);
            $anim = $base.'.webp';
            $still = $base.'.svg';
        } else {
            $anim = Storage::url($this->file_path);
            // У gif/webp первый кадр не вытащить без canvas — чип показывает
            // сам файл; lottie рисует плеер, png анимирует CSS («прыжок»).
            $still = $this->format === 'lottie' ? null : $anim;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'emoji' => $this->emoji,
            'format' => $this->format,
            'anim' => $anim,
            'still' => $still,
            'main' => $this->placement === 'main',
            'active' => $this->is_active,
        ];
    }
}
