<?php

namespace App\Services\Inventory;

use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementAllocation;
use App\Models\InventoryMovementDetail;
use App\Models\InventoryStockPosition;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Revierte el consumo de un LPN de insumo (no semielaborado) devolviendo la
 * cantidad consumida a su posición de stock original. Si la posición se
 * consumió por completo (se eliminó), se recrea en la bodega donde se consumió,
 * usando los snapshots guardados en el detalle del movimiento.
 */
class ConsumptionReversalService
{
    public function __construct(
        private readonly LogisticUnitService $logisticUnitService,
        private readonly StockProjectionService $stockProjectionService,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * Estado de reversión de consumo para un listado de LPN (una sola consulta).
     *
     * @param  iterable<int, InventoryLogisticUnit>  $units
     * @return array<int, array{reversible: bool, reason: ?string, quantity: float, consumptions: array}>
     */
    public function statusForUnits(iterable $units): array
    {
        $units = collect($units)->keyBy(fn (InventoryLogisticUnit $unit) => (int) $unit->id);
        $statuses = [];

        if ($units->isEmpty() || ! $this->allocationsTableExists()) {
            foreach ($units as $unit) {
                $statuses[(int) $unit->id] = $this->emptyStatus('No se encontró consumo pendiente de reversión.');
            }

            return $statuses;
        }

        $pending = $this->pendingAllocations($units->keys()->all())->groupBy(
            fn (InventoryMovementAllocation $allocation) => (int) $allocation->logistic_unit_id,
        );

        foreach ($units as $unit) {
            $statuses[(int) $unit->id] = $this->buildStatus($unit, $pending->get((int) $unit->id, collect()));
        }

        return $statuses;
    }

    public function statusFor(InventoryLogisticUnit $unit): array
    {
        return $this->statusForUnits([$unit])[(int) $unit->id]
            ?? $this->emptyStatus('No se encontró consumo pendiente de reversión.');
    }

    /**
     * @return array{unit: InventoryLogisticUnit, quantity: float, restored: array, allocations: array<int, float>}
     */
    public function reverse(InventoryLogisticUnit $unit, string $reason, int $userId): array
    {
        if (! $this->allocationsTableExists()) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'No está disponible el registro de asignaciones de consumo.',
            ]);
        }

        return DB::transaction(function () use ($unit, $reason, $userId): array {
            /** @var InventoryLogisticUnit $unit */
            $unit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($unit->id);

            if ((string) ($unit->reference_type ?? '') === 'semi_finished_production') {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'Este LPN es un semielaborado. Use la reversión de producción.',
                ]);
            }

            $allocations = $this->pendingAllocations([(int) $unit->id]);

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'El LPN '.$unit->license_plate_number.' no tiene consumo pendiente de reversión.',
                ]);
            }

            $user = User::query()->find($userId);
            $restored = [];
            $events = [];
            $pairs = [];
            $restoredByAllocation = [];
            $total = 0.0;

            foreach ($allocations as $allocation) {
                $detail = $allocation->movementDetail;
                $movement = $detail?->movement;

                if (! $detail || ! $movement) {
                    continue;
                }

                $quantity = $this->pendingQuantity($allocation);

                if ($quantity <= 0.00005) {
                    continue;
                }

                $target = $this->restorePosition($unit, $detail, $movement, $quantity);

                $pairs[$target['material_id'].'-'.$target['location_id']] = [
                    'material_id' => $target['material_id'],
                    'location_id' => $target['location_id'],
                ];

                $events[] = [
                    'event_type' => 'CONSUMPTION_REVERSAL_IN',
                    'movement_id' => $movement->id,
                    'movement_detail_id' => $detail->id,
                    'allocation_id' => $allocation->id,
                    'material_id' => $target['material_id'],
                    'location_id' => $target['location_id'],
                    'logistic_unit_id' => (int) $unit->id,
                    'signed_quantity' => $quantity,
                    'stock_effect' => 'in',
                    'occurred_at' => now(),
                    'actor_user_id' => $userId,
                    'actor_name_snapshot' => $user?->name,
                    'payload' => [
                        'workflow' => 'logistic_unit_consumption_reversal',
                        'logistic_unit_id' => (int) $unit->id,
                        'license_plate_number' => $unit->license_plate_number,
                        'origin_movement_id' => $movement->id,
                        'origin_movement_folio' => $movement->folio,
                        'origin_movement_type' => $movement->type?->codigo,
                        'restored_quantity' => $quantity,
                        'reason' => $reason,
                    ],
                ];

                $metadata = (array) $allocation->metadata;
                $previouslyRestored = round((float) Arr::get($metadata, 'consumption_reversal.restored_quantity', 0), 4);
                $metadata['consumption_reversal'] = [
                    'restored_quantity' => round($previouslyRestored + $quantity, 4),
                    'allocated_quantity' => round((float) $allocation->allocated_quantity, 4),
                    'reason' => $reason,
                    'reversed_at' => now()->toISOString(),
                    'reversed_by_id' => $userId,
                ];
                $allocation->forceFill(['metadata' => $metadata])->save();

                $restoredByAllocation[(int) $allocation->id] = $quantity;
                $restored[] = [
                    'allocation_id' => (int) $allocation->id,
                    'movement_id' => (int) $movement->id,
                    'folio' => $movement->folio,
                    'material_id' => $target['material_id'],
                    'location_id' => $target['location_id'],
                    'position_id' => $target['position_id'],
                    'quantity' => $quantity,
                ];

                $total = round($total + $quantity, 4);
            }

            if ($total <= 0.00005) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'No hay cantidades pendientes de reversión para este LPN.',
                ]);
            }

            $this->ledgerService->appendMany($events);

            // El LPN vuelve a estar disponible con el stock restituido.
            $unit = $this->logisticUnitService->syncLogisticUnitQuantity($unit);

            $locationIds = collect($restored)->pluck('location_id')->unique()->values();

            $unit->forceFill([
                'current_location_id' => $locationIds->count() === 1
                    ? (int) $locationIds->first()
                    : $unit->current_location_id,
                'status' => 'active',
                'closed_at' => null,
                'last_moved_at' => now(),
                'metadata' => $this->reversalMetadata($unit, $reason, $userId, $total, $restored),
            ])->save();

            foreach ($pairs as $pair) {
                $this->stockProjectionService->syncMaterialLocationFromPositions(
                    (int) $pair['material_id'],
                    (int) $pair['location_id'],
                );
            }

            return [
                'unit' => $unit->fresh(),
                'quantity' => $total,
                'restored' => $restored,
                'allocations' => $restoredByAllocation,
            ];
        });
    }

    /**
     * @return Collection<int, InventoryMovementAllocation>
     */
    private function pendingAllocations(array $unitIds): Collection
    {
        if (empty($unitIds)) {
            return collect();
        }

        return InventoryMovementAllocation::query()
            ->with(['movementDetail.movement.type'])
            ->whereIn('logistic_unit_id', $unitIds)
            ->whereHas('movementDetail', fn ($query) => $query->where('sentido', 'salida'))
            ->whereHas('movementDetail.movement', fn ($query) => $query->where('estado', 'aplicado'))
            ->whereHas('movementDetail.movement.type', fn ($query) => $query->where('codigo', 'CONSUMO'))
            ->orderBy('id')
            ->get()
            ->filter(fn (InventoryMovementAllocation $allocation) => $this->pendingQuantity($allocation) > 0.00005)
            ->values();
    }

    private function pendingQuantity(InventoryMovementAllocation $allocation): float
    {
        $allocated = round((float) $allocation->allocated_quantity, 4);
        $restored = round((float) Arr::get((array) $allocation->metadata, 'consumption_reversal.restored_quantity', 0), 4);

        return round(max($allocated - $restored, 0), 4);
    }

    private function buildStatus(InventoryLogisticUnit $unit, Collection $pendingAllocations): array
    {
        if ((string) ($unit->reference_type ?? '') === 'semi_finished_production') {
            return $this->emptyStatus('Este LPN es un semielaborado. Use la reversión de producción.');
        }

        if ($pendingAllocations->isEmpty()) {
            return $this->emptyStatus('El LPN no tiene consumo pendiente de reversión.');
        }

        $consumptions = $pendingAllocations
            ->map(fn (InventoryMovementAllocation $allocation) => [
                'allocation_id' => (int) $allocation->id,
                'folio' => $allocation->movementDetail?->movement?->folio,
                'movement_id' => (int) ($allocation->movementDetail?->movement_id ?? 0),
                'material_id' => (int) ($allocation->movementDetail?->material_id ?? 0),
                'quantity' => $this->pendingQuantity($allocation),
            ])
            ->values()
            ->all();

        return [
            'reversible' => true,
            'reason' => null,
            'quantity' => round(array_sum(array_column($consumptions, 'quantity')), 4),
            'consumptions' => $consumptions,
        ];
    }

    private function emptyStatus(string $reason): array
    {
        return [
            'reversible' => false,
            'reason' => $reason,
            'quantity' => 0.0,
            'consumptions' => [],
        ];
    }

    /**
     * Devuelve la cantidad a su posición original, recreándola si ya no existe.
     *
     * @return array{position_id: int, material_id: int, location_id: int}
     */
    private function restorePosition(
        InventoryLogisticUnit $unit,
        InventoryMovementDetail $detail,
        InventoryMovement $movement,
        float $quantity,
    ): array {
        $metadata = (array) $detail->metadata;
        $positionId = Arr::get($metadata, 'position_id');

        $position = $positionId
            ? InventoryStockPosition::query()->lockForUpdate()->find((int) $positionId)
            : null;

        if ($position) {
            $position->forceFill([
                'quantity' => round((float) $position->quantity + $quantity, 4),
            ])->save();

            return [
                'position_id' => (int) $position->id,
                'material_id' => (int) $position->material_id,
                'location_id' => (int) $position->location_id,
            ];
        }

        $locationId = (int) (
            Arr::get($metadata, 'position_location_snapshot.id')
            ?: $movement->origin_location_id
            ?: $unit->current_location_id
            ?: 0
        );

        if ($locationId <= 0) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'No se pudo determinar la bodega donde devolver el insumo consumido.',
            ]);
        }

        $lotCode = Arr::get($metadata, 'position_lot_code_snapshot') ?? $unit->lot_code;
        $status = Arr::get($metadata, 'position_status_snapshot') ?: 'available';

        $position = InventoryStockPosition::query()->firstOrNew([
            'material_id' => (int) $detail->material_id,
            'location_id' => $locationId,
            'logistic_unit_id' => (int) $unit->id,
            'lot_code' => $lotCode,
            'status' => $status,
        ]);

        $position->quantity = round((float) ($position->quantity ?? 0) + $quantity, 4);
        $position->metadata = [
            ...((array) $position->metadata),
            'restored_by' => 'logistic_unit_consumption_reversal',
            'restored_at' => now()->toISOString(),
            'origin_movement_id' => $movement->id,
        ];
        $position->save();

        return [
            'position_id' => (int) $position->id,
            'material_id' => (int) $position->material_id,
            'location_id' => (int) $position->location_id,
        ];
    }

    private function reversalMetadata(
        InventoryLogisticUnit $unit,
        string $reason,
        int $userId,
        float $total,
        array $restored,
    ): array {
        $metadata = (array) $unit->metadata;
        $metadata['changelog'] = array_merge(
            (array) ($metadata['changelog'] ?? []),
            [[
                'field' => 'status',
                'from' => $unit->getOriginal('status'),
                'to' => 'active',
                'reason' => 'Reversión de consumo de LPN: '.$reason,
                'changed_by' => $userId,
                'changed_at' => now()->toISOString(),
            ]],
        );
        $metadata['consumption_reversal'] = [
            'reason' => $reason,
            'reversed_at' => now()->toISOString(),
            'reversed_by_id' => $userId,
            'restored_quantity' => $total,
            'restored_details' => $restored,
        ];

        return $metadata;
    }

    private function allocationsTableExists(): bool
    {
        return Schema::hasTable('inventory_movement_allocations');
    }
}
