<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementAllocation;
use App\Models\InventoryMovementDetail;
use App\Models\InventorySemiFinishedProduction;
use App\Models\InventoryStockPosition;
use App\Models\InventoryTechnicalSheet;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SemiFinishedProductionReversalService
{
    public function __construct(
        private readonly LogisticUnitService $logisticUnitService,
        private readonly StockProjectionService $stockProjectionService,
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Estado de reversibilidad de un LPN de semielaborado.
     *
     * @return array{production: ?array, reversible: bool, reason: ?string}
     */
    public function statusFor(InventoryLogisticUnit $unit, ?User $user = null): array
    {
        return $this->buildStatus($unit, $this->productionFor($unit), $user);
    }

    /**
     * Versión batcheada para listados: resuelve las producciones de todos los LPN
     * de la página con una sola consulta.
     *
     * @param  iterable<int, InventoryLogisticUnit>  $units
     * @return array<int, array{production: ?array, reversible: bool, reason: ?string}>
     */
    public function statusForUnits(iterable $units, ?User $user = null): array
    {
        $units = collect($units);
        $productionByUnit = $this->productionsByUnit($units->pluck('id')->map(fn ($id) => (int) $id)->all());

        $statuses = [];

        foreach ($units as $unit) {
            $statuses[(int) $unit->id] = $this->buildStatus(
                $unit,
                $productionByUnit->get((int) $unit->id) ?? $this->productionFor($unit),
                $user,
            );
        }

        return $statuses;
    }

    public function reverse(InventoryLogisticUnit $unit, string $reason, int $userId): InventorySemiFinishedProduction
    {
        if (! Schema::hasTable('inventory_semi_finished_productions')) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'La tabla de producciones de semielaborado no está disponible.',
            ]);
        }

        return DB::transaction(function () use ($unit, $reason, $userId): InventorySemiFinishedProduction {
            $unit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $user = User::query()->find($userId);

            $production = $this->persistProduction($this->productionFor($unit));

            $blocked = $this->blockingReason($unit, $production, $user);

            if ($blocked !== null) {
                throw ValidationException::withMessages(['logistic_unit' => $blocked]);
            }

            $restored = [];
            $ledgerEvents = [];
            $syncPairs = [];
            $inputUnitIds = [];

            foreach ($this->consumptionMovements($unit, $production) as $movement) {
                $allocations = InventoryMovementAllocation::query()
                    ->whereIn('movement_detail_id', $movement->details->pluck('id')->all())
                    ->get()
                    ->keyBy('movement_detail_id');

                foreach ($movement->details as $detail) {
                    $quantity = (float) $detail->cantidad;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $allocation = $allocations->get($detail->id);
                    $inputUnitId = $allocation?->logistic_unit_id
                        ?? Arr::get((array) $detail->metadata, 'position_logistic_unit_snapshot.id');

                    $target = $this->restorePosition($detail, $movement, $inputUnitId, $quantity);

                    if ($target['logistic_unit_id']) {
                        $inputUnitIds[] = $target['logistic_unit_id'];
                    }

                    $pairKey = $target['material_id'].'-'.$target['location_id'];
                    $syncPairs[$pairKey] = [
                        'material_id' => $target['material_id'],
                        'location_id' => $target['location_id'],
                    ];

                    $ledgerEvents[] = [
                        'event_type' => 'PRODUCTION_REVERSAL_IN',
                        'movement_id' => $movement->id,
                        'movement_detail_id' => $detail->id,
                        'allocation_id' => $allocation?->id,
                        'material_id' => $target['material_id'],
                        'logistic_unit_id' => $target['logistic_unit_id'],
                        'location_id' => $target['location_id'],
                        'signed_quantity' => $quantity,
                        'stock_effect' => 'in',
                        'occurred_at' => now(),
                        'actor_user_id' => $userId,
                        'actor_name_snapshot' => $user?->name,
                        'payload' => [
                            'workflow' => 'semi_finished_production_reversal',
                            'production_id' => $production->id,
                            'output_logistic_unit_id' => $unit->id,
                            'output_lpn' => $unit->license_plate_number,
                            'origin_movement_id' => $movement->id,
                            'origin_movement_folio' => $movement->folio,
                            'reason' => $reason,
                        ],
                    ];

                    $restored[] = [
                        'material_id' => $target['material_id'],
                        'position_id' => $target['position_id'],
                        'location_id' => $target['location_id'],
                        'logistic_unit_id' => $target['logistic_unit_id'],
                        'quantity' => $quantity,
                        'origin_movement_id' => $movement->id,
                    ];
                }
            }

            $this->ledgerService->appendMany($ledgerEvents);

            foreach (array_unique($inputUnitIds) as $inputUnitId) {
                $inputUnit = InventoryLogisticUnit::query()->lockForUpdate()->find($inputUnitId);

                if ($inputUnit) {
                    $this->logisticUnitService->syncLogisticUnitQuantity($inputUnit);
                }
            }

            foreach ($syncPairs as $pair) {
                $this->stockProjectionService->syncMaterialLocationFromPositions(
                    $pair['material_id'],
                    $pair['location_id'],
                );
            }

            $outputMaterialId = (int) $unit->material_id;
            $outputLocationId = (int) $production->location_id;

            $this->closeOutputUnit($unit, $production, $reason, $userId, $restored);

            if ($outputMaterialId > 0 && $outputLocationId > 0) {
                $this->stockProjectionService->syncMaterialLocationFromPositions($outputMaterialId, $outputLocationId);
            }

            $this->ledgerService->append([
                'event_type' => 'PRODUCTION_REVERSAL_OUTPUT',
                'material_id' => $outputMaterialId ?: null,
                'logistic_unit_id' => (int) $unit->id,
                'location_id' => $outputLocationId ?: null,
                'signed_quantity' => 0,
                'stock_effect' => 'none',
                'occurred_at' => now(),
                'actor_user_id' => $userId,
                'actor_name_snapshot' => $user?->name,
                'payload' => [
                    'workflow' => 'semi_finished_production_reversal',
                    'production_id' => $production->id,
                    'output_logistic_unit_id' => $unit->id,
                    'output_lpn' => $unit->license_plate_number,
                    'quantity' => (float) $production->quantity,
                    'reason' => $reason,
                ],
            ]);

            $production->forceFill([
                'status' => 'reverted',
                'reversed_at' => now(),
                'reversed_by_id' => $userId,
                'reversal_reason' => $reason,
                'metadata' => [
                    ...((array) $production->metadata),
                    'reversal' => [
                        'reversed_at' => now()->toISOString(),
                        'reversed_by_id' => $userId,
                        'reason' => $reason,
                        'restored' => $restored,
                    ],
                ],
            ])->save();

            return $production->fresh();
        });
    }

    /**
     * Producción registrada para el LPN. Si el LPN es de una producción antigua
     * (sin registro), se arma una producción virtual a partir de su referencia.
     */
    private function productionFor(InventoryLogisticUnit $unit): ?InventorySemiFinishedProduction
    {
        if (! Schema::hasTable('inventory_semi_finished_productions')) {
            return null;
        }

        $production = InventorySemiFinishedProduction::query()
            ->where('logistic_unit_id', $unit->id)
            ->orderByDesc('id')
            ->first();

        if ($production) {
            return $production;
        }

        if (blank($unit->reference_type) || $unit->reference_type !== 'semi_finished_production' || ! $unit->reference_id) {
            return null;
        }

        if (! InventoryTechnicalSheet::query()->whereKey((int) $unit->reference_id)->exists()) {
            return null;
        }

        $virtual = new InventorySemiFinishedProduction([
            'logistic_unit_id' => (int) $unit->id,
            'technical_sheet_id' => (int) $unit->reference_id,
            'location_id' => (int) $unit->current_location_id,
            'created_by' => (int) $unit->created_by,
            'quantity' => (float) $unit->base_quantity,
            'status' => 'completed',
            'metadata' => ['derived_from' => 'logistic_unit_reference'],
        ]);

        $virtual->id = null;
        $virtual->exists = false;

        return $virtual;
    }

    private function persistProduction(InventorySemiFinishedProduction $production): InventorySemiFinishedProduction
    {
        if ($production->exists) {
            return $production;
        }

        return InventorySemiFinishedProduction::query()->create([
            'logistic_unit_id' => (int) $production->logistic_unit_id,
            'technical_sheet_id' => (int) $production->technical_sheet_id,
            'location_id' => (int) $production->location_id,
            'created_by' => (int) $production->created_by,
            'quantity' => (float) $production->quantity,
            'status' => 'completed',
            'metadata' => [
                ...((array) $production->metadata),
                'derived_from' => 'logistic_unit_reference',
            ],
        ]);
    }

    /**
     * @param  array<int>  $unitIds
     * @return Collection<int, InventorySemiFinishedProduction>
     */
    private function productionsByUnit(array $unitIds): Collection
    {
        if (empty($unitIds) || ! Schema::hasTable('inventory_semi_finished_productions')) {
            return collect();
        }

        return InventorySemiFinishedProduction::query()
            ->whereIn('logistic_unit_id', $unitIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('logistic_unit_id')
            ->map(fn (Collection $group) => $group->first());
    }

    private function buildStatus(InventoryLogisticUnit $unit, ?InventorySemiFinishedProduction $production, ?User $user): array
    {
        if (! $production) {
            return [
                'production' => null,
                'reversible' => false,
                'reason' => null,
            ];
        }

        $reason = $this->blockingReason($unit, $production, $user);

        return [
            'production' => [
                'id' => $production->id,
                'status' => $production->status,
                'quantity' => (float) $production->quantity,
                'location_id' => (int) $production->location_id,
                'reversed_at' => optional($production->reversed_at)->format('Y-m-d H:i'),
                'reversal_reason' => $production->reversal_reason,
                'derived' => ! $production->exists,
            ],
            'reversible' => $reason === null,
            'reason' => $reason,
        ];
    }

    private function blockingReason(InventoryLogisticUnit $unit, InventorySemiFinishedProduction $production, ?User $user): ?string
    {
        if ($production->status !== 'completed') {
            return 'La producción ya fue revertida'
                .($production->reversed_at ? ' el '.$production->reversed_at->format('d/m/Y H:i') : '')
                .'.';
        }

        if ($unit->status !== 'active') {
            return 'El LPN no está activo (estado actual: '.$unit->status.').';
        }

        if ((int) $unit->current_location_id !== (int) $production->location_id) {
            return 'El LPN fue trasladado fuera de la bodega donde se produjo.';
        }

        $available = $this->availableQuantity($unit);
        $expected = (float) $production->quantity;

        if (abs($available - $expected) > 0.0001) {
            return 'El LPN ya no tiene la cantidad producida (disponible: '
                .$this->formatQuantity($available).' de '.$this->formatQuantity($expected).').';
        }

        $movementCount = InventoryMovementAllocation::query()
            ->where('logistic_unit_id', $unit->id)
            ->count();

        if ($movementCount > 0) {
            return 'El LPN ya tiene '.$movementCount.' movimiento(s) asociado(s); solo se puede revertir antes de moverlo o consumirlo.';
        }

        return $this->authorizationReason($production, $user);
    }

    private function authorizationReason(InventorySemiFinishedProduction $production, ?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $location = InventoryLocation::query()
            ->with('assignedUsers')
            ->find((int) $production->location_id);

        if (! $location) {
            return 'No se pudo resolver la bodega de la producción.';
        }

        $assigned = $location->assignedUsers->pluck('id')->map(fn ($id) => (int) $id);

        if ($assigned->isEmpty()) {
            return null;
        }

        if (! $assigned->contains((int) $user->id)) {
            return 'Solo los usuarios asignados a la bodega '.$location->nombre.' pueden revertir esta producción.';
        }

        return null;
    }

    /**
     * @return Collection<int, InventoryMovement>
     */
    private function consumptionMovements(InventoryLogisticUnit $unit, InventorySemiFinishedProduction $production): Collection
    {
        $ids = array_values(array_filter(
            (array) Arr::get((array) $production->metadata, 'consumption_movement_ids', []),
            fn ($id) => (int) $id > 0,
        ));

        $base = InventoryMovement::query()
            ->with(['details', 'type'])
            ->where('estado', 'aplicado')
            ->whereHas('type', fn ($type) => $type->where('codigo', 'CONSUMO'));

        if (! empty($ids)) {
            $movements = (clone $base)->whereIn('id', $ids)->get();

            if ($movements->isNotEmpty()) {
                return $movements;
            }
        }

        return $base
            ->where('referencia_tipo', 'semi_finished_production')
            ->where('referencia_id', $unit->id)
            ->get();
    }

    /**
     * Devuelve la cantidad consumida a su posición original. Si la posición ya no
     * existe (se consumió completa), se recrea en la bodega actual del LPN insumo.
     */
    private function restorePosition(
        InventoryMovementDetail $detail,
        InventoryMovement $movement,
        ?int $inputUnitId,
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
                'logistic_unit_id' => $position->logistic_unit_id ? (int) $position->logistic_unit_id : null,
            ];
        }

        $inputUnit = $inputUnitId
            ? InventoryLogisticUnit::query()->lockForUpdate()->find($inputUnitId)
            : null;

        $locationId = (int) ($inputUnit?->current_location_id ?: $movement->origin_location_id ?: 0);

        if ($locationId <= 0) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'No se pudo determinar la bodega donde devolver el insumo consumido.',
            ]);
        }

        $lotCode = Arr::get($metadata, 'position_lot_code_snapshot') ?? $inputUnit?->lot_code;
        $status = Arr::get($metadata, 'position_status_snapshot') ?: 'available';

        $position = InventoryStockPosition::query()->firstOrNew([
            'material_id' => (int) $detail->material_id,
            'location_id' => $locationId,
            'logistic_unit_id' => $inputUnit?->id,
            'lot_code' => $lotCode,
            'status' => $status,
        ]);

        $position->quantity = round((float) ($position->quantity ?? 0) + $quantity, 4);
        $position->metadata = [
            ...((array) $position->metadata),
            'restored_by' => 'semi_finished_production_reversal',
            'restored_at' => now()->toISOString(),
            'origin_movement_id' => $movement->id,
        ];
        $position->save();

        return [
            'position_id' => (int) $position->id,
            'material_id' => (int) $position->material_id,
            'location_id' => (int) $position->location_id,
            'logistic_unit_id' => $position->logistic_unit_id ? (int) $position->logistic_unit_id : null,
        ];
    }

    private function closeOutputUnit(
        InventoryLogisticUnit $unit,
        InventorySemiFinishedProduction $production,
        string $reason,
        int $userId,
        array $restored,
    ): void {
        InventoryStockPosition::query()->where('logistic_unit_id', $unit->id)->delete();

        $metadata = (array) $unit->metadata;
        $metadata['changelog'] = array_merge(
            (array) ($metadata['changelog'] ?? []),
            [[
                'field' => 'status',
                'from' => $unit->status,
                'to' => 'closed',
                'reason' => 'Reversión de producción de semielaborado: '.$reason,
                'changed_by' => $userId,
                'changed_at' => now()->toISOString(),
            ]],
        );
        $metadata['production_reversal'] = [
            'production_id' => $production->id,
            'reason' => $reason,
            'reversed_at' => now()->toISOString(),
            'reversed_by_id' => $userId,
            'restored_count' => count($restored),
        ];

        $unit->forceFill([
            'status' => 'closed',
            'available_quantity' => 0,
            'closed_at' => now(),
            'last_moved_at' => now(),
            'metadata' => $metadata,
        ])->save();
    }

    private function availableQuantity(InventoryLogisticUnit $unit): float
    {
        if (! Schema::hasTable('inventory_stock_positions')) {
            return (float) $unit->available_quantity;
        }

        return round((float) InventoryStockPosition::query()
            ->where('logistic_unit_id', $unit->id)
            ->sum('quantity'), 4);
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',');
    }
}
