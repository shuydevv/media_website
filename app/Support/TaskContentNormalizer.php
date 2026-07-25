<?php

namespace App\Support;

/**
 * Единая нормализация содержания ОДНОГО задания из провалидированных
 * данных запроса в форму, готовую для записи в Task/HomeworkTask — общая
 * для банка (Admin\TaskController) и конструктора домашки
 * (Admin\Homework\StoreController/UpdateController). См. TaskContentRules
 * для истории бага, из-за которого эта логика существовала раньше в двух
 * независимых копиях.
 *
 * max_score сюда намеренно не входит — банк и домашка обрабатывают его
 * по-разному (банк всегда пишет число с дефолтом 1; домашка отдельно
 * решает между локальным override и значением из связанного банковского
 * задания), это остаётся на вызывающей стороне.
 */
class TaskContentNormalizer
{
    /**
     * Приводит один импортируемый JSON-объект задания к тем же плоским
     * "текстовым" формам, что шлёт обычная форма (options — строка с
     * переносами, а не массив; table_content — JSON-строка, а не вложенный
     * объект) — чтобы дальше пройти через TaskContentRules/normalize()
     * БЕЗ каких-либо специальных правил для импорта: один и тот же путь
     * валидации/сохранения, что и у ручного ввода.
     */
    public static function flattenImportShapes(array $item): array
    {
        foreach (['options', 'image_auto_options'] as $field) {
            if (isset($item[$field]) && is_array($item[$field])) {
                $item[$field] = implode("\n", $item[$field]);
            }
        }

        if (isset($item['matches']) && is_array($item['matches'])) {
            foreach (['left', 'right'] as $side) {
                if (isset($item['matches'][$side]) && is_array($item['matches'][$side])) {
                    $item['matches'][$side] = implode("\n", $item['matches'][$side]);
                }
            }
        }

        if (isset($item['table_content']) && is_array($item['table_content'])) {
            $item['table_content'] = json_encode($item['table_content'], JSON_UNESCAPED_UNICODE);
        }

        return $item;
    }

    public static function splitLines($value): array
    {
        if (is_array($value)) {
            // textarea даёт массив из ОДНОГО элемента с переносами строк внутри
            $value = implode("\n", $value);
        }
        $value = (string) $value;
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $value)),
            fn ($v) => $v !== ''
        ));
    }

    /**
     * @param array $data Провалидированные данные с плоскими ключами
     *   (type, question_text, options, matches.left/right, table_content,
     *   image_auto_options, order_matters, left_title, right_title,
     *   answer, hint) — вызывающий код сам достаёт нужный элемент массива
     *   tasks[N] перед вызовом.
     */
    public static function normalize(array $data): array
    {
        $options = self::splitLines($data['options'] ?? null);
        $imageAutoOptions = self::splitLines($data['image_auto_options'] ?? null);

        $matches = null;
        if (!empty($data['matches']['left']) || !empty($data['matches']['right'])) {
            $matches = [
                'left'  => self::splitLines($data['matches']['left'] ?? null),
                'right' => self::splitLines($data['matches']['right'] ?? null),
            ];
        }

        $tableContent = null;
        if (($data['type'] ?? null) === 'table') {
            $raw = trim((string) ($data['table_content'] ?? ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                $tableContent = is_array($decoded) ? $decoded : null;
            }
        }

        return [
            'type'               => $data['type'] ?? null,
            'question_text'      => ($data['question_text'] ?? null) ?: null,
            'passage_text'       => ($data['passage_text'] ?? null) ?: null,
            'options'            => $options,
            'matches'            => $matches,
            'table_content'      => $tableContent,
            'image_auto_options' => $imageAutoOptions,
            'order_matters'      => !empty($data['order_matters']),
            'left_title'         => ($data['left_title'] ?? null) ?: null,
            'right_title'        => ($data['right_title'] ?? null) ?: null,
            'answer'             => ($data['answer'] ?? null) ?: null,
            'hint'               => ($data['hint'] ?? null) ?: null,
            'explanation'        => ($data['explanation'] ?? null) ?: null,
        ];
    }
}
