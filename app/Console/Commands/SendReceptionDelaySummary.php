<?php

namespace App\Console\Commands;

use App\Mail\ReceptionDelaySummary;
use App\Models\NotificationLog;
use App\Models\Recepcion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReceptionDelaySummary extends Command
{
    protected $signature = 'reports:reception-delay-summary';

    protected $description = 'Send a daily summary of reception reports sent after a delay threshold.';

    public function handle(): int
    {
        $recipients = config('reports.reception_delay_recipients', []);
        if (empty($recipients)) {
            $this->info('Reception delay summary skipped: no recipients configured.');
            Log::info('Reception delay summary skipped: no recipients configured.');
            return self::SUCCESS;
        }

        $thresholdHours = (int) config('reports.reception_delay_threshold_hours', 12);
        $lookbackHours = (int) config('reports.reception_delay_lookback_hours', 24);
        $now = Carbon::now();
        $since = $now->copy()->subHours($lookbackHours);

        $recepciones = Recepcion::query()
            ->whereNotNull('fecha_g_recepcion')
            ->where('fecha_g_recepcion', '>=', $since->toDateTimeString())
            ->get();

        $rows = [];

        foreach ($recepciones as $recepcion) {
            try {
                $receivedAt = Carbon::parse($recepcion->fecha_g_recepcion);
            } catch (\Throwable $e) {
                continue;
            }

            $log = NotificationLog::query()
                ->where('status', 'success')
                ->where('context->channel', 'recepcion')
                ->where(function ($q) use ($recepcion) {
                    $q->where('context->recepcion_id', $recepcion->id)
                        ->orWhere('context->numero_g_recepcion', $recepcion->numero_g_recepcion);
                })
                ->orderBy('created_at')
                ->first();

            if (! $log || ! $log->created_at || $log->created_at->lessThan($receivedAt)) {
                continue;
            }

            $delayHours = $receivedAt->diffInHours($log->created_at);

            if ($delayHours < $thresholdHours) {
                continue;
            }

            $rows[] = [
                'recepcion_id' => $recepcion->id,
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
                'producer' => $recepcion->n_emisor,
                'received_at' => $receivedAt->toDateTimeString(),
                'sent_at' => $log->created_at->toDateTimeString(),
                'delay_hours' => $delayHours,
                'notification_type' => $log->type,
                'recipient' => $log->recipient,
            ];
        }

        if (empty($rows)) {
            $this->info('Reception delay summary skipped: no delayed receptions found.');
            Log::info('Reception delay summary skipped: no delayed receptions found.', [
                'lookback_hours' => $lookbackHours,
                'threshold_hours' => $thresholdHours,
            ]);
            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new ReceptionDelaySummary(
            $rows,
            $thresholdHours,
            $lookbackHours,
            $since,
            $now
        ));

        $this->info('Reception delay summary sent: ' . count($rows) . ' item(s).');
        Log::info('Reception delay summary sent.', [
            'count' => count($rows),
            'recipients' => $recipients,
        ]);

        return self::SUCCESS;
    }
}
