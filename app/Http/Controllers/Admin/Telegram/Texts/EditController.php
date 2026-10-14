<?php

namespace App\Http\Controllers\Admin\Telegram\Texts;

use App\Http\Controllers\Controller;
use App\Service\Telegram\FunnelTexts;

class EditController extends Controller
{
    public function __invoke()
    {
        return view('admin.telegram.texts', [
            'definitions' => config('telegram_funnel.texts'),
            'texts' => FunnelTexts::all(),
        ]);
    }
}
