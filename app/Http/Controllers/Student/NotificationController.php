<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->paginate(20);

        return view('student.notifications.index', compact('notifications'));
    }

    public function markRead(DatabaseNotification $notification)
    {
        abort_unless(
            $notification->notifiable_type === User::class && $notification->notifiable_id === auth()->id(),
            403
        );

        $notification->markAsRead();

        $actionUrl = $notification->data['action_url'] ?? null;

        return $actionUrl ? redirect($actionUrl) : back();
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function destroy(DatabaseNotification $notification)
    {
        abort_unless(
            $notification->notifiable_type === User::class && $notification->notifiable_id === auth()->id(),
            403
        );

        $notification->delete();

        if (!request()->header('HX-Request')) {
            return back();
        }

        // Ответ — только OOB-фрагмент бейджа счётчика: htmx извлечёт его для
        // #notif-badge-wrapper и подставит оставшуюся (пустую) часть ответа
        // в hx-target самого удалённого пункта — тем самым он и исчезает из списка.
        return response()->view('partials.student-notification-badge', [
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'oob' => true,
        ]);
    }
}
