<?php

namespace App\Support;

/**
 * Единый набор правил валидации содержания ОДНОГО задания — общий для
 * банка заданий (Admin\TaskController) и конструктора домашки
 * (Admin\Homework\StoreRequest/UpdateRequest), которые раньше валидировали
 * одни и те же поля независимо друг от друга и разошлись: 'options' одно
 * время требовал 'array', хотя форма всегда шлёт ОДНУ textarea-строку
 * (баг, уже однажды пойманный и исправленный в домашке, но заново
 * воспроизведённый в банке, потому что это были два разных файла).
 * 'options'/'matches.left'/'matches.right'/'image_auto_options' здесь
 * намеренно 'string', не 'array' — это и есть тот самый факт.
 */
class TaskContentRules
{
    /**
     * @param string $mode   'bank' — задание банка, всегда самостоятельное
     *                       содержание (type обязателен, answer — нет, это
     *                       уже так себя вело в банке, поведение не меняем);
     *                       'homework' — задание в конструкторе домашки,
     *                       может быть 'own' или 'bank' (type/answer
     *                       обязательны только при source=own).
     * @param string $prefix Путь вложенности для правил Laravel, например
     *                       'tasks.*' в конструкторе домашки; '' для банка,
     *                       где поля лежат на верхнем уровне запроса.
     */
    public static function rules(string $mode, string $prefix = ''): array
    {
        $key = fn (string $field) => $prefix === '' ? $field : "{$prefix}.{$field}";

        if ($mode === 'bank') {
            $typeRule = ['required', 'string'];
            $answerRule = ['nullable', 'string'];
        } else {
            $sourceField = $key('source');
            $typeRule = ["required_if:{$sourceField},own", 'nullable', 'string'];
            $answerRule = ["required_if:{$sourceField},own", 'nullable', 'string'];
        }

        $rules = [
            $key('type')               => $typeRule,
            $key('question_text')      => ['nullable', 'string'],
            $key('passage_text')       => ['nullable', 'string'],
            $key('options')            => ['nullable', 'string'],
            $key('matches')            => ['nullable', 'array'],
            $key('matches.left')       => ['nullable', 'string'],
            $key('matches.right')      => ['nullable', 'string'],
            $key('left_title')         => ['nullable', 'string'],
            $key('right_title')        => ['nullable', 'string'],
            $key('table_content')      => ['nullable', 'string'],
            $key('image')              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            $key('image_auto_options') => ['nullable', 'string'],
            $key('order_matters')      => ['nullable', 'boolean'],
            $key('answer')             => $answerRule,
            $key('hint')               => ['nullable', 'string'],
            $key('explanation')        => ['nullable', 'string'],
        ];

        // Баллы — только в домашке (переопределение поверх баллов из
        // критериев банковского номера, см. HomeworkTask::getAttribute()).
        // В банке баллы больше не поле формы содержания — они настраиваются
        // на странице критериев, общие на весь номер (TaskCriteria::max_score).
        if ($mode !== 'bank') {
            $rules[$key('max_score')] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    public static function attributes(string $prefix = ''): array
    {
        $key = fn (string $field) => $prefix === '' ? $field : "{$prefix}.{$field}";

        return [
            $key('type')               => 'Тип задания',
            $key('question_text')      => 'Вопрос / текст',
            $key('answer')             => 'Правильный ответ',
            $key('explanation')        => 'Объяснение задания',
            $key('passage_text')       => 'Текст задания (пассаж)',
            $key('left_title')         => 'Заголовок левой колонки',
            $key('right_title')        => 'Заголовок правой колонки',
            $key('matches.left')       => 'Левая колонка (список)',
            $key('matches.right')      => 'Правая колонка (список)',
            $key('image_auto_options') => 'Варианты ответа (для «Картинка (авто)»)',
            $key('image')              => 'Изображение задания',
            $key('options')            => 'Варианты ответа',
            $key('table_content')      => 'Содержимое таблицы',
        ];
    }
}
