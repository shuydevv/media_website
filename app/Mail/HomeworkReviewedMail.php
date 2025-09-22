<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HomeworkReviewedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $studentName,
        public string $assignmentTitle,
        public ?string $mentorName = null,
        public ?string $score = null,
        public ?string $comment = null,
        public ?string $linkToResult = null
    ) {}

    public function build()
    {
        return $this->subject('Домашняя работа проверена')
            ->view('mail.homework_reviewed');
            // или ->markdown('emails.homework_reviewed'); если предпочитаешь Markdown-шаблон
    }
}
