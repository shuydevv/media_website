<?php

namespace App\Service\Telegram;

/**
 * Разбор параметра deep link t.me/bot?start=… (приходит как «/start <param>»).
 * Код урока (cho / er) — любая часть параметра между «_» или «-», в любом
 * месте и регистре: er_vk1, vk1_er, VK-ER-1 — всё урок er. Остальное —
 * метка рекламы (в нижнем регистре, чтобы VK1 и vk1 не считались в
 * аналитике разными кампаниями). Ищем именно целую часть, а не подстроку:
 * иначе в метке «summer» нашёлся бы «er».
 *
 * Без кода урока весь параметр считается меткой, урок — по умолчанию:
 * человек всё равно получает урок.
 *
 * Чистая функция без БД и конфига — см. tests/Unit/Service/Telegram.
 */
final class StartParam
{
    /**
     * @param  string[]  $lessonCodes
     * @return array{lesson: string, source: ?string}
     */
    public static function parse(?string $param, array $lessonCodes, string $defaultLesson): array
    {
        // Telegram и так пропускает только [A-Za-z0-9_-], но параметр можно
        // отправить и руками текстом «/start что угодно».
        $param = strtolower(substr(preg_replace('/[^A-Za-z0-9_-]/', '', trim((string) $param)) ?? '', 0, 64));

        if ($param === '') {
            return ['lesson' => $defaultLesson, 'source' => null];
        }

        // [часть, разделитель, часть, …] — разделители сохраняем, чтобы метка
        // осталась записанной так же, как в ссылке (vk-ad-1 не станет vk_ad_1).
        $tokens = preg_split('/([_-])/', $param, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($tokens as $i => $token) {
            if ($i % 2 !== 0 || ! in_array($token, $lessonCodes, true)) {
                continue;
            }

            // Убираем код урока и один соседний разделитель.
            $drop = isset($tokens[$i + 1]) ? [$i, $i + 1] : ($i > 0 ? [$i - 1, $i] : [$i]);
            $source = trim(implode('', array_diff_key($tokens, array_flip($drop))), '_-');

            return ['lesson' => $token, 'source' => $source !== '' ? $source : null];
        }

        return ['lesson' => $defaultLesson, 'source' => $param];
    }
}
