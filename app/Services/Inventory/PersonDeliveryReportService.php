<?php

namespace App\Services\Inventory;

use App\Models\InventoryMaterial;
use App\Models\Personal;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PersonDeliveryReportService
{
    public const AREA_SIN_DEFINIR = 'SIN ÁREA';

    /**
     * Normaliza los filtros del reporte de entregas a personas.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'material_id' => trim((string) ($filters['material_id'] ?? '')),
            'area' => trim((string) ($filters['area'] ?? '')),
            'person_id' => trim((string) ($filters['person_id'] ?? '')),
        ];
    }

    /**
     * Detalle de entregas: una fila por material entregado, ordenado por
     * Área, Persona, Material y fecha de entrega.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function detail(array $filters): Collection
    {
        $filters = $this->normalizeFilters($filters);

        $query = DB::table('inventory_person_delivery_items as di')
            ->join('inventory_person_deliveries as d', 'd.id', '=', 'di.person_delivery_id')
            ->join('inventory_materials as m', 'm.id', '=', 'di.material_id')
            ->leftJoin('inventory_units as u', 'u.id', '=', 'm.unit_id')
            ->leftJoin('inventory_cost_centers as cc', 'cc.id', '=', 'd.cost_center_id')
            ->leftJoin('inventory_locations as l', 'l.id', '=', 'd.origin_location_id')
            ->leftJoin('users as usr', 'usr.id', '=', 'd.created_by')
            ->select([
                'd.id as delivery_id',
                'd.codigo as delivery_codigo',
                'd.numero_referencia',
                'd.person_id',
                'd.person_name',
                'd.person_position',
                'd.person_area',
                'd.delivered_at',
                'd.notes',
                'di.cantidad',
                'm.id as material_id',
                'm.codigo as material_codigo',
                'm.nombre as material_nombre',
                'u.codigo as unit_codigo',
                'cc.codigo as cost_center_codigo',
                'cc.nombre as cost_center_nombre',
                'l.nombre as origin_location',
                'usr.name as creator_name',
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByRaw("CASE WHEN d.person_area IS NULL OR TRIM(d.person_area) = '' THEN 1 ELSE 0 END")
            ->orderBy('d.person_area')
            ->orderBy('d.person_name')
            ->orderBy('m.codigo')
            ->orderBy('d.delivered_at')
            ->orderBy('d.id')
            ->get()
            ->map(fn (object $row) => $this->mapDetailRow($row))
            ->values();
    }

    /**
     * Consolidado por área: una fila por área + material, subtotal por área
     * y total general.
     *
     * @param  Collection<int, array<string, mixed>>  $detail
     * @return array<int, array<string, mixed>>
     */
    public function consolidatedByArea(Collection $detail): array
    {
        $rows = [];
        $deliveries = collect();
        $persons = collect();

        foreach ($detail->groupBy('area') as $area => $items) {
            foreach ($items->groupBy('material_id') as $materialItems) {
                $first = $materialItems->first();

                $rows[] = [
                    'type' => 'detail',
                    'area' => (string) $area,
                    'material_codigo' => (string) $first['material_codigo'],
                    'material_nombre' => (string) $first['material_nombre'],
                    'unit_codigo' => (string) $first['unit_codigo'],
                    'cantidad' => round((float) $materialItems->sum('cantidad'), 4),
                    'num_entregas' => $materialItems->pluck('delivery_codigo')->unique()->count(),
                    'num_personas' => $materialItems->pluck('person_id')->unique()->count(),
                    'ultima_entrega' => $this->maxDate($materialItems->pluck('delivered_at')),
                ];
            }

            $areaDeliveries = $items->pluck('delivery_codigo')->unique();
            $areaPersons = $items->pluck('person_id')->unique();

            $deliveries = $deliveries->merge($areaDeliveries);
            $persons = $persons->merge($areaPersons);

            $rows[] = [
                'type' => 'subtotal',
                'area' => (string) $area,
                'material_codigo' => '',
                'material_nombre' => 'SUBTOTAL ÁREA',
                'unit_codigo' => '',
                'cantidad' => round((float) $items->sum('cantidad'), 4),
                'num_entregas' => $areaDeliveries->count(),
                'num_personas' => $areaPersons->count(),
                'ultima_entrega' => $this->maxDate($items->pluck('delivered_at')),
            ];
        }

        if ($rows !== []) {
            $rows[] = [
                'type' => 'total',
                'area' => 'TOTAL GENERAL',
                'material_codigo' => '',
                'material_nombre' => 'TODAS LAS ÁREAS',
                'unit_codigo' => '',
                'cantidad' => round((float) $detail->sum('cantidad'), 4),
                'num_entregas' => $deliveries->unique()->count(),
                'num_personas' => $persons->unique()->count(),
                'ultima_entrega' => $this->maxDate($detail->pluck('delivered_at')),
            ];
        }

        return $rows;
    }

    /**
     * Opciones disponibles para los filtros del reporte.
     *
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'materials' => InventoryMaterial::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (InventoryMaterial $material) => [
                    'id' => $material->id,
                    'codigo' => $material->codigo,
                    'nombre' => $material->nombre,
                ])
                ->all(),
            'people' => Personal::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'cargo', 'area'])
                ->map(fn (Personal $person) => [
                    'id' => $person->id,
                    'nombre' => $person->nombre,
                    'cargo' => $person->cargo,
                    'area' => $person->area,
                ])
                ->all(),
            'areas' => $this->areas(),
        ];
    }

    /**
     * Áreas presentes en el histórico de entregas y en el padrón de personal.
     *
     * @return list<string>
     */
    public function areas(): array
    {
        $deliveryAreas = DB::table('inventory_person_deliveries')
            ->distinct()
            ->pluck('person_area')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        $personalAreas = DB::table('personal')
            ->distinct()
            ->pluck('area')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        $hasUndefinedArea = DB::table('inventory_person_deliveries')
            ->where(function ($query): void {
                $query->whereNull('person_area')->orWhere('person_area', '=', '');
            })
            ->exists();

        $areas = $deliveryAreas
            ->merge($personalAreas)
            ->unique()
            ->sort()
            ->values();

        if ($hasUndefinedArea) {
            $areas->push(self::AREA_SIN_DEFINIR);
        }

        return $areas->all();
    }

    /**
     * @param  \Illuminate\Contracts\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if ($filters['date_from'] !== '') {
            $query->whereDate('d.delivered_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('d.delivered_at', '<=', $filters['date_to']);
        }

        if ($filters['material_id'] !== '') {
            $query->where('di.material_id', (int) $filters['material_id']);
        }

        if ($filters['area'] !== '') {
            $query->whereRaw(
                "COALESCE(NULLIF(TRIM(d.person_area), ''), ?) = ?",
                [self::AREA_SIN_DEFINIR, $filters['area']]
            );
        }

        if ($filters['person_id'] !== '') {
            $query->where('d.person_id', (int) $filters['person_id']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDetailRow(object $row): array
    {
        return [
            'delivered_at' => Carbon::parse($row->delivered_at),
            'area' => $this->normalizeArea($row->person_area),
            'person_id' => (int) $row->person_id,
            'person_name' => (string) $row->person_name,
            'person_position' => (string) ($row->person_position ?: ''),
            'material_id' => (int) $row->material_id,
            'material_codigo' => (string) ($row->material_codigo ?: ''),
            'material_nombre' => (string) ($row->material_nombre ?: ''),
            'unit_codigo' => (string) ($row->unit_codigo ?: ''),
            'cantidad' => round((float) $row->cantidad, 4),
            'delivery_id' => (int) $row->delivery_id,
            'delivery_codigo' => (string) $row->delivery_codigo,
            'numero_referencia' => $row->numero_referencia,
            'cost_center' => $row->cost_center_codigo
                ? trim($row->cost_center_codigo.' · '.(string) $row->cost_center_nombre)
                : '',
            'origin_location' => (string) ($row->origin_location ?: ''),
            'creator_name' => (string) ($row->creator_name ?: ''),
            'notes' => (string) ($row->notes ?: ''),
        ];
    }

    private function normalizeArea(?string $area): string
    {
        $area = trim((string) $area);

        return $area !== '' ? $area : self::AREA_SIN_DEFINIR;
    }

    /**
     * @param  Collection<int, mixed>  $dates
     */
    private function maxDate(Collection $dates): ?Carbon
    {
        $valid = $dates->filter(fn ($value) => $value instanceof DateTimeInterface);

        if ($valid->isEmpty()) {
            return null;
        }

        return Carbon::instance($valid->max());
    }
}
