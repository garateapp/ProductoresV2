<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProducerWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $producer,
        public string $username,
        public string $defaultPassword,
        public string $portalUrl
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Bienvenido al Portal de Productores Gárate Hermanos')
            ->view('emails.producers.welcome', [
                'producer' => $this->producer,
                'username' => $this->username,
                'defaultPassword' => $this->defaultPassword,
                'portalUrl' => $this->portalUrl,
            ]);
    }
}
