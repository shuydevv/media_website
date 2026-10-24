<?php

namespace App\Http\Controllers\Admin\Telegram\Texts;

use App\Http\Controllers\Controller;
use App\Service\Telegram\FunnelTexts;

class EditController extends Controller
{
    public function __invoke()
    {
        // Тексты двух ботов на одной странице — группами.
        return view('admin.telegram.texts', [
            'groups' => [
                'Бот пробного урока' => config('telegram_funnel.texts'),
                'Бот диагностики' => config('telegram_diag.texts') ?? [],
            ],
            'texts' => FunnelTexts::all(),
        ]);
    }
}
