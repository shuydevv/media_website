<?php

namespace App\Http\Controllers;

use App\Service\Demo\DemoMode;
use App\Service\FishFoodService;
use Illuminate\Http\Request;

/**
 * Скрытая страница управления демо-режимом (/demo): ссылки на неё нигде
 * нет, ведущий показа знает адрес. Открывается только из демо-аккаунта —
 * остальным 404, чтобы о странице не догадывались.
 */
class DemoController extends Controller
{
    public function __construct(private DemoMode $demo)
    {
    }

    public function show(Request $request, FishFoodService $fish)
    {
        $user = $this->demoUser($request);
        $level = $fish->levelFor((int) $user->fish_total_fed);
        $thresholds = config('fish.levels');

        return view('demo.show', [
            'user' => $user,
            'fishLevel' => $level,
            'fishLevelName' => $fish->levelName($level),
            'toNextLevel' => isset($thresholds[$level - 1]) ? max(0, $thresholds[$level - 1] - (int) $user->fish_total_fed) : null,
            'summary' => $this->demo->summary($user),
        ]);
    }

    public function nextDay(Request $request)
    {
        $this->demo->nextDay($this->demoUser($request));

        return redirect()->route('demo.show')->with('status', 'Наступило завтра: карточки, назначенные на завтра, ждут повторения, раунды с кормом снова доступны.');
    }

    public function reset(Request $request)
    {
        $this->demo->reset($this->demoUser($request));

        return redirect()->route('demo.show')->with('status', 'Прогресс сброшен к началу показа.');
    }

    private function demoUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->is_demo, 404);

        return $user;
    }
}
