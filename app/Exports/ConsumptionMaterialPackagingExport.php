<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConsumptionMaterialPackagingExport implements FromCollection, WithHeadings, WithMapping, WithStyles
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
            'Servicio',
            'Código material',
            'Nombre material',
            'Cantidad consumida',
            'Tipo',
            'Material original',
            'Código embalaje',
            'Nombre embalaje',
            'Etiqueta',
        ];
    }

    public function map($row): array
    {
        $esSemi = (bool) ($row['material_es_semielaborado'] ?? false);

        return [
            (string) ($row['service_name'] ?? ''),
            (string) $row['material_codigo'],
            (string) $row['material_nombre'],
            number_format((float) $row['cantidad_consumida'], 4, ',', '.'),
            $esSemi ? 'Semielaborado' : 'Original',
            $esSemi ? (string) ($row['material_original_codigo'] . ' · ' . $row['material_original_nombre']) : '',
            (string) $row['embalaje_codigo'],
            (string) $row['embalaje_nombre'],
            (string) $row['etiqueta'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
