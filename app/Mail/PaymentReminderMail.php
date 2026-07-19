<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public string $courseTitle,
        public CarbonInterface $dueAt,
    ) {
    }

    public function build()
    {
        return $this->subject('Скоро оплата за курс «' . $this->courseTitle . '»')
            ->view('mail.payment_reminder');
    }
}
