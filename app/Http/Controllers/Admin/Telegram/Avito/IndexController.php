<?php

namespace App\Http\Controllers\Admin\Telegram\Avito;

use App\Http\Controllers\Controller;
use App\Models\AvitoAccount;

class IndexController extends Controller
{
    public function __invoke()
    {
        return view('admin.telegram.avito.index', [
            'accounts' => AvitoAccount::orderBy('id')->get(),
            'webhookReady' => filled(config('services.avito.webhook_secret')),
        ]);
    }
}
