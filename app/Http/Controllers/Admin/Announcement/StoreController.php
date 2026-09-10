<?php

namespace App\Http\Controllers\Admin\Announcement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Announcement\StoreRequest;
use App\Models\Announcement;

class StoreController extends Controller
{
    public function __invoke(StoreRequest $request)
    {
        $data = $request->validated();
        $allStudents = (bool) ($data['all_students'] ?? false);
        $courseIds = $data['course_ids'] ?? [];

        $announcement = Announcement::create([
            'message' => $data['message'],
            'all_students' => $allStudents,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by_user_id' => auth()->id(),
        ]);

        if (!$allStudents) {
            $announcement->courses()->sync($courseIds);
        }

        return redirect()->route('admin.announcements.index');
    }
}
