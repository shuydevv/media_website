<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Один общий Mailable для всех типов уведомлений, у которых нет
 * собственного класса письма (см. app/Notifications/*) — subject+view+данные,
 * без специфичной логики, поэтому не заводим по классу на тип.
 */
class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subject_,
        public string $view_,
        public array $data_,
    ) {
    }

    public function build()
    {
        return $this->subject($this->subject_)->view($this->view_)->with($this->data_);
    }
}
