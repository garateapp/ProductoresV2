<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Services\Planning\InventoryRepositorySqlsrv;
use App\Services\Planning\MobilePlanningService;
use App\Services\Planning\ProcessProgressSqlsrvService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MobilePlanningController extends Controller
{
    public function __construct(
        private readonly MobilePlanningService $service,
        private readonly InventoryRepositorySqlsrv $inventory,
        private readonly ProcessProgressSqlsrvService $progress,
    ) {
    }

    public function index(Request $request)
    {
        $estado = trim((string) $request->query('estado', 'todo'));
        if (! in_array($estado, ['todo', 'planificado', 'procesado', 'sin_planificar'], true)) {
            $estado = 'todo';
        }

        $filters = [
            'variedad' => $this->queryValues($request, 'variedad'),
            'linea' => $this->queryValues($request, 'linea'),
            'turno' => $this->queryValues($request, 'turno'),
            'exportadora' => $this->queryValues($request, 'exportadora'),
            'estado' => $estado,
            'fecha' => trim((string) $request->query('fecha', now()->toDateString())),
        ];

        $data = $this->service->build($filters);
        $inventoryOptions = $this->inventory->getInventoryFilterOptions();

        return Inertia::render('Planning/Mobile/Index', [
            'filters' => $filters,
            'options' => [
                'variedades' => $this->optionValues($inventoryOptions['variedades'] ?? []),
                'exportadoras' => $this->optionValues($inventoryOptions['exportadoras'] ?? []),
                'lineas' => $data['options']['lineas'] ?? [],
                'turnos' => $data['options']['turnos'] ?? [],
            ],
            'detalle' => $data['detalle'] ?? [],
            'en_curso' => $data['en_curso'] ?? [],
            'grupos' => $data['grupos'] ?? [],
            'resumen' => $data['resumen'] ?? [],
            'velocidad' => $this->progress->kgPerHourByLine(),
        ]);
    }

    private function optionValues(mixed $values): array
    {
        if (! is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->map(function ($value) {
                if (is_array($value)) {
                    $value = $value['value'] ?? $value['label'] ?? '';
                }

                $text = trim((string) $value);

                return $text === '' ? null : ['value' => $text, 'label' => $text];
            })
            ->filter()
            ->unique('value')
            ->values()
            ->all();
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
