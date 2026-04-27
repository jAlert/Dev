<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StageNotificationMail extends Mailable
{
    public function __construct(
        public string $body,
        public string $mailSubject = 'PRMS Notification',
        public ?string $recordUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.stage-notification');
    }
}
