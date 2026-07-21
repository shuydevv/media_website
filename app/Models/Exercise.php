<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $table = 'exercises';
    protected $guarded = false;

    public function topic() {
        return $this->belongsTo(Topic::class, 'topic_id', 'id');
    }

    /**
     * У exercises нет своей колонки category_id — категория определяется
     * через цепочку topic -> section -> category (см. Section::category()).
     * Для запросов используй whereHas('topic.section', ...), это просто
     * accessor-удобство для чтения одного объекта.
     */
    public function getCategoryAttribute(): ?Category
    {
        return $this->topic?->section?->category;
    }
}
