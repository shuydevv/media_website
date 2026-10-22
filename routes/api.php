<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Webhook бота воронки (App\Http\Controllers\Telegram\WebhookController) —
// здесь, а не в web.php: в web-группе каждый апдейт заводил бы строку в
// таблице sessions (SESSION_DRIVER=database). Подлинность — секрет в
// заголовке, не CSRF. Общий лимит api (60/мин с IP) снят: все апдейты идут
// с нескольких IP самого Telegram, и на всплеске трафика из рекламы он
// начал бы отвечать боту 429.
Route::post('/telegram/webhook', \App\Http\Controllers\Telegram\WebhookController::class)
    ->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':api')
    ->name('telegram.webhook');

// Заявки с других моих сайтов (App\Http\Controllers\InboundLeadController):
// шлют из браузера с чужого домена — поэтому здесь, без CSRF; CORS для api/*
// открыт в config/cors.php. Сайт узнаётся по ключу из config/inbound_leads.php.
Route::post('/inbound-lead', [\App\Http\Controllers\InboundLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('inbound-lead.store');
