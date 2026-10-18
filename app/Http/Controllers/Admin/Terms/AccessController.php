<?php

namespace App\Http\Controllers\Admin\Terms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Кому открыт тренажёр терминов сверх автоматического правила (курсы
 * обществознания): дополнительные курсы и отдельные ученики. Само правило
 * доступа — App\Service\Terms\TermAccess.
 */
class AccessController extends Controller
{
    public function index()
    {
        $keyword = mb_strtolower((string) config('terms.subject_keyword'));
        $courses = Course::with('category')->orderBy('title')->get();
        $linkedIds = DB::table('term_trainer_courses')->pluck('course_id')->all();

        $isSubject = fn (Course $c) => $keyword !== ''
            && $c->category
            && str_contains(mb_strtolower($c->category->title), $keyword);

        $grants = DB::table('term_trainer_users')
            ->join('users', 'users.id', '=', 'term_trainer_users.user_id')
            ->leftJoin('users as granter', 'granter.id', '=', 'term_trainer_users.granted_by')
            ->orderByDesc('term_trainer_users.created_at')
            ->get(['users.id', 'users.name', 'users.email', 'granter.name as granted_by', 'term_trainer_users.created_at']);

        return view('admin.terms.access', [
            'keyword' => config('terms.subject_keyword'),
            'autoCourses' => $courses->filter($isSubject)->values(),
            'linkedCourses' => $courses->whereIn('id', $linkedIds)->values(),
            // В выпадающем списке — курсы, которые ещё не дают доступ.
            'otherCourses' => $courses->reject(fn ($c) => $isSubject($c) || in_array($c->id, $linkedIds))->values(),
            'grants' => $grants,
        ]);
    }

    public function storeCourse(Request $request)
    {
        $data = $request->validate(['course_id' => ['required', 'integer', 'exists:courses,id']]);

        DB::table('term_trainer_courses')->insertOrIgnore([
            'course_id' => $data['course_id'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Курс привязан: его ученики с активным доступом видят тренажёр.');
    }

    public function destroyCourse(Course $course)
    {
        DB::table('term_trainer_courses')->where('course_id', $course->id)->delete();

        return back()->with('success', 'Курс отвязан.');
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']], [], ['email' => 'Email']);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return back()->withInput()->withErrors(['email' => 'Ученика с таким email нет.']);
        }

        DB::table('term_trainer_users')->insertOrIgnore([
            'user_id' => $user->id, 'granted_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', "Тренажёр открыт: {$user->name}.");
    }

    public function destroyUser(User $user)
    {
        DB::table('term_trainer_users')->where('user_id', $user->id)->delete();

        return back()->with('success', "Личный доступ закрыт: {$user->name}. Если у ученика есть подходящий курс, тренажёр останется.");
    }
}
