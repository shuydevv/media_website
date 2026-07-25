<?php

namespace App\Http\Controllers\Admin\Homework;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Homework;
use App\Models\HomeworkTask;
use App\Models\Lesson;
use App\Models\Task;
use App\Support\TaskContentNormalizer;
use App\Support\TaskContentRules;
use App\Support\TaskImageImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Массовая загрузка домашки через JSON-файл: {title, description, type,
 * due_at, tasks: [...]}. Каждый элемент tasks — либо {"task_id": N} (взять
 * из банка), либо полный объект содержания (создать «своё», как в
 * конструкторе домашки), опционально с "image_url" — картинка скачивается
 * и сохраняется так же, как при ручной загрузке файла.
 *
 * Курс и урок — НЕ в JSON, а обычные поля той же формы (выбираются на
 * странице импорта через <select>, урок фильтруется по курсу тем же
 * /lessons?course_id=, что и на create.blade.php). Смысл: администратору
 * проще выбрать курс/урок из выпадающего списка на глазах, чем искать и
 * вручную вписывать числовые id в JSON.
 *
 * Идемпотентность: если в JSON есть верхнеуровневый "id" существующей
 * домашки — она не дублируется, а обновляется, причём "tasks" из файла
 * ПОЛНОСТЬЮ заменяют её текущие задания (не сливаются). Без "id" — как
 * раньше, всегда создаётся новая домашка.
 *
 * Урок — необязательное поле: если его не выбрать на форме, у НОВОЙ
 * домашки lesson_id останется пустым (как и раньше, колонка nullable), а у
 * ОБНОВЛЯЕМОЙ (по "id") — сохранится её текущий урок, если он всё ещё
 * относится к выбранному курсу (иначе тоже станет пустым, а не повиснет на
 * чужом курсе). Курс, в отличие от урока, обязателен всегда.
 */
class ImportController extends Controller
{
    use NormalizesTaskContent;

    public function create()
    {
        $courses = Course::orderBy('title')->get();

        return view('admin.homeworks.import', compact('courses'));
    }

    /**
     * Полный образец — не собирается на лету, а лежит файлом в репозитории
     * (resources/import-templates/homework-example.json): по одному примеру
     * каждого из 7 типов заданий + ссылка на банк, с подробными
     * _instructions/content_quality_rules внутри JSON. Раньше этот массив
     * жил прямо в PHP-коде, но был игрушечным (1 тип, без документации) —
     * разошёлся с тем, что реально нужно для генерации контента через
     * нейросеть, и админ получал по кнопке "Скачать пример" не то, что
     * ожидал. Единый файл — источник правды и для кнопки скачивания, и
     * для ручной сверки при доработке схемы (при добавлении нового поля
     * содержания правь этот файл, а не PHP-массив).
     */
    public function example()
    {
        return response()->download(
            resource_path('import-templates/homework-example.json'),
            'homework-example.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'file'      => ['required', 'file', 'mimes:json,txt', 'max:4096'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
        ], [
            'course_id.exists' => 'Выбранный курс не найден.',
            'lesson_id.exists' => 'Выбранный урок не найден.',
        ], ['file' => 'Файл', 'course_id' => 'Курс', 'lesson_id' => 'Урок']);

        $courseId = (int) $request->input('course_id');
        $requestedLessonId = $request->filled('lesson_id') ? (int) $request->input('lesson_id') : null;

        if ($requestedLessonId && !$this->lessonBelongsToCourse($requestedLessonId, $courseId)) {
            return back()->withErrors(['lesson_id' => 'Этот урок не относится к выбранному курсу'])->withInput();
        }

