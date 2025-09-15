<?php

namespace App\Http\Controllers;

use App\Exports\CherriesConsolidatedExport;
use App\Exports\ConsolidatedExport;
use App\Models\Calidad;
use App\Models\Especie;
use App\Models\Recepcion;
use App\Models\User;
use App\Services\QualityChartsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReporteriaController extends Controller
{
    /**
     * Displays the reporting page with filtered data.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $filters = $request->only(['especie_id', 'variedad_id', 'productor_id', 'lote', 'lotes', 'from_date', 'to_date']);
        $ready = $request->filled('especie_id') && $request->filled('lote');

        $especies = Especie::with('variedads')->get();
        $producers = User::whereNotNull('idprod')->get();

        $lotesQuery = Recepcion::query();
        if ($request->filled('especie_id')) {
            $especie = Especie::find($request->input('especie_id'));
            if ($especie) {
                $lotesQuery->where('n_especie', $especie->name);
            }
        }
        $lotes = $lotesQuery->select('id', 'numero_g_recepcion')->get();

        $receptions = collect();
        $query = null;
        if ($ready) {
            $query = Recepcion::query()
                ->with(['calidad.detalles.parametro', 'calidad.detalles.valor', 'producer', 'variedad'])
                ->when($request->filled('especie_id'), function ($query) use ($request) {
                    $especie = Especie::find($request->input('especie_id'));
                    if ($especie) {
                        $query->where('n_especie', $especie->name);
                    }
                })
                ->when($request->filled('variedad_id'), function ($query) use ($request) {
                    $variedad = Variedad::find($request->input('variedad_id'));
                    if ($variedad) {
                        $query->where('n_variedad', $variedad->name);
                    }
                })
                ->when($request->filled('productor_id') && $request->input('productor_id') !== 'all', function ($query) use ($request) {
                    $query->where('id_emisor', $request->input('productor_id'));
                })
                ->when($request->filled('lote'), function ($query) use ($request) {
                    $query->where('numero_g_recepcion', $request->input('lote'));
                })
                ->when($request->filled('lotes') && is_array($request->input('lotes')) && count($request->input('lotes')) > 0, function ($query) use ($request) {
                    $query->whereIn('numero_g_recepcion', $request->input('lotes'));
                })
                ->when($request->filled('from_date'), function ($query) use ($request) {
                    $query->whereDate('fecha_g_recepcion', '>=', $request->input('from_date'));
                })
                ->when($request->filled('to_date'), function ($query) use ($request) {
                    $query->whereDate('fecha_g_recepcion', '<=', $request->input('to_date'));
                });

            $receptions = $query->get();
        }

        // Prepare data for each chart
        $sizeDistribution = $ready ? QualityChartsService::getSizeDistributionData($receptions) : [];
        $averageFirmness = $ready ? QualityChartsService::getPromedioFirmezasData($receptions) : [];
        $firmnessDistribution = $ready ? QualityChartsService::getDistribucionFirmezasData($receptions) : [];
        $solubleSolids = $ready ? QualityChartsService::getSolidosSolublesData($receptions) : [];
        $coverageColor = $ready ? QualityChartsService::getColorCubrimientoData($receptions) : [];
        $qualityDefects = $ready ? $this->getDefectosCalidadData($receptions) : [];
        $conditionDefects = $ready ? $this->getDefectosCondicionData($receptions) : [];
        $pestDamage = $ready ? $this->getDanoPlagaData($receptions) : [];

        // For receptionDetails, we can reuse the same query
        $receptionDetails = null;
        if ($ready && $query) {
            $receptionDetails = $query->paginate(10)->through(function ($item) {
                return [
                    'fecha_g_recepcion' => Carbon::parse($item->fecha_g_recepcion)->format('d/m/Y H:i'),
                    'n_emisor' => $item->producer->name ?? 'N/A',
                    'n_especie' => $item->n_especie ?? 'N/A',
                    'n_variedad' => $item->variedad->name ?? 'N/A',
                    'nota_calidad' => $item->nota_calidad,
                ];
            });
        }

        return Inertia::render('Reporteria/Index', [
            'especies' => $especies,
            'producers' => $producers,
            'lotes' => $lotes,
            'filters' => $filters,
            'ready' => $ready,
            'sizeDistribution' => $sizeDistribution,
            'averageFirmness' => $averageFirmness,
            'firmnessDistribution' => $firmnessDistribution,
            'solubleSolids' => $solubleSolids,
            'coverageColor' => $coverageColor,
            'qualityDefects' => $qualityDefects,
            'conditionDefects' => $conditionDefects,
            'pestDamage' => $pestDamage,
            'receptionDetails' => $receptionDetails,
        ]);
    }

    /**
     * Generates data for Calibre Distribution chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getSizeDistributionData($receptions): array
    {
        $first = $receptions->first();
        if ($first && ($first->n_especie === 'Cherries')) {
            $reception_numbers = $receptions->pluck('numero_g_recepcion')
                ->filter()->unique()->map(fn ($n) => (string) $n)->values()->all();
            if (empty($reception_numbers)) {
                return ['categories' => [], 'series' => []];
            }

            $colors = ['Rojo', 'Rojo Caoba', 'Santina', 'Caoba Oscuro', 'Black'];
            $grades = ['L', 'XL', 'J', '2J', '3J', '4J'];

            $colores = \DB::raw("(VALUES
                ('Rojo'),
                ('Rojo Caoba'),
                ('Santina'),
                ('Caoba Oscuro'),
                ('Black')
            ) AS c(nombre_color)");

            $calibres = \DB::raw("(VALUES
                ('L'),
                ('XL'),
                ('J'),
                ('2J'),
                ('3J'),
                ('4J')
            ) AS f(categoria_calibres)");

            $caseCategoria = "CASE
                    WHEN calibre < 22 THEN 'L'
                    WHEN calibre BETWEEN 22 AND 23.9 THEN 'L'
                    WHEN calibre BETWEEN 24 AND 25.9 THEN 'XL'
                    WHEN calibre BETWEEN 26 AND 27.9 THEN 'J'
                    WHEN calibre BETWEEN 28 AND 29.9 THEN '2J'
                    WHEN calibre BETWEEN 30 AND 31.9 THEN '3J'
                    WHEN calibre >= 32 THEN '4J'
                END";

            $datosSub = \DB::connection('firmpro')
                ->table('fpdatos as fpd')
                ->selectRaw("fpd.nombre_color, {$caseCategoria} AS categoria_calibres, COUNT(*) AS cantidad")
                ->whereIn('fpd.numero_recepcion', $reception_numbers)
                ->groupBy('fpd.nombre_color', \DB::raw($caseCategoria));

            $resultado = \DB::connection('firmpro')->query()
                ->from($colores)
                ->crossJoin($calibres)
                ->leftJoinSub($datosSub, 'd', function ($join) {
                    $join->on('d.nombre_color', '=', 'c.nombre_color')
                        ->on('d.categoria_calibres', '=', 'f.categoria_calibres');
                })
                ->selectRaw('c.nombre_color, f.categoria_calibres, COALESCE(d.cantidad, 0) AS cantidad')
                ->orderBy('f.categoria_calibres')
                ->orderBy('c.nombre_color')
                ->get();

            // Reorientar a series por color sobre categorías de calibre
            $counts = [];
            $totalsByGrade = [];
            foreach ($resultado as $row) {
                $color = $row->nombre_color;
                $grade = $row->categoria_calibres;
                $cantidad = (int) $row->cantidad;
                $counts[$grade][$color] = ($counts[$grade][$color] ?? 0) + $cantidad;
                $totalsByGrade[$grade] = ($totalsByGrade[$grade] ?? 0) + $cantidad;
            }

            $series = [];
            $countsSeries = [];
            foreach ($colors as $c) {
                $data = [];
                $countRow = [];
                foreach ($grades as $g) {
                    $val = $counts[$g][$c] ?? 0;
                    $total = $totalsByGrade[$g] ?? 0;
                    $pct = $total > 0 ? round(($val / $total) * 100, 2) : 0.0;
                    $data[] = $pct;
                    $countRow[] = $val;
                }
                $series[] = ['name' => $c, 'data' => $data];
                $countsSeries[] = ['name' => $c, 'data' => $countRow];
            }

            return ['categories' => $grades, 'series' => $series, 'countsSeries' => $countsSeries];
        }

        // Genérico (no Cherries): sumar % por calibre desde calidad.detalles
        $chartData = [];
        $calibreCounts = [];
        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'DISTRIBUCIÓN DE CALIBRES') {
                        $calibreName = $detail->detalle_item ?? 'N/A';
                        $calibreCounts[$calibreName] = ($calibreCounts[$calibreName] ?? 0) + ($detail->porcentaje_muestra ?? 0);
                    }
                }
            }
        }
        foreach ($calibreCounts as $calibre => $count) {
            $chartData[] = ['calibre' => $calibre, 'count' => $count];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for PROMEDIO FIRMEZAS chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getPromedioFirmezasData($receptions): array
    {
        $categories = ['Muy Firme', 'Firme', 'Sensible', 'Blando'];
        $colors = ['Light', 'Dark', 'Black'];

        $accumulator = [];
        foreach ($categories as $category) {
            $accumulator[$category] = [
                'Light' => [],
                'Dark' => [],
                'Black' => [],
            ];
        }

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                $details = $reception->calidad->detalles
                    ->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')
                    ->values(); // Reset keys to ensure 0-based index

                for ($i = 0; $i < $details->count(); $i++) {
                    $categoryIndex = floor($i / 3);
                    if ($categoryIndex >= count($categories)) {
                        break; // Stop if we have more data than categories
                    }
                    $categoryName = $categories[$categoryIndex];

                    $detail = $details[$i];
                    $color = ucfirst(strtolower($detail->detalle_item));
                    $value = $detail->valor_ss ?? 0;

                    if (in_array($color, $colors)) {
                        $accumulator[$categoryName][$color][] = $value;
                    }
                }
            }
        }

        $finalResults = [];
        foreach ($accumulator as $categoryName => $colorData) {
            foreach ($colorData as $color => $values) {
                $finalResults[$categoryName][$color] = count($values) > 0 ? array_sum($values) / count($values) : 0;
            }
        }

        $series = [
            ['name' => 'Light', 'data' => []],
            ['name' => 'Dark', 'data' => []],
            ['name' => 'Black', 'data' => []],
        ];

        foreach ($finalResults as $categoryName => $colorCounts) {
            $series[0]['data'][] = round($colorCounts['Light'], 2);
            $series[1]['data'][] = round($colorCounts['Dark'], 2);
            $series[2]['data'][] = round($colorCounts['Black'], 2);
        }

        return [
            'categories' => $categories,
            'series' => $series,
        ];
    }

    /**
     * Generates data for DISTRIBUCIÓN DE FIRMEZAS chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getDistribucionFirmezasData($receptions): array
    {
        $chartData = [];
        $firmnessDistributionData = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'FIRMEZAS') {
                        $readingName = $detail->detalle_item ?? 'N/A';
                        $firmnessDistributionData[$readingName] = $detail->valor_ss ?? 0;

                    }
                }
            }
        }

        foreach ($firmnessDistributionData as $readingName => $data) {
            $chartData[] = [
                'reading_name' => $readingName,
                'avg_firmness' => $data,
            ];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for SÓLIDOS SOLUBLES (°BRIX) chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getSolidosSolublesData($receptions): array
    {
        $chartData = [];
        $brixData = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if (in_array($detail->detalle_item, ['LIGHT', 'DARK', 'BLACK'])) {
                        if ($detail->tipo_item === 'SOLIDOS SOLUBLES') {

                            $size = $detail->detalle_item ?? 'N/A';
                            $brixData[$size] = ($detail->valor_ss ?? 0);

                        }
                    }
                }
            }
        }

        foreach ($brixData as $size => $data) {
            $chartData[] = [
                'size' => $size,
                'avg_brix' => $data,
            ];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for COLOR DE CUBRIMIENTO chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getColorCubrimientoData($receptions): array
    {
        $first = $receptions->first();
        if ($first && ($first->n_especie === 'Cherries')) {
            $reception_numbers = $receptions->pluck('numero_g_recepcion')
                ->filter()->unique()->map(fn ($n) => (string) $n)->values()->all();
            if (empty($reception_numbers)) {
                return ['categories' => [], 'series' => []];
            }

            $colors = ['Rojo', 'Rojo Caoba', 'Santina', 'Caoba Oscuro', 'Black'];
            $grades = ['L', 'XL', 'J', '2J', '3J', '4J'];

            $colores = \DB::raw("(VALUES
                ('Rojo'),
                ('Rojo Caoba'),
                ('Santina'),
                ('Caoba Oscuro'),
                ('Black')
            ) AS c(nombre_color)");

            $calibres = \DB::raw("(VALUES
                ('L'),
                ('XL'),
                ('J'),
                ('2J'),
                ('3J'),
                ('4J'),
                ('5J'),
                ('6J'),
                ('7J')
            ) AS f(categoria_calibres)");

            $caseCategoria = "CASE
                    WHEN calibre < 22 THEN 'L'
                    WHEN calibre BETWEEN 22 AND 23.9 THEN 'L'
                    WHEN calibre BETWEEN 24 AND 25.9 THEN 'XL'
                    WHEN calibre BETWEEN 26 AND 27.9 THEN 'J'
                    WHEN calibre BETWEEN 28 AND 29.9 THEN '2J'
                    WHEN calibre BETWEEN 30 AND 31.9 THEN '3J'
                    WHEN calibre BETWEEN 32 AND 33.9 THEN '4J'
                    WHEN calibre BETWEEN 34 AND 35.9 THEN '4J'
                    WHEN calibre BETWEEN 36 AND 37.9 THEN '6J'
                    WHEN calibre > 38  THEN '7J'
                END";

            $datosSub = \DB::connection('firmpro')
                ->table('fpdatos as fpd')
                ->selectRaw("fpd.nombre_color, {$caseCategoria} AS categoria_calibres, COUNT(*) AS cantidad")
                ->whereIn('fpd.numero_recepcion', $reception_numbers)
                ->groupBy('fpd.nombre_color', \DB::raw($caseCategoria));

            $resultado = \DB::connection('firmpro')->query()
                ->from($colores)
                ->crossJoin($calibres)
                ->leftJoinSub($datosSub, 'd', function ($join) {
                    $join->on('d.nombre_color', '=', 'c.nombre_color')
                        ->on('d.categoria_calibres', '=', 'f.categoria_calibres');
                })
                ->selectRaw('c.nombre_color, f.categoria_calibres, COALESCE(d.cantidad, 0) AS cantidad')
                ->orderBy('c.nombre_color')
                ->orderBy('f.categoria_calibres')
                ->get();

            $counts = [];
            $totalsByColor = [];
            foreach ($resultado as $row) {
                $color = $row->nombre_color;
                $grade = $row->categoria_calibres;
                $cantidad = (int) $row->cantidad;
                $counts[$color][$grade] = ($counts[$color][$grade] ?? 0) + $cantidad;
                $totalsByColor[$color] = ($totalsByColor[$color] ?? 0) + $cantidad;
            }

            $series = [];
            $countsSeries = [];
            foreach ($grades as $g) {
                $data = [];
                $countRow = [];
                foreach ($colors as $c) {
                    $val = $counts[$c][$g] ?? 0;
                    $total = $totalsByColor[$c] ?? 0;
                    $pct = $total > 0 ? round(($val / $total) * 100, 2) : 0.0;
                    $data[] = $pct;
                    $countRow[] = $val;
                }
                $series[] = ['name' => $g, 'data' => $data];
                $countsSeries[] = ['name' => $g, 'data' => $countRow];
            }

            return ['categories' => $colors, 'series' => $series, 'countsSeries' => $countsSeries];
        }

        $chartData = [];
        $coverageData = [];
        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'COLOR DE CUBRIMIENTO') {
                        $color = $detail->detalle_item ?? 'N/A';
                        $percentage = $detail->valor_ss ?? 0;
                        $coverageData[$color] = ($coverageData[$color] ?? 0) + $percentage;
                    }
                }
            }
        }
        foreach ($coverageData as $color => $percentageSum) {
            $chartData[] = ['color' => $color, 'percentage' => $percentageSum];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for DEFECTOS CALIDAD chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getDefectosCalidadData($receptions): array
    {
        $chartData = [];
        $defectCounts = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'DEFECTOS DE CALIDAD') {
                        $defect = $detail->detalle_item ?? 'N/A';
                        $defectCounts[$defect] = ($defectCounts[$defect] ?? 0) + ($detail->cantidad ?? 0);
                    }
                }
            }
        }

        foreach ($defectCounts as $defect => $count) {
            $chartData[] = [
                'defect' => $defect,
                'count' => $count,
            ];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for DEFECTOS CONDICION chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getDefectosCondicionData($receptions): array
    {
        $chartData = [];
        $defectCounts = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'DEFECTOS DE CONDICIÓN') {
                        $defect = $detail->detalle_item ?? 'N/A';
                        $defectCounts[$defect] = ($defectCounts[$defect] ?? 0) + ($detail->porcentaje_muestra ?? 0);
                    }
                }
            }
        }

        foreach ($defectCounts as $defect => $count) {
            $chartData[] = [
                'defect' => $defect,
                'count' => $count,
            ];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for DAÑO PLAGA chart.
     *
     * @param  \Illuminate\Support\Collection  $receptions
     */
    private function getDanoPlagaData($receptions): array
    {
        $chartData = [];
        $damageCounts = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'DAÑO DE PLAGA') {
                        $damageType = $detail->detalle_item ?? 'N/A';
                        $damageCounts[$damageType] = ($damageCounts[$damageType] ?? 0) + ($detail->cantidad ?? 0);
                    }
                }
            }
        }

        foreach ($damageCounts as $damageType => $count) {
            $chartData[] = [
                'damage_type' => $damageType,
                'count' => $count,
            ];
        }

        return array_values($chartData);
    }

    public function exportConsolidated(Request $request)
    {
        $filters = $request->only(['especie_id', 'variedad_id', 'productor_id', 'lote', 'lotes', 'from_date', 'to_date']);
        $nombre_especie = '';
        $query = Recepcion::query()
            ->with(['calidad.detalles.parametro', 'calidad.detalles.valor', 'producer', 'variedad'])
            ->when($request->filled('especie_id'), function ($query) use ($request) {
                $especie = Especie::find($request->input('especie_id'));
                $nombre_especie = $especie->name;
                if ($especie) {
                    $query->where('n_especie', $especie->name);
                }
            })
            ->when($request->filled('variedad_id'), function ($query) use ($request) {
                $variedad = \App\Models\Variedad::find($request->input('variedad_id'));
                if ($variedad) {
                    $query->where('n_variedad', $variedad->name);
                }
            })
            ->when($request->filled('productor_id') && $request->input('productor_id') !== 'all', function ($query) use ($request) {
                $query->where('id_emisor', $request->input('productor_id'));
            })
            ->when($request->filled('lote'), function ($query) use ($request) {
                $query->where('numero_g_recepcion', $request->input('lote'));
            })
            ->when($request->filled('lotes') && is_array($request->input('lotes')) && count($request->input('lotes')) > 0, function ($query) use ($request) {
                $query->whereIn('numero_g_recepcion', $request->input('lotes'));
            })
            ->when($request->filled('from_date'), function ($query) use ($request) {
                $query->whereDate('fecha_g_recepcion', '>=', $request->input('from_date'));
            })
            ->when($request->filled('to_date'), function ($query) use ($request) {
                $query->whereDate('fecha_g_recepcion', '<=', $request->input('to_date'));
            });

        $receptions = $query->get();

        $firstReception = $receptions->first();
        $speciesName = $firstReception ? $firstReception->n_especie : null;

        if ($speciesName === 'Cherries') {
            return Excel::download(new CherriesConsolidatedExport($receptions), 'consolidated-report-cherries.xlsx');
        } else {
            // For other species, use the existing ConsolidatedExport
            return Excel::download(new ConsolidatedExport($receptions), 'consolidated-report.xlsx');
        }
    }
}
