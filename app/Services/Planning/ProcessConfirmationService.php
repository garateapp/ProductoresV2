<?php

namespace App\Services\Planning;

use App\Enums\PlanningLotStatus;
use App\Enums\PlanningProcessStatus;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\PackingProcessLineOverride;
use App\Models\Recepcion;
use App\Models\Reservation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessConfirmationService
{
    public function __construct(
        private readonly InventoryRepositorySqlsrv $inventoryRepository,
        private readonly ProcessGeneratorService $generator,
    ) {
    }

    /**
     * Confirma proceso:
     * - Revalida existencia en SQLSRV
     * - Evita sobre-reserva por `n_g_recepcion` (permite saldo en otros turnos/días)
     * - Marca conflictos en lotes y proceso si aplica
     */
    public function confirm(PackingProcess $process): array
    {
        $process->loadMissing(['lots']);

        $lotGroups = $process->lots
            ->filter(fn ($l) => $this->lotHasPlanningKey($l))
            ->groupBy(fn ($l) => $this->lotPlanKey($l));

        $numbers = $process->lots
            ->pluck('n_g_recepcion')
            ->filter()
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values();

        // Exportadora (snapshot a nivel de proceso):
        // - Si es 1 lote: usamos Recepción.exportadora.
        // - Si son varios lotes: VARIAS (si hay más de una exportadora) o la única encontrada.
        $exportadora = null;
        if ($numbers->count() === 1) {
            $exportadora = Recepcion::query()
                ->where('numero_g_recepcion', (string) $numbers->first())
                ->value('exportadora');
            $exportadora = is_string($exportadora) && trim($exportadora) !== '' ? trim($exportadora) : null;
        } elseif ($numbers->count() > 1) {
            $vals = Recepcion::query()
                ->whereIn('numero_g_recepcion', $numbers->all())
                ->pluck('exportadora')
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim((string) $v))
                ->unique()
                ->values();
            if ($vals->count() === 1) {
                $exportadora = $vals->first();
            } elseif ($vals->count() > 1) {
                $exportadora = 'VARIAS';
            }
        }

        $normalKeys = $lotGroups->keys()
            ->filter(fn ($k) => str_starts_with((string) $k, 'recepcion|'))
            ->map(fn ($k) => Str::after((string) $k, 'recepcion|'))
            ->filter(fn ($k) => $k !== '')
            ->values()
            ->all();

        $repackKeys = $lotGroups->keys()
            ->filter(fn ($k) => str_starts_with((string) $k, 'reembalaje|'))
            ->map(fn ($k) => Str::after((string) $k, 'reembalaje|'))
            ->filter(fn ($k) => $k !== '')
            ->values()
            ->all();

        $inventoryNormal = empty($normalKeys)
            ? collect()
            : $this->inventoryRepository->getAvailableLots([
                'especie' => $process->especie,
                'limit' => 1500,
            ])->keyBy('n_g_recepcion');

        $inventoryRepack = empty($repackKeys)
            ? collect()
            : $this->inventoryRepository->getRepackAvailableLots([
                'especie' => $process->especie,
                'source_keys' => $repackKeys,
                'limit' => max(300, count($repackKeys) * 2),
            ])->keyBy('source_key');

        $conflicts = [];

        $hasReservationBins = Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'reserved_bins');
        $hasReservationSourceType = Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'source_type');
        $hasReservationSourceKey = Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'source_key');

        DB::transaction(function () use (
            $process,
            $inventoryNormal,
            $inventoryRepack,
            $lotGroups,
            $exportadora,
            $hasReservationBins,
            $hasReservationSourceType,
            $hasReservationSourceKey,
            &$conflicts
        ) {
            foreach ($lotGroups as $groupKey => $groupLots) {
                $first = $groupLots->first();
                if (! $first) {
                    continue;
                }

                $sourceType = $this->lotSourceType($first);
                $sourceKey = $this->lotSourceKey($first);
                $referenceNg = trim((string) ($first->n_g_recepcion ?? ''));

                $plannedBins = (int) $groupLots->sum(fn ($lot) => (int) ($lot->cantidad_bins ?? 0));
                if ($plannedBins <= 0) {
                    $plannedBins = max(1, (int) $groupLots->count());
                }

                if ($sourceType === 'reembalaje') {
                    $invRow = $inventoryRepack->get($sourceKey);
                    $availableBins = $invRow ? (int) ($invRow['cantidad_bins'] ?? 0) : 0;
                } else {
                    $invRow = $inventoryNormal->get($sourceKey);
                    $availableBins = $invRow ? (int) ($invRow['cantidad_bins'] ?? 0) : 0;
                }

                // Regla nueva: si el lote tiene saldo, puede planificarse en otro turno/día.
                // Conflicto SOLO si la planificación sobrepasa las existencias disponibles.
                $reservedOtherBins = 0;
                if ($hasReservationBins) {
                    $reservedQuery = Reservation::query()
                        ->where('process_id', '!=', $process->id)
                        ->where('estado', 'ACTIVA');

                    if ($hasReservationSourceType && $hasReservationSourceKey) {
                        $reservedQuery
                            ->where('source_type', $sourceType)
                            ->where('source_key', $sourceKey);
                    } else {
                        $reservedQuery->where('n_g_recepcion', $sourceKey);
                    }

                    $reservedOtherBins = (int) $reservedQuery->sum('reserved_bins');
                }

                $remainingBins = max(0, $availableBins - $reservedOtherBins);

                if ($remainingBins < $plannedBins) {
                    $conflicts[] = [
                        'source_type' => $sourceType,
                        'source_key' => $sourceKey,
                        'n_g_recepcion' => $referenceNg !== '' ? $referenceNg : $sourceKey,
                        'planned_bins' => $plannedBins,
                        'available_bins' => $availableBins,
                        'reserved_bins_other_processes' => $reservedOtherBins,
                        'remaining_bins' => $remainingBins,
                    ];

                    $groupLots->each(fn ($lot) => $lot->forceFill(['estado' => PlanningLotStatus::CONFLICTO])->save());

                    continue;
                }

                // Reserva por proceso/origen (recepción o folio reembalaje).
                // Nota: si aún no existe la columna reserved_bins (migración pendiente),
                // hacemos un "best effort" y no marcamos conflicto por reservas previas.
                if ($hasReservationBins) {
                    if ($hasReservationSourceType && $hasReservationSourceKey) {
                        Reservation::query()->updateOrCreate(
                            [
                                'source_type' => $sourceType,
                                'source_key' => $sourceKey,
                                'process_id' => $process->id,
                            ],
                            [
                                'n_g_recepcion' => $referenceNg !== '' ? $referenceNg : $sourceKey,
                                'estado' => 'ACTIVA',
                                'reserved_bins' => $plannedBins,
                            ]
                        );
                    } else {
                        Reservation::query()->updateOrCreate(
                            ['n_g_recepcion' => $sourceKey, 'process_id' => $process->id],
                            ['estado' => 'ACTIVA', 'reserved_bins' => $plannedBins]
                        );
                    }
                } else {
                    if ($hasReservationSourceType && $hasReservationSourceKey) {
                        Reservation::query()->firstOrCreate(
                            [
                                'source_type' => $sourceType,
                                'source_key' => $sourceKey,
                                'process_id' => $process->id,
                            ],
                            [
                                'n_g_recepcion' => $referenceNg !== '' ? $referenceNg : $sourceKey,
                                'estado' => 'ACTIVA',
                            ]
                        );
                    } else {
                        Reservation::query()->firstOrCreate(
                            ['n_g_recepcion' => $sourceKey],
                            ['process_id' => $process->id, 'estado' => 'ACTIVA']
                        );
                    }
                }

                $groupLots->each(fn ($lot) => $lot->forceFill(['estado' => PlanningLotStatus::CONFIRMADO])->save());
            }

            $nextStatus = empty($conflicts)
                ? PlanningProcessStatus::CONFIRMADO
                : PlanningProcessStatus::CONFLICTO;

            $process->forceFill([
                'estado' => $nextStatus,
                'exportadora' => $exportadora ?: ($process->exportadora ?? null),
            ])->save();
        });

        return [
            'ok' => empty($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Finaliza la planificación:
     * - Permite que el usuario asigne varios lotes a líneas/cámaras en un solo "proceso de planificación".
     * - Si $splitByLot es true, genera procesos "reales" 1 por lote (n_g_recepcion).
     * - Si $splitByLot es false (default), mantiene todos los lotes en el mismo proceso.
     *
     * @return array{mode:string, ok:bool, created_process_ids:array<int,int>, conflicts:array<int,array<string,mixed>>}
     */
    public function finalizeAndConfirm(PackingProcess $process, bool $splitByLot = false): array
    {
        $process->loadMissing(['shift', 'lots', 'lineOverrides']);

        $groups = $process->lots
            ->filter(fn ($l) => $this->lotHasPlanningKey($l))
            ->groupBy(fn ($l) => $this->lotPlanKey($l));

        // Modo directo: todos los lotes en un solo proceso.
        if ($groups->count() <= 1 || ! $splitByLot) {
            $res = $this->confirm($process);
            return [
                'mode' => 'single',
                'ok' => (bool) ($res['ok'] ?? false),
                'created_process_ids' => [$process->id],
                'conflicts' => $res['conflicts'] ?? [],
            ];
        }

        // Exportadoras por lote (prefetch) para setear el campo a nivel proceso.
        $exportadorasByLot = Recepcion::query()
            ->whereIn('numero_g_recepcion', $process->lots
                ->pluck('n_g_recepcion')
                ->map(fn ($n) => trim((string) $n))
                ->filter(fn ($n) => $n !== '')
                ->unique()
                ->values()
                ->all())
            ->pluck('exportadora', 'numero_g_recepcion')
            ->map(fn ($v) => is_string($v) && trim($v) !== '' ? trim((string) $v) : null)
            ->all();

        $extraByLine = $process->lineOverrides
            ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (float) $r->extra_horas])
            ->all();
        $hasPlanningModeColumn = Schema::hasColumn('processes', 'planning_mode');

        $createdIds = DB::transaction(function () use ($process, $groups, $extraByLine, $exportadorasByLot, $hasPlanningModeColumn) {
            $created = [];

            foreach ($groups as $groupKey => $lots) {
                $first = $lots->first();
                $n = trim((string) ($first?->n_g_recepcion ?? ''));
                $lineIds = $lots->pluck('packing_line_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $exportadora = $exportadorasByLot[(string) $n] ?? null;
                $newProcessPayload = [
                    'process_batch_id' => $process->process_batch_id,
                    'especie' => (string) $process->especie,
                    'exportadora' => $exportadora,
                    'fecha' => $process->fecha,
                    'shift_id' => (int) $process->shift_id,
                    'extra_horas' => 0,
                    'estado' => PlanningProcessStatus::BORRADOR,
                    'creado_por' => $process->creado_por,
                    'included_packing_line_ids' => $lineIds ?: null,
                    'pedidos' => $process->pedidos,
                ];
                if ($hasPlanningModeColumn) {
                    $newProcessPayload['planning_mode'] = (string) ($process->planning_mode ?? 'normal');
                }
                $newProcess = PackingProcess::create($newProcessPayload);

                // Copiar horas extra por línea (solo las líneas efectivamente usadas por este lote).
                foreach ($lineIds as $lineId) {
                    $extra = (float) ($extraByLine[(int) $lineId] ?? 0);
                    if ($extra <= 0) {
                        continue;
                    }
                    PackingProcessLineOverride::create([
                        'process_id' => $newProcess->id,
                        'packing_line_id' => (int) $lineId,
                        'extra_horas' => $extra,
                    ]);
                }

                // Mover partes del lote (incluye divisiones entre líneas).
                $lotIds = $lots->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all();
                PackingProcessLot::query()
                    ->where('process_id', $process->id)
                    ->whereIn('id', $lotIds)
                    ->update(['process_id' => $newProcess->id]);

                // Renumerar orden por línea dentro del proceso nuevo (para que sea prolijo).
                $newLots = PackingProcessLot::query()
                    ->where('process_id', $newProcess->id)
                    ->orderBy('packing_line_id')
                    ->orderBy('orden')
                    ->orderBy('id')
                    ->get();

                foreach ($newLots->groupBy('packing_line_id') as $lineId => $lineLots) {
                    $i = 1;
                    foreach ($lineLots as $lot) {
                        if ((int) $lot->orden !== $i) {
                            $lot->forceFill(['orden' => $i])->save();
                        }
                        $i++;
                    }
                }

                $this->generator->estimateTimes($newProcess);
                $created[] = $newProcess->id;
            }

            // El proceso original era solo de planificación: queda sin lotes luego de mover.
            $process->delete();

            return $created;
        });

        $okAll = true;
        $conflictsAll = [];
        foreach ($createdIds as $pid) {
            $p = PackingProcess::find($pid);
            if (! $p) {
                continue;
            }
            $res = $this->confirm($p);
            if (! ($res['ok'] ?? false)) {
                $okAll = false;
                foreach (($res['conflicts'] ?? []) as $c) {
                    $conflictsAll[] = ['process_id' => $pid] + (array) $c;
                }
            }
        }

        return [
            'mode' => 'split',
            'ok' => $okAll,
            'created_process_ids' => $createdIds,
            'conflicts' => $conflictsAll,
        ];
    }

    private function lotSourceType($lot): string
    {
        $raw = Str::lower(trim((string) ($lot->source_type ?? '')));
        return in_array($raw, ['recepcion', 'reembalaje'], true) ? $raw : 'recepcion';
    }

    private function lotSourceKey($lot): string
    {
        $type = $this->lotSourceType($lot);
        if ($type === 'reembalaje') {
            $source = trim((string) ($lot->source_key ?? ''));
            if ($source !== '') {
                return $source;
            }
        }

        $n = trim((string) ($lot->n_g_recepcion ?? ''));
        if ($n !== '') {
            return $n;
        }

        return trim((string) ($lot->source_key ?? ''));
    }

    private function lotPlanKey($lot): string
    {
        return $this->lotSourceType($lot).'|'.$this->lotSourceKey($lot);
    }

    private function lotHasPlanningKey($lot): bool
    {
        return $this->lotSourceKey($lot) !== '';
    }
}
