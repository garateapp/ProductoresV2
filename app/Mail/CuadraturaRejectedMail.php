<?php

namespace App\Mail;

use App\Models\Proceso;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class CuadraturaRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Proceso $proceso,
        public string $chiefName,
        public string $comment,
        public string $reviewUrl,
        public ?string $reportUrl
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject("Cuadratura rechazada: proceso {$this->proceso->n_proceso}")
            ->withSymfonyMessage(function (Email $message): void {
                // Evita reescritura de links por tracking (Mailgun)
                $message->getHeaders()->addTextHeader('X-Mailgun-Track', 'no');
                $message->getHeaders()->addTextHeader('X-Mailgun-Track-Clicks', 'no');
                $message->getHeaders()->addTextHeader('X-Mailgun-Track-Opens', 'no');
            })
            ->view('emails.cuadratura.rejected', [
                'proceso' => $this->proceso,
                'chiefName' => $this->chiefName,
                'comment' => $this->comment,
                'reviewUrl' => $this->reviewUrl,
                'reportUrl' => $this->reportUrl,
            ]);
    }
}
