<?php

namespace App\Mail;

use App\Models\Proceso;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProcessReportUploaded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $producer,
        public Proceso $proceso,
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
            ->view('emails.processes.report_uploaded', [
                'producer' => $this->producer,
                'proceso' => $this->proceso,
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
            'Informe de proceso',
            $this->proceso->n_proceso,
        ];

        if (! empty($this->proceso->especie)) {
            $parts[] = $this->proceso->especie;
        }

        if (! empty($this->proceso->variedad)) {
            $parts[] = $this->proceso->variedad;
        }

        return implode(' - ', array_filter($parts));
    }
}
