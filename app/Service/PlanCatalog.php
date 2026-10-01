<?php

namespace App\Service;

use App\Models\Post;
use App\Models\Section;
use App\Models\Topic;
use Illuminate\Support\Collection;

/**
 * Планы ЕГЭ (посты с тегом «Планы»), сгруппированные по разделам и темам
 * (Section → Topic, те же, что у упражнений). Порядок разделов и тем — по
 * id (отдельного поля сортировки у них нет), планов внутри темы — по
 * алфавиту. Планы без темы — в конце, отдельной группой.
 */
class PlanCatalog
{
    /**
     * @return Collection<int, array{
     *     section: ?Section,
     *     topics: Collection<int, array{topic: ?Topic, plans: Collection<int, Post>}>,
     * }>
     */
    public function grouped(?int $onlySectionId = null, ?int $exceptPostId = null): Collection
    {
        $plans = Post::plans()
            ->with(['tags', 'topic.section'])
            ->when($exceptPostId !== null, fn ($q) => $q->where('id', '!=', $exceptPostId))
            ->when($onlySectionId !== null, fn ($q) => $q->whereHas('topic', fn ($t) => $t->where('section_id', $onlySectionId)))
            ->get()
            ->sort(fn (Post $a, Post $b) => $this->sortKey($a) <=> $this->sortKey($b))
            ->values();

        return $plans
            ->groupBy(fn (Post $plan) => $plan->topic?->section_id ?? 0, true)
            ->map(fn (Collection $inSection) => [
                'section' => $inSection->first()->topic?->section,
                'topics' => $inSection
                    ->groupBy(fn (Post $plan) => $plan->topic_id ?? 0, true)
                    ->map(fn (Collection $inTopic) => [
                        'topic' => $inTopic->first()->topic,
                        'plans' => $inTopic->values(),
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function sortKey(Post $plan): array
    {
        return [
            $plan->topic?->section_id ?? PHP_INT_MAX,
            $plan->topic_id ?? PHP_INT_MAX,
            mb_strtolower($plan->full_title),
        ];
    }
}
