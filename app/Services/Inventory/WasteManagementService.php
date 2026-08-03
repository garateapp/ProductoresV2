<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryMovementDetail;
use App\Models\InventoryWasteRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WasteManagementService
{
    public function createFromMovement(InventoryMovement $movement, InventoryMovementDetail $detail, array $context, int $userId): InventoryWasteRecord
    {
        $detectedLocationId = $context['detected_location_id'] ?? null;
        $quarantineLocationId = $context['quarantine_location_id'] ?? null;
        $logisticUnitId = $context['logistic_unit_id'] ?? null;

        if (! $detectedLocationId) {
            throw ValidationException::withMessages([
                'detected_location_id' => 'La ubicación exacta de merma es obligatoria.',
            ]);
        }

        InventoryLocation::query()->findOrFail($detectedLocationId);
        if ($quarantineLocationId) {
            InventoryLocation::query()->findOrFail($quarantineLocationId);
        }

        $quantity = (float) $detail->cantidad;
        $requiresReview = $quantity >= (float) config('inventory.waste.review_threshold_quantity', 50);
        $status = $requiresReview ? 'review_pending' : 'reported';

        return InventoryWasteRecord::create([
            'code' => $this->generateCode(),
            'movement_id' => $movement->id,
            'movement_detail_id' => $detail->id,
            'material_id' => $detail->material_id,
            'logistic_unit_id' => $logisticUnitId,
            'detected_location_id' => $detectedLocationId,
            'quarantine_location_id' => $quarantineLocationId,
            'waste_reason_id' => $movement->waste_reason_id,
            'waste_type_id' => $movement->metadata['waste_type_id'] ?? null,
            'quantity' => $quantity,
            'status' => $status,
            'severity' => $requiresReview ? 'high' : 'normal',
            'requires_supervisor_review' => $requiresReview,
            'photo_path' => $context['photo_path'] ?? null,
            'evidence_payload' => $context['evidence_payload'] ?? null,
            'reported_by' => $userId,
            'reported_at' => now(),
            'notes' => $context['notes'] ?? $movement->observacion,
        ]);
    }

    public function review(InventoryWasteRecord $record, int $userId, array $data = []): InventoryWasteRecord
    {
        $record->forceFill([
            'status' => $data['status'] ?? 'approved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'notes' => $data['notes'] ?? $record->notes,
        ])->save();

        return $record->fresh();
    }

    public function sendToQuarantine(InventoryWasteRecord $record, int $locationId, int $userId): InventoryWasteRecord
    {
        InventoryLocation::query()->findOrFail($locationId);

        $record->forceFill([
            'quarantine_location_id' => $locationId,
            'status' => 'sent_to_quarantine',
            'reviewed_by' => $record->reviewed_by ?? $userId,
            'reviewed_at' => $record->reviewed_at ?? now(),
        ])->save();

        return $record->fresh();
    }

    public function dispose(InventoryWasteRecord $record, int $userId, array $data = []): InventoryWasteRecord
    {
        $record->forceFill([
            'status' => 'disposed',
            'reviewed_by' => $record->reviewed_by ?? $userId,
            'reviewed_at' => $record->reviewed_at ?? now(),
            'notes' => $data['notes'] ?? $record->notes,
        ])->save();

        // Cargar movimiento para verificar tipo de merma
        $record->load(['movement']);
        $metadata = (array) ($record->movement->metadata ?? []);
        
        \Illuminate\Support\Facades\Log::info('Disposing waste record', [
            'id' => $record->id,
            'metadata' => $metadata,
            'waste_type_id' => $metadata['waste_type_id'] ?? 'N/A'
        ]);

        $wasteTypeId = $metadata['waste_type_id'] ?? null;

        if ($wasteTypeId) {
            $wasteType = \App\Models\InventoryWasteType::find($wasteTypeId);
            
            \Illuminate\Support\Facades\Log::info('Checking waste type for act', [
                'wasteTypeId' => $wasteTypeId,
                'exists' => !!$wasteType,
                'permite_devolucion' => $wasteType?->permite_devolucion
            ]);

            // Si el tipo de merma no permite devolución, crear acta
            if ($wasteType && !$wasteType->permite_devolucion) {
                \App\Models\InventoryDestructionAct::create([
                    'waste_record_id' => $record->id,
                    'user_id' => $userId,
                    'folio' => 'ACT-' . $record->code,
                    'observaciones' => $data['notes'] ?? 'Acta generada automáticamente al disponer merma.',
                ]);
            }
        }

        return $record->fresh();
    }

    private function generateCode(): string
    {
        return 'WST-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
