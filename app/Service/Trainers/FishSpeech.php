<?php

namespace App\Service\Trainers;

use App\Models\User;
use App\Service\FishFoodService;
use App\Support\Text;
use Illuminate\Support\Facades\Cache;

/**
 * Что говорит рыба на дашборде. Кандидаты — ситуации всех открытых
 * ученику тренажёров (TrainerHub) и самой рыбы (корм, рост); говорит одна,
 * с наибольшим priority. Фразы и приоритеты — config/fish_speech.php.
 */
class FishSpeech
{
    public function __construct(private FishFoodService $fish)
    {
    }

    /**
     * @param array $hub результат TrainerHub::forUser()
     * @return ?array{text: string, url: ?string, feed: bool, quiet: bool, daily: bool, key: string}
     *   feed — нажатие кормит, а не ведёт по url; daily — показывать раз в
     *   день; key — ситуация с датой: реплика, закрытая крестиком (или
     *   показанная daily), не вернётся, пока key тот же.
     */
    public function forUser(User $user, array $hub): ?array
    {
        $candidates = [];

        foreach ($hub['trainers'] as $t) {
            $status = $t['status'];
            $candidates[] = [
                'situation' => $status['situation'],
                'count' => (int) $status['count'],
                'next' => $status['next'],
                'words' => $t['trainer']->words(),
                'url' => $t['url'],
                'key' => class_basename($t['trainer']).'.'.$status['situation'].'.'.$status['count'],
            ];
        }

        $balance = (int) $user->fish_corm_balance;
        if ($balance > 0 && !$this->fedToday($user)) {
            $progress = $this->fish->progressFor((int) $user->fish_total_fed);
            $need = $progress['isMax'] ? null : max(0, $progress['needed'] - $progress['current']);
            $situation = $need !== null && $need <= $balance ? 'corm_level' : 'corm';
            $candidates[] = [
                'situation' => $situation,
                'need' => $need,
                'corm' => $balance,
                'url' => null,
                'key' => 'fish.'.$situation,
            ];
        }

        if (!$candidates) {
            return null;
        }

        $config = config('fish_speech.situations');

        // Самая важная ситуация; при равной важности — где дел больше.
        usort($candidates, fn ($a, $b) => [$config[$b['situation']]['priority'], $b['count'] ?? 0]
            <=> [$config[$a['situation']]['priority'], $a['count'] ?? 0]);
        $pick = $candidates[0];
        $situation = $config[$pick['situation']];

        $phrases = $situation['phrases'];
        if (!empty($pick['next'])) {
            $phrases = array_merge($phrases, $situation['phrases_next'] ?? []);
        }

        return [
            'text' => $this->fill($this->choose($pick['situation'], $phrases), $pick),
            'url' => $pick['url'],
            'feed' => (bool) ($situation['feed'] ?? false),
            'quiet' => (bool) ($situation['quiet'] ?? false),
            'daily' => (bool) ($situation['daily'] ?? false),
            'key' => now()->toDateString().'.'.$pick['key'],
        ];
    }

    /** Рыбу кормили сегодня — отмечается в FishController::feed(). */
    public function fedToday(User $user): bool
    {
        return Cache::has($this->fedKey($user));
    }

    public function markFed(User $user): void
    {
        Cache::put($this->fedKey($user), true, now()->endOfDay());
    }

    private function fedKey(User $user): string
    {
        return 'fish-fed:'.$user->id.':'.now()->toDateString();
    }

    /** Случайная фраза, но не та же, что в прошлый раз в этой ситуации. */
    private function choose(string $situation, array $phrases): string
    {
        $sessionKey = 'fish_speech_last.'.$situation;
        $last = session($sessionKey);
        $pool = count($phrases) > 1 ? array_values(array_diff($phrases, [$last])) : $phrases;
        $phrase = $pool[array_rand($pool)];
        session([$sessionKey => $phrase]);

        return $phrase;
    }

    private function fill(string $phrase, array $pick): string
    {
        $words = $pick['words'] ?? null;
        $replace = [];
        if ($words) {
            $replace = [
                ':new_what' => Text::plural($pick['count'], $words['new_what']),
                ':what' => Text::plural($pick['count'], $words['what']),
                ':new_items' => $words['new_items'],
                ':New_items' => mb_strtoupper(mb_substr($words['new_items'], 0, 1)).mb_substr($words['new_items'], 1),
                ':items' => $words['items'],
                ':Items' => mb_strtoupper(mb_substr($words['items'], 0, 1)).mb_substr($words['items'], 1),
                ':next' => $pick['next'] ? Text::plural($pick['next'], $words['what']) : '',
            ];
        }
        $replace[':need'] = (string) ($pick['need'] ?? '');
        $replace[':corm'] = (string) ($pick['corm'] ?? '');

        // strtr — длинные ключи раньше коротких (:new_what не превратится
        // в «:new_» + :what).
        return strtr($phrase, $replace);
    }
}
