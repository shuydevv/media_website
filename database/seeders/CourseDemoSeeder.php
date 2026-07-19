<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;
use App\Models\Submission;
use App\Models\Task;
use App\Models\User;
use App\Service\CourseScheduleService;
use App\Service\EnrollmentService;
use App\Service\Homework\AutoGrader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Создаёт демо-курс с полностью заполненной структурой:
 * расписание -> сессии -> уроки -> домашки с заданиями,
 * плюс демо-студента, записанного на курс, со сданной первой домашкой.
 *
 * Запуск: php artisan db:seed --class=CourseDemoSeeder
 */
class CourseDemoSeeder extends Seeder
{
    private const WEEKS = 4;
    private const TASKS_PER_HOMEWORK = 4;

    public function run(): void
    {
        DB::transaction(function () {
            $category = Category::firstOrCreate(['title' => 'Демо-категория']);

            $course = $this->createCourse($category);
            $this->createSchedule($course);
            $taskPool = $this->createTaskPool($category);

            app(CourseScheduleService::class)->generateSessionsForCourse($course);

            $sessions = $course->sessions()->orderBy('date')->orderBy('start_time')->get();

            $firstHomework = null;

            foreach ($sessions as $i => $session) {
                $lesson = Lesson::create([
                    'course_session_id' => $session->id,
                    'title'             => 'Урок ' . ($i + 1),
                    'lesson_type'       => $i % 2 === 0 ? 'theory' : 'practice',
                    'description'       => 'Описание урока ' . ($i + 1),
                    'meet_link'         => "https://meet.example.com/lesson-" . ($i + 1),
                ]);

                $homework = Homework::create([
                    'course_id'        => $course->id,
                    'lesson_id'        => $lesson->id,
                    'title'            => 'Домашка к уроку ' . ($i + 1),
                    'description'      => 'Домашнее задание к уроку ' . ($i + 1),
                    'type'             => 'homework',
                    'due_at'           => Carbon::parse("{$session->date} {$session->end_time}")->addDays(3),
                    'attempts_allowed' => 2,
                ]);

                $this->createTasks($homework, $taskPool);

                $firstHomework ??= $homework;
            }

            $student = $this->createDemoStudent();

            app(EnrollmentService::class)->enrollUser($student, $course, ['source' => 'seeder']);

            if ($firstHomework) {
                $this->createDemoSubmission($firstHomework, $student);
            }

            $this->command?->info("Демо-курс создан: \"{$course->title}\" (ID {$course->id}), сессий: {$sessions->count()}");
            $this->command?->info("Демо-студент: {$student->email} / password");
        });
    }

    private function createCourse(Category $category): Course
    {
        $start = Carbon::now()->next(Carbon::MONDAY);
        $end = $start->copy()->addWeeks(self::WEEKS)->subDay();

        return Course::create([
            'title'       => 'Демо-курс ' . now()->format('d.m.Y H:i:s'),
            'description' => 'Курс, сгенерированный сидером для тестирования расписания/уроков/домашек',
            'price_cents' => 99000,
            'category_id' => $category->id,
            'start_date'  => $start->toDateString(),
            'end_date'    => $end->toDateString(),
        ]);
    }

    private function createSchedule(Course $course): void
    {
        $course->scheduleTemplates()->createMany([
            ['day_of_week' => 'Mon', 'start_time' => '18:00:00', 'duration_minutes' => 90],
            ['day_of_week' => 'Wed', 'start_time' => '18:00:00', 'duration_minutes' => 90],
        ]);

        $course->load('scheduleTemplates');
    }

    /**
     * Банк заданий (таблица tasks) — админская форма требует task_id
     * у каждого HomeworkTask, поэтому пул создаётся заранее и переиспользуется
     * во всех домашках демо-курса.
     *
     * @return array<int, Task>
     */
    private function createTaskPool(Category $category): array
    {
        $pool = [];

        for ($n = 1; $n <= self::TASKS_PER_HOMEWORK; $n++) {
            $pool[$n] = Task::create([
                'category_id' => $category->id,
                'number'      => (string) $n,
                'criteria'    => "Критерии оценивания демо-задания {$n}: 1 балл за верный ответ.",
                'comment'     => 'Задание сгенерировано сидером демо-курса.',
            ]);
        }

        return $pool;
    }

    /**
     * @param array<int, Task> $taskPool
     */
    private function createTasks(Homework $homework, array $taskPool): void
    {
        for ($n = 1; $n < self::TASKS_PER_HOMEWORK; $n++) {
            HomeworkTask::create([
                'homework_id'   => $homework->id,
                'task_id'       => $taskPool[$n]->id,
                'type'          => 'test',
                'question_text' => "Вопрос {$n}",
                'answer'        => (string) $n,
                'max_score'     => 1,
                'order'         => $n,
            ]);
        }

        HomeworkTask::create([
            'homework_id'   => $homework->id,
            'task_id'       => $taskPool[self::TASKS_PER_HOMEWORK]->id,
            'type'          => 'written',
            'question_text' => 'Вопрос ' . self::TASKS_PER_HOMEWORK . ' (развёрнутый ответ)',
            'answer'        => 'Ответ проверяется куратором вручную',
            'max_score'     => 3,
            'order'         => self::TASKS_PER_HOMEWORK,
        ]);
    }

    private function createDemoStudent(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo.student@example.com'],
            [
                'name'              => 'Демо Студент',
                'password'          => Hash::make('password'),
                'role'              => User::ROLE_READER,
                'email_verified_at' => now(),
            ]
        );
    }

    private function createDemoSubmission(Homework $homework, User $student): void
    {
        $tasks = HomeworkTask::where('homework_id', $homework->id)->get();

        $answers = $tasks->mapWithKeys(function (HomeworkTask $task) {
            $answer = $task->type === 'written'
                ? 'Мой развёрнутый ответ на вопрос.'
                : $task->answer;

            return [$task->id => $answer];
        })->all();

        $submission = Submission::create([
            'homework_id' => $homework->id,
            'user_id'     => $student->id,
            'attempt_no'  => 1,
            'answers'     => $answers,
            'status'      => 'pending',
        ]);

        $grade = app(AutoGrader::class)->gradeWithTasks($tasks, $answers);

        $hasManual = $tasks->contains(fn (HomeworkTask $t) => in_array($t->type, HomeworkTask::MANUAL_TYPES, true));

        $submission->autocheck_score  = (int) ($grade['score'] ?? 0);
        $submission->total_score      = (int) ($grade['score'] ?? 0);
        $submission->per_task_results = $grade['per_task'] ?? null;
        $submission->status           = (!$hasManual && !empty($grade['fully_auto'])) ? 'checked' : 'pending';
        $submission->save();
    }
}
