<?php

namespace App\Service\Persons;

use App\Models\Person;
use App\Models\User;
use App\Models\UserPerson;
use Illuminate\Support\Facades\DB;

/**
 * Звания Адмирала Клешни — прогресс ученика в тренажёре личностей
 * (config('persons.ranks'), docs/personalities/Адмирал Клешня — звания и
 * реплики.md). «Герои в строю» — все выученные личности (строка в
 * user_persons появляется после порции новых, где каждую уже спросили
 * двумя заданиями). Ученик ждёт увидеть выученных сразу, а не назавтра
 * после повторения — поэтому не шаг ≥ 2. Последнее врученное
 * звание хранится в users.persons_rank и не уменьшается.
 */
class PersonRanks
{
    public function inLine(User $user): int
    {
        // Только активные: скрытых при импорте (убраны из базы) нет и в цели
        // последнего звания — Person::active()->count() ниже.
        return UserPerson::where('user_id', $user->id)
            ->whereHas('person', fn ($q) => $q->where('is_active', true))
            ->count();
    }

    public function current(User $user): int
    {
        return (int) $user->persons_rank;
    }

    /** Звание по номеру, с посчитанным порогом и номером. */
    public function rank(int $n): ?array
    {
        $rank = config("persons.ranks.{$n}");
        if (!$rank) {
            return null;
        }
        if ($rank['need'] === 'all') {
            // max(1, …) — пустая база не должна вручить высшее звание даром.
            $rank['need'] = max(1, Person::active()->count());
        }

        return $rank + ['n' => $n];
    }

    public function address(User $user): string
    {
        return $this->rank(max(1, $this->current($user)))['address'] ?? 'юнга';
    }

    /**
     * Первый вход в тренажёр: знакомство с адмиралом (звание «Юнга»).
     * Возвращает его один раз, потом — null.
     */
    public function introduce(User $user): ?array
    {
        return $this->raise($user, 1) ? $this->rank(1) : null;
    }

    /**
     * Конец раунда: вручает самое старшее заслуженное звание, если оно выше
     * текущего. Возвращает звание для окна «Приказ по флоту» (один раз) или null.
     */
    public function promote(User $user): ?array
    {
        $before = $this->current($user);
        $inLine = $this->inLine($user);

        $target = 0;
        foreach (array_keys(config('persons.ranks')) as $n) {
            if ($inLine >= $this->rank($n)['need']) {
                $target = $n;
            }
        }

        if ($target <= max(1, $before) || !$this->raise($user, $target)) {
            return null;
        }

        return $this->rank($target) + [
            // Перескочили звание: например, юнга сразу в старшие матросы.
            'skipped' => $target - max(1, $before) >= 2,
            'inLine' => $inLine,
        ];
    }

    /** Полоска на главной тренажёра: звание, сколько в строю, до следующего. */
    public function summary(User $user): array
    {
        $n = max(1, $this->current($user));
        $inLine = $this->inLine($user);
        $goal = $this->rank($n + 1);

        $from = (int) ($this->rank($n)['need'] ?? 0);
        $percent = $goal && $goal['need'] > $from
            ? (int) round(max(0, min(1, ($inLine - $from) / ($goal['need'] - $from))) * 100)
            : 100;

        return [
            'rank' => $this->rank($n),
            'inLine' => $inLine,
            'next' => $goal,
            'left' => $goal ? max(0, $goal['need'] - $inLine) : 0,
            'percent' => $percent,
        ];
    }

    /** Поднять звание атомарно: два параллельных запроса не вручат его дважды. */
    private function raise(User $user, int $to): bool
    {
        $done = DB::table('users')->where('id', $user->id)->where('persons_rank', '<', $to)->update(['persons_rank' => $to]) === 1;
        if ($done) {
            $user->persons_rank = $to;
        }

        return $done;
    }
}
