<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DynamicNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $message,
        public ?int $recordId = null,
        public ?string $moduleSlug = null,
        public ?string $subject = null,
        public bool $sendEmail = false,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->sendEmail && !empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject ?? 'PRMS Notification')
            ->line($this->message);

        if ($this->recordId && $this->moduleSlug) {
            $mail->action('View Record', url("/app/{$this->moduleSlug}/{$this->recordId}"));
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message'     => $this->message,
            'record_id'   => $this->recordId,
            'module_slug' => $this->moduleSlug,
        ];
    }
}
