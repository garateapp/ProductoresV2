<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;
use Throwable;

class ImportFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $label,
        public string $file,
        public Throwable $exception
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $message = $this->exception instanceof ValidationException
            ? implode(' | ', Arr::flatten($this->exception->errors()))
            : $this->exception->getMessage();

        return [
            'type' => $this->type,
            'label' => $this->label,
            'file' => $this->file,
            'message' => $message,
        ];
    }
}
