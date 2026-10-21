<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PersonDeliveryConsolidatedSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    public const TITLE_ROW = 1;

    public const FILTERS_ROW = 2;

    public const HEADER_ROW = 4;

    public const FIRST_DATA_ROW = 5;

    public const HEADER_COLOR = 'FF1F4E79';

    public const SUBTOTAL_COLOR = 'FFDCE6F1';

    public const TOTAL_COLOR = 'FFF2C744';

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     */
    public function __construct(private readonly array $rows, private readonly array $meta = [])
    {
    }

    public function array(): array
    {
        $rows = [
            ['REPORTE DE ENTREGAS DE MATERIALES A PERSONAS - CONSOLIDADO POR ÁREA'],
            [$this->filtersSummary()],
            [''],
            ['Área', 'Código material', 'Material', 'Unidad', 'Cantidad total', 'N° entregas', 'N° personas', 'Última entrega'],
        ];

        foreach ($this->rows as $row) {
            $rows[] = [
                (string) $row['area'],
                (string) $row['material_codigo'],
                (string) $row['material_nombre'],
                (string) $row['unit_codigo'],
                (float) $row['cantidad'],
                (int) $row['num_entregas'],
                (int) $row['num_personas'],
                $row['ultima_entrega'] ? Date::PHPToExcel($row['ultima_entrega']) : '',
            ];
        }

        if (count($rows) === self::HEADER_ROW) {
            $rows[] = ['Sin datos para los filtros seleccionados'];
        }

        return $rows;
    }

    public function title(): string
    {
        return PersonDeliveryReportExport::SHEET_CONSOLIDATED;
    }

    /**
     * @return array<string, float>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 16,
            'C' => 44,
            'D' => 10,
            'E' => 16,
            'F' => 12,
            'G' => 12,
            'H' => 20,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet;
                $lastColumn = 'H';

                $sheet->mergeCells('A'.self::TITLE_ROW.':'.$lastColumn.self::TITLE_ROW);
                $sheet->getStyle('A'.self::TITLE_ROW)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_COLOR]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(self::TITLE_ROW)->setRowHeight(24);

                $sheet->mergeCells('A'.self::FILTERS_ROW.':'.$lastColumn.self::FILTERS_ROW);
                $sheet->getStyle('A'.self::FILTERS_ROW)->applyFromArray([
                    'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF404040']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_COLOR]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']],
                    ],
                ]);

                foreach ($this->styleRows('subtotal') as $rowNumber) {
                    $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::SUBTOTAL_COLOR]],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF808080']],
                        ],
                    ]);
                }

                foreach ($this->styleRows('total') as $rowNumber) {
                    $sheet->getStyle('A'.$rowNumber.':'.$lastColumn.$rowNumber)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TOTAL_COLOR]],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF404040']],
                        ],
                    ]);
                }

                $lastRow = max(self::FIRST_DATA_ROW, $sheet->getHighestRow());
                $sheet->getStyle('E'.self::FIRST_DATA_ROW.':E'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle('H'.self::FIRST_DATA_ROW.':H'.$lastRow)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:mm');

                $sheet->freezePane('A'.self::FIRST_DATA_ROW);
            },
        ];
    }

    /**
     * @return list<int>
     */
    private function styleRows(string $type): array
    {
        $rows = [];

        foreach ($this->rows as $index => $row) {
            if (($row['type'] ?? '') === $type) {
                $rows[] = self::FIRST_DATA_ROW + $index;
            }
        }

        return $rows;
    }

    private function filtersSummary(): string
    {
        $parts = [];

        $generatedAt = (string) ($this->meta['generated_at'] ?? '');
        if ($generatedAt !== '') {
            $parts[] = 'Generado: '.$generatedAt;
        }

        foreach ((array) ($this->meta['filters'] ?? []) as $label => $value) {
            $parts[] = $label.': '.$value;
        }

        return implode(' | ', $parts);
    }
}
