<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockPosition;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MovementService
{
    public function __construct(
        private readonly InventoryTransactionService $inventoryTransactionService,
    )
    {
    }

    public function create(array $data, int $userId, bool $apply = false): InventoryMovement
    {
        $movement = DB::transaction(function () use ($data, $userId): InventoryMovement {
            $type = InventoryMovementType::query()->findOrFail($data['movement_type_id']);
            $detailsTableHasMetadata = Schema::hasColumn('inventory_movement_details', 'metadata');

            $details = collect($data['details'] ?? [])
                ->map(function (array $detail) use ($detailsTableHasMetadata): array {
                    $payload = [
                        'material_id' => (int) $detail['material_id'],
                        'sentido' => $detail['sentido'] ?? 'salida',
                        'cantidad' => (float) $detail['cantidad'],
                        'costo_referencial' => isset($detail['costo_referencial']) && $detail['costo_referencial'] !== ''
                            ? (float) $detail['costo_referencial']
                            : null,
                        'observacion' => $detail['observacion'] ?? null,
                    ];

                    if ($detailsTableHasMetadata) {
                        $positionId = isset($detail['position_id']) && $detail['position_id'] !== ''
                            ? (int) $detail['position_id']
                            : null;

                        $payload['metadata'] = [
                            ...$this->buildPositionMetadata($positionId),
                        ];
                    }

                    return $payload;
                })
                ->filter(fn (array $detail) => $detail['material_id'] > 0 && $detail['cantidad'] > 0)
                ->values();

            $this->validatePayload($type, $data, $details->all());

            $movement = InventoryMovement::create([
                'folio' => $this->generateFolio(),
                'movement_type_id' => $type->id,
                'fecha_movimiento' => $data['fecha_movimiento'],
                'origin_location_id' => ($data['origin_location_id'] ?? null) ?: null,
                'destination_location_id' => ($data['destination_location_id'] ?? null) ?: null,
                'material_request_id' => ($data['material_request_id'] ?? null) ?: null,
                'return_id' => ($data['return_id'] ?? null) ?: null,
                'estado' => 'borrador',
                'referencia_tipo' => ($data['referencia_tipo'] ?? null) ?: null,
                'referencia_id' => ($data['referencia_id'] ?? null) ?: null,
                'motivo' => ($data['motivo'] ?? null) ?: null,
                'observacion' => ($data['observacion'] ?? null) ?: null,
                'created_by' => $userId,
                'scan_session_uuid' => $data['scan_session_uuid'] ?? null,
                'waste_reason_id' => $data['waste_reason_id'] ?? null,
                'requires_photo_evidence' => (bool) ($data['requires_photo_evidence'] ?? false),
                'metadata' => Arr::wrap($data['metadata'] ?? []),
            ]);

            foreach ($details as $detail) {
                $movement->details()->create($detail);
            }

            return $movement->load(['type', 'details.material']);
        });

        if ($apply) {
            return $this->apply($movement, $userId);
        }

        return $movement;
    }

    public function apply(InventoryMovement $movement, int $userId): InventoryMovement
    {
        return $this->inventoryTransactionService->applyMovement($movement, $userId);
    }

    public function confirmReceipt(InventoryMovement $movement, int $userId, ?array $transferUnitIds = null): InventoryMovement
    {
        $movement->loadMissing('transferUnits');

        if ($movement->transferUnits->isNotEmpty()) {
            return $this->inventoryTransactionService->confirmTransferReceipt($movement, $userId, $transferUnitIds ?? []);
        }

        return DB::transaction(function () use ($movement, $userId): InventoryMovement {
            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::query()->lockForUpdate()->findOrFail($movement->id);

            if ($movement->estado !== 'aplicado') {
                throw ValidationException::withMessages([
                    'movement' => 'Solo se pueden confirmar movimientos aplicados.',
                ]);
            }

            if (! $movement->destination_location_id) {
                throw ValidationException::withMessages([
                    'movement' => 'Este movimiento no requiere recepción conforme.',
                ]);
            }

            $movement->forceFill([
                'estado' => 'confirmado',
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ])->save();

            return $movement->fresh(['type', 'origin', 'destination', 'creator', 'approver', 'confirmer', 'details.material']);
        });
    }

    public function rejectTransferUnits(InventoryMovement $movement, int $userId, array $transferUnitIds, string $reason): InventoryMovement
    {
        return $this->inventoryTransactionService->rejectTransferReceipt($movement, $userId, $transferUnitIds, $reason);
    }

    private function validatePayload(InventoryMovementType $type, array $data, array $details): void
    {
        if ($type->requiere_origen && empty($data['origin_location_id'])) {
            throw ValidationException::withMessages([
                'origin_location_id' => 'La ubicación de origen es obligatoria para este movimiento.',
            ]);
        }

        if ($type->requiere_destino && empty($data['destination_location_id'])) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'La ubicación de destino es obligatoria para este movimiento.',
            ]);
        }

        if ($type->requiere_motivo && blank($data['motivo'] ?? null)) {
            throw ValidationException::withMessages([
                'motivo' => 'Debes indicar un motivo para este movimiento.',
            ]);
        }

        if (count($details) === 0) {
            throw ValidationException::withMessages([
                'details' => 'Debes agregar al menos un detalle.',
            ]);
        }

        $requiresExplicitPosition = in_array($type->codigo, ['CONSUMO', 'MERMA', 'AJUSTE_NEG'], true)
            && ! empty($data['origin_location_id'])
            && Schema::hasTable('inventory_stock_positions');

        if ($requiresExplicitPosition) {
            foreach (($data['details'] ?? []) as $index => $rawDetail) {
                $materialId = (int) ($rawDetail['material_id'] ?? 0);
                if ($materialId <= 0) {
                    continue;
                }

                $hasPositionedStock = InventoryStockPosition::query()
                    ->where('location_id', (int) $data['origin_location_id'])
                    ->where('material_id', $materialId)
                    ->where('quantity', '>', 0)
                    ->exists();

                if ($hasPositionedStock && empty($rawDetail['position_id'])) {
                    throw ValidationException::withMessages([
                        "details.$index.position_id" => 'Debes seleccionar una posición de stock para este movimiento.',
                    ]);
                }
            }
        }

        if ($type->codigo === 'PRODUCCION_INTERMEDIA') {
            $flows = collect($details)->pluck('sentido')->filter()->unique()->values();
            if (! $flows->contains('salida') || ! $flows->contains('entrada')) {
                throw ValidationException::withMessages([
                    'details' => 'La producción intermedia requiere materiales de salida y productos de entrada.',
                ]);
            }
        }
    }

    private function generateFolio(): string
    {
        return 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function buildPositionMetadata(?int $positionId): array
    {
        if (! $positionId || ! Schema::hasTable('inventory_stock_positions')) {
            return [
                'position_id' => $positionId,
            ];
        }

        $position = InventoryStockPosition::query()
            ->with(['location:id,codigo,nombre', 'logisticUnit:id,license_plate_number'])
            ->find($positionId);

        if (! $position) {
            return [
                'position_id' => $positionId,
            ];
        }

        return [
            'position_id' => $position->id,
            'position_quantity_snapshot' => round((float) $position->quantity, 4),
            'position_status_snapshot' => $position->status,
            'position_lot_code_snapshot' => $position->lot_code,
            'position_location_snapshot' => $position->location ? [
                'id' => $position->location->id,
                'codigo' => $position->location->codigo,
                'nombre' => $position->location->nombre,
            ] : null,
            'position_logistic_unit_snapshot' => $position->logisticUnit ? [
                'id' => $position->logisticUnit->id,
                'license_plate_number' => $position->logisticUnit->license_plate_number,
            ] : null,
        ];
    }
}
