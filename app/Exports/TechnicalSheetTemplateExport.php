<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TechnicalSheetTemplateExport implements WithMultipleSheets, WithProperties
{
    public function sheets(): array
    {
        return [
            'Embalaje' => new EncabezadosSheet,
            'Material por Unidad de Caja' => new MaterialesUnidadSheet,
            'Material por Pallet' => new MaterialesPalletSheet,
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'GreenEx',
            'title' => 'Plantilla Fichas Técnicas',
            'subject' => 'Carga masiva de fichas técnicas de inventario',
            'description' => 'Plantilla para carga masiva de fichas técnicas con materiales por unidad y pallet',
        ];
    }
}

class EncabezadosSheet implements ShouldAutoSize, WithStyles
{
    private array $headers = [
        'CÓDIGO EMBALAJE',
        'NOMBRE FICHA',
        'ES SEMIELABORADO (SI/NO)',
        'MATERIAL SEMIELABORADO (código)',
        'FECHA VIGENCIA DESDE',
        'FECHA VIGENCIA HASTA',
        'ACTIVO (SI/NO)',
        'OBSERVACIÓN',
    ];

    private string $headerColor = '1F4E79';

    private string $exampleColor = 'FFF2CC';

    public function headers(): array
    {
        return $this->headers;
    }

    public function heading(): array
    {
        return [
            'INSTRUCCIONES',
            '1. Complete los encabezados de cada ficha técnica que desee crear.',
            '2. Use códigos de embalaje existentes en la columna "CÓDIGO EMBALAJE".',
            '3. Si es semielaborado, deje CÓDIGO EMBALAJE vacío y complete MATERIAL SEMIELABORADO.',
            '4. Las fechas deben formato YYYY-MM-DD.',
            '5. Luego complete las hojas "Material por Unidad de Caja" y "Material por Pallet" con los códigos de embalaje correspondientes.',
            '',
            'CÓDIGO EMBALAJE',
            'NOMBRE FICHA',
            'ES SEMIELABORADO (SI/NO)',
            'MATERIAL SEMIELABORADO (código)',
            'FECHA VIGENCIA DESDE',
            'FECHA VIGENCIA HASTA',
            'ACTIVO (SI/NO)',
            'OBSERVACIÓN',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Title row
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'PLANTILLA DE CARGA MASIVA - FICHAS TÉCNICAS');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
        ]);

        // Instructions
        $instructions = [
            'A3' => '1. Complete los encabezados de cada ficha técnica en esta hoja.',
            'A4' => '2. Indique un nombre para cada ficha y use códigos de embalaje existentes (o código de material si es semielaborado).',
            'A5' => '3. Formato de fechas: YYYY-MM-DD (ej: 2026-05-01).',
            'A6' => '4. Luego complete las hojas "Material por Unidad de Caja" y "Material por Pallet".',
            'A7' => '5. Use los mismos códigos de embalaje/material en las 3 hojas para agrupar los registros.',
        ];

        foreach ($instructions as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['size' => 10, 'italic' => true],
            ]);
        }

        // Column headers (row 9)
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        $col = 'A';
        foreach ($this->headers as $index => $header) {
            $col = chr(ord('A') + $index);
            $sheet->setCellValue($col.'9', $header);
            $sheet->getStyle($col.'9')->applyFromArray($headerStyle);
        }

        // Example row (row 10)
        $exampleStyle = [
            'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF808080']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->exampleColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        $exampleData = [
            'A10' => 'EMB-001',
            'B10' => 'Ficha cerezas exportación',
            'C10' => 'NO',
            'D10' => '',
            'E10' => '2026-05-01',
            'F10' => '2026-12-31',
            'G10' => 'SI',
            'H10' => 'Ejemplo de observación',
        ];

        foreach ($exampleData as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->applyFromArray($exampleStyle);
        }

        // Data validation for C column (es_semielaborado)
        $validation = $sheet->getCell('C10')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"SI,NO"');

        // Data validation for G column (activo)
        $validationF = $sheet->getCell('G10')->getDataValidation();
        $validationF->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validationF->setAllowBlank(true);
        $validationF->setShowDropDown(true);
        $validationF->setFormula1('"SI,NO"');

        // Date format for D and E columns
        $sheet->getStyle('E10:F10')->getNumberFormat()->setFormatCode('YYYY-MM-DD');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(22);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(30);

        return [];
    }
}

