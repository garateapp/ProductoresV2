<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Services\Planning\InventoryRepositorySqlsrv;
use App\Services\Planning\MobilePlanningService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MobilePlanningController extends Controller
{
    public function __construct(
        private readonly MobilePlanningService $service,
        private readonly InventoryRepositorySqlsrv $inventory,
    ) {
    }

    public function index(Request $request)
    {
        $filters = [
            'variedad' => $this->queryValues($request, 'variedad'),
            'linea' => $this->queryValues($request, 'linea'),
            'turno' => $this->queryValues($request, 'turno'),
            'exportadora' => $this->queryValues($request, 'exportadora'),
            'fecha' => trim((string) $request->query('fecha', now()->toDateString())),
        ];

        $data = $this->service->build($filters);
        $inventoryOptions = $this->inventory->getInventoryFilterOptions();

        return Inertia::render('Planning/Mobile/Index', [
            'filters' => $filters,
            'options' => [
                'variedades' => $inventoryOptions['variedades'] ?? [],
                'exportadoras' => $inventoryOptions['exportadoras'] ?? [],
                'lineas' => $data['options']['lineas'] ?? [],
                'turnos' => $data['options']['turnos'] ?? [],
            ],
            'detalle' => $data['detalle'] ?? [],
            'grupos' => $data['grupos'] ?? [],
            'resumen' => $data['resumen'] ?? [],
        ]);
    }

    private function queryValues(Request $request, string $key): array
    {
        $values = $request->query($key, []);

        if (! is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->flatMap(fn ($value) => preg_split('/\s*,\s*/', (string) $value) ?: [])
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
