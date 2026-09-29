<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryStockExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected Collection $rows;

    public function __construct(Collection $stocks, Collection $distributionsByPair)
    {
        $flattened = collect();

        foreach ($stocks as $stock) {
            $stockActual = (float) $stock->stock_actual;
            $materialInternalTotal = (float) ($stock->material_internal_total ?? 0);
            $sapOnHand = (float) ($stock->material?->sap_on_hand ?? 0);
            $distributionRatio = $materialInternalTotal > 0
                ? round(($stockActual / $materialInternalTotal) * 100, 2)
                : 0;

            $status = $stockActual < 0 ? 'Negativo' : ($stockActual == 0.0 ? 'En cero' : 'Disponible');

            $dists = $distributionsByPair->get($stock->location_id.'-'.$stock->material_id, []);

            if (empty($dists)) {
                $flattened->push([
                    'location_code' => (string) ($stock->location?->codigo ?? ''),
                    'location_name' => (string) ($stock->location?->nombre ?? ''),
                    'location_type' => (string) ($stock->location?->tipo ?? ''),
                    'material_code' => (string) ($stock->material?->codigo ?? ''),
                    'material_name' => (string) ($stock->material?->nombre ?? ''),
                    'service' => (string) ($stock->material?->service?->name ?? '-'),
                    'family' => (string) ($stock->material?->family?->nombre ?? '-'),
                    'unit' => (string) ($stock->material?->unit?->codigo ?? '-'),
                    'spatial_prefix' => '-',
                    'spatial_column' => '-',
                    'spatial_row' => '-',
                    'position_quantity' => round($stockActual, 4),
                    'lpn_count' => 0,
                    'lot_codes' => '-',
                    'stock_actual' => round($stockActual, 4),
                    'material_internal_total' => round($materialInternalTotal, 4),
                    'sap_on_hand' => round($sapOnHand, 4),
                    'distribution_ratio' => $distributionRatio.'%',
                    'status' => $status,
                ]);
            } else {
                foreach ($dists as $dist) {
                    $flattened->push([
                        'location_code' => (string) ($stock->location?->codigo ?? ''),
                        'location_name' => (string) ($stock->location?->nombre ?? ''),
                        'location_type' => (string) ($stock->location?->tipo ?? ''),
                        'material_code' => (string) ($stock->material?->codigo ?? ''),
                        'material_name' => (string) ($stock->material?->nombre ?? ''),
                        'service' => (string) ($stock->material?->service?->name ?? '-'),
                        'family' => (string) ($stock->material?->family?->nombre ?? '-'),
                        'unit' => (string) ($stock->material?->unit?->codigo ?? '-'),
                        'spatial_prefix' => (string) ($dist['spatial_prefix'] ?? '-'),
                        'spatial_column' => (string) ($dist['spatial_column'] ?? '-'),
                        'spatial_row' => (string) ($dist['spatial_row'] ?? '-'),
                        'position_quantity' => (float) $dist['quantity'],
                        'lpn_count' => (int) $dist['lpn_count'],
                        'lot_codes' => ! empty($dist['lot_codes']) ? implode(', ', $dist['lot_codes']) : '-',
                        'stock_actual' => round($stockActual, 4),
                        'material_internal_total' => round($materialInternalTotal, 4),
                        'sap_on_hand' => round($sapOnHand, 4),
                        'distribution_ratio' => $distributionRatio.'%',
                        'status' => $status,
                    ]);
                }
            }
        }

        $this->rows = $flattened;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Cód. Ubicación',
            'Ubicación',
            'Tipo Ubicación',
            'Cód. Material',
            'Material',
            'Servicio',
            'Familia',
            'Unidad',
            'Prefijo',
            'Columna',
            'Fila',
            'Stock Posición',
            'Bultos (LPN)',
            'Lotes',
            'Stock Total Ubicación',
            'Total Interno',
            'SAP Global',
            '% Distribución Ubicación',
            'Estado',
        ];
    }

    public function map($row): array
    {
        return [
            $row['location_code'],
            $row['location_name'],
            $row['location_type'],
            $row['material_code'],
            $row['material_name'],
            $row['service'],
            $row['family'],
            $row['unit'],
            $row['spatial_prefix'],
            $row['spatial_column'],
            $row['spatial_row'],
            $row['position_quantity'],
            $row['lpn_count'],
            $row['lot_codes'],
            $row['stock_actual'],
            $row['material_internal_total'],
            $row['sap_on_hand'],
            $row['distribution_ratio'],
            $row['status'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
