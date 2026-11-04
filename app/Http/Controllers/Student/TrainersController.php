<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Service\Trainers\TrainerGym;
use App\Service\Trainers\TrainerHub;
use Illuminate\Http\Request;

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
        ]);
    }
}
