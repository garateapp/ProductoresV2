<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CuadraturaApprovalBatchMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(
        public array $items,
        public string $senderName
    ) {
    }

    public function build(): self
    {
        $count = count($this->items);

        return $this
            ->subject("Cuadratura: {$count} proceso(s) para aprobación")
            ->withSymfonyMessage(function (Email $message): void {
                // Evita reescritura de links por tracking (Mailgun)
                $message->getHeaders()->addTextHeader('X-Mailgun-Track', 'no');
                $message->getHeaders()->addTextHeader('X-Mailgun-Track-Clicks', 'no');
                $message->getHeaders()->addTextHeader('X-Mailgun-Track-Opens', 'no');
            })
            ->view('emails.cuadratura.approval_batch', [
                'items' => $this->items,
                'senderName' => $this->senderName,
            ]);
    }
}
