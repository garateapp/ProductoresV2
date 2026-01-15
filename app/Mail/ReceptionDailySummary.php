<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceptionDailySummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows,
        public Carbon $since,
        public Carbon $now
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Resumen de recepciones enviadas')
            ->view('emails.receptions.daily_summary', [
                'rows' => $this->rows,
                'since' => $this->since,
                'now' => $this->now,
            ]);
    }
}
