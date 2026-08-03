<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryStockLocation;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function getStock(int $materialId, int $locationId): InventoryStockLocation
    {
        return InventoryStockLocation::query()->firstOrCreate(
            ['material_id' => $materialId, 'location_id' => $locationId],
            ['stock_actual' => 0]
        );
    }

    public function increase(int $materialId, int $locationId, float $quantity): InventoryStockLocation
    {
        $stock = InventoryStockLocation::query()
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = InventoryStockLocation::create([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'stock_actual' => 0,
            ]);
        }

        $stock->stock_actual = (float) $stock->stock_actual + $quantity;
        $stock->save();

        return $stock;
    }

    public function decrease(int $materialId, int $locationId, float $quantity): InventoryStockLocation
    {
        $stock = InventoryStockLocation::query()
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        $available = (float) ($stock?->stock_actual ?? 0);
        $location = InventoryLocation::findOrFail($locationId);

        if (! $location->permite_stock_negativo && $available < $quantity) {
            throw ValidationException::withMessages([
                'stock' => "Stock insuficiente en {$location->nombre}. Disponible: {$available}. Requerido: {$quantity}.",
            ]);
        }

        if (! $stock) {
            $stock = InventoryStockLocation::create([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'stock_actual' => 0,
            ]);
        }

        $stock->stock_actual = (float) $stock->stock_actual - $quantity;
        $stock->save();

        return $stock;
    }
}
