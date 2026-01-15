<?php

namespace App\Console\Commands;

use App\Mail\ProcessDailySummary;
use App\Models\NotificationLog;
use App\Models\Proceso;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendProcessDailySummary extends Command
{
    protected $signature = 'reports:process-daily-summary';

    protected $description = 'Send a daily summary of process reports sent.';

    public function handle(): int
    {
        $recipients = config('reports.process_daily_recipients', []);
        if (empty($recipients)) {
            $this->info('Process daily summary skipped: no recipients configured.');
            Log::info('Process daily summary skipped: no recipients configured.');
            return self::SUCCESS;
        }

        $now = Carbon::now('America/Santiago');
        $since = $now->copy()->subDay()->startOfDay();
        $until = $now->copy()->endOfDay();

        $processes = Proceso::query()
            ->whereDate('fecha', '>=', $since->toDateString())
            ->whereDate('fecha', '<=', $until->toDateString())
            ->orderBy('fecha')
            ->get();

        if ($processes->isEmpty()) {
            $this->info('Process daily summary skipped: no processes found for the date range.');
            Log::info('Process daily summary skipped: no processes found for the date range.', [
                'since' => $since->toDateTimeString(),
                'until' => $until->toDateTimeString(),
            ]);
            return self::SUCCESS;
        }

        $processIds = $processes->pluck('id')->filter()->unique()->values();
        $processNumbers = $processes->pluck('n_proceso')->filter()->unique()->values();

        $logsQuery = NotificationLog::query()
            ->where('context->channel', 'process')
            ->whereNotIn('recipient', ['carlos.alvarez@greenex.cl', '+56966291494'])
            ->orderBy('created_at');

        $logsQuery->when($processIds->isNotEmpty() || $processNumbers->isNotEmpty(), function ($query) use ($processIds, $processNumbers) {
            $query->where(function ($sub) use ($processIds, $processNumbers) {
                if ($processIds->isNotEmpty()) {
                    $sub->whereIn('context->proceso_id', $processIds);
                }
                if ($processNumbers->isNotEmpty()) {
                    $sub->orWhereIn('context->n_proceso', $processNumbers);
                }
            });
        });

        $logs = $logsQuery->get();

        $processesById = $processes->keyBy('id');
        $processesByNumber = $processes->keyBy('n_proceso');

        $rowsByProcess = [];

        foreach ($processes as $proceso) {
            $rowsByProcess['id:' . $proceso->id] = [
                'n_proceso' => $proceso->n_proceso,
                'csg' => $proceso->c_productor,
                'producer' => $proceso->agricola
                    ?? $proceso->LLP_recepcion
                    ?? $proceso->LPP_recepcion
                    ?? null,
                'estado' => 'sin envio',
                'types' => [],
                'statuses' => [],
            ];
        }

        foreach ($logs as $log) {
            $context = $log->context ?? [];
            $processId = $context['proceso_id'] ?? null;
            $processNumber = $context['n_proceso'] ?? null;

            $proceso = null;
            if ($processId && $processesById->has($processId)) {
                $proceso = $processesById[$processId];
            } elseif ($processNumber && $processesByNumber->has($processNumber)) {
                $proceso = $processesByNumber[$processNumber];
            }

            $rowKey = $proceso
                ? 'id:' . $proceso->id
                : ($processNumber ? 'n:' . $processNumber : 'log:' . $log->id);

            if (! isset($rowsByProcess[$rowKey])) {
                $rowsByProcess[$rowKey] = [
                    'n_proceso' => $proceso->n_proceso ?? $processNumber,
                    'csg' => $proceso->c_productor ?? ($context['c_productor'] ?? null),
                    'producer' => $context['producer_name']
                        ?? $proceso->agricola
                        ?? $proceso->LLP_recepcion
                        ?? $proceso->LPP_recepcion
                        ?? null,
                    'estado' => 'sin envio',
                    'types' => [],
                    'statuses' => [],
                ];
            }

            $rowsByProcess[$rowKey]['types'][] = $log->type ?: null;
            $rowsByProcess[$rowKey]['statuses'][] = $log->status ?: null;
        }

        $rows = array_values(array_map(function (array $row): array {
            $types = array_values(array_filter(array_unique($row['types'])));
            $statuses = array_values(array_filter(array_unique($row['statuses'])));
            $row['type'] = empty($types) ? null : implode(', ', $types);
            $row['estado'] = empty($statuses) ? $row['estado'] : implode(', ', $statuses);
            unset($row['types']);
            unset($row['statuses']);

            return $row;
        }, $rowsByProcess));

        Mail::to($recipients)->send(new ProcessDailySummary($rows, $since, $now));

        $this->info('Process daily summary sent: ' . count($rows) . ' item(s).');
        Log::info('Process daily summary sent.', [
            'count' => count($rows),
            'recipients' => $recipients,
        ]);

        return self::SUCCESS;
    }
}
