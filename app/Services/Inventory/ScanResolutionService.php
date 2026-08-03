<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryLogisticUnit;
use App\Models\InventoryMaterial;
use App\Models\InventoryScanEvent;
use Illuminate\Support\Str;

class ScanResolutionService
{
    public function resolve(string $rawCode, int $userId, array $context = []): array
    {
        $code = trim($rawCode);
        $sessionUuid = (string) ($context['scan_session_uuid'] ?? Str::uuid());
        $deviceCode = $context['device_code'] ?? null;
        $step = $context['step'] ?? 'resolve';
        $movementId = $context['movement_id'] ?? null;

        foreach ([
            ['type' => 'location', 'entity' => $this->resolveLocation($code), 'message' => 'Ubicación válida'],
            ['type' => 'logistic_unit', 'entity' => $this->resolveLogisticUnit($code), 'message' => 'Pallet encontrado'],
            ['type' => 'material', 'entity' => $this->resolveMaterial($code), 'message' => 'Material encontrado'],
        ] as $candidate) {
            if (! $candidate['entity']) {
                continue;
            }

            $result = [
                'entity_type' => $candidate['type'],
                'entity_id' => $candidate['entity']->id,
                'display' => match ($candidate['type']) {
                    'location' => $candidate['entity']->path_code ?: $candidate['entity']->nombre,
                    'logistic_unit' => $candidate['entity']->license_plate_number,
                    default => trim($candidate['entity']->codigo.' · '.$candidate['entity']->nombre),
                },
                'success' => true,
                'message' => $candidate['message'],
            ];

            $this->logScan([
                'scan_session_uuid' => $sessionUuid,
                'movement_id' => $movementId,
                'step' => $step,
                'raw_code' => $code,
                'code_type' => $candidate['type'],
                'resolved_entity_type' => $candidate['type'],
                'resolved_entity_id' => $candidate['entity']->id,
                'success' => true,
                'message' => $candidate['message'],
                'user_id' => $userId,
                'device_code' => $deviceCode,
                'scanned_at' => now(),
                'payload' => $result,
            ]);

            return ['scan_session_uuid' => $sessionUuid, ...$result];
        }

        $result = [
            'entity_type' => 'unknown',
            'entity_id' => null,
            'display' => $code,
            'success' => false,
            'message' => 'Código no reconocido',
        ];

        $this->logScan([
            'scan_session_uuid' => $sessionUuid,
            'movement_id' => $movementId,
            'step' => $step,
            'raw_code' => $code,
            'code_type' => 'unknown',
            'success' => false,
            'message' => $result['message'],
            'user_id' => $userId,
            'device_code' => $deviceCode,
            'scanned_at' => now(),
            'payload' => $result,
        ]);

        return ['scan_session_uuid' => $sessionUuid, ...$result];
    }

    public function resolveLocation(string $rawCode): ?InventoryLocation
    {
        $code = trim($rawCode);

        return InventoryLocation::query()
            ->where('scan_code', $code)
            ->orWhere('codigo', $code)
            ->first();
    }

    public function resolveLogisticUnit(string $rawCode): ?InventoryLogisticUnit
    {
        return InventoryLogisticUnit::query()
            ->where('license_plate_number', trim($rawCode))
            ->first();
    }

    public function resolveMaterial(string $rawCode): ?InventoryMaterial
    {
        return InventoryMaterial::query()
            ->where('codigo', trim($rawCode))
            ->first();
    }

    public function logScan(array $data): InventoryScanEvent
    {
        return InventoryScanEvent::create($data);
    }
}
