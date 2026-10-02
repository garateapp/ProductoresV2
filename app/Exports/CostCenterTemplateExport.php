<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CostCenterTemplateExport implements WithMultipleSheets, WithProperties
{
    public const SHEET_NAME = 'Centros de Costo';

    public const HEADER_ROW = 10;

    public const FIRST_DATA_ROW = 11;

    public function sheets(): array
    {
        return [
            self::SHEET_NAME => new CostCenterDataSheet,
            'Ejemplo' => new CostCenterExampleSheet,
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
}

class CostCenterDataSheet implements FromArray, WithStyles
{
    private array $headers = ['CÓDIGO', 'NOMBRE', 'DESCRIPCIÓN', 'SERVICIO', 'ACTIVO'];

    private string $headerColor = '1F4E79';

    public function array(): array
    {
        return [
            ['PLANTILLA DE CARGA MASIVA - CENTROS DE COSTO'],
            [''],
            ['INSTRUCCIONES'],
            ['1. Escriba sus datos en esta hoja a partir de la fila '.CostCenterTemplateExport::FIRST_DATA_ROW.'.'],
            ['2. "CÓDIGO" es obligatorio y único: identifica el centro, si ya existe se actualiza.'],
            ['3. "NOMBRE" es obligatorio. "DESCRIPCIÓN" y "SERVICIO" son opcionales.'],
            ['4. En "SERVICIO" escriba el nombre exacto de un servicio ya creado en el sistema.'],
            ['5. "ACTIVO" acepta SI o NO. Si se deja vacío se importa como SI.'],
            [''],
            $this->headers,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->setTitle(CostCenterTemplateExport::SHEET_NAME);

        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
        ]);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
        ]);

        for ($row = 4; $row <= 8; $row++) {
            $sheet->getStyle('A'.$row)->applyFromArray([
                'font' => ['size' => 10, 'italic' => true],
            ]);
        }

        $this->styleHeaderRow($sheet);

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setSqref('E'.CostCenterTemplateExport::FIRST_DATA_ROW.':E500');
        $validation->setFormula1('"SI,NO"');

        $this->styleWidths($sheet);

        return [];
    }

    public function styleHeaderRow(Worksheet $sheet): void
    {
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $sheet->getStyle($column.CostCenterTemplateExport::HEADER_ROW)->applyFromArray($headerStyle);
        }
    }

    public function styleWidths(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(12);
    }
}

class CostCenterExampleSheet implements FromArray, WithStyles
{
    private array $headers = ['CÓDIGO', 'NOMBRE', 'DESCRIPCIÓN', 'SERVICIO', 'ACTIVO'];

    private string $headerColor = '2E75B6';

    public function array(): array
    {
        return [
            ['EJEMPLO DE UNA FILA (esta hoja no se importa)'],
            ['CC-1001', 'Bodega central', 'Centro principal de distribución', 'Nombre exacto del servicio o vacío', 'SI'],
            ['CC-1002', 'Producción línea 1', '', '', 'NO'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
        ]);

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        foreach ($this->headers as $index => $header) {
            $column = chr(ord('A') + $index);
            $sheet->setCellValue($column.'3', $header);
            $sheet->getStyle($column.'3')->applyFromArray($headerStyle);
        }

        $exampleStyle = [
            'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF808080']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        $sheet->getStyle('A4:E5')->applyFromArray($exampleStyle);

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(12);

        return [];
    }
}
