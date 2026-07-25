<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskCriteria;
use App\Models\User;
use App\Models\Category;
use App\Service\ImageCompressor;
use App\Support\TaskContentNormalizer;
use App\Support\TaskContentRules;
use Illuminate\Http\Request;

/**
 * Банк заданий — содержание (тип/вопрос/варианты/ответ) редактируется здесь.
 * Критерии проверки (criteria/ai_rationale_template/comment) — намеренно
 * отдельная страница (editCriteria/updateCriteria), не часть этой формы:
 * задание в банке может быть авто-проверяемым и вообще не нуждаться в
 * критериях для куратора, и наоборот — критерии правятся независимо от
 * содержания вопроса.
 */
class TaskController extends Controller
{
    private function assertAdmin(): void
    {
        $u = auth()->user();
        abort_unless($u && (int)$u->role === User::ROLE_ADMIN, 403, 'Только для администратора.');
    }

    public function index(Request $request)
    {
        $this->assertAdmin();

        $q = Task::query()->with('category');

        if ($cid = $request->get('category_id')) $q->where('category_id', (int)$cid);
        if ($n   = $request->get('number'))      $q->where('number', 'like', "%{$n}%");
        if ($search = $request->get('search')) {
            $q->where(function($w) use ($search) {
                $w->where('number','like',"%{$search}%")
                  ->orWhere('question_text','like',"%{$search}%");
            })->orWhereHas('category', function($w) use ($search) {
                $w->where('title','like',"%{$search}%");
            });
        }

        $tasks = $q->latest()->paginate(20);
        $categories = Category::orderBy('title')->get(['id','title']);

        return view('admin.tasks.index', [
            'tasks'      => $tasks,
            'categories' => $categories,
            'filters'    => $request->only(['category_id','number','search']),
        ]);
    }

    public function create()
    {
        $this->assertAdmin();
        $categories = Category::orderBy('title')->get(['id','title']);
        $numberOptions = $this->distinctNumbers();
        return view('admin.tasks.create', compact('categories', 'numberOptions'));
    }

    public function store(Request $request)
    {
        $this->assertAdmin();

        $data = $this->validateContent($request);
        $data = $this->applyImageUpload($request, $data);

        $task = new Task($data);
        $task->save();

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Задание создано. Критерии проверки можно заполнить отдельно.');
    }

    public function show(Task $task)
    {
        $this->assertAdmin();
        $task->load('category');
        return view('admin.tasks.show', ['task' => $task]);
    }

    /**
     * Копия задания в один клик. image_path физически не копируется —
     * ImageCompressor::storeAs() всегда пишет под новым случайным именем
     * и никогда не перезаписывает существующий файл, так что оригинал и
     * копия могут безопасно указывать на один и тот же файл: правка
     * картинки одной из записей просто загрузит новый файл под новым
     * именем, не тронув то, на что смотрит другая. criteria_override НЕ
     * копируется — это осознанно точечное исключение для оригинального
     * задания, а не то, что стоит тиражировать по умолчанию.
     */
    public function duplicate(Task $task)
    {
        $this->assertAdmin();

        $copy = $task->replicate(['criteria_override']);
        $copy->save();

        return redirect()->route('admin.tasks.edit', $copy)->with('success', 'Задание продублировано.');
    }

    public function edit(Task $task)
    {
        $this->assertAdmin();
        $task->load('category');
        $categories = Category::orderBy('title')->get(['id','title']);
        $numberOptions = $this->distinctNumbers();
        return view('admin.tasks.edit', compact('task', 'categories', 'numberOptions'));
    }

    /** Уже использованные номера в банке — подсказки в datalist поля "№ в ЕГЭ". */
    private function distinctNumbers(): array
    {
        return Task::distinctNumbers();
    }

    /**
     * Живой предпросмотр задания по ID для поля "Задание из банка" в
     * конструкторе домашки — раньше это был <select> со ВСЕМИ заданиями
     * категории курса разом (нежизнеспособно, если в банке сотни заданий),
     * теперь админ вводит ID напрямую, а это отдаёт короткую карточку —
     * визуально проверить, что ID тот самый, до сохранения формы.
     */
    public function lookup(Task $task)
    {
        $this->assertAdmin();
        $task->load('category:id,title');

        return response()->json([
            'id'             => $task->id,
            'number'         => $task->number,
            'type'           => $task->type,
            'preview'        => $task->question_text ? \Illuminate\Support\Str::limit(strip_tags($task->question_text), 80) : null,
            'category_id'    => $task->category_id,
            'category_title' => $task->category?->title,
        ]);
    }

