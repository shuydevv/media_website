<?php

namespace App\Service\Sms;

use Illuminate\Support\Facades\Log;

class FakeSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("[SMS FAKE] {$phone}: {$message}");
    }
}
