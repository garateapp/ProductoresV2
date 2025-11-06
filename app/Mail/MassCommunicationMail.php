<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MassCommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $serviceName,
        public string $subjectLine,
        public string $messageBody,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
        public ?string $attachmentMime = null,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mass-communication',
            with: [
                'serviceName' => $this->serviceName,
                'messageBody' => $this->messageBody,
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->attachmentPath) {
            return [];
        }

        return [
            \Illuminate\Mail\Mailables\Attachment::fromPath(Storage::path($this->attachmentPath))
                ->as($this->attachmentName ?? basename($this->attachmentPath))
                ->withMime($this->attachmentMime),
        ];
    }
}
