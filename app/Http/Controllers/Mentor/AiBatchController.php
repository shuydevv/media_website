<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\Homework\ClaudeBatchGrader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Кнопка «Проверить разом» на странице очереди (mentor/submissions). */
class AiBatchController extends Controller
{
    public function store(Request $request, ClaudeBatchGrader $batches)
    {
        $u = $request->user();
        abort_unless(
            $u && in_array((int) $u->role, [User::ROLE_MENTOR, User::ROLE_ADMIN], true),
            403,
            'Доступ только для куратора или администратора.'
        );

        try {
            $record = $batches->submit($u->id);
        } catch (\Throwable $e) {
            Log::error('ClaudeBatchGrader submit failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Не удалось отправить задания на ИИ-проверку: ' . $e->getMessage());
        }

        if (!$record) {
            return back()->with('success', 'Нет новых письменных заданий для ИИ-проверки.');
        }

        return back()->with('success', "Отправлено на ИИ-проверку: {$record->request_count} зад. "
            . 'Черновики появятся в работах по мере готовности — обычно в течение часа.');
    }
}
