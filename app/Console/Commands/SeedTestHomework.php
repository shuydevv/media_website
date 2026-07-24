<?php

namespace App\Console\Commands;

use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Тестовая домашка со всеми типами заданий сразу — для ручного прогона
 * визарда прохождения (student.submissions.*) и проверки куратором
 * (mentor.submissions.*) без необходимости заводить каждый тип руками через
 * админку. Содержание условий/ответов — чисто заглушки, реальный текст тут
 * не важен (см. запрос пользователя), важно только что представлены все 7
 * типов из HomeworkTask (5 авто + 2 ручных).
 */
class SeedTestHomework extends Command
{
    protected $signature = 'homeworks:seed-test
        {lesson : ID урока, к которому привязать тестовую домашку}
        {--days=7 : через сколько дней дедлайн (due_at)}';

    protected $description = 'Создать тестовую домашку со всеми типами заданий, привязанную к указанному уроку';

    public function handle(): int
    {
        $lesson = Lesson::find((int) $this->argument('lesson'));
        if (!$lesson) {
            $this->error('Урок с таким id не найден.');
            return self::FAILURE;
        }

        $courseId = $lesson->courseSession?->course_id;
        if (!$courseId) {
            $this->error('У урока нет привязанной сессии курса (courseSession) — не могу определить course_id для домашки.');
            return self::FAILURE;
        }

        $homework = DB::transaction(function () use ($lesson, $courseId) {
            $homework = Homework::create([
                'title'            => 'Тестовая домашка (все типы заданий)',
                'description'      => 'Служебная домашка для ручного тестирования визарда — по одному заданию каждого типа.',
                'type'             => 'homework',
                'due_at'           => now()->addDays((int) $this->option('days')),
                'course_id'        => $courseId,
                'lesson_id'        => $lesson->id,
                'attempts_allowed' => 2,
            ]);

            $order = 1;

            // 1) test — авто, неупорядоченный (order_matters=false): "234 = 432"
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'type'          => 'test',
                'question_text' => 'Выберите номера верных утверждений (введите цифры без пробелов, порядок не важен).',
                'options'       => ['Первое утверждение', 'Второе утверждение', 'Третье утверждение', 'Четвёртое утверждение'],
                'answer'        => '13',
                'hint'          => 'Подсказка: правильные варианты — первый и третий.',
                'max_score'     => 2,
                'order'         => $order++,
            ]);

            // 2) text_with_questions — авто, пассаж + вопрос, порядок не важен
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'type'          => 'text_with_questions',
                'passage_text'  => "Это тестовый текстовый отрывок для задания с вопросами.\nВторой абзац отрывка — тоже заглушка.",
                'question_text' => 'На основе текста укажите номера верных ответов.',
                'answer'        => '135',
                'max_score'     => 3,
                'order'         => $order++,
            ]);

            // 3) matching — авто, порядок важен (позиция = левый пункт, символ = номер правого)
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'type'          => 'matching',
                'question_text' => 'Соотнесите элементы левой и правой колонки.',
                'left_title'    => 'Левая колонка',
                'right_title'   => 'Правая колонка',
                'matches'       => [
                    'left'  => ['Левый пункт 1', 'Левый пункт 2', 'Левый пункт 3'],
                    'right' => ['Правый пункт 1', 'Правый пункт 2', 'Правый пункт 3'],
                ],
                'answer'        => '231',
                'order_matters' => true,
                'max_score'     => 3,
                'order'         => $order++,
            ]);

            // 4) image_auto — авто, картинки нет (image_path не задан, задание
            // всё равно отвечаемо: вопрос + варианты видны без картинки).
            HomeworkTask::create([
                'homework_id'        => $homework->id,
                'type'               => 'image_auto',
                'question_text'      => 'Определите число на изображении (картинка не приложена — тестовые данные).',
                'image_auto_options' => ['Вариант A', 'Вариант B', 'Вариант C'],
                'answer'             => '12',
                'max_score'          => 2,
                'order'              => $order++,
            ]);

            // 5) table — авто, порядок важен, 2 пропуска
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'type'          => 'table',
                'question_text' => 'Заполните пропуски в таблице (цифры по порядку пропусков).',
                'table_content' => [
                    'cols'   => ['Колонка 1', 'Колонка 2'],
                    'rows'   => [
                        ['Значение А', ''],
                        ['Значение Б', ''],
                    ],
                    'blanks' => [
                        ['r' => 0, 'c' => 1, 'key' => '1'],
                        ['r' => 1, 'c' => 1, 'key' => '2'],
                    ],
                ],
                'answer'        => '12',
                'order_matters' => true,
                'max_score'     => 2,
                'order'         => $order++,
            ]);

            // 6) written — ручная проверка, развёрнутый ответ
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'type'          => 'written',
                'passage_text'  => 'Тестовый отрывок для развёрнутого ответа.',
                'question_text' => 'Напишите развёрнутый ответ по тексту выше.',
                'answer'        => 'Образцовый развёрнутый ответ (заглушка) для куратора.',
                'max_score'     => 3,
                'order'         => $order++,
            ]);

            // 7) image_manual — ручная проверка, картинка не приложена
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'type'          => 'image_manual',
                'question_text' => 'Опишите, что изображено на картинке (картинка не приложена — тестовые данные).',
                'answer'        => 'Образцовый ответ куратора (заглушка).',
                'max_score'     => 3,
                'order'         => $order++,
            ]);

            return $homework;
        });

        $this->info("Создана домашка #{$homework->id} «{$homework->title}» (7 заданий, все типы), привязана к уроку #{$lesson->id}, дедлайн: {$homework->due_at}.");
        $this->line('Начать прохождение: ' . route('student.submissions.create', $homework));

        return self::SUCCESS;
    }
}
