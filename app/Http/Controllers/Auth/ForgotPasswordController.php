<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    public function __construct()
    {
        // ограничим частоту отправки: не чаще 5 раз в минуту
        $this->middleware('throttle:5,1')->only('sendResetLinkEmail');
    }
}

