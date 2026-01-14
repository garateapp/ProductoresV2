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
        $since = $now->copy()->startOfDay();

        $logs = NotificationLog::query()
            ->where('context->channel', 'process')
            ->whereBetween('created_at', [$since->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()])
            ->whereNotIn('recipient', ['carlos.alvarez@greenex.cl','+56966291494'])
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Process daily summary skipped: no process notifications found today.');
            Log::info('Process daily summary skipped: no process notifications found today.', [
                'since' => $since->toDateTimeString(),
                'until' => $now->toDateTimeString(),
            ]);
            return self::SUCCESS;
        }

        $processIds = $logs->pluck('context.proceso_id')->filter()->unique()->values();
        $processNumbers = $logs->pluck('context.n_proceso')->filter()->unique()->values();

        $processesById = $processIds->isNotEmpty()
            ? Proceso::query()->whereIn('id', $processIds)->get()->keyBy('id')
            : collect();
        $processesByNumber = $processNumbers->isNotEmpty()
            ? Proceso::query()->whereIn('n_proceso', $processNumbers)->get()->keyBy('n_proceso')
            : collect();

        $rowsByProcess = [];

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
                    'estado' => null,
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
            $row['estado'] = empty($statuses) ? null : implode(', ', $statuses);
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
