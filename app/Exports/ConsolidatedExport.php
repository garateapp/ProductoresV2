<?php

namespace App\Exports;

use App\Models\Recepcion;
use App\Models\Valor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ConsolidatedExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    protected $receptions;
    protected $headings;
    protected $calibreHeadings = [];
    protected $firmezasHeadings = [];
    protected $distFirmezasHeadings = [];
    protected $solidosSolublesHeadings = [];
    protected $colorCubrimientoHeadings = [];
    protected $colorFondoHeadings = [];
    protected $defectosCondicionHeadings = [];
    protected $defectosCalidadHeadings = [];
    protected $danoPlagaHeadings = [];

    public function __construct(Collection $receptions)
    {
        $this->receptions = $receptions;
        $this->generateHeadings();
    }

    public function collection()
    {
        return $this->receptions;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @var Recepcion $reception
     */
    public function map($reception): array
    {
        $row = [];

        // GRUPO: IDENTIFICACION DE LA MUESTRA
        $row[] = $reception->numero_g_recepcion ?? '';
        $row[] = $reception->producer->name ?? '';
        $row[] = $reception->n_variedad ?? '';
        $row[] = $reception->fecha_g_recepcion ?? '';
        $row[] = $reception->calidad->detalles->firstWhere('temperatura', '!=', null)->temperatura ?? '';
        $row[] = $reception->peso_neto ?? '';

        // GRUPO: EXPORTACION
        $defectosCalidadSum = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CALIDAD')
            ->sum('cantidad');
        $defectosCondicionSum = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CONDICIÓN')
            ->sum('cantidad');
        $estimacionExportacion = 100 - ($defectosCalidadSum + $defectosCondicionSum);

        $row[] = $estimacionExportacion;
        $row[] = ''; // Exportable Proceso
        $row[] = ''; // Diferencia (will be filled with formula in AfterSheet)

        // GRUPO: DISTRIBUCION DE CALIBRE
        $calibresData = $reception->calidad->detalles
            ->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES');

        foreach ($this->calibreHeadings as $calibreName) {
            $detail = $calibresData->firstWhere('detalle_item', $calibreName);
            $row[] = $detail->porcentaje_muestra ?? 0;
        }

        // GRUPO: PROMEDIO DE FIRMEZAS
        $firmezasData = $reception->calidad->detalles
            ->whereIn('tipo_item', ['GRANDE', 'MEDIANO', 'CHICO'])
            ->where('detalle_item', '!=', 'Solidos Solubles');

        $grandeData = $firmezasData->where('tipo_item', 'GRANDE');
        $row[] = $grandeData->firstWhere('detalle_item', 'Mejilla 1')->valor_ss ?? 0;
        $row[] = $grandeData->firstWhere('detalle_item', 'Mejilla 2')->valor_ss ?? 0;

        $medianoData = $firmezasData->where('tipo_item', 'MEDIANO');
        $row[] = $medianoData->firstWhere('detalle_item', 'Mejilla 1')->valor_ss ?? 0;
        $row[] = $medianoData->firstWhere('detalle_item', 'Mejilla 2')->valor_ss ?? 0;

        $chicoData = $firmezasData->where('tipo_item', 'CHICO');
        $row[] = $chicoData->firstWhere('detalle_item', 'Mejilla 1')->valor_ss ?? 0;
        $row[] = $chicoData->firstWhere('detalle_item', 'Mejilla 2')->valor_ss ?? 0;

        // GRUPO: DISTRIBUCIÓN DE FIRMEZAS
        $distFirmezasData = $reception->calidad->detalles
            ->where('tipo_item', 'PRESIONES');

        foreach ($this->distFirmezasHeadings as $heading) {
            $detail = $distFirmezasData->firstWhere('detalle_item', $heading);
            $row[] = $detail->valor_ss ?? 0;
        }

        // GRUPO: SÓLIDOS SOLUBLES (°BRIX)
        $solidosData = $reception->calidad->detalles
            ->whereIn('tipo_item', ['GRANDE', 'MEDIANO', 'CHICO'])
            ->where('detalle_item', 'Solidos Solubles');

        $row[] = $solidosData->firstWhere('tipo_item', 'GRANDE')->valor_ss ?? 0;
        $row[] = $solidosData->firstWhere('tipo_item', 'MEDIANO')->valor_ss ?? 0;
        $row[] = $solidosData->firstWhere('tipo_item', 'CHICO')->valor_ss ?? 0;

        // GRUPO: COLOR DE CUBRIMIENTO
        $colorCubrimientoData = $reception->calidad->detalles
            ->where('tipo_item', 'COLOR DE CUBRIMIENTO');

        foreach ($this->colorCubrimientoHeadings as $heading) {
            $detail = $colorCubrimientoData->firstWhere('detalle_item', $heading);
            $row[] = $detail->porcentaje_muestra ?? 0;
        }

        // GRUPO: COLOR DE FONDO
        $colorFondoData = $reception->calidad->detalles
            ->where('tipo_item', 'COLOR DE FONDO');

        foreach ($this->colorFondoHeadings as $heading) {
            $detail = $colorFondoData->firstWhere('detalle_item', $heading);
            $row[] = $detail->porcentaje_muestra ?? 0;
        }

        // GRUPO: DEFECTOS CONDICION
        $defectosCondicionData = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CONDICIÓN');

        foreach ($this->defectosCondicionHeadings as $heading) {
            $detail = $defectosCondicionData->firstWhere('detalle_item', $heading);
            $row[] = $detail->cantidad ?? 0;
        }

        // GRUPO: DEFECTOS CALIDAD
        $defectosCalidadData = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CALIDAD');

        foreach ($this->defectosCalidadHeadings as $heading) {
            $detail = $defectosCalidadData->firstWhere('detalle_item', $heading);
            $row[] = $detail->cantidad ?? 0;
        }

        // GRUPO: DAÑO PLAGA
        $danoPlagaData = $reception->calidad->detalles
            ->where('tipo_item', 'DAÑO PLAGA');

        foreach ($this->danoPlagaHeadings as $heading) {
            $detail = $danoPlagaData->firstWhere('detalle_item', $heading);
            $row[] = $detail->cantidad ?? 0;
        }

        return $row;
    }

    protected function generateHeadings()
    {
        $headings = [];

        // GRUPO: IDENTIFICACION DE LA MUESTRA
        $headings = array_merge($headings, ['Lote', 'Productor', 'Variedad', 'Fecha Recepción', 'T° Pulpa', 'Kilos']);

        // GRUPO: EXPORTACION
        $headings = array_merge($headings, ['Estimacion Exportacion', 'Exportable Proceso', 'Diferencia']);

        // Get species from the first reception to generate dynamic headings
        $firstReception = $this->receptions->first();
        if ($firstReception) {
            $speciesName = $firstReception->n_especie;

            // GRUPO: DISTRIBUCION DE CALIBRE
            $calibreNames = Valor::where('parametro_id', 6)
                                  ->where('especie', $speciesName)
                                  ->pluck('name')
                                  ->toArray();
            sort($calibreNames, SORT_NATURAL | SORT_FLAG_CASE);
            $this->calibreHeadings = $calibreNames;
            $headings = array_merge($headings, $this->calibreHeadings);

            // GRUPO: PROMEDIO DE FIRMEZAS
            $this->firmezasHeadings = [
                'GRANDE - Mejilla 1', 'GRANDE - Mejilla 2',
                'MEDIANO - Mejilla 1', 'MEDIANO - Mejilla 2',
                'CHICO - Mejilla 1', 'CHICO - Mejilla 2',
            ];
            $headings = array_merge($headings, $this->firmezasHeadings);

            // GRUPO: DISTRIBUCIÓN DE FIRMEZAS
            $this->distFirmezasHeadings = Valor::where('parametro_id', 16)
                                                 ->where('especie', $speciesName)
                                                 ->orderBy('name')
                                                 ->pluck('name')
                                                 ->toArray();
            $headings = array_merge($headings, $this->distFirmezasHeadings);

            // GRUPO: SÓLIDOS SOLUBLES (°BRIX)
            $this->solidosSolublesHeadings = ['GRANDE - Solidos Solubles', 'MEDIANO - Solidos Solubles', 'CHICO - Solidos Solubles'];
            $headings = array_merge($headings, $this->solidosSolublesHeadings);

            // GRUPO: COLOR DE CUBRIMIENTO
            $this->colorCubrimientoHeadings = Valor::whereIn('parametro_id', [1])
                                                     ->where('especie', $speciesName)
                                                     ->orderBy('name')
                                                     ->pluck('name')
                                                     ->toArray();
            $headings = array_merge($headings, $this->colorCubrimientoHeadings);

            // GRUPO: COLOR DE FONDO
            $this->colorFondoHeadings = $this->receptions->pluck('calidad.detalles')
                                                          ->flatten()
                                                          ->where('tipo_item', 'COLOR DE FONDO')
                                                          ->pluck('detalle_item')
                                                          ->unique()
                                                          ->sort()
                                                          ->values()
                                                          ->toArray();
            $headings = array_merge($headings, $this->colorFondoHeadings);

            // GRUPO: DEFECTOS CONDICION
            $this->defectosCondicionHeadings = Valor::where('parametro_id', 5)
                                                      ->where('especie', $speciesName)
                                                      ->orderBy('name')
                                                      ->pluck('name')
                                                      ->toArray();
            $headings = array_merge($headings, $this->defectosCondicionHeadings);

            // GRUPO: DEFECTOS CALIDAD
            $this->defectosCalidadHeadings = Valor::where('parametro_id', 4)
                                                    ->where('especie', $speciesName)
                                                    ->orderBy('name')
                                                    ->pluck('name')
                                                    ->toArray();
            $headings = array_merge($headings, $this->defectosCalidadHeadings);

            // GRUPO: DAÑO PLAGA
            $this->danoPlagaHeadings = Valor::where('parametro_id', 3)
                                              ->where('especie', $speciesName)
                                              ->orderBy('name')
                                              ->pluck('name')
                                              ->toArray();
            $headings = array_merge($headings, $this->danoPlagaHeadings);
        }

        $this->headings = $headings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Insert a new row for the group headers
                $event->sheet->insertNewRowBefore(1, 1);

                $groups = [
                    'IDENTIFICACION DE LA MUESTRA' => 6,
                    'EXPORTACION' => 3,
                    'DISTRIBUCION DE CALIBRE' => count($this->calibreHeadings),
                    'PROMEDIO DE FIRMEZAS' => count($this->firmezasHeadings),
                    'DISTRIBUCIÓN DE FIRMEZAS' => count($this->distFirmezasHeadings),
                    'SÓLIDOS SOLUBLES (°BRIX)' => count($this->solidosSolublesHeadings),
                    'COLOR DE CUBRIMIENTO' => count($this->colorCubrimientoHeadings),
                    'COLOR DE FONDO' => count($this->colorFondoHeadings),
                    'DEFECTOS CONDICION' => count($this->defectosCondicionHeadings),
                    'DEFECTOS CALIDAD' => count($this->defectosCalidadHeadings),
                    'DAÑO PLAGA' => count($this->danoPlagaHeadings),
                ];

                $currentColumn = 1;
                $groupColors = [
                    'IDENTIFICACION DE LA MUESTRA' => '92D050',
                    'EXPORTACION' => 'FF9999',
                    'DISTRIBUCION DE CALIBRE' => 'F4B084',
                    'PROMEDIO DE FIRMEZAS' => 'FFD966',
                    'DISTRIBUCIÓN DE FIRMEZAS' => '00B0F0',
                    'SÓLIDOS SOLUBLES (°BRIX)' => 'AEAAAA',
                    'COLOR DE CUBRIMIENTO' => '9BC2E6',
                    'COLOR DE FONDO' => '9BC2E6',
                    'DEFECTOS CONDICION' => '8EA9DB',
                    'DEFECTOS CALIDAD' => 'DD2FD1',
                    'DAÑO PLAGA' => '8686F6',
                ];

                foreach ($groups as $groupName => $colCount) {
                    if ($colCount > 0) {
                        $startColumn = Coordinate::stringFromColumnIndex($currentColumn);
                        $endColumnIndex = $currentColumn + $colCount - 1;
                        $endColumn = Coordinate::stringFromColumnIndex($endColumnIndex);

                        // Merge cells and set value
                        $event->sheet->mergeCells("{$startColumn}1:{$endColumn}1");
                        $event->sheet->setCellValue("{$startColumn}1", $groupName);

                        // Style the merged cell
                        $event->sheet->getStyle("{$startColumn}1:{$endColumn}1")->applyFromArray([
                            'font' => ['bold' => true],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => $groupColors[$groupName]],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['argb' => 'FF000000'],
                                ],
                            ],
                        ]);

                        $currentColumn += $colCount;
                    }
                }

                // Style the main header row (row 2)
                $event->sheet->getStyle('A2:' . $event->sheet->getHighestColumn() . '2')->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Apply colors to main headings (row 2)
                $currentColumn = 1; // Reset column counter
                foreach ($groups as $groupName => $colCount) {
                    if ($colCount > 0) {
                        $startColumn = Coordinate::stringFromColumnIndex($currentColumn);
                        $endColumnIndex = $currentColumn + $colCount - 1;
                        $endColumn = Coordinate::stringFromColumnIndex($endColumnIndex);

                        // Apply background color to the main headings in this group
                        $event->sheet->getStyle("{$startColumn}2:{$endColumn}2")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => $groupColors[$groupName]],
                            ],
                        ]);

                        $currentColumn += $colCount;
                    }
                }

                // Apply borders to data (from row 3 onwards)
                $highestRow = $event->sheet->getHighestRow();
                $highestColumn = $event->sheet->getHighestColumn();
                $event->sheet->getStyle('A3:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Apply stripe styling to data rows (from row 3 onwards)
                for ($row = 3; $row <= $highestRow; $row++) {
                    if ($row % 2 == 0) { // Even rows
                        $event->sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFE0E0E0'], // Light grey
                            ],
                        ]);
                    }
                }

                // Set sheet name to species name
                $firstReception = $this->receptions->first();
                if ($firstReception) {
                    $speciesName = $firstReception->n_especie;
                    $event->sheet->setTitle($speciesName);
                }

                // Add formula to Diferencia column (Column I, 9th column)
                $diferenciaColumn = Coordinate::stringFromColumnIndex(9);
                for ($row = 3; $row <= $highestRow; $row++) {
                    $estimacionExportacionCell = Coordinate::stringFromColumnIndex(7) . $row; // Column G
                    $exportableProcesoCell = Coordinate::stringFromColumnIndex(8) . $row; // Column H
                    $formula = "={$estimacionExportacionCell}-{$exportableProcesoCell}";
                    $event->sheet->setCellValue($diferenciaColumn . $row, $formula);
                }
            },
        ];
    }
}
