<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmEvent;
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
            'lost_reason' => ['nullable', Rule::in(array_keys(User::CRM_LOST_REASONS))],
            'lost_comment' => ['nullable', 'string', 'max:300'],
        ]);

        $before = $user->crmStatus()['key'];
        $user->crm_stage = $data['stage'] ?? null;
        // Причина живёт только вместе с отказом: вернули в работу — стёрли.
        $user->crm_lost_reason = $user->crm_stage === 'lost' ? ($data['lost_reason'] ?? 'other') : null;
        $user->save();

        $after = $user->crmStatus();
        if ($after['key'] === 'lost') {
            $comment = trim((string) ($data['lost_comment'] ?? ''));
            CrmEvent::log($user, 'lost', 'Отказался: '.User::CRM_LOST_REASONS[$user->crm_lost_reason].($comment !== '' ? ' — '.$comment : ''));
        } elseif ($after['key'] !== $before) {
            CrmEvent::log($user, 'stage', 'Статус: '.$after['label']);
        }

        return response()->json([
            'ok' => true,
            'stage' => $user->crm_stage,
            'status' => $user->crmStatusPayload(),
        ]);
    }
}
