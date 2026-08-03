<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlanningSagLabelMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $label
     */
    public function __construct(public array $label)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Etiqueta SAG proceso '.$this->label['process_number'])
            ->view('emails.planning.sag_label', [
                'label' => $this->label,
            ]);
    }
}
