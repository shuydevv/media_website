<?php

namespace App\Service\Homework;

use App\Models\Submission;
use App\Models\User;

/**
 * Мини-карточка ученика для страницы проверки (mentor/submissions/{id}):
 * курсы, сколько работ проверено, средний результат за последние работы и
 * слабые места по номерам заданий — чтобы куратор видел, на что обратить
 * внимание в комментарии.
 *
 * Как и StudentTaskHistory, смотрит только последние LIMIT проверенных
 * работ: per_task_results живёт в JSON без индексов.
 */
class StudentReviewSummary
{
    private const LIMIT = 20;

    /** Номер считается слабым, если в среднем набрано меньше этой доли баллов. */
    public const WEAK_BELOW = 0.6;

    /**
     * @return array{
     *   courses: list<string>, checked: int, recent_percent: ?int, recent_count: int,
     *   numbers: list<array{number:string, score:int, max:int, count:int, percent:int, weak:bool}>
     * }
     */
    public function for(User $student, Submission $current): array
    {
        $submissions = Submission::query()
            ->where('user_id', $student->id)
            ->where('id', '!=', $current->id)
            ->where('status', 'checked')
            ->with('homework.tasks')
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get();

        // Средний % — по последним 5 работам (по всем заданиям, и тест, и письменная часть).
        $recent = $submissions->take(5)->map(function (Submission $s) {
            $max = (int) ($s->homework?->tasks->sum(fn ($t) => (int) ($t->max_score ?? 0)) ?? 0);
            return $max > 0 ? min(1, (int) $s->total_score / $max) : null;
        })->filter(fn ($p) => $p !== null);

        // Слабые места — по письменным заданиям с номером ЕГЭ.
        $byNumber = [];
        foreach ($submissions as $s) {
            $per = $s->per_task_results ?? [];
            foreach ($s->homework?->tasks ?? [] as $task) {
                $number = (string) ($task->number ?? '');
                $row = $per[(string) $task->id] ?? null;
                if ($number === '' || $task->isAutoGradable()
                    || !is_array($row) || !empty($row['skipped']) || !array_key_exists('score', $row)) {
                    continue;
                }
                $byNumber[$number]['score'] = ($byNumber[$number]['score'] ?? 0) + (int) $row['score'];
                $byNumber[$number]['max'] = ($byNumber[$number]['max'] ?? 0) + (int) ($task->max_score ?? 1);
                $byNumber[$number]['count'] = ($byNumber[$number]['count'] ?? 0) + 1;
            }
        }

        $numbers = [];
        foreach ($byNumber as $number => $agg) {
            $ratio = $agg['max'] > 0 ? $agg['score'] / $agg['max'] : 0;
            $numbers[] = [
                'number'  => (string) $number,
                'score'   => $agg['score'],
                'max'     => $agg['max'],
                'count'   => $agg['count'],
                'percent' => (int) round($ratio * 100),
                // По одной попытке выводов не делаем.
                'weak'    => $agg['count'] >= 2 && $ratio < self::WEAK_BELOW,
            ];
        }
        usort($numbers, fn ($a, $b) => (int) $a['number'] <=> (int) $b['number'] ?: strcmp($a['number'], $b['number']));

        return [
            'courses'        => $student->courses()->wherePivot('status', 'active')->pluck('title')->all(),
            'checked'        => Submission::where('user_id', $student->id)->where('status', 'checked')->distinct()->count('homework_id'),
            'recent_percent' => $recent->isNotEmpty() ? (int) round($recent->avg() * 100) : null,
            'recent_count'   => $recent->count(),
            'numbers'        => $numbers,
        ];
    }
}
