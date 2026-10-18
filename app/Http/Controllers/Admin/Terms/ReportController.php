<?php

namespace App\Http\Controllers\Admin\Terms;

use App\Http\Controllers\Controller;
use App\Models\TermFeature;
use App\Models\TermReport;
use Illuminate\Http\Request;

/**
 * Жалобы «Сообщить об ошибке» из квиза по признакам: верный признак и
 * три варианта, которые видел ученик. Частые жалобы на один признак —
 * повод развести признаки в базе терминов (group) и переимпортировать.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $resolved = $request->query('tab') === 'resolved';

        $reports = TermReport::with(['term', 'feature', 'user'])
            ->when($resolved, fn ($q) => $q->whereNotNull('resolved_at'), fn ($q) => $q->whereNull('resolved_at'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // Тексты вариантов, которые видел ученик, — одним запросом на страницу.
        $distractors = TermFeature::with('term')
            ->whereIn('id', $reports->pluck('distractor_ids')->flatten()->unique())
            ->get()
            ->keyBy('id');

        // Сколько всего открытых жалоб на тот же признак.
        $sameFeature = TermReport::whereNull('resolved_at')
            ->whereIn('feature_id', $reports->pluck('feature_id'))
            ->selectRaw('feature_id, count(*) as c')
            ->groupBy('feature_id')
            ->pluck('c', 'feature_id');

        return view('admin.terms.reports', compact('reports', 'distractors', 'sameFeature', 'resolved'));
    }

    /** «Разобрано» / «Вернуть в новые». */
    public function update(TermReport $termReport)
    {
        $termReport->update(['resolved_at' => $termReport->resolved_at ? null : now()]);

        return back()->with('success', $termReport->resolved_at ? 'Отмечено как разобранное.' : 'Возвращено в новые.');
    }
}
