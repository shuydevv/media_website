<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistController extends Controller
{
    /**
     * Взаимоисключающий выбор одной стадии (не независимые флаги — "связались"
     * и "пробный урок пройден" не могут быть отмечены одновременно), поэтому
     * просто перезаписываем crm_stage целиком, а не переключаем отдельное поле.
     */
    public function __invoke(Request $request, User $user)
    {
        $data = $request->validate([
            'stage' => ['nullable', Rule::in(['contacted', 'trial_done', 'lost'])],
        ]);

        $user->crm_stage = $data['stage'] ?? null;
        $user->save();

        return response()->json([
            'ok' => true,
            'stage' => $user->crm_stage,
            'status' => $user->crmStatusPayload(),
        ]);
    }
}
