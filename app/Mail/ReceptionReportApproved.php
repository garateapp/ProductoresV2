<?php

namespace App\Mail;

use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceptionReportApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $producer,
        public Recepcion $recepcion,
        public ?string $reportUrl,
        public ?string $attachmentPath,
        public string $filename,
        public ?string $formattedDate = null
    ) {
    }

    public function build(): self
    {
        $mail = $this
            ->subject($this->generateSubject())
            ->view('emails.receptions.report_approved', [
                'producer' => $this->producer,
                'recepcion' => $this->recepcion,
                'reportUrl' => $this->reportUrl,
                'formattedDate' => $this->formattedDate,
            ]);

        if ($this->attachmentPath && is_file($this->attachmentPath)) {
            $mail->attach($this->attachmentPath, [
                'as' => $this->filename,
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }

    protected function generateSubject(): string
    {
        $parts = [
            'Informe de recepcion',
            $this->recepcion->numero_g_recepcion,
        ];

        if (! empty($this->recepcion->n_especie)) {
            $parts[] = $this->recepcion->n_especie;
        }

        if (! empty($this->recepcion->n_variedad)) {
            $parts[] = $this->recepcion->n_variedad;
        }

        return implode(' - ', array_filter($parts));
    }
}
