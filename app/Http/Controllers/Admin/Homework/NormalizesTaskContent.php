<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Support\TaskContentNormalizer;

/**
 * Общая нормализация содержания задания — используется и при создании
 * «одноразового» задания прямо в домашке (StoreController/UpdateController),
 * и при опциональном сохранении того же содержания в банк («Также сохранить
 * в банк заданий»). Делегирует в App\Support\TaskContentNormalizer — тот же
 * класс, что использует и банк заданий (Admin\TaskController), чтобы логика
 * не расходилась в две независимые копии (см. TaskContentRules — там же
 * история бага, из-за которого это уже один раз разошлось).
 */
trait NormalizesTaskContent
{
    private function normalizeTaskContent(array $taskData): array
    {
        return TaskContentNormalizer::normalize($taskData);
    }
}
