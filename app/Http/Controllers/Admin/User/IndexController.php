<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\LessonView;
use App\Models\User;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    /**
     * Список пользователей как рабочий список преподавателя: по каждому
     * ученику видно, давно ли он заходил и давно ли ему давали обратную
     * связь, — чтобы выбрать, кому записывать голосовое, не открывая
     * каждого (подробности — на странице ученика, см. ShowController).
     *
     * Цифры считаются подзапросами прямо в выборке (withMax/withCount), а
     * не через StudentReport: тот делает десятки запросов на одного
     * ученика, на странице из 20 строк это были бы сотни.
     */
    public const SORTS = [
        'feedback' => 'Давно без обратной связи',
        'inactive' => 'Давно не заходил',
        'new' => 'Сначала новые',
    ];

    public function __invoke(Request $request)
    {
        $q = trim($request->string('q')->toString());

        // "Ученики" — те, кто записан хотя бы на один активный курс; "Все" —
        // включая кураторов, админов и зарегистрировавшихся без курса.
        $scope = $request->query('scope') === 'all' ? 'all' : 'students';
        $sort = array_key_exists($request->query('sort'), self::SORTS)
            ? $request->query('sort')
            : ($scope === 'students' ? 'feedback' : 'new');

        $users = User::query()
            ->when($scope === 'students', function ($query) {
                $query->where('role', User::ROLE_READER)
                    ->whereHas('courses', fn ($c) => $c->where('course_user.status', 'active'));
            })
            ->when($q, function ($query) use ($q) {
                $query->where(function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
            ->with(['courses' => fn ($c) => $c->wherePivot('status', 'active')])
            ->withMax('feedback', 'created_at')
            ->withMax('activityDays', 'last_seen_at')
            ->withCount([
                'activityDays as active_days_14' => fn ($d) => $d->where('date', '>=', now()->subDays(13)->toDateString()),
                'submissions as submitted_30' => fn ($s) => $s->where('status', '!=', 'in_progress')
                    ->where('created_at', '>=', now()->subDays(30)),
                'lessonViews as lessons_14' => fn ($v) => $v->whereIn('kind', LessonView::VIDEO_KINDS)
                    ->where('last_watched_at', '>=', now()->subDays(14)),
            ])
            // Сортировки "давно без…": сначала те, у кого этого не было
            // вовсе (NULL), затем от самых старых дат к свежим.
            ->when($sort === 'feedback', fn ($query) => $query
                ->orderByRaw('feedback_max_created_at is not null')
                ->orderBy('feedback_max_created_at'))
            ->when($sort === 'inactive', fn ($query) => $query
                ->orderByRaw('activity_days_max_last_seen_at is not null')
                ->orderBy('activity_days_max_last_seen_at'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'scope' => $scope,
            'sort' => $sort,
            'sorts' => self::SORTS,
        ]);
    }
}
