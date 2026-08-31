<?php

namespace App\Services\Inventory;

use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementAllocation;
use App\Models\InventoryMovementDetail;
use App\Models\InventoryStockPosition;
use App\Models\InventoryTransferUnit;
use App\Notifications\InventoryTransferReturnPendingNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InventoryTransactionService
{
    public function __construct(
        private readonly StockProjectionService $stockProjectionService,
        private readonly LedgerService $ledgerService,
        private readonly LogisticUnitService $logisticUnitService,
        private readonly WasteManagementService $wasteManagementService,
    ) {
    }

    public function applyMovement(InventoryMovement $movement, int $userId, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $userId, $context): InventoryMovement {
            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::query()
                ->with(['type', 'details.material', 'origin', 'destination.assignedUsers', 'creator', 'transferUnits.logisticUnit'])
                ->lockForUpdate()
                ->findOrFail($movement->id);

            if ($movement->estado !== 'borrador') {
                throw ValidationException::withMessages([
                    'movement' => 'Solo se pueden aplicar movimientos en borrador.',
                ]);
            }

            if ($this->usesTransferUnits($movement)) {
                return $this->applyTransferDispatch($movement, $userId);
            }

            $ledgerEvents = [];
            $createdWasteRecords = [];
            $metadata = (array) $movement->metadata;
            $allowNegativeStock = (bool) Arr::get($metadata, 'allow_negative_stock', false);
            $positionPairsToSync = [];

            foreach ($movement->details as $detail) {
                $position = $this->resolveStockPosition($detail);
                $logisticUnit = $this->resolveLogisticUnit($movement, $context, $position);
                $allocation = $this->createAllocation($movement, $detail, $logisticUnit, $position);

                foreach ($this->buildEventData($movement, $detail, $allocation, $userId, $logisticUnit, $context) as $eventData) {
                    $ledgerEvents[] = $eventData;
                }

                $positionPairsToSync = [
                    ...$positionPairsToSync,
                    ...$this->applyLogisticUnitEffect($movement, $detail, $logisticUnit, $userId, $position),
                ];

                if ($movement->type?->codigo === 'MERMA') {
                    $createdWasteRecords[] = $this->wasteManagementService->createFromMovement(
                        $movement,
                        $detail,
                        [
                            'detected_location_id' => Arr::get($metadata, 'detected_location_id', $movement->origin_location_id),
                            'quarantine_location_id' => Arr::get($metadata, 'quarantine_location_id'),
                            'logistic_unit_id' => $logisticUnit?->id,
                            'photo_path' => Arr::get($metadata, 'photo_path'),
                            'evidence_payload' => Arr::get($metadata, 'evidence_payload'),
                            'notes' => $movement->observacion,
                        ],
                        $userId,
                    );
                }
            }

            $createdEvents = $this->ledgerService->appendMany($ledgerEvents);
            $firstEvent = ! empty($createdEvents) ? $createdEvents[0] : null;
            $lastEvent = ! empty($createdEvents) ? $createdEvents[array_key_last($createdEvents)] : null;

            foreach ($createdEvents as $event) {
                if (in_array($event->stock_effect, ['in', 'out'], true) && $event->material_id && $event->location_id) {
                    $this->stockProjectionService->applyDelta(
                        (int) $event->material_id,
                        (int) $event->location_id,
                        (float) $event->signed_quantity,
                        $event->id,
                        $allowNegativeStock,
                    );
                }
            }

            $this->syncTouchedPositionPairs($positionPairsToSync);

            $movement->forceFill([
                'estado' => 'aplicado',
                'approved_by' => $userId,
                'applied_at' => now(),
                'receipt_hash' => $movement->destination_location_id
                    ? hash('sha256', $this->buildReceiptPayload($movement))
                    : null,
                'ledger_hash' => $lastEvent?->event_hash,
                'ledger_sequence_from' => $firstEvent?->sequence,
                'ledger_sequence_to' => $lastEvent?->sequence,
                'metadata' => [
                    ...((array) $movement->metadata),
                    'waste_record_ids' => collect($createdWasteRecords)->pluck('id')->values()->all(),
                ],
            ])->save();

            return $movement->fresh([
                'type',
                'origin',
                'destination',
                'creator',
                'approver',
                'details.material',
                'ledgerEvents',
                'wasteRecords',
            ]);
        });
    }

    public function confirmTransferReceipt(InventoryMovement $movement, int $userId, array $transferUnitIds = []): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $userId, $transferUnitIds): InventoryMovement {
            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::query()
                ->with(['type', 'details.material', 'origin.assignedUsers', 'destination.assignedUsers', 'creator', 'transferUnits.logisticUnit'])
                ->lockForUpdate()
                ->findOrFail($movement->id);

            if ($movement->estado !== 'aplicado') {
                throw ValidationException::withMessages([
                    'movement' => 'Solo se pueden confirmar movimientos aplicados.',
                ]);
            }

            if (! $this->usesTransferUnits($movement)) {
                throw ValidationException::withMessages([
                    'movement' => 'Este movimiento no utiliza recepción por pallet.',
                ]);
            }

            $candidateUnits = $movement->transferUnits
                ->whereIn('status', ['in_transit', 'return_pending'])
                ->values();

            if ($candidateUnits->isEmpty()) {
                throw ValidationException::withMessages([
                    'movement' => 'No existen pallets pendientes para este traslado.',
                ]);
            }

            $selectedUnits = empty($transferUnitIds)
                ? $candidateUnits
                : $candidateUnits->whereIn('id', collect($transferUnitIds)->map(fn ($id) => (int) $id)->all())->values();

            if ($selectedUnits->isEmpty()) {
                throw ValidationException::withMessages([
                    'transfer_unit_ids' => 'Debes seleccionar al menos un pallet pendiente.',
                ]);
            }

            $statuses = $selectedUnits->pluck('status')->unique()->values();
            if ($statuses->count() !== 1) {
                throw ValidationException::withMessages([
                    'transfer_unit_ids' => 'No puedes mezclar pallets en tránsito con pallets de retorno pendiente en una misma confirmación.',
                ]);
            }

            if ($statuses->first() === 'return_pending') {
                return $this->confirmTransferReturn($movement, $userId, $selectedUnits);
            }

            return $this->confirmTransferDestinationReceipt($movement, $userId, $selectedUnits);
        });
    }

    public function rejectTransferReceipt(InventoryMovement $movement, int $userId, array $transferUnitIds, string $reason): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $userId, $transferUnitIds, $reason): InventoryMovement {
            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::query()
                ->with(['type', 'origin.assignedUsers', 'destination.assignedUsers', 'creator', 'transferUnits.logisticUnit'])
                ->lockForUpdate()
                ->findOrFail($movement->id);

            if ($movement->estado !== 'aplicado') {
                throw ValidationException::withMessages([
                    'movement' => 'Solo se pueden rechazar movimientos aplicados.',
                ]);
            }

            if (! $this->usesTransferUnits($movement)) {
                throw ValidationException::withMessages([
                    'movement' => 'Este movimiento no utiliza rechazo por pallet.',
                ]);
            }

            $assignedReceivers = $movement->destination?->assignedUsers ?? collect();
            if ($assignedReceivers->isNotEmpty() && ! $assignedReceivers->pluck('id')->contains($userId)) {
                throw ValidationException::withMessages([
                    'movement' => 'Solo los usuarios asignados a la ubicación destino pueden rechazar pallets en tránsito.',
                ]);
            }

            $selectedUnits = $movement->transferUnits
                ->where('status', 'in_transit')
                ->whereIn('id', collect($transferUnitIds)->map(fn ($id) => (int) $id)->all())
                ->values();

            if ($selectedUnits->isEmpty()) {
                throw ValidationException::withMessages([
                    'transfer_unit_ids' => 'Debes seleccionar al menos un pallet en tránsito para rechazar.',
                ]);
            }

            $ledgerEvents = [];
            $notifiableUnits = [];

            foreach ($selectedUnits as $selectedUnit) {
                $transferUnit = InventoryTransferUnit::query()->lockForUpdate()->findOrFail($selectedUnit->id);
                $logisticUnit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($transferUnit->logistic_unit_id);

                $transferUnit->forceFill([
                    'status' => 'return_pending',
                    'rejected_by' => $userId,
                    'rejected_at' => now(),
                    'rejection_reason' => $reason,
                    'metadata' => [
                        ...((array) $transferUnit->metadata),
                        'rejection_snapshot' => [
                            'rejected_at' => now()->toDateTimeString(),
                            'reason' => $reason,
                        ],
                        'lifecycle' => [
                            ...((array) data_get($transferUnit->metadata, 'lifecycle')),
                            'rejected_at' => now()->toDateTimeString(),
                        ],
                    ],
                ])->save();

                $ledgerEvents[] = $this->buildTransferRejectedEventData($movement, $transferUnit, $userId, $logisticUnit, $reason);
                $notifiableUnits[] = [
                    'id' => $transferUnit->id,
                    'license_plate_number' => $logisticUnit->license_plate_number,
                    'quantity' => (float) $transferUnit->quantity,
                    'reason' => $reason,
                    'status' => 'return_pending',
                ];
            }

            $createdEvents = $this->ledgerService->appendMany($ledgerEvents);
            $lastEvent = ! empty($createdEvents) ? $createdEvents[array_key_last($createdEvents)] : null;

            $movement->forceFill([
                'ledger_hash' => $lastEvent?->event_hash ?? $movement->ledger_hash,
                'ledger_sequence_to' => $lastEvent?->sequence ?? $movement->ledger_sequence_to,
            ])->save();

            $originUsers = $movement->origin?->assignedUsers ?? collect();
            if ($originUsers->isNotEmpty()) {
                Notification::send(
                    $originUsers->where('id', '!=', $userId),
                    new InventoryTransferReturnPendingNotification($movement, $notifiableUnits)
                );
            }

            return $movement->fresh([
                'type',
                'origin',
                'destination',
                'creator',
                'transferUnits.logisticUnit',
            ]);
        });
    }

    private function resolveLogisticUnit(InventoryMovement $movement, array $context, ?InventoryStockPosition $position = null): ?InventoryLogisticUnit
    {
        $logisticUnitId = $context['logistic_unit_id']
            ?? Arr::get((array) $movement->metadata, 'logistic_unit_id')
            ?? $position?->logistic_unit_id
            ?? null;

        if (! $logisticUnitId) {
            return null;
        }

        return InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($logisticUnitId);
    }

    private function createAllocation(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        ?InventoryLogisticUnit $logisticUnit,
        ?InventoryStockPosition $position = null
    ): InventoryMovementAllocation
    {
        return InventoryMovementAllocation::create([
            'movement_detail_id' => $detail->id,
            'logistic_unit_id' => $logisticUnit?->id,
            'allocated_quantity' => $detail->cantidad,
            'from_location_id' => $movement->origin_location_id,
            'to_location_id' => $movement->destination_location_id,
            'allocation_type' => $position ? 'stock_position' : ($logisticUnit ? 'logistic_unit' : 'generic'),
            'metadata' => [
                'movement_type' => $movement->type?->codigo,
                'position_id' => $position?->id ?? Arr::get((array) $detail->metadata, 'position_id'),
            ],
        ]);
    }

    private function createTransferUnitAllocation(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        InventoryTransferUnit $transferUnit,
        ?InventoryLogisticUnit $logisticUnit,
        string $allocationType
    ): InventoryMovementAllocation {
        return InventoryMovementAllocation::create([
            'movement_detail_id' => $detail->id,
            'logistic_unit_id' => $logisticUnit?->id,
            'allocated_quantity' => $transferUnit->quantity,
            'from_location_id' => $transferUnit->origin_location_id,
            'to_location_id' => $transferUnit->destination_location_id,
            'allocation_type' => $allocationType,
            'metadata' => [
                'movement_type' => $movement->type?->codigo,
                'transfer_unit_id' => $transferUnit->id,
                'position_snapshots' => Arr::get((array) $transferUnit->metadata, 'position_snapshots', []),
            ],
        ]);
    }

    private function buildEventData(InventoryMovement $movement, InventoryMovementDetail $detail, InventoryMovementAllocation $allocation, int $userId, ?InventoryLogisticUnit $logisticUnit, array $context): array
    {
        $positionId = Arr::get((array) $detail->metadata, 'position_id')
            ?? Arr::get((array) $allocation->metadata, 'position_id');

        $basePayload = [
            'folio' => $movement->folio,
            'movement_type' => $movement->type?->codigo,
            'material_id' => $detail->material_id,
            'cantidad' => (float) $detail->cantidad,
            'origin_location_id' => $movement->origin_location_id,
            'destination_location_id' => $movement->destination_location_id,
            'logistic_unit_id' => $logisticUnit?->id,
            'position_id' => $positionId,
            'context' => $context,
        ];

        $actorName = (string) ($movement->creator?->name ?? 'system');
        $common = [
            'movement_id' => $movement->id,
            'movement_detail_id' => $detail->id,
            'allocation_id' => $allocation->id,
            'material_id' => $detail->material_id,
            'logistic_unit_id' => $logisticUnit?->id,
            'occurred_at' => $movement->fecha_movimiento ?? now(),
            'actor_user_id' => $userId,
            'actor_name_snapshot' => $actorName,
            'device_code' => Arr::get((array) $movement->metadata, 'device_code'),
            'correlation_uuid' => $movement->scan_session_uuid,
        ];

        return match ((string) $movement->type?->codigo) {
            'TRANSFERENCIA', 'DEVOLUCION' => [
                [
                    ...$common,
                    'event_type' => 'TRANSFER_OUT',
                    'location_id' => $movement->origin_location_id,
                    'signed_quantity' => -(float) $detail->cantidad,
                    'stock_effect' => 'out',
                    'payload' => $basePayload,
                ],
                [
                    ...$common,
                    'event_type' => 'TRANSFER_IN',
                    'location_id' => $movement->destination_location_id,
                    'signed_quantity' => (float) $detail->cantidad,
                    'stock_effect' => 'in',
                    'payload' => $basePayload,
                ],
            ],
            'CONSUMO' => [[
                ...$common,
                'event_type' => 'CONSUME_OUT',
                'location_id' => $movement->origin_location_id,
                'signed_quantity' => -(float) $detail->cantidad,
                'stock_effect' => 'out',
                'payload' => $basePayload,
            ]],
            'MERMA' => [[
                ...$common,
                'event_type' => 'WASTE_OUT',
                'location_id' => $movement->origin_location_id,
                'signed_quantity' => -(float) $detail->cantidad,
                'stock_effect' => 'out',
                    'payload' => [
                        ...$basePayload,
                        'detected_location_id' => Arr::get((array) $movement->metadata, 'detected_location_id', $movement->origin_location_id),
                        'quarantine_location_id' => Arr::get((array) $movement->metadata, 'quarantine_location_id'),
                    ],
                ]],
            'AJUSTE_NEG' => [[
                ...$common,
                'event_type' => 'ADJUST_NEG',
                'location_id' => $movement->origin_location_id,
                'signed_quantity' => -(float) $detail->cantidad,
                'stock_effect' => 'out',
                'payload' => $basePayload,
            ]],
            'AJUSTE_POS' => [[
                ...$common,
                'event_type' => 'ADJUST_POS',
                'location_id' => $movement->destination_location_id,
                'signed_quantity' => (float) $detail->cantidad,
                'stock_effect' => 'in',
                'payload' => $basePayload,
            ]],
            'PRODUCCION_INTERMEDIA' => [[
                ...$common,
                'event_type' => $detail->sentido === 'entrada' ? 'PRODUCTION_INTERMEDIATE_IN' : 'PRODUCTION_INTERMEDIATE_OUT',
                'location_id' => $detail->sentido === 'entrada' ? $movement->destination_location_id : $movement->origin_location_id,
                'signed_quantity' => $detail->sentido === 'entrada' ? (float) $detail->cantidad : -(float) $detail->cantidad,
                'stock_effect' => $detail->sentido === 'entrada' ? 'in' : 'out',
                'payload' => $basePayload,
            ]],
            default => [],
        };
    }

    private function buildTransferDispatchEventData(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        InventoryMovementAllocation $allocation,
        InventoryTransferUnit $transferUnit,
        int $userId,
        ?InventoryLogisticUnit $logisticUnit
    ): array {
        return [
            'movement_id' => $movement->id,
            'movement_detail_id' => $detail->id,
            'allocation_id' => $allocation->id,
            'material_id' => $detail->material_id,
            'location_id' => $transferUnit->origin_location_id,
            'logistic_unit_id' => $logisticUnit?->id,
            'event_type' => 'TRANSFER_OUT',
            'signed_quantity' => -(float) $transferUnit->quantity,
            'stock_effect' => 'out',
            'payload' => [
                'folio' => $movement->folio,
                'movement_type' => $movement->type?->codigo,
                'material_id' => $detail->material_id,
                'cantidad' => (float) $transferUnit->quantity,
                'origin_location_id' => $transferUnit->origin_location_id,
                'destination_location_id' => $transferUnit->destination_location_id,
                'logistic_unit_id' => $logisticUnit?->id,
                'transfer_unit_id' => $transferUnit->id,
                'position_snapshots' => Arr::get((array) $transferUnit->metadata, 'position_snapshots', []),
            ],
            'occurred_at' => $movement->fecha_movimiento ?? now(),
            'actor_user_id' => $userId,
            'actor_name_snapshot' => (string) ($movement->creator?->name ?? 'system'),
            'device_code' => Arr::get((array) $movement->metadata, 'device_code'),
            'correlation_uuid' => $movement->scan_session_uuid,
        ];
    }

    private function buildTransferReceiptEventData(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        InventoryMovementAllocation $allocation,
        InventoryTransferUnit $transferUnit,
        int $userId,
        ?InventoryLogisticUnit $logisticUnit
    ): array {
        return [
            'movement_id' => $movement->id,
            'movement_detail_id' => $detail->id,
            'allocation_id' => $allocation->id,
            'material_id' => $detail->material_id,
            'location_id' => $transferUnit->destination_location_id,
            'logistic_unit_id' => $logisticUnit?->id,
            'event_type' => 'TRANSFER_IN',
            'signed_quantity' => (float) $transferUnit->quantity,
            'stock_effect' => 'in',
            'payload' => [
                'folio' => $movement->folio,
                'movement_type' => $movement->type?->codigo,
                'material_id' => $detail->material_id,
                'cantidad' => (float) $transferUnit->quantity,
                'origin_location_id' => $transferUnit->origin_location_id,
                'destination_location_id' => $transferUnit->destination_location_id,
                'logistic_unit_id' => $logisticUnit?->id,
                'transfer_unit_id' => $transferUnit->id,
                'position_snapshots' => Arr::get((array) $transferUnit->metadata, 'position_snapshots', []),
            ],
            'occurred_at' => now(),
            'actor_user_id' => $userId,
            'actor_name_snapshot' => (string) ($movement->creator?->name ?? 'system'),
            'device_code' => Arr::get((array) $movement->metadata, 'device_code'),
            'correlation_uuid' => $movement->scan_session_uuid,
        ];
    }

    private function buildTransferReturnReceiptEventData(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        InventoryMovementAllocation $allocation,
        InventoryTransferUnit $transferUnit,
        int $userId,
        ?InventoryLogisticUnit $logisticUnit
    ): array {
        return [
            'movement_id' => $movement->id,
            'movement_detail_id' => $detail->id,
            'allocation_id' => $allocation->id,
            'material_id' => $detail->material_id,
            'location_id' => $transferUnit->origin_location_id,
            'logistic_unit_id' => $logisticUnit?->id,
            'event_type' => 'TRANSFER_RETURN_IN',
            'signed_quantity' => (float) $transferUnit->quantity,
            'stock_effect' => 'in',
            'payload' => [
                'folio' => $movement->folio,
                'movement_type' => $movement->type?->codigo,
                'material_id' => $detail->material_id,
                'cantidad' => (float) $transferUnit->quantity,
                'origin_location_id' => $transferUnit->origin_location_id,
                'destination_location_id' => $transferUnit->destination_location_id,
                'logistic_unit_id' => $logisticUnit?->id,
                'transfer_unit_id' => $transferUnit->id,
                'rejection_reason' => $transferUnit->rejection_reason,
                'position_snapshots' => Arr::get((array) $transferUnit->metadata, 'position_snapshots', []),
            ],
            'occurred_at' => now(),
            'actor_user_id' => $userId,
            'actor_name_snapshot' => (string) ($movement->creator?->name ?? 'system'),
            'device_code' => Arr::get((array) $movement->metadata, 'device_code'),
            'correlation_uuid' => $movement->scan_session_uuid,
        ];
    }

    private function buildTransferRejectedEventData(
        InventoryMovement $movement,
        InventoryTransferUnit $transferUnit,
        int $userId,
        ?InventoryLogisticUnit $logisticUnit,
        string $reason
    ): array {
        return [
            'movement_id' => $movement->id,
            'movement_detail_id' => null,
            'allocation_id' => null,
            'material_id' => $transferUnit->material_id,
            'location_id' => $transferUnit->destination_location_id,
            'logistic_unit_id' => $logisticUnit?->id,
            'event_type' => 'TRANSFER_REJECTED',
            'signed_quantity' => 0,
            'stock_effect' => 'none',
            'payload' => [
                'folio' => $movement->folio,
                'transfer_unit_id' => $transferUnit->id,
                'logistic_unit_id' => $logisticUnit?->id,
                'origin_location_id' => $transferUnit->origin_location_id,
                'destination_location_id' => $transferUnit->destination_location_id,
                'rejection_reason' => $reason,
                'position_snapshots' => Arr::get((array) $transferUnit->metadata, 'position_snapshots', []),
            ],
            'occurred_at' => now(),
            'actor_user_id' => $userId,
            'actor_name_snapshot' => (string) ($movement->creator?->name ?? 'system'),
            'device_code' => Arr::get((array) $movement->metadata, 'device_code'),
            'correlation_uuid' => $movement->scan_session_uuid,
        ];
    }

    private function applyLogisticUnitEffect(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        ?InventoryLogisticUnit $logisticUnit,
        int $userId,
        ?InventoryStockPosition $position = null
    ): array
    {
        $code = (string) $movement->type?->codigo;
        $quantity = (float) $detail->cantidad;

        if ($position) {
            $pairs = $this->applyPositionEffect($movement, $detail, $logisticUnit, $position, $quantity);

            // Si la posición se consumió completamente (full transfer) y hay LPN,
            // relocalizar el pallet al destino para TRANSFERENCIA/DEVOLUCION
            if ($logisticUnit && ! $position->exists
                && in_array($code, ['TRANSFERENCIA', 'DEVOLUCION'], true)
                && $movement->destination_location_id) {
                $this->logisticUnitService->relocate($logisticUnit, (int) $movement->destination_location_id, $userId, null);
            }

            return $pairs;
        }

        if (! $logisticUnit) {
            return [];
        }

        if ($movement->origin_location_id && (int) $logisticUnit->current_location_id !== (int) $movement->origin_location_id) {
            throw ValidationException::withMessages([
                'logistic_unit' => 'El pallet no está en la ubicación de origen indicada.',
            ]);
        }

        if (in_array($code, ['TRANSFERENCIA', 'DEVOLUCION'], true) && $movement->destination_location_id) {
            $this->logisticUnitService->relocate($logisticUnit, (int) $movement->destination_location_id, $userId, null);
            return [];
        }

        if (in_array($code, ['CONSUMO', 'AJUSTE_NEG'], true)) {
            $this->logisticUnitService->consume($logisticUnit, $quantity, $userId, []);
            return [];
        }

        if ($code === 'MERMA') {
            $this->logisticUnitService->waste($logisticUnit, $quantity, $userId, []);
        }

        return [];
    }

    private function resolveStockPosition(InventoryMovementDetail $detail): ?InventoryStockPosition
    {
        $positionId = Arr::get((array) $detail->metadata, 'position_id');

        if (! $positionId || ! $this->positionsTableExists()) {
            return null;
        }

        return InventoryStockPosition::query()
            ->lockForUpdate()
            ->findOrFail($positionId);
    }

    private function applyPositionEffect(
        InventoryMovement $movement,
        InventoryMovementDetail $detail,
        ?InventoryLogisticUnit $logisticUnit,
        InventoryStockPosition $position,
        float $quantity
    ): array {
        if ((int) $position->material_id !== (int) $detail->material_id) {
            throw ValidationException::withMessages([
                'details' => 'La posición seleccionada no corresponde al material del movimiento.',
            ]);
        }

        if ($movement->origin_location_id && (int) $position->location_id !== (int) $movement->origin_location_id) {
            throw ValidationException::withMessages([
                'details' => 'La posición seleccionada no pertenece a la ubicación de origen indicada.',
            ]);
        }

        if ($quantity > (float) $position->quantity) {
            throw ValidationException::withMessages([
                'details' => 'La posicion seleccionada no tiene stock suficiente.',
            ]);
        }

        $pairs = [
            ['material_id' => (int) $position->material_id, 'location_id' => (int) $position->location_id],
        ];

        $code = (string) $movement->type?->codigo;

        if (in_array($code, ['TRANSFERENCIA', 'DEVOLUCION'], true) && $movement->destination_location_id) {
            if ((int) $position->location_id === (int) $movement->destination_location_id) {
                throw ValidationException::withMessages([
                    'destination_location_id' => 'La ubicación destino debe ser distinta al origen.',
                ]);
            }

            $isPartialPositionTransfer = $quantity < (float) $position->quantity;
            $targetLuId = $isPartialPositionTransfer ? null : $position->logistic_unit_id;

            $target = InventoryStockPosition::query()->firstOrNew([
                'material_id' => $position->material_id,
                'location_id' => $movement->destination_location_id,
                'logistic_unit_id' => $targetLuId,
                'lot_code' => $position->lot_code,
                'status' => $position->status,
            ]);

            $target->quantity = round((float) ($target->quantity ?? 0) + $quantity, 4);
            $target->save();

            $pairs[] = [
                'material_id' => (int) $position->material_id,
                'location_id' => (int) $movement->destination_location_id,
            ];
        }

        $remaining = round((float) $position->quantity - $quantity, 4);
        if ($remaining <= 0) {
            $position->delete();
        } else {
            $position->forceFill(['quantity' => $remaining])->save();
        }

        if ($logisticUnit) {
            $this->logisticUnitService->syncLogisticUnitQuantity($logisticUnit);
        }

        return $pairs;
    }

    private function syncTouchedPositionPairs(array $pairs): void
    {
        if (! $this->positionsTableExists()) {
            return;
        }

        $uniquePairs = collect($pairs)
            ->filter(fn (array $pair) => isset($pair['material_id'], $pair['location_id']))
            ->unique(fn (array $pair) => $pair['material_id'].'-'.$pair['location_id'])
            ->values();

        foreach ($uniquePairs as $pair) {
            $this->stockProjectionService->syncMaterialLocationFromPositions(
                (int) $pair['material_id'],
                (int) $pair['location_id'],
            );
        }
    }

    private function positionsTableExists(): bool
    {
        return Schema::hasTable('inventory_stock_positions');
    }

    private function usesTransferUnits(InventoryMovement $movement): bool
    {
        $workflow = Arr::get((array) $movement->metadata, 'workflow');

        return $workflow === 'transfer_scan'
            && in_array($movement->type?->codigo, ['TRANSFERENCIA', 'DEVOLUCION'], true)
            && $movement->transferUnits->isNotEmpty();
    }

    private function applyTransferDispatch(InventoryMovement $movement, int $userId): InventoryMovement
    {
        $ledgerEvents = [];
        $detailByMaterial = $movement->details->keyBy('material_id');

        foreach ($movement->transferUnits as $transferUnit) {
            $transferUnit = InventoryTransferUnit::query()->lockForUpdate()->findOrFail($transferUnit->id);
            /** @var InventoryMovementDetail|null $detail */
            $detail = $detailByMaterial->get($transferUnit->material_id);

            if (! $detail) {
                throw ValidationException::withMessages([
                    'movement' => 'No fue posible asociar el material del pallet al detalle del movimiento.',
                ]);
            }

            $logisticUnit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($transferUnit->logistic_unit_id);

            $isPositionScoped = $this->isPositionScopedTransferUnit($transferUnit);

            if (! $isPositionScoped && (int) $logisticUnit->current_location_id !== (int) $transferUnit->origin_location_id) {
                throw ValidationException::withMessages([
                    'logistic_unit_codes' => "El pallet {$logisticUnit->license_plate_number} no está en la ubicación de origen resuelta.",
                ]);
            }

            $snapshot = $this->snapshotTransferUnitPositions($logisticUnit, (int) $transferUnit->origin_location_id, $transferUnit);
            if ($this->positionsTableExists() && $isPositionScoped && empty($snapshot)) {
                throw ValidationException::withMessages([
                    'logistic_unit_codes' => "El pallet {$logisticUnit->license_plate_number} no tiene posiciones disponibles en la ubicación de origen.",
                ]);
            }

            $snapshotQuantity = round((float) collect($snapshot)->sum('quantity'), 4);
            if ($this->positionsTableExists() && ! empty($snapshot) && abs($snapshotQuantity - round((float) $transferUnit->quantity, 4)) > 0.0001) {
                throw ValidationException::withMessages([
                    'logistic_unit_codes' => "Las posiciones del pallet {$logisticUnit->license_plate_number} no coinciden con la cantidad a trasladar.",
                ]);
            }

            $transferUnit->forceFill([
                'metadata' => [
                    ...((array) $transferUnit->metadata),
                    'position_snapshots' => $snapshot,
                    'origin_location_snapshot' => $this->locationSnapshot((int) $transferUnit->origin_location_id),
                    'destination_location_snapshot' => $this->locationSnapshot((int) $transferUnit->destination_location_id),
                    'dispatch_snapshot' => [
                        'captured_at' => now()->toDateTimeString(),
                        'position_count' => count($snapshot),
                        'quantity' => $snapshotQuantity,
                    ],
                ],
            ])->save();

            $allocation = $this->createTransferUnitAllocation($movement, $detail, $transferUnit, $logisticUnit, 'transfer_dispatch');
            $ledgerEvents[] = $this->buildTransferDispatchEventData(
                $movement,
                $detail,
                $allocation,
                $transferUnit,
                $userId,
                $logisticUnit,
            );

            $isPartial = $this->isPositionScopedTransferUnit($transferUnit);

            if ($isPartial) {
                $this->logisticUnitService->syncLogisticUnitQuantity($logisticUnit);
            } else {
                $this->logisticUnitService->markInTransit($logisticUnit, $userId, [
                    'movement_id' => $movement->id,
                    'transfer_unit_id' => $transferUnit->id,
                    'destination_location_id' => $transferUnit->destination_location_id,
                ]);
            }

            $transferUnit->forceFill([
                'status' => 'in_transit',
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
                'metadata' => [
                    ...((array) $transferUnit->metadata),
                    'lifecycle' => [
                        ...((array) data_get($transferUnit->metadata, 'lifecycle')),
                        'dispatched_at' => now()->toDateTimeString(),
                    ],
                ],
            ])->save();
        }

        $createdEvents = $this->ledgerService->appendMany($ledgerEvents);
        $firstEvent = ! empty($createdEvents) ? $createdEvents[0] : null;
        $lastEvent = ! empty($createdEvents) ? $createdEvents[array_key_last($createdEvents)] : null;

        foreach ($createdEvents as $event) {
            if (in_array($event->stock_effect, ['in', 'out'], true) && $event->material_id && $event->location_id) {
                $this->stockProjectionService->applyDelta(
                    (int) $event->material_id,
                    (int) $event->location_id,
                    (float) $event->signed_quantity,
                    $event->id,
                );
            }
        }

        $movement->forceFill([
            'estado' => 'aplicado',
            'approved_by' => $userId,
            'applied_at' => now(),
            'receipt_hash' => hash('sha256', $this->buildReceiptPayload($movement)),
            'ledger_hash' => $lastEvent?->event_hash,
            'ledger_sequence_from' => $firstEvent?->sequence,
            'ledger_sequence_to' => $lastEvent?->sequence,
        ])->save();

        return $movement->fresh([
            'type',
            'origin',
            'destination',
            'creator',
            'approver',
            'details.material',
            'transferUnits.logisticUnit',
        ]);
    }

    private function buildReceiptPayload(InventoryMovement $movement): string
    {
        $detailPayload = $movement->details
            ->map(fn (InventoryMovementDetail $detail) => implode('|', [
                $detail->material_id,
                $detail->sentido,
                number_format((float) $detail->cantidad, 4, '.', ''),
            ]))
            ->implode(';');

        return implode('||', [
            $movement->folio,
            (string) $movement->movement_type_id,
            (string) $movement->fecha_movimiento,
            (string) $movement->origin_location_id,
            (string) $movement->destination_location_id,
            $detailPayload,
        ]);
    }

    private function confirmTransferDestinationReceipt(InventoryMovement $movement, int $userId, $selectedUnits): InventoryMovement
    {
        $assignedReceivers = $movement->destination?->assignedUsers ?? collect();
        if ($assignedReceivers->isNotEmpty() && ! $assignedReceivers->pluck('id')->contains($userId)) {
            throw ValidationException::withMessages([
                'movement' => 'Solo los usuarios asignados a la ubicación destino pueden confirmar la recepción de pallets en tránsito.',
            ]);
        }

        $detailByMaterial = $movement->details->keyBy('material_id');
        $ledgerEvents = [];

        foreach ($selectedUnits as $selectedUnit) {
            $transferUnit = InventoryTransferUnit::query()->lockForUpdate()->findOrFail($selectedUnit->id);
            if ($transferUnit->status !== 'in_transit') {
                throw ValidationException::withMessages([
                    'transfer_unit_ids' => 'Solo se pueden recibir pallets en tránsito.',
                ]);
            }

            /** @var InventoryMovementDetail|null $detail */
            $detail = $detailByMaterial->get($transferUnit->material_id);
            if (! $detail) {
                throw ValidationException::withMessages([
                    'movement' => 'No fue posible resolver el detalle del material para la recepción del pallet.',
                ]);
            }

            $logisticUnit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($transferUnit->logistic_unit_id);
            $allocation = $this->createTransferUnitAllocation($movement, $detail, $transferUnit, $logisticUnit, 'transfer_receipt');
            $ledgerEvents[] = $this->buildTransferReceiptEventData($movement, $detail, $allocation, $transferUnit, $userId, $logisticUnit);

            $this->receiveTransferUnit($transferUnit, $logisticUnit, $userId, $movement->id);

            $transferUnit->forceFill([
                'status' => 'received',
                'received_by' => $userId,
                'received_at' => now(),
                'metadata' => [
                    ...((array) $transferUnit->metadata),
                    'receipt_snapshot' => [
                        'received_at' => now()->toDateTimeString(),
                        'destination_location_snapshot' => $this->locationSnapshot((int) $transferUnit->destination_location_id),
                    ],
                    'lifecycle' => [
                        ...((array) data_get($transferUnit->metadata, 'lifecycle')),
                        'received_at' => now()->toDateTimeString(),
                    ],
                ],
            ])->save();
        }

        return $this->finalizeTransferUnitMutation($movement, $userId, $ledgerEvents);
    }

    private function confirmTransferReturn(InventoryMovement $movement, int $userId, $selectedUnits): InventoryMovement
    {
        $assignedOrigins = $movement->origin?->assignedUsers ?? collect();
        if ($assignedOrigins->isNotEmpty() && ! $assignedOrigins->pluck('id')->contains($userId)) {
            throw ValidationException::withMessages([
                'movement' => 'Solo los usuarios asignados a la ubicación origen pueden confirmar el retorno de pallets rechazados.',
            ]);
        }

        $detailByMaterial = $movement->details->keyBy('material_id');
        $ledgerEvents = [];

        foreach ($selectedUnits as $selectedUnit) {
            $transferUnit = InventoryTransferUnit::query()->lockForUpdate()->findOrFail($selectedUnit->id);
            if ($transferUnit->status !== 'return_pending') {
                throw ValidationException::withMessages([
                    'transfer_unit_ids' => 'Solo se pueden confirmar pallets con retorno pendiente.',
                ]);
            }

            /** @var InventoryMovementDetail|null $detail */
            $detail = $detailByMaterial->get($transferUnit->material_id);
            if (! $detail) {
                throw ValidationException::withMessages([
                    'movement' => 'No fue posible resolver el detalle del material para el retorno del pallet.',
                ]);
            }

            $logisticUnit = InventoryLogisticUnit::query()->lockForUpdate()->findOrFail($transferUnit->logistic_unit_id);
            $allocation = $this->createTransferUnitAllocation($movement, $detail, $transferUnit, $logisticUnit, 'transfer_return_receipt');
            $ledgerEvents[] = $this->buildTransferReturnReceiptEventData($movement, $detail, $allocation, $transferUnit, $userId, $logisticUnit);

            $this->returnTransferUnitToOrigin($transferUnit, $logisticUnit, $userId, $movement->id);

            $transferUnit->forceFill([
                'status' => 'returned',
                'returned_by' => $userId,
                'returned_at' => now(),
                'metadata' => [
                    ...((array) $transferUnit->metadata),
                    'return_snapshot' => [
                        'returned_at' => now()->toDateTimeString(),
                        'origin_location_snapshot' => $this->locationSnapshot((int) $transferUnit->origin_location_id),
                    ],
                    'lifecycle' => [
                        ...((array) data_get($transferUnit->metadata, 'lifecycle')),
                        'returned_at' => now()->toDateTimeString(),
                    ],
                ],
            ])->save();
        }

        return $this->finalizeTransferUnitMutation($movement, $userId, $ledgerEvents);
    }

    private function finalizeTransferUnitMutation(InventoryMovement $movement, int $userId, array $ledgerEvents): InventoryMovement
    {
        $createdEvents = $this->ledgerService->appendMany($ledgerEvents);
        $firstEvent = ! empty($createdEvents) ? $createdEvents[0] : null;
        $lastEvent = ! empty($createdEvents) ? $createdEvents[array_key_last($createdEvents)] : null;

        foreach ($createdEvents as $event) {
            if (in_array($event->stock_effect, ['in', 'out'], true) && $event->material_id && $event->location_id) {
                $this->stockProjectionService->applyDelta(
                    (int) $event->material_id,
                    (int) $event->location_id,
                    (float) $event->signed_quantity,
                    $event->id,
                );
            }
        }

        $movement->refresh();
        $movement->load(['transferUnits']);
        $allClosed = $movement->transferUnits->every(fn (InventoryTransferUnit $unit) => in_array($unit->status, ['received', 'returned'], true));

        if ($allClosed) {
            $movement->forceFill([
                'estado' => 'confirmado',
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
                'ledger_hash' => $lastEvent?->event_hash ?? $movement->ledger_hash,
                'ledger_sequence_to' => $lastEvent?->sequence ?? $movement->ledger_sequence_to,
                'ledger_sequence_from' => $movement->ledger_sequence_from ?? $firstEvent?->sequence,
            ])->save();
        } else {
            $movement->forceFill([
                'ledger_hash' => $lastEvent?->event_hash ?? $movement->ledger_hash,
                'ledger_sequence_to' => $lastEvent?->sequence ?? $movement->ledger_sequence_to,
                'ledger_sequence_from' => $movement->ledger_sequence_from ?? $firstEvent?->sequence,
            ])->save();
        }

        return $movement->fresh([
            'type',
            'origin',
            'destination',
            'creator',
            'approver',
            'confirmer',
            'details.material',
            'transferUnits.logisticUnit',
        ]);
    }

    private function snapshotTransferUnitPositions(InventoryLogisticUnit $logisticUnit, int $originLocationId, ?InventoryTransferUnit $transferUnit = null): array
    {
        if (! $this->positionsTableExists()) {
            return [];
        }

        $metadata = (array) ($transferUnit?->metadata ?? []);
        $positionId = (int) (
            Arr::get($metadata, 'partial_transfer.position_id')
            ?? Arr::get($metadata, 'requested_position_id')
            ?? 0
        );
        $requestedQuantity = (float) (
            Arr::get($metadata, 'partial_transfer.quantity')
            ?? Arr::get($metadata, 'requested_quantity')
            ?? 0
        );

        return InventoryStockPosition::query()
            ->with('location:id,codigo,nombre')
            ->where('logistic_unit_id', $logisticUnit->id)
            ->where('location_id', $originLocationId)
            ->where('quantity', '>', 0)
            ->when($positionId > 0, fn ($query) => $query->where('id', $positionId))
            ->orderBy('id')
            ->get()
            ->map(function (InventoryStockPosition $position) use ($logisticUnit, $positionId, $requestedQuantity): array {
                $quantity = round((float) $position->quantity, 4);
                if ($positionId > 0) {
                    $quantity = round(min($quantity, max($requestedQuantity, 0)), 4);
                }

                return [
                    'position_id' => $position->id,
                    'material_id' => (int) $position->material_id,
                    'location_id' => (int) $position->location_id,
                    'quantity' => $quantity,
                    'lot_code' => $position->lot_code,
                    'status' => $position->status,
                    'location_snapshot' => $position->location ? [
                        'id' => $position->location->id,
                        'codigo' => $position->location->codigo,
                        'nombre' => $position->location->nombre,
                    ] : null,
                    'logistic_unit_snapshot' => [
                        'id' => $logisticUnit->id,
                        'license_plate_number' => $logisticUnit->license_plate_number,
                    ],
                ];
            })
            ->filter(fn (array $snapshot) => (float) $snapshot['quantity'] > 0)
            ->values()
            ->all();
    }

    private function isPositionScopedTransferUnit(InventoryTransferUnit $transferUnit): bool
    {
        $metadata = (array) $transferUnit->metadata;

        return filled(Arr::get($metadata, 'partial_transfer.position_id'))
            || filled(Arr::get($metadata, 'requested_position_id'));
    }

    private function receiveTransferUnit(InventoryTransferUnit $transferUnit, InventoryLogisticUnit $logisticUnit, int $userId, int $movementId): void
    {
        if (! $this->positionsTableExists()) {
            $this->logisticUnitService->relocate($logisticUnit, (int) $transferUnit->destination_location_id, $userId, [
                'movement_id' => $movementId,
                'transfer_unit_id' => $transferUnit->id,
            ]);

            return;
        }

        $snapshots = collect(Arr::get((array) $transferUnit->metadata, 'position_snapshots', []));

        if ($snapshots->isEmpty()) {
            $logisticUnit->forceFill([
                'current_location_id' => $transferUnit->destination_location_id,
                'status' => 'active',
                'last_moved_at' => now(),
                'metadata' => array_diff_key((array) $logisticUnit->metadata, ['transfer_context' => true]),
            ])->save();

            return;
        }

        foreach ($snapshots as $snapshot) {
            $position = InventoryStockPosition::query()
                ->lockForUpdate()
                ->find($snapshot['position_id'] ?? null);

            if (! $position) {
                $position = InventoryStockPosition::query()
                    ->lockForUpdate()
                    ->where('logistic_unit_id', $transferUnit->logistic_unit_id)
                    ->where('location_id', $transferUnit->origin_location_id)
                    ->where('material_id', $snapshot['material_id'] ?? $transferUnit->material_id)
                    ->where('lot_code', $snapshot['lot_code'] ?? null)
                    ->where('status', $snapshot['status'] ?? 'available')
                    ->first();
            }

            if (! $position) {
                throw ValidationException::withMessages([
                    'transfer_unit_ids' => 'No fue posible resolver las posiciones despachadas para confirmar la recepción.',
                ]);
            }

            $this->logisticUnitService->transferPosition(
                $position,
                (int) $transferUnit->destination_location_id,
                (float) ($snapshot['quantity'] ?? 0),
                $userId,
                false,
            );

            if ($this->isPositionScopedTransferUnit($transferUnit)) {
                $this->logisticUnitService->syncLogisticUnitQuantity($logisticUnit);
            }
        }

        $logisticUnit->forceFill([
            'current_location_id' => $this->isPositionScopedTransferUnit($transferUnit)
                ? $transferUnit->origin_location_id
                : $transferUnit->destination_location_id,
            'status' => 'active',
            'last_moved_at' => now(),
            'metadata' => array_diff_key((array) $logisticUnit->metadata, ['transfer_context' => true]),
        ])->save();
    }

    private function returnTransferUnitToOrigin(InventoryTransferUnit $transferUnit, InventoryLogisticUnit $logisticUnit, int $userId, int $movementId): void
    {
        if (! $this->positionsTableExists()) {
            $this->logisticUnitService->relocate($logisticUnit, (int) $transferUnit->origin_location_id, $userId, [
                'movement_id' => $movementId,
                'transfer_unit_id' => $transferUnit->id,
                'return' => true,
            ]);

            return;
        }

        $destinationPositions = InventoryStockPosition::query()
            ->where('logistic_unit_id', $transferUnit->logistic_unit_id)
            ->where('location_id', $transferUnit->destination_location_id)
            ->lockForUpdate()
            ->get();

        foreach ($destinationPositions as $position) {
            $this->logisticUnitService->transferPosition(
                $position,
                (int) $transferUnit->origin_location_id,
                (float) $position->quantity,
                $userId,
                false,
            );
        }

        if ($destinationPositions->isEmpty()) {
            $snapshots = collect(Arr::get((array) $transferUnit->metadata, 'position_snapshots', []));
            $originHasPositions = InventoryStockPosition::query()
                ->where('logistic_unit_id', $transferUnit->logistic_unit_id)
                ->where('location_id', $transferUnit->origin_location_id)
                ->exists();

            if (! $originHasPositions) {
                foreach ($snapshots as $snapshot) {
                    InventoryStockPosition::query()->create([
                        'material_id' => $snapshot['material_id'] ?? $transferUnit->material_id,
                        'location_id' => $transferUnit->origin_location_id,
                        'logistic_unit_id' => $transferUnit->logistic_unit_id,
                        'quantity' => (float) ($snapshot['quantity'] ?? 0),
                        'lot_code' => $snapshot['lot_code'] ?? null,
                        'status' => $snapshot['status'] ?? 'available',
                        'metadata' => [
                            'restored_from_transfer_return' => true,
                            'transfer_unit_id' => $transferUnit->id,
                        ],
                    ]);
                }
            }
        }

        $logisticUnit->forceFill([
            'current_location_id' => $transferUnit->origin_location_id,
            'status' => 'active',
            'last_moved_at' => now(),
            'metadata' => array_diff_key((array) $logisticUnit->metadata, ['transfer_context' => true]),
        ])->save();

        $this->logisticUnitService->syncLogisticUnitQuantity($logisticUnit);
    }

    private function locationSnapshot(int $locationId): ?array
    {
        $location = \App\Models\InventoryLocation::query()->find($locationId, ['id', 'codigo', 'nombre']);

        if (! $location) {
            return null;
        }

        return [
            'id' => $location->id,
            'codigo' => $location->codigo,
            'nombre' => $location->nombre,
        ];
    }
}
