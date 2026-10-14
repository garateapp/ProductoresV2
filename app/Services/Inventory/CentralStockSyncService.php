<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovementType;
use App\Models\InventoryStockPosition;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CentralStockSyncService
{
    private const CENTRAL_LOCATION_CODE = 'BODEGA_CENTRAL';

    private const PRODUCTION_LOCATION_CODE = 'PRODUCCION_1';

    private const CENTRAL_WAREHOUSES = ['BD009', 'BD026'];

    private const PRODUCTION_WAREHOUSE = 'BD010';

    public function __construct(
        private readonly MovementService $movementService,
    ) {}

    public function syncFromSapOnHand(int $userId): array
    {
        $centralLocation = InventoryLocation::query()
            ->where('codigo', self::CENTRAL_LOCATION_CODE)
            ->first();

        if (! $centralLocation) {
            throw ValidationException::withMessages([
                'inventory' => 'No existe la ubicación Bodega Central.',
            ]);
        }

        $productionLocation = InventoryLocation::query()
            ->where('codigo', self::PRODUCTION_LOCATION_CODE)
            ->first();

        if (! $productionLocation) {
            throw ValidationException::withMessages([
                'inventory' => 'No existe la ubicación Producción 1.',
            ]);
        }

        $positiveType = InventoryMovementType::query()->where('codigo', 'AJUSTE_POS')->first();
        $negativeType = InventoryMovementType::query()->where('codigo', 'AJUSTE_NEG')->first();

        if (! $positiveType || ! $negativeType) {
            throw ValidationException::withMessages([
                'inventory' => 'No están configurados los tipos AJUSTE_POS y AJUSTE_NEG.',
            ]);
        }

        $locations = [
            $centralLocation->id => $centralLocation,
            $productionLocation->id => $productionLocation,
        ];

        $materials = InventoryMaterial::query()
            ->whereNull('service_id')
            ->with(['stockLocations' => fn ($query) => $query
                ->whereIn('location_id', array_keys($locations))
                ->select(['id', 'material_id', 'location_id', 'stock_actual'])])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'sap_on_hand', 'metadata']);

        $positiveByLocation = [];
        $negativeByLocation = [];
        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($materials as $material) {
            foreach ($this->expectedStockByLocation($material, $centralLocation->id, $productionLocation->id) as $locationId => $expected) {
                $internal = round((float) ($material->stockLocations
                    ->firstWhere('location_id', $locationId)?->stock_actual ?? 0), 4);
                $difference = round($expected - $internal, 4);

                if (abs($difference) < 0.0001) {
                    continue;
                }

                if ($difference > 0) {
                    $positiveByLocation[$locationId][] = $this->buildDetail($material->id, $difference, 'entrada');
                    $positiveCount++;

                    continue;
                }

                foreach ($this->buildNegativeDetails($material->id, $locationId, abs($difference)) as $detail) {
                    $negativeByLocation[$locationId][] = $detail;
                }

                $negativeCount++;
            }
        }

        $createdFolios = [];

        foreach ($locations as $locationId => $location) {
            $positives = $positiveByLocation[$locationId] ?? [];
            $negatives = $negativeByLocation[$locationId] ?? [];

            if (! empty($positives)) {
                $movement = $this->movementService->create([
                    'movement_type_id' => $positiveType->id,
                    'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
                    'origin_location_id' => null,
                    'destination_location_id' => $locationId,
                    'referencia_tipo' => 'sap_sync',
                    'referencia_id' => null,
                    'motivo' => 'Sincronización SAP a '.$location->nombre,
                    'observacion' => 'Carga automática desde vGEX_STOCK.',
                    'details' => $positives,
                ], $userId, true);

                $createdFolios[] = $movement->folio;
            }

            if (! empty($negatives)) {
                $movement = $this->movementService->create([
                    'movement_type_id' => $negativeType->id,
                    'fecha_movimiento' => now()->format('Y-m-d H:i:s'),
                    'origin_location_id' => $locationId,
                    'destination_location_id' => null,
                    'referencia_tipo' => 'sap_sync',
                    'referencia_id' => null,
                    'motivo' => 'Ajuste por conciliación SAP en '.$location->nombre,
                    'observacion' => 'Regularización automática desde vGEX_STOCK.',
                    'details' => $negatives,
                ], $userId, true);

                $createdFolios[] = $movement->folio;
            }
        }

        return [
            'location' => $centralLocation->nombre.' y '.$productionLocation->nombre,
            'materials_adjusted' => $positiveCount + $negativeCount,
            'positive_count' => $positiveCount,
            'negative_count' => $negativeCount,
            'folios' => $createdFolios,
        ];
    }

    private function expectedStockByLocation(InventoryMaterial $material, int $centralLocationId, int $productionLocationId): array
    {
        $sap = $material->metadata['sap'] ?? [];

        $central = $sap['central_stock'] ?? null;
        $production = $sap['production_stock'] ?? null;

        if ($central === null) {
            $central = (float) ($material->sap_on_hand ?? 0);
        }

        if ($production === null) {
            $production = 0.0;
        }

        return [
            $centralLocationId => round((float) $central, 4),
            $productionLocationId => round((float) $production, 4),
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

    /**
     * Asigna la baja FIFO contra las posiciones de stock existentes. Cuando la
     * diferencia supera el stock posicionado, el remanente se aplica de forma genérica.
     */
    private function buildNegativeDetails(int $materialId, int $locationId, float $quantity): array
    {
        $quantity = round($quantity, 4);

        if (! Schema::hasTable('inventory_stock_positions')) {
            return [$this->buildDetail($materialId, $quantity, 'salida')];
        }

        $positions = InventoryStockPosition::query()
            ->where('location_id', $locationId)
            ->where('material_id', $materialId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($positions->isEmpty()) {
            return [$this->buildDetail($materialId, $quantity, 'salida')];
        }

        $details = [];
        $remaining = $quantity;

        foreach ($positions as $position) {
            if ($remaining <= 0.0001) {
                break;
            }

            $take = round(min($remaining, (float) $position->quantity), 4);

            if ($take <= 0) {
                continue;
            }

            $details[] = [
                'material_id' => $materialId,
                'sentido' => 'salida',
                'cantidad' => $take,
                'observacion' => null,
                'position_id' => $position->id,
            ];

            $remaining = round($remaining - $take, 4);
        }

        if ($remaining > 0.0001) {
            $details[] = $this->buildDetail($materialId, $remaining, 'salida');
        }

        return $details;
    }
}
