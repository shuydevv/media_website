<?php

namespace App\Service\Trainers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Trainer::planSessions() для двух устройств тренажёров: интервальных
 * (термины, даты, личности — сессии review/learn) и «сессионных»
 * (суждения, задания — обычные сессии/раунды; «повторить ошибки» не в счёт).
 */
trait PlanSessionRows
{
    /** @param class-string<Model> $model */
    private function spacedPlanSessions(string $model, User $user, int $limit): array
    {
        return $model::query()
            ->where('user_id', $user->id)
            ->whereIn('kind', ['review', 'learn'])
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->limit($limit)
            ->get(['kind', 'day', 'total', 'created_at', 'finished_at'])
            ->map(fn ($s) => [
                'kind' => $s->kind,
                // У изучения на термин два шага (карточка и квиз).
                'items' => $s->kind === 'learn' ? intdiv((int) $s->total, 2) : (int) $s->total,
                'seconds' => (int) $s->created_at->diffInSeconds($s->finished_at),
                'finished_at' => $s->finished_at,
                'day' => \Illuminate\Support\Carbon::parse($s->day)->toDateString(),
            ])
            ->all();
    }

    /** @param class-string<Model> $model */
    private function regularPlanSessions(string $model, User $user, int $limit): array
    {
        return $model::query()
            ->where('user_id', $user->id)
            ->where('kind', 'regular')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->limit($limit)
            ->get(['day', 'total', 'created_at', 'finished_at'])
            ->map(fn ($s) => [
                'kind' => 'session',
                'items' => (int) $s->total,
                'seconds' => (int) $s->created_at->diffInSeconds($s->finished_at),
                'finished_at' => $s->finished_at,
                'day' => \Illuminate\Support\Carbon::parse($s->day)->toDateString(),
            ])
            ->all();
    }
}
