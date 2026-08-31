<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class ConsumptionReportService
{
    /**
     * Reporte de consumo de materiales por servicio.
     *
     * Consumo = movimientos CONSUMO de folios de auto consumo aplicados
     * (normales + temporales). Mermas = movimientos MERMA aplicados.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        $byMaterial = $this->aggregatedRows($filters);

        return [
            'filters' => $filters,
            'totals' => $this->totalsFromRows($byMaterial),
            'byService' => $this->groupByService($byMaterial),
            'byMaterial' => $byMaterial,
            'byDate' => $this->dateRows($filters),
            'movements' => $this->movementRows($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = $dateTo = now()->toDateString();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'service_id' => trim((string) ($filters['service_id'] ?? '')),
            'material_id' => trim((string) ($filters['material_id'] ?? '')),
            'origin_location_id' => trim((string) ($filters['origin_location_id'] ?? '')),
            'tipo_folio' => trim((string) ($filters['tipo_folio'] ?? '')),
            'incluir_mermas' => filter_var($filters['incluir_mermas'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Query\Builder
     */
    private function baseQuery(array $filters)
    {
        $query = DB::table('inventory_movement_details as d')
            ->join('inventory_movements as m', 'm.id', '=', 'd.movement_id')
            ->join('inventory_movement_types as t', 't.id', '=', 'm.movement_type_id')
            ->join('inventory_materials as mat', 'mat.id', '=', 'd.material_id')
            ->leftJoin('services as s', 's.id', '=', 'mat.service_id')
            ->leftJoin('inventory_auto_consumption_folios as f', 'f.movement_id', '=', 'm.id')
            ->whereIn('m.estado', ['aplicado', 'confirmado'])
            ->where('d.cantidad', '>', 0);

        if ($filters['date_from'] !== '') {
            $query->whereDate('m.fecha_movimiento', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('m.fecha_movimiento', '<=', $filters['date_to']);
        }

        if ($filters['service_id'] !== '') {
            $query->where('mat.service_id', (int) $filters['service_id']);
        }

        if ($filters['material_id'] !== '') {
            $query->where('d.material_id', (int) $filters['material_id']);
        }

        if ($filters['origin_location_id'] !== '') {
            $query->where('m.origin_location_id', (int) $filters['origin_location_id']);
        }

        // El consumo solo proviene de folios de auto consumo; las mermas se incluyen siempre que se pidan.
        if ($filters['incluir_mermas']) {
            $query->where(function ($inner): void {
                $inner->where(function ($sub): void {
                    $sub->where('t.codigo', 'CONSUMO')->whereNotNull('f.id');
                })->orWhere('t.codigo', 'MERMA');
            });
        } else {
            $query->where('t.codigo', 'CONSUMO')->whereNotNull('f.id');
        }

        if ($filters['tipo_folio'] === 'temporal') {
            $query->where('t.codigo', 'CONSUMO')
                ->where(DB::raw('UPPER(f.folio)'), 'like', '%T');
        } elseif ($filters['tipo_folio'] === 'normal') {
            $query->where('t.codigo', 'CONSUMO')
                ->whereNotNull('f.id')
                ->where(DB::raw('UPPER(f.folio)'), 'not like', '%T');
        } elseif ($filters['tipo_folio'] === 'merma') {
            $query->where('t.codigo', 'MERMA');
        }

        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('f.folio', 'like', $like)
                    ->orWhere('mat.nombre', 'like', $like)
                    ->orWhere('mat.codigo', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function aggregatedRows(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->select([
                'mat.service_id',
                's.name as service_name',
                'd.material_id',
                'mat.codigo as material_codigo',
                'mat.nombre as material_nombre',
                DB::raw("SUM(CASE WHEN t.codigo = 'MERMA' THEN d.cantidad ELSE 0 END) as merma"),
                DB::raw("SUM(CASE WHEN t.codigo = 'CONSUMO' AND (f.id IS NULL OR UPPER(f.folio) NOT LIKE '%T') THEN d.cantidad ELSE 0 END) as normal"),
                DB::raw("SUM(CASE WHEN t.codigo = 'CONSUMO' AND UPPER(f.folio) LIKE '%T' THEN d.cantidad ELSE 0 END) as temporal"),
            ])
            ->groupBy('mat.service_id', 's.name', 'd.material_id', 'mat.codigo', 'mat.nombre')
            ->orderBy('service_name')
            ->orderBy('mat.nombre')
            ->get();

        return array_values($rows->map(function ($row): array {
            $normal = round((float) $row->normal, 4);
            $temporal = round((float) $row->temporal, 4);
            $merma = round((float) $row->merma, 4);

            return [
                'service_id' => $row->service_id !== null ? (int) $row->service_id : null,
                'service_name' => (string) ($row->service_name ?: 'Sin servicio'),
                'material_id' => (int) $row->material_id,
                'material_codigo' => (string) $row->material_codigo,
                'material_nombre' => (string) $row->material_nombre,
                'normal' => $normal,
                'temporal' => $temporal,
                'merma' => $merma,
                'consumo_total' => round($normal + $temporal, 4),
                'gran_total' => round($normal + $temporal + $merma, 4),
            ];
        })->all());
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupByService(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row) => $row['service_id'] ?? 0)
            ->map(function ($items): array {
                $normal = round((float) $items->sum('normal'), 4);
                $temporal = round((float) $items->sum('temporal'), 4);
                $merma = round((float) $items->sum('merma'), 4);

                return [
                    'service_id' => $items->first()['service_id'],
                    'service_name' => $items->first()['service_name'],
                    'materiales' => $items->count(),
                    'normal' => $normal,
                    'temporal' => $temporal,
                    'merma' => $merma,
                    'consumo_total' => round($normal + $temporal, 4),
                    'gran_total' => round($normal + $temporal + $merma, 4),
                ];
            })
            ->sortBy('service_name')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totalsFromRows(array $rows): array
    {
        $normal = round((float) array_sum(array_column($rows, 'normal')), 4);
        $temporal = round((float) array_sum(array_column($rows, 'temporal')), 4);
        $merma = round((float) array_sum(array_column($rows, 'merma')), 4);

        return [
            'consumo_normal' => $normal,
            'consumo_temporal' => $temporal,
            'consumo_total' => round($normal + $temporal, 4),
            'merma' => $merma,
            'gran_total' => round($normal + $temporal + $merma, 4),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function dateRows(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->select([
                DB::raw('DATE(m.fecha_movimiento) as fecha'),
                DB::raw("SUM(CASE WHEN t.codigo = 'MERMA' THEN d.cantidad ELSE 0 END) as merma"),
                DB::raw("SUM(CASE WHEN t.codigo = 'CONSUMO' AND (f.id IS NULL OR UPPER(f.folio) NOT LIKE '%T') THEN d.cantidad ELSE 0 END) as normal"),
                DB::raw("SUM(CASE WHEN t.codigo = 'CONSUMO' AND UPPER(f.folio) LIKE '%T' THEN d.cantidad ELSE 0 END) as temporal"),
            ])
            ->groupBy(DB::raw('DATE(m.fecha_movimiento)'))
            ->orderBy(DB::raw('DATE(m.fecha_movimiento)'))
            ->get();

        return array_values($rows->map(function ($row): array {
            $normal = round((float) $row->normal, 4);
            $temporal = round((float) $row->temporal, 4);
            $merma = round((float) $row->merma, 4);

            return [
                'fecha' => (string) $row->fecha,
                'normal' => $normal,
                'temporal' => $temporal,
                'merma' => $merma,
                'consumo_total' => round($normal + $temporal, 4),
                'gran_total' => round($normal + $temporal + $merma, 4),
            ];
        })->all());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function movementRows(array $filters): array
    {
        $rows = $this->baseQuery($filters)
            ->leftJoin('inventory_locations as ol', 'ol.id', '=', 'm.origin_location_id')
            ->select([
                'm.id as movement_id',
                'm.folio as movement_folio',
                'm.estado as movement_estado',
                'm.observacion',
                'm.fecha_movimiento',
                't.codigo as tipo_codigo',
                't.nombre as tipo_nombre',
                'f.folio as folio_produccion',
                'f.cantidad as folio_cantidad',
                'f.es_temporal',
                'ol.nombre as origin_nombre',
                DB::raw('SUM(d.cantidad) as cantidad'),
            ])
            ->groupBy(
                'm.id', 'm.folio', 'm.estado', 'm.observacion', 'm.fecha_movimiento',
                't.codigo', 't.nombre', 'f.folio', 'f.cantidad', 'f.es_temporal', 'ol.nombre'
            )
            ->orderByDesc('m.fecha_movimiento')
            ->orderByDesc('m.id')
            ->limit(100)
            ->get();

        return array_values($rows->map(function ($row): array {
            if ($row->tipo_codigo === 'MERMA') {
                $categoria = 'merma';
            } elseif ((bool) $row->es_temporal) {
                $categoria = 'temporal';
            } else {
                $categoria = 'normal';
            }

            return [
                'movement_id' => (int) $row->movement_id,
                'movement_folio' => (string) $row->movement_folio,
                'movement_estado' => (string) $row->movement_estado,
                'tipo' => (string) $row->tipo_nombre,
                'categoria' => $categoria,
                'categoria_label' => match ($categoria) {
                    'merma' => 'Merma',
                    'temporal' => 'Temporal',
                    default => 'Normal',
                },
                'fecha' => substr((string) $row->fecha_movimiento, 0, 10),
                'folio_produccion' => $row->folio_produccion,
                'folio_cantidad' => $row->folio_cantidad !== null ? (float) $row->folio_cantidad : null,
                'origen' => $row->origin_nombre,
                'cantidad' => round((float) $row->cantidad, 4),
            ];
        })->all());
    }
}