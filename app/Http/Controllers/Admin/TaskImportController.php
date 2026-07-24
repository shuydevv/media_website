<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskContentNormalizer;
use App\Support\TaskContentRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Массовая загрузка заданий в банк через JSON-файл — один объект или
 * массив объектов с теми же полями, что форма банка. Изображения через
 * импорт не переносятся (только текстовое содержание — картинка
 * добавляется вручную после импорта). Частичный успех: что провалидировалось,
 * то сохраняется, по остальным строкам — отчёт с причиной.
 */
class TaskImportController extends Controller
{
    private function assertAdmin(): void
    {
        $u = auth()->user();
        abort_unless($u && (int) $u->role === User::ROLE_ADMIN, 403, 'Только для администратора.');
    }

    public function create()
    {
        $this->assertAdmin();
        return view('admin.tasks.import');
    }

    public function store(Request $request)
    {
        $this->assertAdmin();

        $request->validate(['file' => ['required', 'file']], [], ['file' => 'Файл']);

        $raw = file_get_contents($request->file('file')->getRealPath());
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['file' => 'Не удалось разобрать JSON: ' . json_last_error_msg()]);
        }

        // Один объект задания или массив объектов — оба варианта допустимы.
        $items = (is_array($decoded) && array_is_list($decoded)) ? $decoded : [$decoded];

        $results = [];
        $created = 0;

        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                $results[] = ['index' => $i, 'ok' => false, 'message' => 'Строка не является объектом'];
                continue;
            }

            $categoryId = $item['category_id'] ?? null;
            if (!$categoryId && !empty($item['category'])) {
                $categoryId = Category::where('title', $item['category'])->value('id');
            }

            $flat = array_merge(
                TaskContentNormalizer::flattenImportShapes($item),
                ['category_id' => $categoryId]
            );

            $rules = array_merge(TaskContentRules::rules('bank'), [
                'category_id' => ['required', 'integer', 'exists:categories,id'],
                'number'      => ['nullable', 'string', 'max:255'],
                'is_public'   => ['nullable', 'boolean'],
            ]);

            $validator = Validator::make($flat, $rules, [], TaskContentRules::attributes());
            if ($validator->fails()) {
                $results[] = ['index' => $i, 'ok' => false, 'message' => implode(' ', $validator->errors()->all())];
                continue;
            }

            $data = $validator->validated();
            $content = TaskContentNormalizer::normalize($data);

            // Баллы сюда не входят — они общие для номера (TaskCriteria::
            // max_score), настраиваются на странице критериев после импорта,
            // не за каждое отдельное задание.
            $task = Task::create(array_merge($content, [
                'category_id' => (int) $data['category_id'],
                'number'      => $data['number'] ?? null,
                'is_public'   => (bool) ($item['is_public'] ?? false),
            ]));

            $created++;
            $results[] = ['index' => $i, 'ok' => true, 'task_id' => $task->id];
        }

        return view('admin.tasks.import', [
            'results' => $results,
            'created' => $created,
            'total'   => count($items),
        ]);
    }
}
