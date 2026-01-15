<?php

namespace App\Console\Commands;

use App\Mail\ReceptionDailySummary;
use App\Models\NotificationLog;
use App\Models\Recepcion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReceptionDailySummary extends Command
{
    protected $signature = 'reports:reception-daily-summary';

    protected $description = 'Send a daily summary of reception reports sent.';

    public function handle(): int
    {
        $recipients = array_values(array_diff(
            config('reports.reception_daily_recipients', []),

        ));
        if (empty($recipients)) {
            $this->info('Reception daily summary skipped: no recipients configured.');
            Log::info('Reception daily summary skipped: no recipients configured.');
            return self::SUCCESS;
        }

        $now = Carbon::now('America/Santiago');
        $since = $now->copy()->subDay()->startOfDay();
        $until = $now->copy()->endOfDay();

        $receptions = Recepcion::query()
            ->whereDate('fecha_g_recepcion', '>=', $since->toDateString())
            ->whereDate('fecha_g_recepcion', '<=', $until->toDateString())
            ->orderBy('fecha_g_recepcion')
            ->get();

        if ($receptions->isEmpty()) {
            $this->info('Reception daily summary skipped: no receptions found for the date range.');
            Log::info('Reception daily summary skipped: no receptions found for the date range.', [
                'since' => $since->toDateTimeString(),
                'until' => $until->toDateTimeString(),
            ]);
            return self::SUCCESS;
        }

        $receptionIds = $receptions->pluck('id')->filter()->unique()->values();
        $receptionNumbers = $receptions->pluck('numero_g_recepcion')->filter()->unique()->values();

        $logsQuery = NotificationLog::query()
            ->where('context->channel', 'recepcion')
            ->whereNotIn('recipient', ['carlos.alvarez@greenex.cl', '+56966291494'])
            ->orderBy('created_at');

        $logsQuery->when($receptionIds->isNotEmpty() || $receptionNumbers->isNotEmpty(), function ($query) use ($receptionIds, $receptionNumbers) {
            $query->where(function ($sub) use ($receptionIds, $receptionNumbers) {
                if ($receptionIds->isNotEmpty()) {
                    $sub->whereIn('context->recepcion_id', $receptionIds);
                }
                if ($receptionNumbers->isNotEmpty()) {
                    $sub->orWhereIn('context->numero_g_recepcion', $receptionNumbers);
                }
            });
        });

        $logs = $logsQuery->get();

        $receptionsById = $receptions->keyBy('id');
        $receptionsByNumber = $receptions->keyBy('numero_g_recepcion');

        $rowsByReception = [];

        foreach ($receptions as $recepcion) {
            $rowsByReception['id:' . $recepcion->id] = [
                'numero_g_recepcion' => $recepcion->numero_g_recepcion,
                'fecha' => $recepcion->fecha_g_recepcion,
                'csg' => $recepcion->csg_productor_rotulado ?? $recepcion->Codigo_Sag_emisor ?? null,
                'producer' => $recepcion->n_emisor
                    ?? $recepcion->n_productor_rotulado
                    ?? null,
                'tipo' => $recepcion->tipo_g_recepcion ?? null,
                'estado' => 'sin envio',
                'types' => [],
                'statuses' => [],
            ];
        }

        foreach ($logs as $log) {
            $context = $log->context ?? [];
            $receptionId = $context['recepcion_id'] ?? null;
            $receptionNumber = $context['numero_g_recepcion'] ?? null;

            $recepcion = null;
            if ($receptionId && $receptionsById->has($receptionId)) {
                $recepcion = $receptionsById[$receptionId];
            } elseif ($receptionNumber && $receptionsByNumber->has($receptionNumber)) {
                $recepcion = $receptionsByNumber[$receptionNumber];
            }

            $rowKey = $recepcion
                ? 'id:' . $recepcion->id
                : ($receptionNumber ? 'n:' . $receptionNumber : 'log:' . $log->id);

            if (! isset($rowsByReception[$rowKey])) {
                $rowsByReception[$rowKey] = [
                    'numero_g_recepcion' => $recepcion->numero_g_recepcion ?? $receptionNumber,
                    'fecha' => $recepcion->fecha_g_recepcion ?? null,
                    'csg' => $recepcion->csg_productor_rotulado ?? $recepcion->Codigo_Sag_emisor ?? null,
                    'producer' => $context['producer_name']
                        ?? $recepcion->n_emisor
                        ?? $recepcion->n_productor_rotulado
                        ?? null,
                    'tipo' => $recepcion->tipo_g_recepcion ?? null,
                    'estado' => 'sin envio',
                    'types' => [],
                    'statuses' => [],
                ];
            }

            $rowsByReception[$rowKey]['types'][] = $log->type ?: null;
            $rowsByReception[$rowKey]['statuses'][] = $log->status ?: null;
        }

        $rows = array_values(array_map(function (array $row): array {
            $types = array_values(array_filter(array_unique($row['types'])));
            $statuses = array_values(array_filter(array_unique($row['statuses'])));
            $row['type'] = empty($types) ? null : implode(', ', $types);
            $row['estado'] = empty($statuses) ? $row['estado'] : implode(', ', $statuses);
            unset($row['types']);
            unset($row['statuses']);

            return $row;
        }, $rowsByReception));

        Mail::to($recipients)->send(new ReceptionDailySummary($rows, $since, $now));

        $this->info('Reception daily summary sent: ' . count($rows) . ' item(s).');
        Log::info('Reception daily summary sent.', [
            'count' => count($rows),
            'recipients' => $recipients,
        ]);

        return self::SUCCESS;
    }
}
