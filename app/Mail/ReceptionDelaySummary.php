<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReceptionDelaySummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows,
        public int $thresholdHours,
        public int $lookbackHours,
        public Carbon $since,
        public Carbon $now
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject("Recepciones con informe tardio (>{$this->thresholdHours}h)")
            ->view('emails.receptions.delay_summary', [
                'rows' => $this->rows,
                'thresholdHours' => $this->thresholdHours,
                'lookbackHours' => $this->lookbackHours,
                'since' => $this->since,
                'now' => $this->now,
            ]);
    }
}
