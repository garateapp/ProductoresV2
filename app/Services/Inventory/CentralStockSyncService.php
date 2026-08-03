<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CentralStockSyncService
{
    public function __construct(
        private readonly MovementService $movementService,
    ) {
    }

    public function syncFromSapOnHand(int $userId): array
    {
        $centralLocation = InventoryLocation::query()
            ->where('codigo', 'BODEGA_CENTRAL')
            ->first();

        if (! $centralLocation) {
            throw ValidationException::withMessages([
                'inventory' => 'No existe la ubicación Bodega Central.',
            ]);
        }

        $positiveType = InventoryMovementType::query()->where('codigo', 'AJUSTE_POS')->first();
        $negativeType = InventoryMovementType::query()->where('codigo', 'AJUSTE_NEG')->first();

        if (! $positiveType || ! $negativeType) {
            throw ValidationException::withMessages([
                'inventory' => 'No están configurados los tipos AJUSTE_POS y AJUSTE_NEG.',
            ]);
        }

        $materials = InventoryMaterial::query()
            ->with(['stockLocations' => fn ($query) => $query
                ->where('location_id', $centralLocation->id)
                ->select(['id', 'material_id', 'location_id', 'stock_actual'])])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'sap_on_hand']);

        $positiveDetails = [];
        $negativeDetails = [];

        foreach ($materials as $material) {
            $sapOnHand = round((float) ($material->sap_on_hand ?? 0), 4);
            $internalStock = round((float) ($material->stockLocations->first()?->stock_actual ?? 0), 4);
            $difference = round($sapOnHand - $internalStock, 4);

            if (abs($difference) < 0.0001) {
                continue;
            }

            if ($difference > 0) {
                $positiveDetails[] = $this->buildDetail($material->id, $difference, 'entrada');
                continue;
            }

            $negativeDetails[] = $this->buildDetail($material->id, abs($difference), 'salida');
        }

        $createdFolios = [];

        if (! empty($positiveDetails)) {
            $movement = $this->movementService->create([
                'movement_type_id' => $positiveType->id,
                'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
                'origin_location_id' => null,
                'destination_location_id' => $centralLocation->id,
                'referencia_tipo' => 'sap_sync',
                'referencia_id' => null,
                'motivo' => 'Sincronización SAP a Bodega Central',
                'observacion' => 'Carga automática desde sap_on_hand.',
                'details' => $positiveDetails,
            ], $userId, true);

            $createdFolios[] = $movement->folio;
        }

        if (! empty($negativeDetails)) {
            $movement = $this->movementService->create([
                'movement_type_id' => $negativeType->id,
                'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
                'origin_location_id' => $centralLocation->id,
                'destination_location_id' => null,
                'referencia_tipo' => 'sap_sync',
                'referencia_id' => null,
                'motivo' => 'Ajuste por conciliación SAP en Bodega Central',
                'observacion' => 'Regularización automática desde sap_on_hand.',
                'details' => $negativeDetails,
            ], $userId, true);

            $createdFolios[] = $movement->folio;
        }

        return [
            'location' => $centralLocation->nombre,
            'materials_adjusted' => count($positiveDetails) + count($negativeDetails),
            'positive_count' => count($positiveDetails),
            'negative_count' => count($negativeDetails),
            'folios' => $createdFolios,
        ];
    }

    private function buildDetail(int $materialId, float $quantity, string $direction): array
    {
        return [
            'material_id' => $materialId,
            'sentido' => $direction,
            'cantidad' => round($quantity, 4),
            'observacion' => null,
        ];
    }
}
