<?php

namespace App\Mail;

use App\Models\Recepcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceptionReportPreview extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Recepcion $recepcion,
        public ?string $previewUrl,
        public ?string $attachmentPath,
        public string $filename
    ) {
    }

    public function build(): self
    {
        $mail = $this
            ->subject($this->generateSubject())
            ->view('emails.receptions.report_preview', [
                'recepcion' => $this->recepcion,
                'previewUrl' => $this->previewUrl,
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
            'Previsualización informe de recepción',
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

