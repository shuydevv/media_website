<?php

namespace App\Mail\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $loginUrl, public int $daysValid)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Доступ к личному кабинету',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user.invite',
            with: ['loginUrl' => $this->loginUrl, 'daysValid' => $this->daysValid],
        );
    }
}
