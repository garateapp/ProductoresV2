<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProcessPendingSummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows,
        public int $thresholdHours,
        public Carbon $cutoff,
        public Carbon $now
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject("Procesos sin informe (>{$this->thresholdHours}h)")
            ->view('emails.processes.pending_summary', [
                'rows' => $this->rows,
                'thresholdHours' => $this->thresholdHours,
                'cutoff' => $this->cutoff,
                'now' => $this->now,
            ]);
    }
}
