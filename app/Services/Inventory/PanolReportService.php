<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use Illuminate\Support\Facades\DB;

class PanolReportService
{

    /**
     * Reporte de control del pañol: stock en línea por material (61xx, Bodega Central)
     * con el agregado de entregas a personas según los filtros.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        $deliverySub = $this->deliveryAggregatesSubquery($filters);
        $centralLocation = $this->centralLocation();

        $hasDeliveryFilters = $filters['date_from'] !== ''
            || $filters['date_to'] !== ''
            || $filters['person_id'] !== ''
            || $filters['cargo'] !== ''
            || $filters['area'] !== '';

        $rows = DB::table('inventory_materials as mat')
            ->leftJoin('inventory_units as u', 'u.id', '=', 'mat.unit_id')
            ->leftJoin('inventory_stock_locations as sl', function ($join) use ($centralLocation): void {
                $join->on('sl.material_id', '=', 'mat.id')
                    ->where('sl.location_id', '=', $centralLocation?->id ?? 0);
            })
            ->leftJoinSub($deliverySub, 'dlv', 'dlv.material_id', '=', 'mat.id')
            ->where('mat.activo', true)
            ->where(function ($query) use ($hasDeliveryFilters, $filters): void {
                if ($hasDeliveryFilters) {
                    $query->whereNotNull('dlv.material_id');
                } elseif ($filters['solo_con_stock']) {
                    $query->where(function ($stock): void {
                        $stock->where('sl.stock_actual', '!=', 0);
                    })->orWhereNotNull('dlv.material_id');
                }
            });

        if ($filters['material_id'] !== '') {
            $rows->where('mat.id', (int) $filters['material_id']);
        }

        if ($filters['q'] !== '') {
            $like = '%'.$filters['q'].'%';
            $rows->where(function ($query) use ($like): void {
                $query->where('mat.nombre', 'like', $like)
                    ->orWhere('mat.codigo', 'like', $like);
            });
        }

        $rows = $rows
            ->select([
                'mat.id as material_id',
                'mat.codigo as material_codigo',
                'mat.nombre as material_nombre',
                'mat.consumo_inmediato as consumo_inmediato',
                'u.codigo as unit_codigo',
                DB::raw('COALESCE(sl.stock_actual, 0) as stock_actual'),
                DB::raw('COALESCE(dlv.total_entregado, 0) as total_entregado'),
                DB::raw('COALESCE(dlv.num_entregas, 0) as num_entregas'),
                'dlv.ultima_entrega',
            ])
            ->orderBy('mat.codigo')
            ->get();

        $detailsByMaterial = $this->deliveryDetailRows($filters)
            ->groupBy('material_id');

        $mapped = $rows->map(function ($row) use ($detailsByMaterial): array {
            return [
                'material_id' => (int) $row->material_id,
                'material_codigo' => (string) $row->material_codigo,
                'material_nombre' => (string) $row->material_nombre,
                'consumo_inmediato' => (bool) $row->consumo_inmediato,
                'unit_codigo' => (string) ($row->unit_codigo ?: ''),
                'stock_actual' => round((float) $row->stock_actual, 4),
                'total_entregado' => round((float) $row->total_entregado, 4),
                'num_entregas' => (int) $row->num_entregas,
                'ultima_entrega' => $row->ultima_entrega,
                'deliveries' => $detailsByMaterial->get((int) $row->material_id, collect())->values()->all(),
            ];
        })->all();

        return [
            'filters' => $filters,
            'rows' => $mapped,
            'totals' => $this->totalsFromRows($mapped),
        ];
    }

    /**
     * Subconsulta de agregados de entrega por material, según filtros de fecha/persona/cargo/área.
     *
     * @param  array<string, mixed>  $filters
     */
    private function deliveryAggregatesSubquery(array $filters)
    {
        $query = DB::table('inventory_person_delivery_items as di')
            ->join('inventory_person_deliveries as d', 'd.id', '=', 'di.person_delivery_id')
            ->join('inventory_materials as m', 'm.id', '=', 'di.material_id')
            ->select([
                'di.material_id',
                DB::raw('SUM(di.cantidad) as total_entregado'),
                DB::raw('COUNT(DISTINCT d.id) as num_entregas'),
                DB::raw('MAX(d.delivered_at) as ultima_entrega'),
            ])
            ->groupBy('di.material_id');

        $this->applyDeliveryFilters($query, $filters);

        return $query;
    }

    /**
     * Detalle de entregas por material (quién, cuándo y cuánto), según los mismos filtros.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function deliveryDetailRows(array $filters)
    {
        $query = DB::table('inventory_person_delivery_items as di')
            ->join('inventory_person_deliveries as d', 'd.id', '=', 'di.person_delivery_id')
            ->join('inventory_materials as m', 'm.id', '=', 'di.material_id')
            ->select([
                'di.material_id',
                'd.id as delivery_id',
                'd.codigo as delivery_codigo',
                'd.person_name as person_name',
                'd.person_position as person_position',
                'd.person_area as person_area',
                'd.delivered_at as delivered_at',
                'di.cantidad as cantidad',
            ])
            ->orderBy('d.delivered_at')
            ->orderBy('d.id');

        $this->applyDeliveryFilters($query, $filters);

        return $query->get();
    }

    /**
     * Aplica los filtros de entrega (fecha/persona/cargo/área) a un query ya unido contra materiales.
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDeliveryFilters($query, array $filters): void
    {
        $query->leftJoin('personal as p', 'p.id', '=', 'd.person_id');

        if ($filters['date_from'] !== '') {
            $query->whereDate('d.delivered_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('d.delivered_at', '<=', $filters['date_to']);
        }

        if ($filters['person_id'] !== '') {
            $query->where('d.person_id', (int) $filters['person_id']);
        }

        if ($filters['cargo'] !== '') {
            $cargo = '%'.$filters['cargo'].'%';
            $query->where(function ($inner) use ($cargo): void {
                $inner->where('d.person_position', 'like', $cargo)
                    ->orWhere('p.cargo', 'like', $cargo);
            });
        }

        if ($filters['area'] !== '') {
            $area = '%'.$filters['area'].'%';
            $query->where(function ($inner) use ($area): void {
                $inner->where('d.person_area', 'like', $area)
                    ->orWhere('p.area', 'like', $area);
            });
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totalsFromRows(array $rows): array
    {
        return [
            'materiales' => count($rows),
            'stock_total' => round((float) array_sum(array_column($rows, 'stock_actual')), 4),
            'total_entregado' => round((float) array_sum(array_column($rows, 'total_entregado')), 4),
            'num_entregas' => (int) array_sum(array_column($rows, 'num_entregas')),
        ];
    }

    private function centralLocation(): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where('es_bodega_central', true)
            ->first() ?? InventoryLocation::query()
            ->where('codigo', 'BODEGA_CENTRAL')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'material_id' => trim((string) ($filters['material_id'] ?? '')),
            'person_id' => trim((string) ($filters['person_id'] ?? '')),
            'cargo' => trim((string) ($filters['cargo'] ?? '')),
            'area' => trim((string) ($filters['area'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
            'solo_con_stock' => filter_var(
                (string) ($filters['solo_con_stock'] ?? '1'),
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }
}