    /**
     * Живая подсказка при вводе номера: есть ли уже критерии/баллы для
     * этой пары (категория, номер). Не блокирует сохранение — номер может
     * быть совершенно новым, это нормально, просто admin должен понимать,
     * что критерии/баллы пока не заданы и подставится значение по
     * умолчанию (1 балл, без критериев), пока он их не заполнит.
     */
    public function criteriaCheck(Request $request)
    {
        $this->assertAdmin();

        $categoryId = (int) $request->get('category_id');
        $number = trim((string) $request->get('number'));

        if (!$categoryId || $number === '') {
            return response()->json(['exists' => null]);
        }

        $criteria = TaskCriteria::where('category_id', $categoryId)->where('number', $number)->first();

        return response()->json([
            'exists'    => $criteria !== null,
            'max_score' => $criteria->max_score ?? null,
        ]);
    }

    public function update(Request $request, Task $task)
    {
        $this->assertAdmin();

        $data = $this->validateContent($request);
        $data = $this->applyImageUpload($request, $data, $task);

        $task->fill($data)->save();

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Изменения сохранены.');
    }

    /**
     * Критерии редактируются ОБЩИМИ на пару (категория, номер) — правка
     * здесь сразу видна на всех заданиях банка с этим номером, не только
     * на текущем. criteria_override — редкое точечное исключение прямо на
     * задании, для тех единичных случаев, когда номер тот же, а критерии
     * у конкретного вопроса отличаются.
     */
    public function editCriteria(Task $task)
    {
        $this->assertAdmin();
        $task->load('category');

        $criteria = TaskCriteria::firstOrNew([
            'category_id' => $task->category_id,
            'number'      => $task->number,
        ]);

        $siblingCount = Task::where('category_id', $task->category_id)
            ->where('number', $task->number)
            ->count();

        return view('admin.tasks.criteria.edit', compact('task', 'criteria', 'siblingCount'));
    }

    public function updateCriteria(Request $request, Task $task)
    {
        $this->assertAdmin();

        $data = $request->validate([
            'criteria' => ['nullable','string'],
            'ai_rationale_template' => ['nullable','string'],
            'comment' => ['nullable','string'],
            'max_score' => ['nullable','integer','min:1'],
            'criteria_override' => ['nullable','string'],
        ], [], [
            'criteria' => 'Критерии',
            'ai_rationale_template' => 'AI-шаблон «Обоснование баллов»',
            'comment' => 'Комментарий',
            'max_score' => 'Баллы за задание',
            'criteria_override' => 'Уникальные критерии для этого задания',
        ]);

        TaskCriteria::updateOrCreate(
            ['category_id' => $task->category_id, 'number' => $task->number],
            [
                'criteria'              => ($data['criteria'] ?? null) ?: null,
                'ai_rationale_template' => ($data['ai_rationale_template'] ?? null) ?: null,
                'comment'               => ($data['comment'] ?? null) ?: null,
                'max_score'             => isset($data['max_score']) ? (int) $data['max_score'] : 1,
            ]
        );

        $task->fill([
            'criteria_override' => ($data['criteria_override'] ?? null) ?: null,
        ])->save();

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Критерии сохранены.');
    }

    private function validateContent(Request $request): array
    {
        $rules = array_merge(TaskContentRules::rules('bank'), [
            'category_id' => ['required','integer','exists:categories,id'],
            'number'      => ['nullable','string','max:255'],
            'is_public'   => ['nullable','boolean'],
        ]);
        $attributes = array_merge(TaskContentRules::attributes(), [
            'category_id' => 'Категория',
            'number'      => 'Номер',
        ]);

        $data = $request->validate($rules, [], $attributes);

        $content = TaskContentNormalizer::normalize($data);

        return array_merge($content, [
            'category_id'    => (int) $data['category_id'],
            'number'         => ($data['number'] ?? null) ?: null,
            'is_public'      => $request->boolean('is_public'),
        ]);
    }

    private function applyImageUpload(Request $request, array $data, ?Task $task = null): array
    {
        $file = $request->file('image');
        if ($file && $file->isValid()) {
            $data['image_path'] = ImageCompressor::forContent()->storeAs($file, 'homework_images');
        } else {
            $data['image_path'] = $task->image_path ?? null;
        }

        return $data;
    }
}
