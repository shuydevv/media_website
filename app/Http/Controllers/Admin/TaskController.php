<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;

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
                $w->where('number','like',"%{$search}%");
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
        return view('admin.tasks.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->assertAdmin();

        $data = $this->validateData($request);

        $task = new Task($data);
        $task->save();

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Запись создана.');
    }

    public function show(Task $task)
    {
        $this->assertAdmin();
        $task->load('category');
        return view('admin.tasks.show', ['task' => $task]);
    }

    public function edit(Task $task)
    {
        $this->assertAdmin();
        $task->load('category');
        $categories = Category::orderBy('title')->get(['id','title']);
        return view('admin.tasks.edit', compact('task','categories'));
    }

    public function update(Request $request, Task $task)
    {
        $this->assertAdmin();

        $data = $this->validateData($request);
        $task->fill($data)->save();

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Изменения сохранены.');
    }

    private function validateData(Request $request): array
    {
        $request->validate([
            'category_id' => ['required','integer','exists:categories,id'],
            'number'      => ['nullable','string','max:255'],
            'criteria' => ['required','string'],
            'ai_rationale_template' => ['nullable','string'],
            'comment' => ['nullable','string'],
        ], [], [
            'category_id' => 'Категория',
            'number'      => 'Номер',
            'criteria' => 'Критерии',
            'ai_rationale_template' => 'AI-шаблон «Обоснование баллов»',
            'comment' => 'Комментарий',
        ]);

        // $criteria = json_decode((string)$request->input('criteria_json'), true);
        // if (!is_array($criteria)) {
        //     return back()->withInput()->with('error', 'Поле «Критерии (JSON)» должно быть корректным JSON.')->throwResponse();
        // }

        return [
            'category_id'           => (int)$request->input('category_id'),
            'number'                => $request->string('number')->toString() ?: null,
            'criteria'              => $request->input('criteria') ?: null,
            'ai_rationale_template' => $request->input('ai_rationale_template') ?: null,
            'comment'               => $request->input('comment') ?: null,
        ];
    }
}
