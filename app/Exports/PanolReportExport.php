<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PanolReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Código',
            'Producto',
            'Unidad',
            'Consumo inmediato',
            'Stock pañol',
            'Total entregado',
            'N° entregas',
            'Última entrega',
        ];
    }

    public function map($row): array
    {
        return [
            (string) $row['material_codigo'],
            (string) $row['material_nombre'],
            (string) ($row['unit_codigo'] ?? ''),
            (bool) ($row['consumo_inmediato'] ?? false) ? 'Sí' : 'No',
            number_format((float) $row['stock_actual'], 4, ',', '.'),
            number_format((float) $row['total_entregado'], 4, ',', '.'),
            (int) $row['num_entregas'],
            $row['ultima_entrega'] ? substr((string) $row['ultima_entrega'], 0, 10) : '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}