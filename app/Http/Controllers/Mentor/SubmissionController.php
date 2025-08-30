<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Мои текущие проверки (залоченные мной)
        $mine = Submission::query()
        ->with(['user','homework'])
        ->where('locked_by', $user->id)
        ->where('lock_expires_at', '>', now())
        ->orderByDesc('lock_expires_at')
        ->get();

        // Очередь доступных к проверке (не заняты и не завершены)
        $queue = Submission::query()
        ->with(['user','homework'])
        ->whereNull('locked_by')
              ->orWhereNull('lock_expires_at')
              ->orWhere('lock_expires_at', '<=', now())
        ->where('status', 'pending')
        ->orderBy('created_at')
        ->get();

        // ⚠️ Работы с пропущенными заданиями (для админа)
        $skipped = Submission::with(['user','homework'])
        ->whereNotIn('status', ['checked','finished','done']) // не финальные
        ->get()
        ->filter(function (Submission $s) {
            $per = $s->per_task_results ?? [];
            foreach ($per as $row) {
                if (!empty($row['skipped'])) return true;
            }
            return false;
        })
        ->values();


        $subs = Submission::with(['user','homework'])->latest()->paginate(20);
        return view('mentor.submissions.index', ['submissions' => $subs, 'mine' => $mine, 'queue' => $queue, 'skipped' => $skipped], );
    }

    public function show(Submission $submission)
    {
        $this->authorize('view', $submission);
        DB::transaction(function () use ($submission) {
        // заблокируем запись на уровне БД до конца транзакции
        $submission = \App\Models\Submission::where('id', $submission->id)->lockForUpdate()->first();

        $now = now();
        $expired = !$submission->lock_expires_at || $submission->lock_expires_at->isPast();

        if ($expired || $submission->locked_by === auth()->id()) {
            // лок свободен или мой — ставим себе
            $submission->locked_by = auth()->id();
            $submission->lock_expires_at = $now->copy()->addHour();
            $submission->save();
        } else {
            // чужой активный лок → выбрасываем 403
            abort(403, 'Эта работа уже проверяется другим куратором.');
        }
    });

        $homework = $submission->homework;
$tasksRaw = $homework->tasks ?? [];
if (is_string($tasksRaw)) {
    $dec = json_decode($tasksRaw, true);
    $tasksRaw = is_array($dec) ? $dec : [];
}

// Соберём список возможных task_id из JSON (id или task_id)
$taskIds = [];
foreach ($tasksRaw as $t) {
    if (is_array($t)) {
        $tid = $t['id'] ?? $t['task_id'] ?? null;
    } elseif (is_object($t)) {
        $tid = $t->id ?? $t->task_id ?? null;
    } else {
        $tid = null;
    }
    if ($tid !== null && is_numeric($tid)) {
        $taskIds[] = (int) $tid;
    }
}
$taskIds = array_values(array_unique($taskIds));

// Разовый запрос к БД по всем id
$tasksFromDb = \App\Models\Task::query()
    ->select(['id', 'criteria', 'comment'])
    ->whereIn('id', $taskIds)
    ->get()
    ->keyBy('id');

// Нормализатор критериев: текст/JSON/«лес бэкслешей»
$normalizeCriteria = function ($raw) {
    $txt = (string) ($raw ?? '');
    if ($txt === '') return '';
    $decoded = json_decode($txt, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // попробуем разэкранировать один раз и снова распарсить
        $unescaped = preg_replace('/\\\\{2,}/', '\\', trim($txt, "\" \t\n\r\0\x0B"));
        $decoded = json_decode($unescaped, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $txt = $unescaped;
        }
    }
    if (is_array($decoded)) {
        $lines = [];
        if (array_is_list($decoded)) {
            foreach ($decoded as $v) $lines[] = is_array($v) ? implode(' ', $v) : (string) $v;
        } else {
            foreach ($decoded as $k => $v) {
                $prefix = is_string($k) && $k !== '' ? ($k . ': ') : '';
                $lines[] = $prefix . (is_array($v) ? implode(' ', $v) : (string) $v);
            }
        }
        return implode("\n", array_filter(array_map('trim', $lines)));
    }
    return $txt;
};

// Соберём «карту» критериев по taskId: сначала берём из JSON, если пусто — из БД
$criteriaByTaskId = [];
$commentByTaskId  = [];

foreach ($tasksRaw as $i => $t) {
    $obj = is_array($t) ? (object) $t : (is_object($t) ? $t : (object)[]);
    $tid = $obj->id ?? $obj->task_id ?? ("t_manual_$i"); // может быть псевдо-id

    // Кандидаты на критерии из JSON
    $candidates = [
        $obj->criteria ?? null,
        $obj->rubric ?? null,
        $obj->rules ?? null,
    ];
    $criteria = '';
    foreach ($candidates as $cand) {
        if (!empty($cand)) { $criteria = (string) $cand; break; }
    }

    // Если пусто и есть числовой id — фоллбек к БД
    if ($criteria === '') {
        $numericId = $obj->id ?? $obj->task_id ?? null;
        if ($numericId !== null && is_numeric($numericId) && $tasksFromDb->has((int) $numericId)) {
            $criteria = (string) ($tasksFromDb[(int)$numericId]->criteria ?? '');
        }
    }

    $criteriaByTaskId[(string)$tid] = $normalizeCriteria($criteria);

    // Аналогично комментарий для проверяющего
    $comment = '';
    foreach ([
        $obj->comment ?? null,
        $obj->mentor_comment ?? null,
        $obj->review_comment ?? null,
        $obj->notes ?? null,
        $obj->tips ?? null,
    ] as $cand) {
        if (!empty($cand)) { $comment = (string) $cand; break; }
    }
    if ($comment === '') {
        $numericId = $obj->id ?? $obj->task_id ?? null;
        if ($numericId !== null && is_numeric($numericId) && $tasksFromDb->has((int) $numericId)) {
            $comment = (string) ($tasksFromDb[(int)$numericId]->comment ?? '');
        }
    }
    $commentByTaskId[(string)$tid] = trim($comment);
}
        return view('mentor.submissions.show', ['submission' => $submission]);
    }

    public function update(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);

        $data = $request->validate([
            'score'   => 'nullable|integer|min:0|max:100',
            'comment' => 'nullable|string',
            'status'  => 'required|in:submitted,checked',
        ]);

        $submission->update($data);

        return back()->with('success', 'Оценка сохранена.');
    }
}