        $raw = file_get_contents($request->file('file')->getRealPath());
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['file' => 'Не удалось разобрать JSON: ' . json_last_error_msg()])->withInput();
        }

        $topRules = Validator::make($decoded ?? [], [
            'id'          => ['nullable', 'integer', 'min:1'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['nullable', 'string'],
            'due_at'      => ['nullable', 'date'],
            'tasks'       => ['nullable', 'array'],
        ], [], [
            'title' => 'Название',
        ]);

        if ($topRules->fails()) {
            return back()->withErrors($topRules)->withInput();
        }

        $data = $topRules->validated();
        $tasks = $decoded['tasks'] ?? [];

        // Валидируем ВСЕ задания заранее, ничего не записывая в БД — только
        // после этого понятно, есть ли смысл вообще создавать/трогать
        // домашку. Так и должна была работать транзакция, но homeworks/
        // homework_tasks/tasks сидят на MyISAM (проверено через SHOW TABLE
        // STATUS), а он не поддерживает ROLLBACK вообще — начатая и
        // "прерванная" DB::transaction() на самом деле молча коммитит всё,
        // что успело записаться. Единственный надёжный способ не оставить
        // домашку-призрак без единого задания — не писать в БД, пока не
        // убедились, что писать есть что.
        [$results, $pending, $created] = $this->prepareTasks($tasks);

        if (count($tasks) > 0 && $created === 0) {
            return back()->withErrors([
                'file' => 'Ни одно из ' . count($tasks) . ' заданий не прошло валидацию — домашка не сохранена.',
            ])->withInput();
        }

        $existing = !empty($data['id']) ? Homework::find($data['id']) : null;
        $isUpdate = (bool) $existing;

        // Урок необязателен. Если не выбран на форме: у новой домашки
        // остаётся пустым (как раньше — колонка nullable); у обновляемой —
        // сохраняем её текущий урок, но только если он всё ещё относится к
        // выбранному курсу (курс тоже мог поменяться при этом же импорте) —
        // иначе получилась бы домашка с уроком от чужого курса.
        $lessonId = $requestedLessonId;
        if ($lessonId === null && $existing && $existing->lesson_id && $this->lessonBelongsToCourse($existing->lesson_id, $courseId)) {
            $lessonId = $existing->lesson_id;
        }

        $attrs = [
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'] ?? 'homework',
            'due_at'      => $data['due_at'] ?? null,
            'course_id'   => $courseId,
            'lesson_id'   => $lessonId,
        ];

        if ($existing) {
            $existing->update($attrs);
            // Полная замена, не слияние — иначе пришлось бы придумывать
            // построчное сопоставление "старое задание N = новое M", а для
            // JSON-файла, который просто переиспользуют целиком, это лишняя
            // сложность без явной пользы. Раз мы уже знаем (см. выше), что
            // есть минимум одно валидное задание на замену — старые можно
            // без риска удалять прямо сейчас.
            $existing->tasks()->delete();
            $homework = $existing;
        } else {
            $homework = Homework::create($attrs);
        }

        $results = $this->persistTasks($homework, $pending, $results, $courseId);
        ksort($results);

        return view('admin.homeworks.import', [
            'courses'   => Course::orderBy('title')->get(),
            'results'   => $results,
            'created'   => $created,
            'total'     => count($tasks),
            'homework'  => $homework,
            'isUpdate'  => $isUpdate,
        ]);
    }

    /**
     * Фаза 1 — только чтение/валидация, ни одной записи в БД.
     * @return array{0: array, 1: array, 2: int} [$results (только провалы), $pending (готовые к записи), $created]
     */
    private function prepareTasks(array $tasks): array
    {
        $results = [];
        $pending = [];
        $created = 0;

        foreach ($tasks as $i => $item) {
            if (!is_array($item)) {
                $results[$i] = ['index' => $i, 'ok' => false, 'label' => '', 'message' => 'Строка не является объектом'];
                continue;
            }

            $label = $this->resultLabel($item);

            if (isset($item['task_id'])) {
                if (!Task::whereKey($item['task_id'])->exists()) {
                    $results[$i] = ['index' => $i, 'ok' => false, 'label' => $label, 'message' => "Задание из банка id={$item['task_id']} не найдено"];
                    continue;
                }
                $pending[] = [
                    'kind'  => 'bank',
                    'index' => $i,
                    'label' => $label,
                    'task_id' => (int) $item['task_id'],
                    'order' => $item['order'] ?? ($i + 1),
                    'max_score' => isset($item['max_score']) ? (int) $item['max_score'] : null,
                ];
                $created++;
                continue;
            }

            $flat = TaskContentNormalizer::flattenImportShapes($item);
            $validator = Validator::make($flat, TaskContentRules::rules('bank'), [], TaskContentRules::attributes());
            if ($validator->fails()) {
                $results[$i] = ['index' => $i, 'ok' => false, 'label' => $label, 'message' => implode(' ', $validator->errors()->all())];
                continue;
            }

            $pending[] = [
                'kind'    => 'own',
                'index'   => $i,
                'label'   => $label,
                'item'    => $item,
                'content' => $this->normalizeTaskContent($validator->validated()),
                'order'   => $item['order'] ?? ($i + 1),
                'max_score' => isset($item['max_score']) ? (int) $item['max_score'] : 1,
                // Номер в ЕГЭ — как и в ручной форме (StoreController/
                // UpdateController): без него для импортированного задания
                // не найдутся критерии/баллы номера, даже если они уже
                // заведены в банке под этим номером.
                'number'  => ($item['number'] ?? null) ?: null,
            ];
            $created++;
        }

        return [$results, $pending, $created];
    }

    /**
     * Фаза 2 — сама запись. Вызывается, только когда уже точно решено, что
     * домашка будет создана/обновлена (см. store()) — здесь только пишем.
     */
    private function persistTasks(Homework $homework, array $pending, array $results, int $courseId): array
    {
        foreach ($pending as $p) {
            if ($p['kind'] === 'bank') {
                HomeworkTask::create([
                    'homework_id' => $homework->id,
                    'task_id'     => $p['task_id'],
                    'order'       => $p['order'],
                    'max_score'   => $p['max_score'],
                ]);
                $results[$p['index']] = ['index' => $p['index'], 'ok' => true, 'label' => $p['label'], 'note' => 'из банка'];
                continue;
            }

            $content = $p['content'];
            $item = $p['item'];

            $imageNote = null;
            if (!empty($item['image_url']) && is_string($item['image_url'])) {
                $imageError = null;
                $path = TaskImageImporter::download($item['image_url'], $imageError);
                if ($path) {
                    $content['image_path'] = $path;
                } else {
                    $imageNote = "картинка не загружена: {$imageError}";
                }
            }

            $homeworkTask = HomeworkTask::create(array_merge($content, [
                'homework_id' => $homework->id,
                'order'       => $p['order'],
                'max_score'   => $p['max_score'],
                'task_number' => $p['number'],
            ]));

            if (!empty($item['save_to_bank'])) {
                $categoryId = Course::find($courseId)?->category_id;
                if ($categoryId) {
                    // Баллы в банк не копируются — там они общие для номера
                    // (TaskCriteria::max_score), не своя колонка на Task.
                    $bankTask = Task::create(array_merge($content, [
                        'category_id' => $categoryId,
                        'number'      => $p['number'],
                    ]));
                    $homeworkTask->task_id = $bankTask->id;
                    $homeworkTask->save();
                }
            }

            $note = 'своё содержание';
            if ($imageNote) {
                $note .= " ({$imageNote})";
            }
            $results[$p['index']] = ['index' => $p['index'], 'ok' => true, 'label' => $p['label'], 'note' => $note];
        }

        return $results;
    }

    /** Короткая метка для строки отчёта — чтобы не искать задание по номеру строки в файле. */
    private function resultLabel(array $item): string
    {
        $text = $item['question_text'] ?? $item['passage_text'] ?? $item['answer'] ?? null;

        return $text ? Str::limit((string) $text, 50) : '';
    }

    /** Та же проверка "урок и правда из этого курса", что и в UpdateController. */
    private function lessonBelongsToCourse(int $lessonId, int $courseId): bool
    {
        return Lesson::query()
            ->join('course_sessions', 'course_sessions.id', '=', 'lessons.course_session_id')
            ->where('lessons.id', $lessonId)
            ->where('course_sessions.course_id', $courseId)
            ->exists();
    }
}
