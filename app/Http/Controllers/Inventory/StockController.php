<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryLocation;
use App\Models\InventoryMaterial;
use App\Models\InventoryMaterialFamily;
use App\Models\InventoryStockLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request): Response
    {
        $this->authorizeInventory($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'location_id' => (string) $request->input('location_id', ''),
            'location_type' => (string) $request->input('location_type', ''),
            'material_id' => (string) $request->input('material_id', ''),
            'family_id' => (string) $request->input('family_id', ''),
            'stock_state' => (string) $request->input('stock_state', 'positive'),
            'per_page' => (string) $request->input('per_page', '20'),
        ];

        $perPage = in_array((int) $filters['per_page'], [20, 50, 100], true) ? (int) $filters['per_page'] : 20;

        $baseQuery = $this->buildStockQuery($filters, true);

        $this->applyStockStateFilter($baseQuery, $filters['stock_state']);

        $summaryQuery = $this->buildStockQuery($filters);
        $this->applyStockStateFilter($summaryQuery, $filters['stock_state']);

        $positionsCount = (clone $baseQuery)->count();
        $positivePositions = (clone $baseQuery)->where('stock_actual', '>', 0)->count();
        $negativePositions = (clone $baseQuery)->where('stock_actual', '<', 0)->count();
        $stockTotal = (float) (clone $baseQuery)->sum('stock_actual');

        $locationsSummary = $summaryQuery
            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock_locations.location_id')
            ->groupBy('inventory_locations.id', 'inventory_locations.nombre', 'inventory_locations.tipo')
            ->orderByDesc(DB::raw('SUM(inventory_stock_locations.stock_actual)'))
            ->limit(8)
            ->get([
                'inventory_locations.id',
                'inventory_locations.nombre',
                'inventory_locations.tipo',
                DB::raw('COUNT(*) as positions_count'),
                DB::raw('SUM(inventory_stock_locations.stock_actual) as stock_total'),
                DB::raw('SUM(CASE WHEN inventory_stock_locations.stock_actual < 0 THEN 1 ELSE 0 END) as negative_positions'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nombre' => $row->nombre,
                'tipo' => $row->tipo,
                'positions_count' => (int) $row->positions_count,
                'stock_total' => (float) $row->stock_total,
                'negative_positions' => (int) $row->negative_positions,
            ])
            ->values();

        $stocks = $baseQuery
            ->orderByDesc('stock_actual')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (InventoryStockLocation $stock) {
                $stockActual = (float) $stock->stock_actual;
                $materialInternalTotal = (float) ($stock->material_internal_total ?? 0);
                $sapOnHand = (float) ($stock->material?->sap_on_hand ?? 0);

                return [
                    'id' => $stock->id,
                    'location' => [
                        'id' => $stock->location?->id,
                        'codigo' => $stock->location?->codigo,
                        'nombre' => $stock->location?->nombre,
                        'tipo' => $stock->location?->tipo,
                    ],
                    'material' => [
                        'id' => $stock->material?->id,
                        'codigo' => $stock->material?->codigo,
                        'nombre' => $stock->material?->nombre,
                        'familia' => $stock->material?->family?->nombre,
                        'unidad' => $stock->material?->unit?->codigo,
                    ],
                    'stock_actual' => $stockActual,
                    'material_internal_total' => $materialInternalTotal,
                    'sap_on_hand' => $sapOnHand,
                    'distribution_ratio' => $materialInternalTotal > 0
                        ? round(($stockActual / $materialInternalTotal) * 100, 2)
                        : 0,
                    'status' => $stockActual < 0 ? 'negative' : ($stockActual == 0.0 ? 'zero' : 'positive'),
                ];
            });

        return Inertia::render('Inventory/Stocks/Index', [
            'filters' => $filters,
            'summary' => [
                'positions' => $positionsCount,
                'positive_positions' => $positivePositions,
                'negative_positions' => $negativePositions,
                'stock_total' => round($stockTotal, 4),
            ],
            'locationSummaries' => $locationsSummary,
            'stocks' => $stocks,
            'locations' => InventoryLocation::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo'])
                ->map(fn (InventoryLocation $location) => [
                    'id' => $location->id,
                    'nombre' => $location->nombre,
                    'tipo' => $location->tipo,
                ]),
            'materials' => InventoryMaterial::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'families' => InventoryMaterialFamily::query()->orderBy('nombre')->get(['id', 'nombre']),
            'locationTypes' => InventoryLocation::query()
                ->select('tipo')
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo')
                ->values(),
        ]);
    }

    private function applyStockStateFilter(Builder $query, string $stockState): void
    {
        match ($stockState) {
            'negative' => $query->where('stock_actual', '<', 0),
            'zero' => $query->where('stock_actual', '=', 0),
            'all' => null,
            default => $query->where('stock_actual', '>', 0),
        };
    }

    private function buildStockQuery(array $filters, bool $withRelations = false): Builder
    {
        $query = InventoryStockLocation::query();

        if ($withRelations) {
            $query->select('inventory_stock_locations.*')
                ->selectSub(
                    InventoryStockLocation::query()
                        ->selectRaw('COALESCE(SUM(stock_actual), 0)')
                        ->whereColumn('material_id', 'inventory_stock_locations.material_id'),
                    'material_internal_total'
                )
                ->with([
                    'location:id,codigo,nombre,tipo,activo',
                    'material:id,codigo,nombre,family_id,unit_id,sap_on_hand,activo',
                    'material.family:id,nombre',
                    'material.unit:id,codigo',
                ]);
        }

        return $query
            ->whereHas('location', function (Builder $query) use ($filters): void {
                $query->when($filters['location_id'] !== '', fn (Builder $inner) => $inner->where('id', $filters['location_id']))
                    ->when($filters['location_type'] !== '', fn (Builder $inner) => $inner->where('tipo', $filters['location_type']));
            })
            ->whereHas('material', function (Builder $query) use ($filters): void {
                $query->when($filters['material_id'] !== '', fn (Builder $inner) => $inner->where('id', $filters['material_id']))
                    ->when($filters['family_id'] !== '', fn (Builder $inner) => $inner->where('family_id', $filters['family_id']));
            })
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery->whereHas('material', function (Builder $materialQuery) use ($filters): void {
                        $materialQuery->where('codigo', 'like', '%'.$filters['q'].'%')
                            ->orWhere('nombre', 'like', '%'.$filters['q'].'%');
                    })->orWhereHas('location', function (Builder $locationQuery) use ($filters): void {
                        $locationQuery->where('codigo', 'like', '%'.$filters['q'].'%')
                            ->orWhere('nombre', 'like', '%'.$filters['q'].'%');
                    });
                });
            });
    }
}
