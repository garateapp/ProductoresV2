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
    /**
     * @var array<int, Collection<int, InventoryLogisticUnit>>
     */
    private array $familyCache = [];

    public function __construct(
        private readonly LogisticUnitService $logisticUnitService,
        private readonly StockProjectionService $stockProjectionService,
        private readonly LedgerService $ledgerService,
    ) {}

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

    public function reverse(
        InventoryLogisticUnit $unit,
        string $reason,
        int $userId,
        string $scope = 'unit',
    ): InventorySemiFinishedProduction {
        if (! Schema::hasTable('inventory_semi_finished_productions')) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'La tabla de producciones de semielaborado no está disponible.',
            ]);
        }

        if (! in_array($scope, ['unit', 'production'], true)) {
            throw ValidationException::withMessages([
                'scope' => 'El alcance de reversión no es válido.',
            ]);
        }

        return DB::transaction(function () use ($unit, $reason, $userId, $scope): InventorySemiFinishedProduction {
            $unit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $user = User::query()->find($userId);
            $productionCandidate = $this->productionFor($unit);

            if (! $productionCandidate) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'El LPN no tiene una producción de semielaborado asociada.',
                ]);
            }

            $unitOriginId = (int) Arr::get((array) $unit->metadata, 'origin_unit_id', 0);
            if (! $productionCandidate->exists && $unitOriginId > 0) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'No se puede revertir un hijo de división individualmente; no tiene consumo registrado.',
                ]);
            }

            $production = $this->persistProduction($productionCandidate);

            if ($production->exists) {
                $production = InventorySemiFinishedProduction::query()
                    ->lockForUpdate()
                    ->findOrFail($production->id);
            }

            $this->familyCache = [];

            $family = $this->familyUnits($production);
            $familyCount = $family->count();
            $rootUnit = $family->firstWhere('id', (int) $production->logistic_unit_id) ?? $unit;

            $candidateUnits = $scope === 'production'
                ? $family->filter(fn (InventoryLogisticUnit $familyUnit) => $familyUnit->status === 'active')->values()
                : collect([$unit]);

            $targetIds = $candidateUnits
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (empty($targetIds)) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'No hay pallets activos para revertir en esta producción.',
                ]);
            }

            $locked = InventoryLogisticUnit::query()
                ->whereIn('id', $targetIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $targets = collect($targetIds)
                ->map(fn (int $id) => $locked->get($id))
                ->filter()
                ->values();

            $targetQuantities = [];

            foreach ($targets as $target) {
                $targetOriginId = (int) Arr::get((array) $target->metadata, 'origin_unit_id', 0);
                if (! $productionCandidate->exists && $targetOriginId > 0) {
                    throw ValidationException::withMessages([
                        'logistic_unit' => 'No se puede revertir un hijo de división individualmente; no tiene consumo registrado.',
                    ]);
                }

                $expected = $this->expectedQuantity($target, $production, $familyCount);
                $blocked = $this->blockingReason($target, $production, $user, $expected);

                if ($blocked !== null) {
                    throw ValidationException::withMessages(['logistic_unit' => $blocked]);
                }

                $targetQuantities[(int) $target->id] = $expected;
            }

            $productionQuantity = round((float) $production->quantity, 4);
            $batchQuantity = round(array_sum($targetQuantities), 4);
            $metadata = (array) $production->metadata;
            $reversal = (array) ($metadata['reversal'] ?? []);
            $alreadyRestored = round((float) ($reversal['restored_quantity'] ?? 0), 4);

            if ($alreadyRestored + $batchQuantity > $productionQuantity + 0.0001) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'La cantidad a revertir supera la cantidad producida.',
                ]);
            }

            if ($scope === 'production' && abs(($alreadyRestored + $batchQuantity) - $productionQuantity) > 0.0001) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'No se puede revertir la producción completa: faltan pallets activos o fueron movidos.',
                ]);
            }

            $restoredQuantityAfter = round($alreadyRestored + $batchQuantity, 4);
            $isComplete = abs($restoredQuantityAfter - $productionQuantity) <= 0.0001;
            $restoredDetails = (array) ($reversal['restored_details'] ?? []);
            $restored = [];
            $ledgerEvents = [];
            $syncPairs = [];
            $inputUnitIds = [];
            $outputUnitIds = $targets->pluck('id')->map(fn ($id) => (int) $id)->all();
            $outputLpns = $targets->pluck('license_plate_number')->all();

            foreach ($this->consumptionMovements($rootUnit, $production) as $movement) {
                $allocations = InventoryMovementAllocation::query()
                    ->whereIn('movement_detail_id', $movement->details->pluck('id')->all())
                    ->get()
                    ->keyBy('movement_detail_id');

                foreach ($movement->details as $detail) {
                    $quantity = (float) $detail->cantidad;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $alreadyDetail = round((float) ($restoredDetails[(int) $detail->id] ?? 0), 4);
                    $targetCumulative = $isComplete
                        ? $quantity
                        : round($quantity * ($restoredQuantityAfter / max($productionQuantity, 0.0001)), 4);
                    $restoreQuantity = round($targetCumulative - $alreadyDetail, 4);

                    if ($restoreQuantity <= 0.00005) {
                        continue;
                    }

                    $allocation = $allocations->get($detail->id);
                    $inputUnitId = $allocation?->logistic_unit_id
                        ?? Arr::get((array) $detail->metadata, 'position_logistic_unit_snapshot.id');

                    $target = $this->restorePosition($detail, $movement, $inputUnitId, $restoreQuantity);

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
                        'signed_quantity' => $restoreQuantity,
                        'stock_effect' => 'in',
                        'occurred_at' => now(),
                        'actor_user_id' => $userId,
                        'actor_name_snapshot' => $user?->name,
                        'payload' => [
                            'workflow' => 'semi_finished_production_reversal',
                            'production_id' => $production->id,
                            'reversal_scope' => $scope,
                            'output_logistic_unit_id' => count($outputUnitIds) === 1 ? $outputUnitIds[0] : null,
                            'output_logistic_unit_ids' => $outputUnitIds,
                            'output_lpn' => count($outputLpns) === 1 ? $outputLpns[0] : null,
                            'output_lpns' => $outputLpns,
                            'reversed_quantity' => $batchQuantity,
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
                        'quantity' => $restoreQuantity,
                        'origin_movement_id' => $movement->id,
                        'reversed_unit_ids' => $outputUnitIds,
                    ];

                    $restoredDetails[(int) $detail->id] = round($alreadyDetail + $restoreQuantity, 4);
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

            $outputPairs = [];
            $outputEvents = [];

            foreach ($targets as $target) {
                $targetQuantity = $targetQuantities[(int) $target->id];
                $this->closeOutputUnit($target, $production, $reason, $userId, $restored, $targetQuantity);

                $outputMaterialId = (int) $target->material_id;
                $outputLocationId = (int) $production->location_id;

                if ($outputMaterialId > 0 && $outputLocationId > 0) {
                    $outputPairs[$outputMaterialId.'-'.$outputLocationId] = [
                        'material_id' => $outputMaterialId,
                        'location_id' => $outputLocationId,
                    ];
                }

                $outputEvents[] = [
                    'event_type' => 'PRODUCTION_REVERSAL_OUTPUT',
                    'material_id' => $outputMaterialId ?: null,
                    'logistic_unit_id' => (int) $target->id,
                    'location_id' => $outputLocationId ?: null,
                    'signed_quantity' => 0,
                    'stock_effect' => 'none',
                    'occurred_at' => now(),
                    'actor_user_id' => $userId,
                    'actor_name_snapshot' => $user?->name,
                    'payload' => [
                        'workflow' => 'semi_finished_production_reversal',
                        'production_id' => $production->id,
                        'reversal_scope' => $scope,
                        'output_logistic_unit_id' => (int) $target->id,
                        'output_lpn' => $target->license_plate_number,
                        'quantity' => $targetQuantity,
                        'production_quantity' => $productionQuantity,
                        'reason' => $reason,
                    ],
                ];
            }

            $this->ledgerService->appendMany($outputEvents);

            foreach ($outputPairs as $pair) {
                $this->stockProjectionService->syncMaterialLocationFromPositions(
                    $pair['material_id'],
                    $pair['location_id'],
                );
            }

            $reversalUnits = (array) ($reversal['units'] ?? []);

            foreach ($targets as $target) {
                $reversalUnits[(string) $target->id] = [
                    'logistic_unit_id' => (int) $target->id,
                    'license_plate_number' => $target->license_plate_number,
                    'quantity' => $targetQuantities[(int) $target->id],
                    'reason' => $reason,
                    'reversed_at' => now()->toISOString(),
                    'reversed_by_id' => $userId,
                ];
            }

            $newReversal = [
                ...$reversal,
                'restored' => [
                    ...((array) ($reversal['restored'] ?? [])),
                    ...$restored,
                ],
                'units' => $reversalUnits,
                'restored_quantity' => $restoredQuantityAfter,
                'restored_details' => $restoredDetails,
                'last_reason' => $reason,
                'last_reversed_by_id' => $userId,
                'last_reversed_at' => now()->toISOString(),
            ];

            if ($isComplete) {
                $newReversal['reversed_at'] = now()->toISOString();
                $newReversal['reversed_by_id'] = $userId;
                $newReversal['reason'] = $reason;
            }

            $production->forceFill([
                'status' => $isComplete ? 'reverted' : 'completed',
                'reversed_at' => $isComplete ? now() : null,
                'reversed_by_id' => $isComplete ? $userId : null,
                'reversal_reason' => $isComplete ? $reason : null,
                'metadata' => [
                    ...$metadata,
                    'reversal' => $newReversal,
                ],
            ])->save();

            return $production->fresh();
        });
    }

    /**
     * Producción registrada para el LPN. Si el LPN es de una producción antigua
     * (sin registro), se arma una producción virtual a partir de su referencia.
     */
    /**
     * Reconcilia reversiones antiguas de hijos divididos que cerraron el LPN pero
     * no devolvieron insumos. Puede ejecutarse en modo lectura o aplicar los ajustes.
     *
     * @return array{summary: array<string, mixed>, roots: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    /**
     * @param  array{
     *     root_ids: list<int>,
     *     exclude_root_ids: list<int>,
     *     material_codes: list<string>,
     *     child_lpns: list<string>,
     * }  $filters
     * @param  array<string|int, float>  $partialQuantities
     * @return array{summary: array<string, mixed>, roots: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function reconcileLegacySplitReversals(
        bool $apply = false,
        array $filters = [],
        array $partialQuantities = [],
    ): array {
        if (! Schema::hasTable('inventory_semi_finished_productions')
            || ! Schema::hasTable('inventory_logistic_units')) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'Las tablas de producciones o LPN no están disponibles.',
            ]);
        }

        $runner = fn (): array => $this->runLegacySplitReconciliation($apply, $filters, $partialQuantities);

        return $apply ? DB::transaction($runner) : $runner();
    }

    private function runLegacySplitReconciliation(bool $apply, array $filters, array $partialQuantities): array
    {
        $reason = 'Reconciliación de reversión histórica de pallets divididos';
        $materialCodes = DB::table('inventory_materials')->pluck('codigo', 'id');
        $children = InventorySemiFinishedProduction::query()
            ->with('logisticUnit')
            ->where('status', 'reverted')
            ->orderBy('id')
            ->get()
            ->filter(function (InventorySemiFinishedProduction $production): bool {
                $unit = $production->logisticUnit;

                if (! $unit || (int) Arr::get((array) $unit->metadata, 'origin_unit_id', 0) <= 0) {
                    return false;
                }

                return blank(Arr::get((array) $production->metadata, 'reversal.legacy_reconciliation.applied_at'));

                $childLpns = (array) ($filters['child_lpns'] ?? []);
                if (! empty($childLpns) && ! in_array($unit?->license_plate_number, $childLpns, true)) {
                    return false;
                }

                return true;
            });

        $groups = [];
        $skipped = [];

        foreach ($children as $child) {
            $unit = $child->logisticUnit;
            $root = $this->rootProductionForReconciliation($unit);

            if (! $root) {
                $skipped[] = [
                    'child_production_id' => (int) $child->id,
                    'logistic_unit_id' => (int) $child->logistic_unit_id,
                    'lpn' => $unit?->license_plate_number,
                    'quantity' => (float) $child->quantity,
                    'reason' => 'No se encontró la producción raíz.',
                ];

                continue;
            }

            $rootKey = $root->exists ? (string) $root->id : 'virtual-'.spl_object_id($root);
            $groups[$rootKey] ??= [
                'root' => $root,
                'children' => [],
            ];
            $groups[$rootKey]['children'][] = [
                'production' => $child,
                'unit' => $unit,
            ];
        }

        $roots = [];

        foreach ($groups as $group) {
            $root = $group['root'];
            $groupChildren = $group['children'];

            $rootIds = (array) ($filters['root_ids'] ?? []);
            $excludeRootIds = (array) ($filters['exclude_root_ids'] ?? []);

            if (! empty($rootIds) && ! in_array((int) $root->id, $rootIds, true)) {
                foreach ($groupChildren as $child) {
                    $skipped[] = $this->legacyChildSkip($child, 'Raíz excluida por filtro --root-id.');
                }

                continue;
            }

            if (! empty($excludeRootIds) && in_array((int) $root->id, $excludeRootIds, true)) {
                foreach ($groupChildren as $child) {
                    $skipped[] = $this->legacyChildSkip($child, 'Raíz excluida por filtro --exclude-root-id.');
                }

                continue;
            }

            if (! $root->exists) {
                foreach ($groupChildren as $child) {
                    $skipped[] = $this->legacyChildSkip($child, 'La producción raíz es virtual y no tiene consumos registrados.');
                }

                continue;
            }

            if ($apply) {
                $root = InventorySemiFinishedProduction::query()
                    ->lockForUpdate()
                    ->findOrFail($root->id);
            }

            $rootUnit = InventoryLogisticUnit::query()->find((int) $root->logistic_unit_id);
            $rootQuantity = round((float) $root->quantity, 4);

            $reconciliationUserId = (int) ($rootUnit?->created_by ?? $root->created_by ?? 0);
            $legacyQuantity = round((float) collect($groupChildren)
                ->sum(fn (array $child) => (float) $child['production']->quantity), 4);
            $metadata = (array) $root->metadata;
            $reversal = (array) ($metadata['reversal'] ?? []);
            $alreadyRestored = round((float) ($reversal['restored_quantity'] ?? 0), 4);
            $restoredDetails = (array) ($reversal['restored_details'] ?? []);
            $restoredAfter = round($alreadyRestored + $legacyQuantity, 4);

            $rootSummary = [
                'root_production_id' => (int) $root->id,
                'root_lpn' => $rootUnit?->license_plate_number,
                'root_status' => $root->status,
                'production_quantity' => $rootQuantity,
                'already_restored' => $alreadyRestored,
                'legacy_quantity' => $legacyQuantity,
                'restored_after' => min($restoredAfter, $rootQuantity),
                'child_production_ids' => collect($groupChildren)
                    ->pluck('production.id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
                'child_lpns' => collect($groupChildren)
                    ->pluck('unit.license_plate_number')
                    ->values()
                    ->all(),
                'restoration_lines' => [],
            ];

            if ($root->status !== 'completed') {
                $rootSummary['action'] = 'skipped';
                $rootSummary['reason'] = 'La producción raíz ya fue revertida por completo; no requiere devolución adicional.';
                $roots[] = $rootSummary;

                if ($apply) {
                    foreach ($groupChildren as $child) {
                        $this->markLegacyChildReconciled(
                            $child['production'],
                            $root,
                            'covered_by_root_full_reversal',
                            $reason,
                        );
                    }
                }

                continue;
            }

            if ($rootQuantity <= 0 || $restoredAfter > $rootQuantity + 0.0001) {
                foreach ($groupChildren as $child) {
                    $skipped[] = $this->legacyChildSkip($child, 'La cantidad histórica excede la cantidad producida de la raíz.');
                }

                continue;
            }

            if (! $rootUnit) {
                foreach ($groupChildren as $child) {
                    $skipped[] = $this->legacyChildSkip($child, 'No se encontró el LPN de salida de la producción raíz.');
                }

                continue;
            }

            $isComplete = abs($restoredAfter - $rootQuantity) <= 0.0001;
            $restorationOps = [];

            foreach ($this->consumptionMovements($rootUnit, $root) as $movement) {
                $allocations = InventoryMovementAllocation::query()
                    ->whereIn('movement_detail_id', $movement->details->pluck('id')->all())
                    ->get()
                    ->keyBy('movement_detail_id');

                foreach ($movement->details as $detail) {
                    $quantity = (float) $detail->cantidad;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $materialCode = $materialCodes->get((int) $detail->material_id);
                    $materialCodesFilter = (array) ($filters['material_codes'] ?? []);
                    if (! empty($materialCodesFilter) && ! in_array($materialCode, $materialCodesFilter, true)) {
                        continue;
                    }

                    $alreadyDetail = round((float) ($restoredDetails[(int) $detail->id] ?? 0), 4);
                    $targetCumulative = $isComplete
                        ? $quantity
                        : round($quantity * ($restoredAfter / max($rootQuantity, 0.0001)), 4);
                    $restoreQuantity = round($targetCumulative - $alreadyDetail, 4);

                    $partialQuantities = (array) ($partialQuantities ?? []);
                    $partialKey = null;
                    foreach ($partialQuantities as $key => $partialQty) {
                        if ((string) $key === (string) (int) $detail->material_id || (string) $key === $materialCode) {
                            $partialKey = $key;
                            break;
                        }
                    }

                    if ($partialKey !== null) {
                        $partialQty = round((float) $partialQuantities[$partialKey], 4);
                        if ($partialQty > 0 && $partialQty <= $restoreQuantity) {
                            $restoreQuantity = $partialQty;
                        } elseif ($partialQty > $restoreQuantity) {
                            $restoreQuantity = $restoreQuantity;
                        }
                    }

                    if ($restoreQuantity <= 0.00005) {
                        continue;
                    }

                    $allocation = $allocations->get($detail->id);
                    $inputUnitId = $allocation?->logistic_unit_id
                        ?? Arr::get((array) $detail->metadata, 'position_logistic_unit_snapshot.id');

                    $restorationOps[] = [
                        'movement' => $movement,
                        'detail' => $detail,
                        'allocation' => $allocation,
                        'input_unit_id' => $inputUnitId ? (int) $inputUnitId : null,
                        'quantity' => $restoreQuantity,
                    ];

                    $rootSummary['restoration_lines'][] = [
                        'material_id' => (int) $detail->material_id,
                        'material_code' => $materialCode,
                        'quantity' => $restoreQuantity,
                        'movement_id' => (int) $movement->id,
                        'movement_detail_id' => (int) $detail->id,
                    ];
                }
            }

            $rootSummary['action'] = empty($restorationOps) && ! $isComplete ? 'skipped' : ($apply ? 'applied' : 'pending');

            if (empty($restorationOps) && ! $isComplete) {
                $rootSummary['reason'] = 'No se encontraron consumos pendientes para devolver.';
                $roots[] = $rootSummary;

                continue;
            }

            if (! $apply) {
                $roots[] = $rootSummary;

                continue;
            }

            $ledgerEvents = [];
            $syncPairs = [];
            $inputUnitIds = [];
            $reversed = [];

            foreach ($restorationOps as $operation) {
                $movement = $operation['movement'];
                $detail = $operation['detail'];
                $restoreQuantity = $operation['quantity'];
                $target = $this->restorePosition(
                    $detail,
                    $movement,
                    $operation['input_unit_id'],
                    $restoreQuantity,
                );

                if ($target['logistic_unit_id']) {
                    $inputUnitIds[] = $target['logistic_unit_id'];
                }

                $syncPairs[$target['material_id'].'-'.$target['location_id']] = [
                    'material_id' => $target['material_id'],
                    'location_id' => $target['location_id'],
                ];

                $ledgerEvents[] = [
                    'event_type' => 'PRODUCTION_REVERSAL_IN',
                    'movement_id' => $movement->id,
                    'movement_detail_id' => $detail->id,
                    'allocation_id' => $operation['allocation']?->id,
                    'material_id' => $target['material_id'],
                    'logistic_unit_id' => $target['logistic_unit_id'],
                    'location_id' => $target['location_id'],
                    'signed_quantity' => $restoreQuantity,
                    'stock_effect' => 'in',
                    'occurred_at' => now(),
                    'actor_user_id' => $reconciliationUserId,
                    'actor_name_snapshot' => 'Reconciliación histórica',
                    'payload' => [
                        'workflow' => 'semi_finished_production_reversal',
                        'reconciliation' => 'legacy_split_child_reversal',
                        'production_id' => (int) $root->id,
                        'reversal_scope' => 'production',
                        'legacy_child_production_ids' => $rootSummary['child_production_ids'],
                        'output_logistic_unit_ids' => collect($groupChildren)
                            ->pluck('unit.id')
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all(),
                        'output_lpns' => $rootSummary['child_lpns'],
                        'reversed_quantity' => $legacyQuantity,
                        'origin_movement_id' => (int) $movement->id,
                        'origin_movement_folio' => $movement->folio,
                        'reason' => $reason,
                    ],
                ];

                $reversed[] = [
                    'material_id' => $target['material_id'],
                    'position_id' => $target['position_id'],
                    'location_id' => $target['location_id'],
                    'logistic_unit_id' => $target['logistic_unit_id'],
                    'quantity' => $restoreQuantity,
                    'origin_movement_id' => (int) $movement->id,
                    'reversed_unit_ids' => $rootSummary['child_production_ids'],
                ];

                $restoredDetails[(int) $detail->id] = round($alreadyDetail + $restoreQuantity, 4);
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

            $reversalUnits = (array) ($reversal['units'] ?? []);

            foreach ($groupChildren as $child) {
                $childProduction = $child['production'];
                $childUnit = $child['unit'];
                $reversalUnits[(string) $childUnit->id] = [
                    'logistic_unit_id' => (int) $childUnit->id,
                    'license_plate_number' => $childUnit->license_plate_number,
                    'quantity' => (float) $childProduction->quantity,
                    'reason' => $reason,
                    'reversed_at' => optional($childProduction->reversed_at)->toISOString(),
                    'reversed_by_id' => (int) $childProduction->reversed_by_id,
                    'source' => 'legacy_split_child_reconciliation',
                ];
            }

            $newReversal = [
                ...$reversal,
                'restored' => [
                    ...((array) ($reversal['restored'] ?? [])),
                    ...$reversed,
                ],
                'units' => $reversalUnits,
                'restored_quantity' => min($restoredAfter, $rootQuantity),
                'restored_details' => $restoredDetails,
                'last_reason' => $reason,
                'last_reversed_by_id' => null,
                'last_reversed_at' => now()->toISOString(),
                'legacy_reconciliation' => [
                    'applied_at' => now()->toISOString(),
                    'quantity' => $legacyQuantity,
                    'child_production_ids' => $rootSummary['child_production_ids'],
                    'reason' => $reason,
                ],
            ];

            if ($isComplete) {
                $newReversal['reversed_at'] = now()->toISOString();
                $newReversal['reversed_by_id'] = null;
                $newReversal['reason'] = $reason;
            }

            $root->forceFill([
                'status' => $isComplete ? 'reverted' : 'completed',
                'reversed_at' => $isComplete ? now() : null,
                'reversed_by_id' => $isComplete ? null : null,
                'reversal_reason' => $isComplete ? $reason : null,
                'metadata' => [
                    ...$metadata,
                    'reversal' => $newReversal,
                ],
            ])->save();

            foreach ($groupChildren as $child) {
                $this->markLegacyChildReconciled(
                    $child['production'],
                    $root,
                    'applied',
                    $reason,
                );
            }

            $roots[] = $rootSummary;
        }

        return [
            'summary' => [
                'mode' => $apply ? 'apply' : 'dry-run',
                'legacy_child_productions' => $children->count(),
                'root_groups' => count($roots),
                'applied_roots' => collect($roots)->where('action', 'applied')->count(),
                'pending_roots' => collect($roots)->where('action', 'pending')->count(),
                'skipped_roots' => collect($roots)->where('action', 'skipped')->count(),
                'skipped_children' => count($skipped),
            ],
            'roots' => $roots,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array{production: InventorySemiFinishedProduction, unit: InventoryLogisticUnit}  $child
     * @return array<string, mixed>
     */
    private function legacyChildSkip(array $child, string $reason): array
    {
        return [
            'child_production_id' => (int) $child['production']->id,
            'logistic_unit_id' => (int) $child['unit']->id,
            'lpn' => $child['unit']->license_plate_number,
            'quantity' => (float) $child['production']->quantity,
            'reason' => $reason,
        ];
    }

    private function markLegacyChildReconciled(
        InventorySemiFinishedProduction $child,
        InventorySemiFinishedProduction $root,
        string $status,
        string $reason,
    ): void {
        $metadata = (array) $child->metadata;
        $reversal = (array) ($metadata['reversal'] ?? []);
        $reversal['legacy_reconciliation'] = [
            'status' => $status,
            'root_production_id' => (int) $root->id,
            'quantity' => (float) $child->quantity,
            'reason' => $reason,
            'applied_at' => now()->toISOString(),
        ];

        $child->forceFill([
            'metadata' => [
                ...$metadata,
                'reversal' => $reversal,
            ],
        ])->save();
    }

    private function rootProductionForReconciliation(InventoryLogisticUnit $unit): ?InventorySemiFinishedProduction
    {
        $originId = (int) Arr::get((array) $unit->metadata, 'origin_unit_id', 0);
        $visited = [(int) $unit->id => true];
        $root = null;

        while ($originId > 0 && ! isset($visited[$originId])) {
            $visited[$originId] = true;
            $origin = InventoryLogisticUnit::query()->find($originId);

            if (! $origin) {
                break;
            }

            $production = InventorySemiFinishedProduction::query()
                ->where('logistic_unit_id', $origin->id)
                ->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->first();

            if ($production) {
                $root = $production;
            }

            $originId = (int) Arr::get((array) $origin->metadata, 'origin_unit_id', 0);
        }

        return $root;
    }

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

        $originId = (int) Arr::get((array) $unit->metadata, 'origin_unit_id', 0);
        $visited = [(int) $unit->id => true];

        while ($originId > 0 && ! isset($visited[$originId])) {
            $visited[$originId] = true;
            $origin = InventoryLogisticUnit::query()->find($originId);

            if (! $origin) {
                break;
            }

            $production = InventorySemiFinishedProduction::query()
                ->where('logistic_unit_id', $origin->id)
                ->orderByDesc('id')
                ->first();

            if ($production) {
                return $production;
            }

            $originId = (int) Arr::get((array) $origin->metadata, 'origin_unit_id', 0);
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
     * @return Collection<int, InventoryLogisticUnit>
     */
    private function familyUnits(InventorySemiFinishedProduction $production): Collection
    {
        $cacheKey = (int) ($production->id ?? 0);

        if ($cacheKey > 0 && isset($this->familyCache[$cacheKey])) {
            return $this->familyCache[$cacheKey];
        }

        $rootId = (int) $production->logistic_unit_id;
        $candidates = InventoryLogisticUnit::query()
            ->where(function ($query) use ($rootId, $production) {
                $query->whereKey($rootId)
                    ->orWhere(function ($referenceQuery) use ($production) {
                        $referenceQuery
                            ->where('reference_type', 'semi_finished_production')
                            ->where('reference_id', (int) $production->technical_sheet_id);
                    });
            })
            ->get();

        $byId = $candidates->keyBy('id');

        $family = $candidates
            ->filter(function (InventoryLogisticUnit $candidate) use ($rootId, $byId) {
                $current = $candidate;
                $visited = [];

                while ($current) {
                    if ((int) $current->id === $rootId) {
                        return true;
                    }

                    if (isset($visited[(int) $current->id])) {
                        return false;
                    }

                    $visited[(int) $current->id] = true;
                    $originId = (int) Arr::get((array) $current->metadata, 'origin_unit_id', 0);

                    if ($originId <= 0) {
                        return false;
                    }

                    $current = $byId->get($originId);
                }

                return false;
            })
            ->values();

        if ($cacheKey > 0) {
            $this->familyCache[$cacheKey] = $family;
        }

        return $family;
    }

    private function expectedQuantity(
        InventoryLogisticUnit $unit,
        InventorySemiFinishedProduction $production,
        int $familyCount,
    ): float {
        if ($familyCount > 1) {
            return round((float) $unit->base_quantity, 4);
        }

        return round((float) $production->quantity, 4);
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
                'batch' => [
                    'is_split' => false,
                    'unit_count' => 0,
                    'total_units' => 0,
                    'total_quantity' => 0.0,
                    'production_quantity' => 0.0,
                    'reversed_quantity' => 0.0,
                    'reversible' => false,
                    'reason' => null,
                ],
            ];
        }

        $family = $this->familyUnits($production);
        $familyCount = $family->count();
        $expected = $this->expectedQuantity($unit, $production, $familyCount);
        $reason = $this->blockingReason($unit, $production, $user, $expected);

        $originId = (int) Arr::get((array) $unit->metadata, 'origin_unit_id', 0);
        if (! $production->exists && $originId > 0) {
            $reason = 'Este pallet es hijo de una división y no tiene consumo registrado para revertir.';
        }

        $activeUnits = $family
            ->filter(fn (InventoryLogisticUnit $familyUnit) => $familyUnit->status === 'active')
            ->values();

        $batchReason = null;

        foreach ($activeUnits as $activeUnit) {
            $blocked = $this->blockingReason(
                $activeUnit,
                $production,
                $user,
                $this->expectedQuantity($activeUnit, $production, $familyCount),
            );

            if ($blocked !== null) {
                $batchReason = $blocked;
                break;
            }
        }

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
            'batch' => [
                'is_split' => $familyCount > 1,
                'unit_count' => $activeUnits->count(),
                'total_units' => $familyCount,
                'total_quantity' => round((float) $activeUnits->sum(fn (InventoryLogisticUnit $activeUnit) => (float) $activeUnit->base_quantity), 4),
                'production_quantity' => round((float) $production->quantity, 4),
                'reversed_quantity' => round((float) Arr::get((array) $production->metadata, 'reversal.restored_quantity', 0), 4),
                'reversible' => $activeUnits->isNotEmpty() && $batchReason === null,
                'reason' => $batchReason,
            ],
        ];
    }

    private function blockingReason(
        InventoryLogisticUnit $unit,
        InventorySemiFinishedProduction $production,
        ?User $user,
        ?float $expected = null,
    ): ?string {
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
        $expected = round($expected ?? (float) $production->quantity, 4);

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
        float $reversedQuantity,
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
            'reversed_quantity' => $reversedQuantity,
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
