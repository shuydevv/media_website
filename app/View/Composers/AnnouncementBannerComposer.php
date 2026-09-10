<?php

namespace App\View\Composers;

use App\Models\Announcement;
use Illuminate\View\View;

class AnnouncementBannerComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (!$user || !$user->isStudent()) {
            $view->with(['activeAnnouncements' => collect()]);
            return;
        }

        $courseIds = $user->courses()->pluck('courses.id');

        $announcements = Announcement::active()
            ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $user->id))
            ->where(function ($q) use ($courseIds) {
                $q->where('all_students', true)
                    ->orWhereHas('courses', fn ($q2) => $q2->whereIn('courses.id', $courseIds));
            })
            ->latest()
            ->get();

        $view->with(['activeAnnouncements' => $announcements]);
    }
}
