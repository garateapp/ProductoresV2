<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Services\Planning\FruitInventoryService;
use App\Services\Planning\InventoryRepositorySqlsrv;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FruitInventoryController extends Controller
{
    use AuthorizesPlanning;

    public function __construct(
        private readonly FruitInventoryService $service,
        private readonly InventoryRepositorySqlsrv $inventory,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $filters = [
            'especie' => $this->queryValues($request, 'especie'),
            'variedad' => $this->queryValues($request, 'variedad'),
            'fecha_from' => trim((string) $request->query('fecha_from', now()->toDateString())),
            'fecha_to' => trim((string) $request->query('fecha_to', now()->toDateString())),
            'exportadora' => $this->queryValues($request, 'exportadora'),
        ];

        $data = $this->service->build($filters);

        return Inertia::render('Planning/FruitInventory/Index', [
            'filters' => $filters,
            'options' => $this->inventory->getInventoryFilterOptions(),
            'detalle' => $data['detalle'],
            'resumen' => $data['resumen'],
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
