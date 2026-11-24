<?php

namespace App\Services\Planning;

use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
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

                $stockPeso = round((float) ($stock['peso_neto'] ?? 0), 3);
                $planPeso = $plan ? round((float) $plan['asignado_peso_neto'], 3) : 0;
                $peso = $plan ? $planPeso : $stockPeso;
                $asignado = $planPeso;
                $pendiente = round(max(0, $stockPeso - $asignado), 3);
                $bins = $plan ? (int) $plan['asignado_cantidad_bins'] : (int) ($stock['cantidad_bins'] ?? 0);

                $row = $plan
                    ? array_merge($stock ?? [], $plan['fields'] ?? [])
                    : ($stock ?? [
                        'n_g_recepcion' => $ng,
                        'n_variedad' => null,
                        'cantidad_bins' => 0,
                        'peso_neto' => 0,
                    ]);

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
                    'cantidad_bins' => $bins,
                    'asignado_cantidad_bins' => $plan ? (int) $plan['asignado_cantidad_bins'] : 0,
                    'peso_neto' => $peso,
                    'asignado_peso_neto' => $asignado,
                    'pendiente_peso_neto' => $pendiente,
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
                    'fields' => [],
                ];

                $plan['asignado_peso_neto'] += (float) ($lot->peso_neto ?? 0);
                $plan['asignado_cantidad_bins'] += (int) ($lot->cantidad_bins ?? 0);

                foreach ($this->planFields($process, $lot) as $key => $value) {
                    if (($plan['fields'][$key] ?? null) === null || trim((string) $plan['fields'][$key]) === '') {
                        $plan['fields'][$key] = $value;
                    }
                }

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

    private function planFields(PackingProcess $process, PackingProcessLot $lot): array
    {
        $fechaRecepcion = $lot->fecha_recepcion;
        if ($fechaRecepcion !== null && method_exists($fechaRecepcion, 'toDateString')) {
            $fechaRecepcion = $fechaRecepcion->toDateString();
        }

        $variedad = trim((string) ($lot->n_variedad ?? '')) !== ''
            ? $lot->n_variedad
            : ($lot->variedad_original ?? null);
        $categoria = trim((string) ($lot->categoria_origen ?? '')) !== ''
            ? $lot->categoria_origen
            : ($lot->source_categoria ?? null);

        $fields = [
            'n_especie' => $process->especie ?? null,
            'n_exportadora' => $process->exportadora ?? null,
            'n_variedad' => $variedad,
            'csg_productor' => $lot->csg_productor ?? null,
            'n_productor' => $lot->n_productor ?? null,
            'fecha_recepcion' => $fechaRecepcion,
            'n_categoria' => $categoria,
            'nota_calidad_sqlsrv' => $lot->setup_nota_calidad ?? null,
            'c_centrocosto' => $lot->sdp_centrocosto ?? null,
            'referencias_recepcion'=$lot->sispro??null,
            //'n_g_recepcion'=>$lot->n_g_recepcion??null,
        ];

        return collect($fields)
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->all();
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
