<?php

namespace App\Exports;

use App\Models\Recepcion;
use App\Models\Valor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

class CherriesConsolidatedExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
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
    protected $distFirmezasSegregacionHeadings = [];
    protected $defectosPudricionHeadings = [];
    protected $danoplagaHeadings = [];
    protected $firmnessData;

    public function __construct(Collection $receptions)
    {
        $this->receptions = $receptions;
        $this->firmnessData = $this->getFirmnessDataForAllReceptions($receptions);
        $this->generateHeadings();
    }

    private function getFirmnessDataForAllReceptions(Collection $receptions)
    {
        $reception_numbers = $receptions->pluck('numero_g_recepcion')->filter()->unique()->map(function ($item) {
            return strval($item);
        })->all();

        if (empty($reception_numbers)) {
            return collect();
        }

        $data = DB::connection('firmpro')->table('fpdatos')
            ->select(
                'numero_recepcion',
                DB::raw("CASE WHEN nombre_color = 'Rojo' THEN 'Light' WHEN nombre_color IN ('Rojo Caoba','Santina') THEN 'Dark' WHEN nombre_color IN ('Caoba Oscuro','Black') THEN 'Black' END AS grupo_color"),
                DB::raw("CASE WHEN firmeza >= 280 THEN 'MUY FIRME' WHEN firmeza BETWEEN 199.1 AND 279.9 THEN 'FIRME' WHEN firmeza BETWEEN 179.1 AND 199 THEN 'SENSIBLE' WHEN firmeza BETWEEN 0.1 AND 179 THEN 'BLANDO' END AS categoria_firmeza"),
                DB::raw("COUNT(*) AS cantidad")
            )
            ->whereIn('numero_recepcion', $reception_numbers)
            ->groupBy('numero_recepcion', DB::raw("CASE WHEN nombre_color = 'Rojo' THEN 'Light' WHEN nombre_color IN ('Rojo Caoba','Santina') THEN 'Dark' WHEN nombre_color IN ('Caoba Oscuro','Black') THEN 'Black' END, CASE WHEN firmeza >= 280 THEN 'MUY FIRME' WHEN firmeza BETWEEN 199.1 AND 279.9 THEN 'FIRME' WHEN firmeza BETWEEN 179.1 AND 199 THEN 'SENSIBLE' WHEN firmeza BETWEEN 0.1 AND 179 THEN 'BLANDO' END"))
            ->get();

        return $data->groupBy('numero_recepcion');
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

        // GRUPO: IDENTIFICACIÓN DE LA MUESTRA
        $row[] = $reception->numero_g_recepcion ?? '';
        $row[] = $reception->producer->name ?? '';
        $row[] = ''; // Productor Real (blank)
        $row[] = $reception->n_variedad ?? '';
        $row[] = ''; // Variedad Real (blank)
        $row[] = $reception->fecha_g_recepcion ?? '';
        $row[] = ''; // N° Frutos Muestra (blank)
        $row[] = $reception->peso_neto ?? '';
        $row[] = $reception->calidad->detalles->firstWhere('temperatura', '!=', null)->temperatura ?? '';
        $row[] = ''; // Esponja (blank)
        $row[] = ''; // Humedad (blank)
        $row[] = ''; // Configuración Exportadora (blank)
        $row[] = ''; // N° Firmpro (blank)
        $row[] = ''; // Zonal (blank)

        // GRUPO: CLASIFICACIÓN
        $row[] = $reception->nota_calidad ?? '';
        $row[] = ''; // Motivo (blank)
        $row[] = ''; // Upgrade (blank)
        $row[] = ''; // Motivo2 (blank)

        $defectosCalidadSum = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CALIDAD')
            ->sum('cantidad');
        $defectosCondicionSum = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CONDICIÓN')
            ->sum('cantidad');
        $estimacionExportacion = 100 - ($defectosCalidadSum + $defectosCondicionSum);
        $row[] = $estimacionExportacion;

        $row[] = ''; // % Exportable Proceso (blank)
        $row[] = ''; // Diferencia (blank, formula will be added in AfterSheet)

        // GRUPO: TIEMPO DE MUESTRA
        $row[] = ''; // Hora Inicio (blank)
        $row[] = ''; // Hora Término (blank)
        $row[] = ''; // Total Tiempo (blank)

        // GRUPO: DISTRIBUCIÓN DE CALIBRES (%)
        $row[] = ''; // Precalibre (blank)

        $calibresData = $reception->calidad->detalles
            ->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES');

        $calibreValues = [];
        $calibreNames = ['L', 'XL', 'J', '2J', '3J', '4J', '5J']; // Order matters here

        foreach ($calibreNames as $calibreName) {
            $detail = $calibresData->firstWhere('detalle_item', $calibreName);
            $value = $detail->porcentaje_muestra ?? 0;
            $calibreValues[] = $value;
            $row[] = $value;
        }

        $row[] = array_sum($calibreValues); // Total

        // GRUPO: DISTRIBUCIÓN DE COLOR (%)
        $row[] = ''; // Fuera color (blank)
        $row[] = ''; // Light (Rojo) (blank)
        $row[] = ''; // Dark (Rojo Caoba) (blank)
        $row[] = ''; // Dark (Santina) (blank)
        $row[] = ''; // Black (Caoba Oscuro) (blank)
        $row[] = ''; // Black (Negro) (blank)
        $row[] = ''; // Total (blank)

        // GRUPO: SOLIDOS SOLUBLES
        $solidosData = $reception->calidad->detalles
            ->where('tipo_item', 'SOLIDOS SOLUBLES');

        foreach ($this->solidosSolublesHeadings as $heading) {
            $detail = $solidosData->firstWhere('detalle_item', $heading);
            $row[] = $detail->valor_ss ?? 0;
        }

        // GRUPO: FIRMEZAS
        // Sub-group: Promedio Firmezas por Color
        $firmezasPorColorData = $reception->calidad->detalles
            ->where('tipo_item', 'FIRMEZAS');

        $row[] = $firmezasPorColorData->firstWhere('detalle_item', 'LIGHT')->valor_ss ?? 0;
        $row[] = $firmezasPorColorData->firstWhere('detalle_item', 'DARK')->valor_ss ?? 0;
        $row[] = $firmezasPorColorData->firstWhere('detalle_item', 'BLACK')->valor_ss ?? 0;

        // Sub-group: % Firmezas General & % Distribución de Firmezas por Segregación de Color

        $numero_recepcion = $reception->numero_g_recepcion;
        $datos_raw = $this->firmnessData->get($numero_recepcion, collect());

        // 2. Create a lookup map for easy access
        $datos_lookup = [];
        foreach ($datos_raw as $item) {
            if ($item->grupo_color && $item->categoria_firmeza) { // Ignore nulls from CASE
                $key = "{$item->grupo_color}-{$item->categoria_firmeza}";
                $datos_lookup[$key] = $item->cantidad;
            }
        }

        // 3. Define all possible categories and groups to create a full grid
        $firmeza_categories = ['MUY FIRME', 'FIRME', 'SENSIBLE', 'BLANDO'];
        $color_groups = ['Light', 'Dark', 'Black'];

        $resultados = collect();
        foreach ($color_groups as $color) {
            foreach ($firmeza_categories as $category) {
                $key = "{$color}-{$category}";
                $cantidad = $datos_lookup[$key] ?? 0;
                $resultados->push((object)[
                    'grupo_color' => $color,
                    'categoria_firmeza' => $category,
                    'cantidad' => $cantidad,
                ]);
            }
        }

        // 4. Calculate percentages
        $total_frutos = $resultados->sum('cantidad');
        $distribucionFirmezaPercentages = [];
        foreach ($resultados as $item) {
            // Convert category to Title Case to match headings
            $category = ucwords(strtolower($item->categoria_firmeza));
            $key = "{$category} - {$item->grupo_color}";
            $percentage = ($total_frutos > 0) ? ($item->cantidad / $total_frutos) * 100 : 0;
            $distribucionFirmezaPercentages[$key] = $percentage;
        }

        // 5. Sum percentages for the summary columns
        $firmBlando = ($distribucionFirmezaPercentages['Blando - Light'] ?? 0) +
                      ($distribucionFirmezaPercentages['Blando - Dark'] ?? 0) +
                      ($distribucionFirmezaPercentages['Blando - Black'] ?? 0);

        $firmSensible = ($distribucionFirmezaPercentages['Sensible - Light'] ?? 0) +
                        ($distribucionFirmezaPercentages['Sensible - Dark'] ?? 0) +
                        ($distribucionFirmezaPercentages['Sensible - Black'] ?? 0);

        $firmFirme = ($distribucionFirmezaPercentages['Firme - Light'] ?? 0) +
                     ($distribucionFirmezaPercentages['Firme - Dark'] ?? 0) +
                     ($distribucionFirmezaPercentages['Firme - Black'] ?? 0);

        $firmMuyFirme = ($distribucionFirmezaPercentages['Muy Firme - Light'] ?? 0) +
                        ($distribucionFirmezaPercentages['Muy Firme - Dark'] ?? 0) +
                        ($distribucionFirmezaPercentages['Muy Firme - Black'] ?? 0);

        // Add % Firmezas General to the row
        $row[] = $firmBlando;
        $row[] = $firmSensible;
        $row[] = $firmFirme;
        $row[] = $firmMuyFirme;

        // Add % Distribución de Firmezas por Segregación de Color to the row
        foreach ($this->distFirmezasSegregacionHeadings as $heading) {
            $row[] = $distribucionFirmezaPercentages[$heading] ?? 0;
        }

        // GRUPO: % TIPO DE PUDRICIÓN
        $porcPudricionData = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CONDICIÓN');

        $totalPudricion = 0;
        foreach ($this->defectosPudricionHeadings as $heading) {
            $detail = $porcPudricionData->firstWhere('detalle_item', $heading);
            $value = $detail->cantidad ?? 0;
            $row[] = $value;
            $totalPudricion += $value;
        }
        $row[] = $totalPudricion;

        // GRUPO: % PLAGA
        $danoPlagaData = $reception->calidad->detalles
            ->where('tipo_item', 'DAÑO DE PLAGA');

        $totalDanoPlaga = 0;
        foreach ($this->danoplagaHeadings as $heading) {
            $detail = $danoPlagaData->firstWhere('detalle_item', $heading);
            $value = $detail->cantidad ?? 0;
            $row[] = $value;
            $totalDanoPlaga += $value;
        }
        $row[] = $totalDanoPlaga;

        // GRUPO: DEFECTOS DE CALIDAD
        $defectosCalidadData = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CALIDAD');

        foreach ($this->defectosCalidadHeadings as $heading) {
            $detail = $defectosCalidadData->firstWhere('detalle_item', $heading);
            $row[] = $detail->cantidad ?? 0;
        }

        // GRUPO: DEFECTOS DE CONDICIÓN
        $defectosCondicionData = $reception->calidad->detalles
            ->where('tipo_item', 'DEFECTOS DE CONDICIÓN');

        foreach ($this->defectosCondicionHeadings as $heading) {
            $detail = $defectosCondicionData->firstWhere('detalle_item', $heading);
            $row[] = $detail->cantidad ?? 0;
        }

        return $row;
    }

    protected function generateHeadings()
    {
        $headings = [];

        $firstReception = $this->receptions->first();
        $speciesName = 'Cherries';
        if ($firstReception) {
            $speciesName = $firstReception->n_especie;
        }

        // GRUPO: IDENTIFICACIÓN DE LA MUESTRA
        $headings = array_merge($headings, [
            'Lote',
            'Productor',
            'Productor Real',
            'Variedad',
            'Variedad Real',
            'Fecha Recepción',
            'N° Frutos Muestra',
            'Kilos',
            'T° Pulpa (°C)',
            'Esponja',
            'Humedad',
            'Configuración Exportadora',
            'N° Firmpro',
            'Zonal',
        ]);

        // GRUPO: CLASIFICACIÓN
        $headings = array_merge($headings, [
            'Nota Calidad',
            'Motivo',
            'Upgrade',
            'Motivo2',
            '% Estimación Exportación Recepción',
            '% Exportable Proceso',
            'Diferencia',
        ]);

        // GRUPO: TIEMPO DE MUESTRA
        $headings = array_merge($headings, [
            'Hora Inicio',
            'Hora Término',
            'Total Tiempo',
        ]);

        // GRUPO: DISTRIBUCIÓN DE CALIBRES (%)
        $headings = array_merge($headings, [
            'Precalibre',
            'L', 'XL', 'J', '2J', '3J', '4J', '5J',
            'Total',
        ]);

        // GRUPO: DISTRIBUCIÓN DE COLOR (%)
        $headings = array_merge($headings, [
            'Fuera color',
            'Light (Rojo)',
            'Dark (Rojo Caoba)',
            'Dark (Santina)',
            'Black (Caoba Oscuro)',
            'Black (Negro)',
            'Total',
        ]);

        // GRUPO: SOLIDOS SOLUBLES
        $this->solidosSolublesHeadings = Valor::where('parametro_id', 17)
                                        ->where('especie', $speciesName)
                                        ->pluck('name')
                                        ->toArray();
        sort($this->solidosSolublesHeadings, SORT_NATURAL | SORT_FLAG_CASE);
        $headings = array_merge($headings, $this->solidosSolublesHeadings);

        // GRUPO: FIRMEZAS
        // Sub-group: Promedio Firmezas por Color
        $headings = array_merge($headings, [
            'x̅ FIRM Light', 'x̅ FIRM Dark', 'x̅ FIRM Black',
        ]);

        // Sub-group: % Firmezas General (blank for now)
        $headings = array_merge($headings, [
            '% FIRM Blando', '% FIRM Sensible', '% FIRM Firme', '% FIRM Muy Firme',
        ]);

        // Sub-group: % Distribución de Firmezas por Segregación de Color
        $categories = ['Muy Firme', 'Firme', 'Sensible', 'Blando'];
        $colors = ['Light', 'Dark', 'Black'];
        foreach ($categories as $category) {
            foreach ($colors as $color) {
                $this->distFirmezasSegregacionHeadings[] = "{$category} - {$color}";
            }
        }
        $headings = array_merge($headings, $this->distFirmezasSegregacionHeadings);

        // GRUPO: % TIPO DE PUDRICIÓN
        $this->defectosPudricionHeadings = Valor::where('parametro_id', 5)
                                                  ->where('especie', $speciesName)
                                                  ->where('name', 'LIKE', 'Pudrición%')
                                                  ->orderBy('name')
                                                  ->pluck('name')
                                                  ->toArray();
        $headings = array_merge($headings, $this->defectosPudricionHeadings);
        $headings[] = 'Total Defecto Pudrición';

        // GRUPO: % PLAGA
        $this->danoplagaHeadings = Valor::where ('parametro_id', 3)
                                        ->where('especie', $speciesName)
                                        ->orderBy('name')
                                        ->pluck('name')
                                        ->toArray();
        $headings = array_merge($headings, $this->danoplagaHeadings);
        $headings[] = 'Total Daño Plaga';

        // GRUPO: DEFECTOS DE CALIDAD
        $this->defectosCalidadHeadings = Valor::where('parametro_id', 4)
                                                ->where('especie', $speciesName)
                                                ->orderBy('name')
                                                ->pluck('name')
                                                ->toArray();
        $headings = array_merge($headings, $this->defectosCalidadHeadings);

        // GRUPO: DEFECTOS DE CONDICIÓN
        $this->defectosCondicionHeadings = Valor::where('parametro_id', 5)
                                                  ->where('especie', $speciesName)
                                                  ->where('name', 'NOT LIKE', 'Pudrición%') // Exclude Pudrición for this group
                                                  ->orderBy('name')
                                                  ->pluck('name')
                                                  ->toArray();
        $headings = array_merge($headings, $this->defectosCondicionHeadings);

        $this->headings = $headings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Insert a new row for the group headers
                $event->sheet->insertNewRowBefore(1, 1);

                $groups = [
                    'IDENTIFICACIÓN DE LA MUESTRA' => 14,
                    'CLASIFICACIÓN' => 7,
                    'TIEMPO DE MUESTRA' => 3,
                    'DISTRIBUCIÓN DE CALIBRES (%)' => 9,
                    'DISTRIBUCIÓN DE COLOR (%)' => 7,
                    'SOLIDOS SOLUBLES' => count($this->solidosSolublesHeadings),
                    'FIRMEZAS' => 19,
                    '% TIPO DE PUDRICIÓN' => count($this->defectosPudricionHeadings) + 1,
                    '% PLAGA' => count($this->danoplagaHeadings) + 1,
                    'DEFECTOS DE CALIDAD' => count($this->defectosCalidadHeadings),
                    'DEFECTOS DE CONDICIÓN' => count($this->defectosCondicionHeadings),
                ];

                $currentColumn = 1;
                $groupColors = [
                    'IDENTIFICACIÓN DE LA MUESTRA' => '92D050',
                    'CLASIFICACIÓN' => 'FF9999',
                    'TIEMPO DE MUESTRA' => 'FFD966',
                    'DISTRIBUCIÓN DE CALIBRES (%)' => 'F4B084',
                    'DISTRIBUCIÓN DE COLOR (%)' => '9BC2E6',
                    'SOLIDOS SOLUBLES' => 'AEAAAA',
                    'FIRMEZAS' => 'FFD966',
                    '% TIPO DE PUDRICIÓN' => '8EA9DB',
                    '% PLAGA' => '8686F6',
                    'DEFECTOS DE CALIDAD' => 'DD2FD1',
                    'DEFECTOS DE CONDICIÓN' => '8EA9DB',
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

                // Add formula to Diferencia column (Column U, 21st column)
                $diferenciaColumn = Coordinate::stringFromColumnIndex(21);
                for ($row = 3; $row <= $highestRow; $row++) { // Start from row 3 for data
                    $estimacionExportacionCell = Coordinate::stringFromColumnIndex(19) . $row; // Column S
                    $exportableProcesoCell = Coordinate::stringFromColumnIndex(20) . $row; // Column T
                    $formula = "={$estimacionExportacionCell}-{$exportableProcesoCell}";
                    $event->sheet->setCellValue($diferenciaColumn . $row, $formula);
                }
            },
        ];
    }
}
