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

class PersonDeliveryDetailSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    public const TITLE_ROW = 1;

    public const FILTERS_ROW = 2;

    public const HEADER_ROW = 4;

    public const FIRST_DATA_ROW = 5;

    public const HEADER_COLOR = 'FF1F4E79';

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
            ['REPORTE DE ENTREGAS DE MATERIALES A PERSONAS - DETALLE'],
            [$this->filtersSummary()],
            [''],
            [
                'Fecha entrega',
                'Área',
                'Persona',
                'Cargo',
                'Código material',
                'Material',
                'Unidad',
                'Cantidad',
                'N° acta',
                'N° referencia',
                'Centro de costo',
                'Ubicación origen',
                'Entregado por',
                'Observación',
            ],
        ];

        foreach ($this->rows as $row) {
            $rows[] = [
                Date::PHPToExcel($row['delivered_at']),
                (string) $row['area'],
                (string) $row['person_name'],
                (string) $row['person_position'],
                (string) $row['material_codigo'],
                (string) $row['material_nombre'],
                (string) $row['unit_codigo'],
                (float) $row['cantidad'],
                (string) $row['delivery_codigo'],
                $row['numero_referencia'] !== null ? (string) $row['numero_referencia'] : '',
                (string) $row['cost_center'],
                (string) $row['origin_location'],
                (string) $row['creator_name'],
                (string) $row['notes'],
            ];
        }

        if (count($rows) === self::HEADER_ROW) {
            $rows[] = ['Sin datos para los filtros seleccionados'];
        }

        return $rows;
    }

    public function title(): string
    {
        return PersonDeliveryReportExport::SHEET_DETAIL;
    }

    /**
     * @return array<string, float>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 26,
            'C' => 30,
            'D' => 26,
            'E' => 16,
            'F' => 44,
            'G' => 10,
            'H' => 14,
            'I' => 26,
            'J' => 16,
            'K' => 30,
            'L' => 26,
            'M' => 24,
            'N' => 36,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet;
                $lastColumn = 'N';

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

                $lastRow = max(self::FIRST_DATA_ROW, $sheet->getHighestRow());
                $sheet->getStyle('A'.self::FIRST_DATA_ROW.':A'.$lastRow)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:mm');
                $sheet->getStyle('H'.self::FIRST_DATA_ROW.':H'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.0000');

                $sheet->freezePane('A'.self::FIRST_DATA_ROW);
            },
        ];
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
