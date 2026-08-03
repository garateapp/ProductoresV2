<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMovement;
use App\Models\InventoryProduction;
use App\Models\InventoryStockLocation;
use App\Services\Inventory\TheoreticalConsumptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request, TheoreticalConsumptionService $theoreticalConsumptionService): Response
    {
        $this->authorizeInventory($request);

        $stocks = InventoryStockLocation::query()
            ->with(['location:id,nombre', 'material:id,codigo,nombre'])
            ->where('stock_actual', '>', 0)
            ->orderByDesc('stock_actual')
            ->limit(12)
            ->get()
            ->map(fn (InventoryStockLocation $stock) => [
                'id' => $stock->id,
                'location' => $stock->location?->nombre,
                'material' => trim(($stock->material?->codigo ?? '').' · '.($stock->material?->nombre ?? '')),
                'stock_actual' => (float) $stock->stock_actual,
            ]);

        $movements = InventoryMovement::query()
            ->with(['type:id,codigo,nombre', 'origin:id,nombre', 'destination:id,nombre', 'creator:id,name'])
            ->latest('fecha_movimiento')
            ->limit(10)
            ->get()
            ->map(fn (InventoryMovement $movement) => [
                'id' => $movement->id,
                'folio' => $movement->folio,
                'fecha_movimiento' => optional($movement->fecha_movimiento)->format('Y-m-d H:i'),
                'estado' => $movement->estado,
                'tipo' => $movement->type?->nombre,
                'origen' => $movement->origin?->nombre,
                'destino' => $movement->destination?->nombre,
                'creador' => $movement->creator?->name,
            ]);

        $productions = InventoryProduction::query()
            ->with('packaging:id,nombre')
            ->latest('fecha')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(function (InventoryProduction $production) use ($theoreticalConsumptionService) {
                $calculation = $theoreticalConsumptionService->forProduction($production);

                return [
                    'id' => $production->id,
                    'fecha' => optional($production->fecha)->format('Y-m-d'),
                    'turno' => $production->turno,
                    'linea' => $production->linea,
                    'embalaje' => $production->packaging?->nombre,
                    'cantidad_cajas' => (float) $production->cantidad_cajas,
                    'cantidad_pallets' => (float) $production->cantidad_pallets,
                    'teorico_total' => $calculation['summary']['theoretical_total'] ?? 0,
                    'real_total' => $calculation['summary']['real_total'] ?? 0,
                    'desviacion_total' => $calculation['summary']['deviation_total'] ?? 0,
                ];
            });

        return Inertia::render('Inventory/Dashboard', [
            'summary' => [
                'materials' => InventoryMaterial::query()->count(),
                'locations' => InventoryLocation::query()->where('activo', true)->count(),
                'movements_today' => InventoryMovement::query()->whereDate('fecha_movimiento', now()->toDateString())->count(),
                'pending_receipts' => InventoryMovement::query()->where('estado', 'aplicado')->whereNotNull('destination_location_id')->count(),
                'low_stock_count' => InventoryMaterial::underMinimumStock()->count(),
            ],
            'stocks' => $stocks,
            'movements' => $movements,
            'productions' => $productions,
            'lowStockMaterials' => InventoryMaterial::underMinimumStock()->limit(5)->get(['id', 'codigo', 'nombre', 'stock_minimo', 'sap_on_hand']),
        ]);
    }
}
