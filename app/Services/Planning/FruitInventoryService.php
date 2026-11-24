<?php

namespace App\Services\Planning;

use App\Models\PackingProcess;
use App\Models\Recepcion;

class FruitInventoryService
{
    public function __construct(private readonly InventoryRepositorySqlsrv $inventory)
    {
    }

    public function build(array $filters = []): array
    {
        $planes = $this->planByNg($filters);
        $planNgs = array_keys($planes);

        $stockByNg = $this->inventory->getAvailableLots([
            'id_empresa' => $filters['id_empresa'] ?? 1,
            'creacion_tipo' => $filters['creacion_tipo'] ?? 'RFG',
            'especie' => $filters['especie'] ?? null,
            'variedad' => $filters['variedad'] ?? null,
            'exportadora' => $filters['exportadora'] ?? null,
            'limit' => (int) ($filters['limit'] ?? 5000),
        ])
            ->values()
            ->mapWithKeys(fn (array $row) => [trim((string) ($row['n_g_recepcion'] ?? '')) => $row])
            ->filter(fn ($row, string $ng) => $ng !== '');

        $ngs = collect($planNgs)
            ->merge($stockByNg->keys())
            ->unique()
            ->values()
            ->all();

        $exportadoraByNg = $this->exportadoraByNg($ngs);

        $detalle = collect($ngs)
            ->map(function (string $ng) use ($planes, $stockByNg, $exportadoraByNg, $filters) {
                $plan = $planes[$ng] ?? null;
                $stock = $stockByNg->get($ng);

                if (! $stock && ! $this->planPassesFilters($plan, $filters)) {
                    return null;
                }

                $row = $stock ?? [
                    'n_g_recepcion' => $ng,
                    'n_exportadora' => $plan['exportadora'] ?? '',
                    'n_especie' => $plan['especie'] ?? null,
                    'n_variedad' => null,
                    'cantidad_bins' => 0,
                    'peso_neto' => 0,
                ];

                $peso = round((float) ($row['peso_neto'] ?? 0), 3);
                $planPeso = $plan ? round((float) $plan['asignado_peso_neto'], 3) : 0;
                $asignado = round($peso > 0 ? min($peso, $planPeso) : $planPeso, 3);

                $exportadora = trim((string) ($row['n_exportadora'] ?? ''));
                if ($exportadora === '') {
                    $exportadora = trim((string) ($plan['exportadora'] ?? ''));
                }
                if ($exportadora === '') {
                    $exportadora = trim((string) ($exportadoraByNg[$ng] ?? ''));
                }
                if ($exportadora === '') {
                    $exportadora = '(sin exportadora)';
                }

                return [
                    'exportadora' => $exportadora,
                    'especie' => $row['n_especie'] ?? $row['especie'] ?? null,
                    'numproceso' => $plan['numproceso'] ?? '—',
                    'fecha_proceso' => $plan['fecha_proceso'] ?? null,
                    'n_g_recepcion' => $ng,
                    'csg_productor' => $row['csg_productor'] ?? null,
                    'referencias_recepcion' => $row['referencias_recepcion'] ?? null,
                    'numero_guia' => $row['numero_guia'] ?? null,
                    'fecha_recepcion' => $row['fecha_recepcion'] ?? null,
                    'fecha_cosecha' => $row['fecha_cosecha'] ?? null,
                    'n_categoria' => $row['n_categoria'] ?? null,
                    'n_productor' => $row['n_productor'] ?? null,
                    'n_variedad' => $row['n_variedad'] ?? $row['variedad'] ?? null,
                    'nota_calidad_sqlsrv' => $row['nota_calidad_sqlsrv'] ?? null,
                    'c_centrocosto' => $row['c_centrocosto'] ?? null,
                    'n_bodega' => $row['n_bodega'] ?? null,
                    'cantidad_bins' => (int) ($row['cantidad_bins'] ?? 0),
                    'asignado_cantidad_bins' => $plan ? (int) $plan['asignado_cantidad_bins'] : 0,
                    'peso_neto' => $peso,
                    'asignado_peso_neto' => $asignado,
                    'pendiente_peso_neto' => round(max(0, $peso - $asignado), 3),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $detalleCollection = collect($detalle);

        return [
            'detalle' => $detalle,
            'resumen' => [
                'general' => [
                    'lotes' => $detalleCollection->count(),
                    'cantidad_bins' => (int) $detalleCollection->sum('cantidad_bins'),
                    'asignado_cantidad_bins' => (int) $detalleCollection->sum('asignado_cantidad_bins'),
                    'peso_neto' => round((float) $detalleCollection->sum('peso_neto'), 3),
                    'asignado_peso_neto' => round((float) $detalleCollection->sum('asignado_peso_neto'), 3),
                    'pendiente_peso_neto' => round((float) $detalleCollection->sum('pendiente_peso_neto'), 3),
                ],
                'por_exportadora' => $detalleCollection
                    ->groupBy('exportadora')
                    ->map(function ($items, string $exportadora) {
                        return [
                            'exportadora' => $exportadora,
                            'lotes' => $items->count(),
                            'cantidad_bins' => (int) $items->sum('cantidad_bins'),
                            'asignado_cantidad_bins' => (int) $items->sum('asignado_cantidad_bins'),
                            'peso_neto' => round((float) $items->sum('peso_neto'), 3),
                            'asignado_peso_neto' => round((float) $items->sum('asignado_peso_neto'), 3),
                            'pendiente_peso_neto' => round((float) $items->sum('pendiente_peso_neto'), 3),
                        ];
                    })
                    ->sortBy('exportadora')
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function planByNg(array $filters): array
    {
        $query = PackingProcess::query()->with('lots');

        if (! empty($filters['especie'])) {
            $query->whereIn('especie', $this->normalizeValues($filters['especie']));
        }
        if (! empty($filters['exportadora'])) {
            $query->whereIn('exportadora', $this->normalizeValues($filters['exportadora']));
        }
        if (! empty($filters['fecha_from'])) {
            $query->whereDate('fecha', '>=', (string) $filters['fecha_from']);
        }
        if (! empty($filters['fecha_to'])) {
            $query->whereDate('fecha', '<=', (string) $filters['fecha_to']);
        }

        $planes = [];

        foreach ($query->get() as $process) {
            foreach ($process->lots as $lot) {
                $ng = trim((string) ($lot->n_g_recepcion ?? ''));
                if ($ng === '') {
                    continue;
                }

                $plan = $planes[$ng] ?? [
                    'asignado_peso_neto' => 0,
                    'asignado_cantidad_bins' => 0,
                    'numprocesos' => [],
                    'especie' => trim((string) ($process->especie ?? '')),
                    'exportadora' => trim((string) ($process->exportadora ?? '')),
                    'fecha_proceso' => $process->fecha ? $process->fecha->toDateString() : null,
                ];

                $plan['asignado_peso_neto'] += (float) ($lot->peso_neto ?? 0);
                $plan['asignado_cantidad_bins'] += (int) ($lot->cantidad_bins ?? 0);

                $numproceso = trim((string) ($process->numproceso ?? ''));
                $label = $numproceso !== '' ? $numproceso : '#'.$process->id;
                if (! in_array($label, $plan['numprocesos'], true)) {
                    $plan['numprocesos'][] = $label;
                }

                if ($plan['especie'] === '') {
                    $plan['especie'] = trim((string) ($process->especie ?? ''));
                }
                if ($plan['exportadora'] === '') {
                    $plan['exportadora'] = trim((string) ($process->exportadora ?? ''));
                }
                if ($plan['fecha_proceso'] === null && $process->fecha) {
                    $plan['fecha_proceso'] = $process->fecha->toDateString();
                }

                $planes[$ng] = $plan;
            }
        }

        foreach ($planes as $ng => $plan) {
            sort($plan['numprocesos'], SORT_NATURAL);
            $planes[$ng]['numproceso'] = implode(', ', $plan['numprocesos']);
            unset($planes[$ng]['numprocesos']);
        }

        return $planes;
    }

    private function planPassesFilters(?array $plan, array $filters): bool
    {
        if ($plan === null) {
            return false;
        }

        $especies = $this->normalizeValues($filters['especie'] ?? null);
        if ($especies !== [] && ! in_array(trim((string) ($plan['especie'] ?? '')), $especies, true)) {
            return false;
        }

        $exportadoras = $this->normalizeValues($filters['exportadora'] ?? null);
        if ($exportadoras !== [] && ! in_array(trim((string) ($plan['exportadora'] ?? '')), $exportadoras, true)) {
            return false;
        }

        if ($this->normalizeValues($filters['variedad'] ?? null) !== []) {
            return false;
        }

        return true;
    }

    private function normalizeValues(mixed $values): array
    {
        if ($values === null || $values === '') {
            return [];
        }

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

    private function exportadoraByNg(array $ngs)
    {
        if ($ngs === []) {
            return collect();
        }

        return Recepcion::query()
            ->whereIn('numero_g_recepcion', $ngs)
            ->pluck('exportadora', 'numero_g_recepcion')
            ->map(fn ($value) => trim((string) ($value ?? '')));
    }
}
