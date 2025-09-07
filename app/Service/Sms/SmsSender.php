<?php
namespace App\Service\Sms;

interface SmsSender {
    public function send(string $phone, string $message): void;
}
