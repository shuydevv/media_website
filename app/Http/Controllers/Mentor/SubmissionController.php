<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $subs = Submission::with(['user','homework'])->latest()->paginate(20);
        return view('mentor.submissions.index', ['submissions' => $subs]);
    }

    public function show(Submission $submission)
    {
        $this->authorize('view', $submission);
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
