<?php

namespace App\Exports;

use App\Models\InventoryStockLocation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryStockExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected Collection $stocks) {}

    public function collection(): Collection
    {
        return $this->stocks;
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
            'Stock Ubicación',
            'Total Interno',
            'SAP Global',
            '% Distribución',
            'Estado',
        ];
    }

    /**
     * @param  InventoryStockLocation  $stock
     */
    public function map($stock): array
    {
        $stockActual = (float) $stock->stock_actual;
        $materialInternalTotal = (float) ($stock->material_internal_total ?? 0);
        $sapOnHand = (float) ($stock->material?->sap_on_hand ?? 0);
        $distributionRatio = $materialInternalTotal > 0
            ? round(($stockActual / $materialInternalTotal) * 100, 2)
            : 0;

        $status = $stockActual < 0 ? 'Negativo' : ($stockActual == 0.0 ? 'En cero' : 'Disponible');

        return [
            (string) ($stock->location?->codigo ?? ''),
            (string) ($stock->location?->nombre ?? ''),
            (string) ($stock->location?->tipo ?? ''),
            (string) ($stock->material?->codigo ?? ''),
            (string) ($stock->material?->nombre ?? ''),
            (string) ($stock->material?->service?->name ?? '-'),
            (string) ($stock->material?->family?->nombre ?? '-'),
            (string) ($stock->material?->unit?->codigo ?? '-'),
            round($stockActual, 4),
            round($materialInternalTotal, 4),
            round($sapOnHand, 4),
            $distributionRatio.'%',
            $status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
