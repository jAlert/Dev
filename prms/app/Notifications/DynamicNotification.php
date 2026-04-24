<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DynamicNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $message,
        public ?int $recordId = null,
        public ?string $moduleSlug = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
