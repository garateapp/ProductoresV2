<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CostCenterTemplateExport implements FromArray, WithProperties, WithStyles
{
    public const SHEET_NAME = 'Centros de Costo';

    public const HEADER_ROW = 11;

    public const FIRST_DATA_ROW = 12;

    private string $headerColor = '1F4E79';

    private string $exampleColor = 'FFF2CC';

    public function array(): array
    {
        return [
            ['PLANTILLA DE CARGA MASIVA - CENTROS DE COSTO'],
            [''],
            ['INSTRUCCIONES'],
            ['1. Complete una fila por centro de costo a crear o actualizar.'],
            ['2. "CÓDIGO" es obligatorio y único: identifica el centro, si ya existe se actualiza.'],
            ['3. "NOMBRE" es obligatorio. "DESCRIPCIÓN" y "SERVICIO" son opcionales.'],
            ['4. En "SERVICIO" escriba el nombre exacto de un servicio ya creado en el sistema.'],
            ['5. "ACTIVO" acepta SI o NO. Si se deja vacío se importa como SI.'],
            ['6. La fila de ejemplo (gris) se ignora al importar: escriba sus datos en filas nuevas.'],
            [''],
            ['CÓDIGO', 'NOMBRE', 'DESCRIPCIÓN', 'SERVICIO', 'ACTIVO'],
            ['CC-1001', 'Bodega central', 'Centro principal de distribución', '', 'SI'],
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'GreenEx',
            'title' => 'Plantilla Centros de Costo',
            'subject' => 'Carga masiva de centros de costo de inventario',
            'description' => 'Plantilla para carga masiva del catálogo de centros de costo',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setTitle(self::SHEET_NAME);

        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
        ]);

        for ($row = 4; $row <= 9; $row++) {
            $sheet->getStyle('A'.$row)->applyFromArray([
                'font' => ['size' => 10, 'italic' => true],
            ]);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getStyle($column.self::HEADER_ROW)->applyFromArray($headerStyle);
        }

        $exampleStyle = [
            'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF808080']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->exampleColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        $sheet->getStyle('A'.self::FIRST_DATA_ROW.':E'.self::FIRST_DATA_ROW)->applyFromArray($exampleStyle);

        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setSqref('E'.self::FIRST_DATA_ROW.':E200');
        $validation->setFormula1('"SI,NO"');

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(12);

        return [];
    }
}
