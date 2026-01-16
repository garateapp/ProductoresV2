<?php

namespace App\Console\Commands;

use App\Mail\ProcessPendingSummary;
use App\Models\Proceso;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendProcessPendingSummary extends Command
{
    protected $signature = 'reports:process-pending-summary';

    protected $description = 'Send a daily summary of processes without a report after a delay threshold.';

    public function handle(): int
    {
        $recipients = array_values(array_diff(
            config('reports.process_pending_recipients', []),
            []
        ));

        if (empty($recipients)) {
            $this->info('Process pending summary skipped: no recipients configured.');
            Log::info('Process pending summary skipped: no recipients configured.');
            return self::SUCCESS;
        }

        $thresholdHours = (int) config('reports.process_pending_threshold_hours', 24);
        $now = Carbon::now('America/Santiago');
        $cutoff = $now->copy()->subHours($thresholdHours);

        $processes = Proceso::query()
            ->where(function ($query) {
                $query->whereNull('informe')
                    ->orWhere('informe', '');
            })
            ->whereNotNull('fecha')
            ->get();

        $rows = [];

        foreach ($processes as $proceso) {
            try {
                $processDate = Carbon::parse($proceso->fecha, 'America/Santiago');
            } catch (\Throwable $e) {
                continue;
            }

            if ($processDate->greaterThan($cutoff)) {
                continue;
            }

            $rows[] = [
                'n_proceso' => $proceso->n_proceso,
                'producer' => $proceso->agricola ?? $proceso->LLP_recepcion ?? null,
                'lote_recepcion' => $proceso->LPP_recepcion ?? $proceso->lote_recepcion ?? null,
                'especie' => $proceso->especie ?? null,
                'variedad' => $proceso->variedad ?? null,
                'fecha' => $processDate->toDateTimeString(),
            ];
        }

        if (empty($rows)) {
            $this->info('Process pending summary skipped: no overdue processes found.');
            Log::info('Process pending summary skipped: no overdue processes found.', [
                'threshold_hours' => $thresholdHours,
                'cutoff' => $cutoff->toDateTimeString(),
            ]);
            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new ProcessPendingSummary($rows, $thresholdHours, $cutoff, $now));

        $this->info('Process pending summary sent: ' . count($rows) . ' item(s).');
        Log::info('Process pending summary sent.', [
            'count' => count($rows),
            'recipients' => $recipients,
        ]);

        return self::SUCCESS;
    }
}
