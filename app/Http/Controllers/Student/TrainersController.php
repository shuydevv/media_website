<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Service\Trainers\DailyPlanService;
use App\Service\Trainers\TrainerGym;
use App\Service\Trainers\TrainerHub;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «Качалка»: залы историков и обществоведов со снарядами —
 * открытыми ученику тренажёрами и теми, что ещё в разработке. Залы и
 * расстановка — config/trainers.php, сборка — TrainerGym. Кнопка
 * «Тренажёры» у рыбы ведёт сюда.
 */
class TrainersController extends Controller
{
    public function index(Request $request, TrainerHub $hub, TrainerGym $gym)
    {
        $user = $request->user();
        $gym = $gym->build($hub->forUser($user), $request->query('hall'));

        return view('student.trainers.index', $gym + [
            'cormBalance' => (int) $user->fish_corm_balance,
            'norms' => config('daily_plan.norms'),
            'norm' => $user->trainer_daily_minutes,
        ]);
    }

    /** Норма тренажёров на день («План на сегодня»), выбирает сам ученик. */
    public function norm(Request $request, DailyPlanService $plans)
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', Rule::in(config('daily_plan.norms'))],
            'hall' => ['nullable', 'string', 'max:30'],
        ]);

        $plans->setNorm($request->user(), (int) $data['minutes']);

        return redirect()->route('student.trainers.index', array_filter(['hall' => $data['hall'] ?? null]));
    }
}
