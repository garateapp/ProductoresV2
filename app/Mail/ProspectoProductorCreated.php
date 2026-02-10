<?php

namespace App\Mail;

use App\Models\ProspectoProductor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProspectoProductorCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProspectoProductor $prospecto)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Nuevo prospecto de productor')
            ->view('emails.prospectos.productor_created', [
                'prospecto' => $this->prospecto,
            ]);
    }
}
