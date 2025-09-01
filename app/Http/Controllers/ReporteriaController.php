<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Valor;
use App\Models\Detalle;
use App\Models\Especie;
use App\Models\User;
use App\Models\Recepcion;
use App\Models\Calidad;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReporteriaController extends Controller
{
    /**
     * Displays the reporting page with filtered data.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $filters = $request->only(['especie_id', 'variedad_id', 'productor_id', 'lote']);

        $especies = Especie::with('variedads')->get();
        $producers = User::whereNotNull('idprod')->get();

        $lotesQuery = Recepcion::query();
        if ($request->filled('especie_id')) {
            $especie = Especie::find($request->input('especie_id'));
            if($especie){
                $lotesQuery->where('n_especie', $especie->name);
            }
        }
        $lotes = $lotesQuery->select('id', 'numero_g_recepcion')->get();

        $query = Recepcion::query()
            ->with(['calidad.detalles.parametro', 'calidad.detalles.valor', 'producer', 'variedad'])
            ->when($request->filled('especie_id'), function ($query) use ($request) {
                $especie = Especie::find($request->input('especie_id'));
                if($especie){
                    $query->where('n_especie', $especie->name);
                }
            })
            ->when($request->filled('variedad_id'), function ($query) use ($request) {
                $variedad = Variedad::find($request->input('variedad_id'));
                if($variedad){
                    $query->where('n_variedad', $variedad->name);
                }
            })
            ->when($request->filled('productor_id') && $request->input('productor_id') !== 'all', function ($query) use ($request) {
                $query->where('id_emisor', $request->input('productor_id'));
            })
            ->when($request->filled('lote'), function ($query) use ($request) {
                $query->where('numero_g_recepcion', $request->input('lote'));
            });

        $receptions = $query->get();

        // Prepare data for each chart
        $sizeDistribution = $this->getSizeDistributionData($receptions);
        $averageFirmness = $this->getPromedioFirmezasData($receptions);
        $firmnessDistribution = $this->getDistribucionFirmezasData($receptions);
        $solubleSolids = $this->getSolidosSolublesData($receptions);
        $coverageColor = $this->getColorCubrimientoData($receptions);
        $qualityDefects = $this->getDefectosCalidadData($receptions);
        $conditionDefects = $this->getDefectosCondicionData($receptions);
        $pestDamage = $this->getDanoPlagaData($receptions);



        // For receptionDetails, we can reuse the same query
        $receptionDetails = $query->paginate(10)->through(function ($item) {
            return [
                'fecha_g_recepcion' => Carbon::parse($item->fecha_g_recepcion)->format('d/m/Y H:i'), // Format the date$item->fecha_g_recepcion,
                'n_emisor' => $item->producer->name ?? 'N/A',
                'n_especie' => $item->n_especie ?? 'N/A',
                'n_variedad' => $item->variedad->name ?? 'N/A',
                'nota_calidad' => $item->nota_calidad, // Assuming this field exists
            ];
        });

        return Inertia::render('Reporteria/Index', [
            'especies' => $especies,
            'producers' => $producers,
            'lotes' => $lotes,
            'filters' => $filters,
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
     * @param \Illuminate\Support\Collection $receptions
     * @return array
     */
    private function getSizeDistributionData($receptions): array
    {
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
            $chartData[] = [
                'calibre' => $calibre,
                'count' => $count,
            ];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for PROMEDIO FIRMEZAS chart.
     *
     * @param \Illuminate\Support\Collection $receptions
     * @return array
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
     * @param \Illuminate\Support\Collection $receptions
     * @return array
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
                        $firmnessDistributionData[$readingName] =  $detail->valor_ss ?? 0;

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
     * @param \Illuminate\Support\Collection $receptions
     * @return array
     */
    private function getSolidosSolublesData($receptions): array
    {
        $chartData = [];
        $brixData = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if (in_array($detail->detalle_item, ["LIGHT", "DARK", "BLACK"])) {
                        if($detail->tipo_item === 'SOLIDOS SOLUBLES'){

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
     * @param \Illuminate\Support\Collection $receptions
     * @return array
     */
    private function getColorCubrimientoData($receptions): array
    {
        $chartData = [];
        $coverageData = [];

        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'COLOR DE CUBRIMIENTO') {
                        $color = $detail->detalle_item ?? 'N/A';
                        $quantity = $detail->valor_ss ?? 0;


                        $percentage = $detail->valor_ss ?? 0;
                        $coverageData[$color] = ($coverageData[$color] ?? 0) + $percentage;
                    }
                }
            }
        }

        foreach ($coverageData as $color => $percentageSum) {
            $chartData[] = [
                'color' => $color,
                'percentage' => $percentageSum,
            ];
        }

        return array_values($chartData);
    }

    /**
     * Generates data for DEFECTOS CALIDAD chart.
     *
     * @param \Illuminate\Support\Collection $receptions
     * @return array
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
     * @param \Illuminate\Support\Collection $receptions
     * @return array
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
     * @param \Illuminate\Support\Collection $receptions
     * @return array
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
}