class MaterialesUnidadSheet implements ShouldAutoSize, WithStyles
{
    private array $headers = [
        'CÓDIGO EMBALAJE / SEMIELABORADO',
        'CÓDIGO MATERIAL',
        'CÓDIGO MATERIAL REEMPLAZO',
        'CANTIDAD ESTÁNDAR',
        'CALIBRE',
    ];

    private string $headerColor = '2E75B6';

    public function headers(): array
    {
        return $this->headers;
    }

    public function heading(): array
    {
        return $this->headers;
    }

    public function styles(Worksheet $sheet): array
    {
        // Title
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'MATERIAL POR UNIDAD DE CAJA');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
        ]);

        $sheet->setCellValue('A3', 'Use los mismos códigos de la hoja "Embalaje" para agrupar los materiales por ficha técnica.');
        $sheet->getStyle('A3')->applyFromArray(['font' => ['size' => 10, 'italic' => true]]);

        // Column headers (row 5)
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        foreach ($this->headers as $index => $header) {
            $col = chr(ord('A') + $index);
            $sheet->setCellValue($col.'5', $header);
            $sheet->getStyle($col.'5')->applyFromArray($headerStyle);
        }

        // Example rows
        $exampleStyle = [
            'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF808080']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        $examples = [
            ['A6' => 'EMB-001', 'B6' => 'MAT-001', 'C6' => '', 'D6' => '1.5', 'E6' => 'L'],
            ['A7' => 'EMB-001', 'B7' => 'MAT-002', 'C7' => 'MAT-003', 'D7' => '2.0', 'E7' => 'XL'],
        ];

        foreach ($examples as $rowData) {
            foreach ($rowData as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->applyFromArray($exampleStyle);
            }
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(14);

        return [];
    }
}

class MaterialesPalletSheet implements ShouldAutoSize, WithStyles
{
    private array $headers = [
        'CÓDIGO EMBALAJE / SEMIELABORADO',
        'CÓDIGO MATERIAL',
        'CÓDIGO MATERIAL REEMPLAZO',
        'CANTIDAD ESTÁNDAR',
        'CALIBRE',
    ];

    private string $headerColor = 'C55A11';

    public function headers(): array
    {
        return $this->headers;
    }

    public function heading(): array
    {
        return $this->headers;
    }

    public function styles(Worksheet $sheet): array
    {
        // Title
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'MATERIAL POR PALLET');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
        ]);

        $sheet->setCellValue('A3', 'Use los mismos códigos de la hoja "Embalaje" para agrupar los materiales por ficha técnica.');
        $sheet->getStyle('A3')->applyFromArray(['font' => ['size' => 10, 'italic' => true]]);

        // Column headers (row 5)
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF'.$this->headerColor]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        foreach ($this->headers as $index => $header) {
            $col = chr(ord('A') + $index);
            $sheet->setCellValue($col.'5', $header);
            $sheet->getStyle($col.'5')->applyFromArray($headerStyle);
        }

        // Example rows
        $exampleStyle = [
            'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF808080']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
            ],
        ];

        $examples = [
            ['A6' => 'EMB-001', 'B6' => 'MAT-010', 'C6' => '', 'D6' => '48.0', 'E6' => ''],
            ['A7' => 'EMB-001', 'B7' => 'MAT-011', 'C7' => '', 'D7' => '1.0', 'E7' => ''],
        ];

        foreach ($examples as $rowData) {
            foreach ($rowData as $cell => $value) {
                $sheet->setCellValue($cell, $value);
                $sheet->getStyle($cell)->applyFromArray($exampleStyle);
            }
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(14);

        return [];
    }
}
