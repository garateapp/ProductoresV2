<?php

namespace App\Services\Inventory;

use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LogisticUnitService
{
    public function __construct(
        private readonly StockProjectionService $stockProjectionService,
        private readonly LotService $lotService,
    ) {}

    public function create(array $data, int $userId): InventoryLogisticUnit
    {
        return $this->createMany($data, 1, $userId)->first();
    }

    public function createMany(array $data, int $count, int $userId): \Illuminate\Support\Collection
    {
        if ($count < 1) {
            throw ValidationException::withMessages([
                'pallet_count' => 'La cantidad de pallets debe ser al menos 1.',
            ]);
        }

        return DB::transaction(function () use ($data, $count, $userId): \Illuminate\Support\Collection {
            $lpnCodes = $this->resolveLpnCodes((int) $data['material_id'], $count, $data['license_plate_number'] ?? null);
            $lotCode = $this->resolveLotCode($data['lot_code'] ?? null);

            $units = collect();

            foreach ($lpnCodes as $lpn) {
                $unit = InventoryLogisticUnit::create([
                    ...$data,
                    'license_plate_number' => $lpn,
                    'lot_code' => $lotCode,
                    'created_by' => $userId,
                ]);

                $this->createStockPosition($unit);
                $this->syncLpnQuantity($unit);

                $units->push($unit);
            }

            return $units;
        });
    }

    public function split(InventoryLogisticUnit $unit, int $count, array $data, int $userId): array
    {
        if ($count < 2) {
            throw ValidationException::withMessages([
                'pallet_count' => 'Para dividir se necesitan al menos 2 pallets.',
            ]);
        }

        return DB::transaction(function () use ($unit, $count, $data, $userId): array {
            $unit = InventoryLogisticUnit::query()
                ->whereKey($unit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($unit->status !== 'active') {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'Solo se pueden dividir LPN en estado activo.',
                ]);
            }

            $total = (float) $unit->available_quantity;
            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'logistic_unit' => 'El LPN no tiene stock para dividir.',
                ]);
            }

            $perPallet = round($total / $count, 4);
            $lastPallet = round($total - ($perPallet * ($count - 1)), 4);

            if ($perPallet <= 0 || $lastPallet <= 0) {
                throw ValidationException::withMessages([
                    'pallet_count' => 'La cantidad no alcanza para dividir en '.$count.' pallets con al menos 0.0001 por pallet.',
                ]);
            }

            $lpnCodes = $this->suggestLicensePlateNumbers((int) $unit->material_id, $count);

            $newUnits = [];

            foreach ($lpnCodes as $index => $lpn) {
                $quantity = $index === $count - 1 ? $lastPallet : $perPallet;

                $newUnit = InventoryLogisticUnit::create([
                    'license_plate_number' => $lpn,
                    'material_id' => $unit->material_id,
                    'current_location_id' => $unit->current_location_id,
                    'spatial_prefix' => $data['spatial_prefix'] ?? $unit->spatial_prefix,
                    'spatial_column' => $data['spatial_column'] ?? $unit->spatial_column,
                    'spatial_row' => $data['spatial_row'] ?? $unit->spatial_row,
                    'status' => 'active',
                    'base_quantity' => $quantity,
                    'available_quantity' => $quantity,
                    'unit_id' => $unit->unit_id,
                    'lot_code' => $unit->lot_code,
                    'supplier_lot' => $unit->supplier_lot,
                    'production_batch' => $unit->production_batch,
                    'reference_type' => $unit->reference_type,
                    'reference_id' => $unit->reference_id,
                    'received_at' => $unit->received_at,
                    'dispatch_guide' => $unit->dispatch_guide,
                    'created_by' => $userId,
                    'metadata' => [
                        'created_from' => 'logistic_unit_split',
                        'origin_unit_id' => $unit->id,
                        'origin_lpn' => $unit->license_plate_number,
                    ],
                ]);

                $this->createStockPosition($newUnit);
                $this->syncLpnQuantity($newUnit);

                $newUnits[] = $newUnit;
            }

            $this->close($unit, $userId, 'Dividido en '.$count.' pallets: '.implode(', ', $lpnCodes));

            if ($unit->current_location_id) {
                $this->stockProjectionService->syncMaterialLocationFromPositions(
                    (int) $unit->material_id,
                    (int) $unit->current_location_id,
                );
            }

            return $newUnits;
        });
    }

    private function resolveLpnCodes(int $materialId, int $count, ?string $provided): array
    {
        if ($count === 1 && ! blank($provided)) {
            return [(string) $provided];
        }

        return $this->suggestLicensePlateNumbers($materialId, $count);
    }

    private function resolveLotCode(?string $lotCode): string
    {
        if (blank($lotCode)) {
            return $this->lotService->nextCode();
        }

        $this->lotService->advancePast($lotCode);

        return (string) $lotCode;
    }

    private function createStockPosition(InventoryLogisticUnit $unit): void
    {
        if (! $this->positionsTableExists() || ! $unit->current_location_id || (float) $unit->available_quantity <= 0) {
            return;
        }

        InventoryStockPosition::updateOrCreate(
            [
                'material_id' => $unit->material_id,
                'location_id' => $unit->current_location_id,
                'logistic_unit_id' => $unit->id,
                'lot_code' => $unit->normalizedLotCode(),
                'status' => 'available',
            ],
            [
                'quantity' => (float) $unit->available_quantity,
                'metadata' => [
                    'created_from' => 'logistic_unit_registration',
                    'license_plate_number' => $unit->license_plate_number,
                ],
            ],
        );

        $this->stockProjectionService->syncMaterialLocationFromPositions(
            (int) $unit->material_id,
            (int) $unit->current_location_id,
        );
    }

    public function findByCode(string $code): ?InventoryLogisticUnit
    {
        return InventoryLogisticUnit::query()
            ->where('license_plate_number', trim($code))
            ->first();
    }

    public function suggestLicensePlateNumber(int $materialId, bool $isWaste = false): string
    {
        return $this->suggestLicensePlateNumbers($materialId, 1, $isWaste)[0];
    }

    public function suggestLicensePlateNumbers(int $materialId, int $count, bool $isWaste = false): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('La cantidad de LPN a sugerir debe ser mayor a cero.');
        }

        $material = InventoryMaterial::query()->findOrFail($materialId);
        $sequence = InventoryLogisticUnit::query()
            ->where('material_id', $material->id)
            ->count() + 1;

        $result = [];

        while (count($result) < $count) {
            $licensePlateNumber = $this->formatSuggestedLicensePlateNumber($material, $sequence, $isWaste);
            $sequence++;

            if (! InventoryLogisticUnit::query()->where('license_plate_number', $licensePlateNumber)->exists()) {
                $result[] = $licensePlateNumber;
            }
        }

        return $result;
    }

    public function transferPosition(InventoryStockPosition $position, int $toLocationId, float $quantity, int $userId, bool $syncStockProjection = true): void
    {
        unset($userId);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser numérica y positiva.',
            ]);
        }

        if ((int) $position->location_id === $toLocationId) {
            throw ValidationException::withMessages([
                'to_location_id' => 'La ubicación destino debe ser distinta al origen.',
            ]);
        }

        if ($quantity > $position->quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'La posicion seleccionada no tiene stock suficiente.',
            ]);
        }

        DB::transaction(function () use ($position, $toLocationId, $quantity, $syncStockProjection) {
            $position = InventoryStockPosition::query()
                ->lockForUpdate()
                ->with('logisticUnit')
                ->findOrFail($position->id);

            $logisticUnit = $position->logisticUnit;

            // 1. Decrementar origen
            $position->decrement('quantity', $quantity);
            if ($position->fresh()->quantity <= 0) {
                $position->delete();
            }

            // 2. Incrementar o crear destino (compatible por material, LPN y lote)
            $target = InventoryStockPosition::firstOrNew([
                'material_id' => $position->material_id,
                'location_id' => $toLocationId,
                'logistic_unit_id' => $position->logistic_unit_id,
                'lot_code' => $position->lot_code,
                'status' => $position->status,
            ]);

            $target->quantity = ($target->quantity ?? 0) + $quantity;
            $target->save();

            // 3. Sincronizar disponible en LPN si aplica
            if ($logisticUnit) {
                $this->syncLpnQuantity($logisticUnit);
            }

            if ($syncStockProjection) {
                $this->stockProjectionService->syncMaterialLocationFromPositions(
                    (int) $position->material_id,
                    (int) $position->location_id,
                );
                $this->stockProjectionService->syncMaterialLocationFromPositions(
                    (int) $position->material_id,
                    $toLocationId,
                );
            }
        });
    }



    public function relocate(
    InventoryLogisticUnit $unit,
    int $toLocationId,
    int $userId,
    ?int $materialRequestId = null
): InventoryLogisticUnit {
    unset($userId);

    if ((int) $unit->current_location_id === $toLocationId) {
        throw ValidationException::withMessages([
            'to_location_id' => 'La ubicación destino debe ser distinta al origen.',
        ]);
    }

    $syncPairs = [];

    $unit = DB::transaction(function () use ($unit, $toLocationId, $materialRequestId, &$syncPairs) {
        $unit = InventoryLogisticUnit::query()
            ->whereKey($unit->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! in_array($unit->status, ['active', 'in_transit'], true)) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'La unidad logística no está activa.',
            ]);
        }

        if ((int) $unit->current_location_id === $toLocationId) {
            throw ValidationException::withMessages([
                'to_location_id' => 'La ubicación destino debe ser distinta al origen.',
            ]);
        }

        $fromLocationId = $unit->current_location_id;

        if ($this->positionsTableExists() && $fromLocationId) {
            $positions = InventoryStockPosition::query()
                ->where('logistic_unit_id', $unit->id)
                ->where('location_id', $fromLocationId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($positions as $position) {
                $quantity = (float) $position->quantity;

                if ($quantity <= 0) {
                    continue;
                }

                $target = InventoryStockPosition::query()
                    ->where('material_id', $position->material_id)
                    ->where('location_id', $toLocationId)
                    ->where('logistic_unit_id', $position->logistic_unit_id)
                    ->where('lot_code', $position->lot_code)
                    ->where('status', $position->status)
                    ->lockForUpdate()
                    ->first();

                if ($target) {
                    $target->quantity = round(((float) $target->quantity) + $quantity, 4);
                    $target->save();
                } else {
                    InventoryStockPosition::create([
                        'material_id' => $position->material_id,
                        'location_id' => $toLocationId,
                        'logistic_unit_id' => $position->logistic_unit_id,
                        'lot_code' => $position->lot_code,
                        'status' => $position->status,
                        'quantity' => $quantity,
                        'metadata' => [
                            ...((array) $position->metadata),
                            'relocated_from_location_id' => $fromLocationId,
                            'material_request_id' => $materialRequestId,
                        ],
                    ]);
                }

                $syncPairs[] = [
                    'material_id' => (int) $position->material_id,
                    'from_location_id' => (int) $fromLocationId,
                    'to_location_id' => (int) $toLocationId,
                ];

                $position->delete();
            }
        }

        $unit->forceFill([
            'current_location_id' => $toLocationId,
            'status' => 'active',
            'last_moved_at' => now(),
        ])->save();

        $this->syncLpnQuantity($unit);

        return $unit->fresh();
    }, 3);

    foreach ($syncPairs as $pair) {
        $this->stockProjectionService->syncMaterialLocationFromPositions(
            $pair['material_id'],
            $pair['from_location_id'],
        );

        $this->stockProjectionService->syncMaterialLocationFromPositions(
            $pair['material_id'],
            $pair['to_location_id'],
        );
    }

    return $unit;
}

    public function markInTransit(InventoryLogisticUnit $unit, int $userId, array $context = []): InventoryLogisticUnit
    {
        unset($userId);

        if ($unit->status !== 'active') {
            throw ValidationException::withMessages([
                'logistic_unit' => 'La unidad logística no está disponible para traslado.',
            ]);
        }

        $unit->forceFill([
            'current_location_id' => null,
            'status' => 'in_transit',
            'last_moved_at' => now(),
            'metadata' => [
                ...((array) $unit->metadata),
                'transfer_context' => $context,
            ],
        ])->save();

        return $unit->fresh();
    }

    public function consume(InventoryLogisticUnit $unit, float $quantity, int $userId, array $context = []): InventoryLogisticUnit
    {
        unset($userId, $context);

        return $this->decreaseQuantity($unit, $quantity, 'consumed');
    }

    public function waste(InventoryLogisticUnit $unit, float $quantity, int $userId, array $context = []): InventoryLogisticUnit
    {
        unset($userId, $context);

        return $this->decreaseQuantity($unit, $quantity, 'waste');
    }

    public function syncLogisticUnitQuantity(InventoryLogisticUnit $unit): InventoryLogisticUnit
    {
        return $this->syncLpnQuantity($unit);
    }

    private function decreaseQuantity(InventoryLogisticUnit $unit, float $quantity, string $finalStatus): InventoryLogisticUnit
    {
        // Nota: Esta lógica es legacy. La nueva fuente de verdad
        // debe descontar de inventory_stock_positions y sincronizar
        // el total hacia la unidad logística para compatibilidad.

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        $available = (float) $unit->available_quantity;
        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad supera lo disponible en el pallet.',
            ]);
        }

        $remaining = round($available - $quantity, 4);

        $unit->forceFill([
            'available_quantity' => $remaining,
            'status' => $remaining <= 0 ? $finalStatus : $unit->status,
            'closed_at' => $remaining <= 0 ? now() : $unit->closed_at,
        ])->save();

        return $unit->fresh();
    }

    public function update(InventoryLogisticUnit $unit, array $data, int $userId): InventoryLogisticUnit
    {
        $allowedFields = [
            'license_plate_number', 'material_id', 'current_location_id',
            'spatial_prefix', 'spatial_column', 'spatial_row',
            'base_quantity', 'available_quantity', 'unit_id',
            'lot_code', 'supplier_lot', 'production_batch',
            'dispatch_guide',
        ];

        return DB::transaction(function () use ($unit, $data, $userId, $allowedFields) {
            $changes = [];
            foreach ($allowedFields as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                $oldValue = $unit->{$field};
                $newValue = $data[$field];
                if ($oldValue != $newValue) {
                    $changes[] = [
                        'field' => $field,
                        'from' => $oldValue,
                        'to' => $newValue,
                        'changed_by' => $userId,
                        'changed_at' => now()->toISOString(),
                    ];
                }
            }

            if (empty($changes)) {
                return $unit->fresh();
            }

            $metadata = (array) ($unit->metadata ?? []);
            $metadata['changelog'] = array_merge(
                (array) ($metadata['changelog'] ?? []),
                $changes,
            );

            $unit->forceFill([
                ...$data,
                'metadata' => $metadata,
                'last_moved_at' => now(),
            ])->save();

            if ($unit->wasChanged('available_quantity') && $this->positionsTableExists()) {
                $position = InventoryStockPosition::updateOrCreate(
                    [
                        'material_id' => $unit->material_id,
                        'location_id' => $unit->current_location_id,
                        'logistic_unit_id' => $unit->id,
                        'lot_code' => $unit->normalizedLotCode(),
                        'status' => 'available',
                    ],
                    [
                        'quantity' => (float) ($data['available_quantity'] ?? $unit->available_quantity),
                        'metadata' => [
                            'created_from' => 'logistic_unit_update',
                            'license_plate_number' => $unit->license_plate_number,
                        ],
                    ],
                );
            }

            $this->syncLpnQuantity($unit);

            return $unit->fresh();
        });
    }

    public function close(InventoryLogisticUnit $unit, int $userId, ?string $reason = null): InventoryLogisticUnit
    {
        return DB::transaction(function () use ($unit, $userId, $reason) {
            $metadata = (array) ($unit->metadata ?? []);
            $metadata['changelog'] = array_merge(
                (array) ($metadata['changelog'] ?? []),
                [[
                    'field' => 'status',
                    'from' => $unit->status,
                    'to' => 'closed',
                    'reason' => $reason ?? 'Eliminación manual',
                    'changed_by' => $userId,
                    'changed_at' => now()->toISOString(),
                ]],
            );

            $unit->forceFill([
                'status' => 'closed',
                'available_quantity' => 0,
                'closed_at' => now(),
                'last_moved_at' => now(),
                'metadata' => $metadata,
            ])->save();

            if ($this->positionsTableExists()) {
                InventoryStockPosition::query()
                    ->where('logistic_unit_id', $unit->id)
                    ->delete();
            }

            return $unit->fresh();
        });
    }

    private function syncLpnQuantity(InventoryLogisticUnit $unit): InventoryLogisticUnit
    {
        if (! $this->positionsTableExists()) {
            return $unit->fresh();
        }

        $availableQuantity = (float) InventoryStockPosition::query()
            ->where('logistic_unit_id', $unit->id)
            ->sum('quantity');

        $currentStatus = (string) ($unit->status ?? '');
        $nextStatus = $availableQuantity <= 0
            ? 'closed'
            : ($currentStatus === 'closed' || $currentStatus === '' ? 'active' : $currentStatus);

        $unit->forceFill([
            'available_quantity' => round($availableQuantity, 4),
            'status' => $nextStatus,
            'closed_at' => $availableQuantity <= 0 ? ($unit->closed_at ?? now()) : null,
        ])->save();

        return $unit->fresh();
    }

    private function positionsTableExists(): bool
    {
        return Schema::hasTable('inventory_stock_positions');
    }

    private function formatSuggestedLicensePlateNumber(InventoryMaterial $material, int $sequence, bool $isWaste = false): string
    {
        $suffix = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        $serviceId = (string) ($material->service_id ?: 0);
        $materialCode = preg_replace('/\s+/', '', trim((string) $material->codigo)) ?: 'MAT'.$material->id;

        $prefix = $isWaste ? 'MERMA-' : '';
        $maxMaterialCodeLength = max(1, 100 - strlen($serviceId) - strlen($suffix) - strlen($prefix) - 2);

        return $prefix.$serviceId.'-'.substr($materialCode, 0, $maxMaterialCodeLength).'-'.$suffix;
    }
}
