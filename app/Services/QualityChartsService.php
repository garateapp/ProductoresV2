<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QualityChartsService
{
    public static function getSizeDistributionData(Collection $receptions): array
    {
        $first = $receptions->first();
        if ($first && ($first->n_especie === 'Cherries')) {
            $reception_numbers = $receptions->pluck('numero_g_recepcion')->filter()->unique()->map(fn ($n) => (string) $n)->values()->all();
            if (empty($reception_numbers)) {
                return ['categories' => [], 'series' => [], 'countsSeries' => []];
            }

            $conexion = DB::connection('firmpro'); // usa SIEMPRE esta conexión

            // --- 1) Subquery COLORES con unionAll (sin DB::raw en from) ---
            $colors = ['Rojo','Rojo Caoba','Santina','Caoba Oscuro','Black'];
            $coloresQ = $conexion->query()
                ->selectRaw("'Rojo' AS nombre_color")
                ->unionAll($conexion->query()->selectRaw("'Rojo Caoba' AS nombre_color"))
                ->unionAll($conexion->query()->selectRaw("'Santina' AS nombre_color"))
                ->unionAll($conexion->query()->selectRaw("'Caoba Oscuro' AS nombre_color"))
                ->unionAll($conexion->query()->selectRaw("'Negro' AS nombre_color"));

            // --- CALIBRES con alias en cada UNION ---
            $calibresAllQ = $conexion->query()
                ->selectRaw("'L'  AS categoria_calibres")
                ->unionAll($conexion->query()->selectRaw("'XL' AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'J'  AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'2J' AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'3J' AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'4J' AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'5J' AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'6J' AS categoria_calibres"))
                ->unionAll($conexion->query()->selectRaw("'7J' AS categoria_calibres"));


            // --- 3) CASE reutilizable ---
            $caseCategoria = "
                CASE
                    WHEN calibre < 22 THEN 'L'
                    WHEN calibre BETWEEN 22 AND 23.9 THEN 'L'
                    WHEN calibre BETWEEN 24 AND 25.9 THEN 'XL'
                    WHEN calibre BETWEEN 26 AND 27.9 THEN 'J'
                    WHEN calibre BETWEEN 28 AND 29.9 THEN '2J'
                    WHEN calibre BETWEEN 30 AND 31.9 THEN '3J'
                    WHEN calibre BETWEEN 32 AND 33.9 THEN '4J'
                    WHEN calibre BETWEEN 34 AND 35.9 THEN '5J'
                    WHEN calibre BETWEEN 35 AND 36.9 THEN '6J'
                    WHEN calibre >= 37 THEN '7J'
                END
            ";

            // --- 4) Subconsulta datos ---
            $datosSub = $conexion->table('fruitcloud.dbo.fpdatos AS fpd')
                ->selectRaw("fpd.nombre_color, {$caseCategoria} AS categoria_calibres, COUNT(*) AS cantidad")
                ->where('fpd.numero_recepcion', '74') // <-- si quieres, cambia por ->whereIn('fpd.numero_recepcion', $reception_numbers)
                ->groupBy('fpd.nombre_color', DB::raw($caseCategoria));

            // --- 5) Flag 6J/7J ---
            $hay6y7Sub = $conexion->query()
                ->fromSub($datosSub, 'd')
                ->selectRaw("
                    CASE
                    WHEN COALESCE(SUM(CASE WHEN d.categoria_calibres IN ('6J','7J') THEN d.cantidad END),0) > 0
                    THEN 1 ELSE 0
                    END AS hay
                ");

            // --- 6) Calibres filtrados usando fromSub + joinSub ---
            $calibresFiltrados = $conexion->query()
                ->fromSub($calibresAllQ, 'f')
                ->joinSub($hay6y7Sub, 'h', function($j){ $j->whereRaw('1=1'); }) // CROSS JOIN
                ->where(function ($q) {
                    $q->whereNotIn('f.categoria_calibres', ['6J','7J'])
                    ->orWhere('h.hay', 1);
                })
                ->select('f.categoria_calibres');

            // --- 7) Query final: fromSub(colores) + joinSub(calibres filtrados) + leftJoinSub(datos) ---
            $resultado = $conexion->query()
                ->fromSub($coloresQ, 'c')
                ->joinSub($calibresFiltrados, 'f', function($j){ $j->whereRaw('1=1'); }) // CROSS JOIN
                ->leftJoinSub($datosSub, 'd', function ($join) {
                    $join->on('d.nombre_color', '=', 'c.nombre_color')
                        ->on('d.categoria_calibres', '=', 'f.categoria_calibres');
                })
                ->selectRaw("c.nombre_color, f.categoria_calibres, COALESCE(d.cantidad, 0) AS cantidad")
                ->orderBy('c.nombre_color')
                ->orderBy('f.categoria_calibres')
                ->get();

            // --- 8) Ajusta el orden de categorías según el flag ---
            $hay6y7 = (int) $conexion->query()->fromSub($hay6y7Sub, 'x')->value('hay');
            $grades = $hay6y7
                ? ['L','XL','J','2J','3J','4J','5J','6J','7J']
                : ['L','XL','J','2J','3J','4J','5J'];

            $counts = [];
            $totalsByGrade = [];
            foreach ($resultado as $row) {
                $counts[$row->categoria_calibres][$row->nombre_color] = ($counts[$row->categoria_calibres][$row->nombre_color] ?? 0) + (int) $row->cantidad;
                $totalsByGrade[$row->categoria_calibres] = ($totalsByGrade[$row->categoria_calibres] ?? 0) + (int) $row->cantidad;
            }
            $series = [];
            $countsSeries = [];


            foreach ($colors as $c) {
                $data = [];
                $countRow = [];
                foreach ($grades as $g) {
                    $val = $counts[$g][$c] ?? 0;
                    $total = $totalsByGrade[$g] ?? 0;
                    $data[] = $total > 0 ? round(($val / $total) * 100, 2) : 0.0;
                    $countRow[] = $val;
                }
                $series[] = ['name' => $c, 'data' => $data];
                $countsSeries[] = ['name' => $c, 'data' => $countRow];
            }

            return ['categories' => $grades, 'series' => $series, 'countsSeries' => $countsSeries];
        }

        $chartData = [];
        $calibreCounts = [];
        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'DISTRIBUCIÓN DE CALIBRES') {
                        $name = $detail->detalle_item ?? 'N/A';
                        $calibreCounts[$name] = ($calibreCounts[$name] ?? 0) + ($detail->porcentaje_muestra ?? 0);
                    }
                }
            }
        }
        foreach ($calibreCounts as $calibre => $count) {
            $chartData[] = ['calibre' => $calibre, 'count' => $count];
        }

        return array_values($chartData);
    }

    public static function getPromedioFirmezasData(Collection $receptions): array
{
    // Definición con una clave estable para indexar
    $categories = [
        ['key' => 'muy_firme', 'top' => 'Muy Firme >280-1000', 'bottom' => 'Durofel >75'],
        ['key' => 'firme',     'top' => 'Firme >200-279',       'bottom' => 'Durofel 72-74.9'],
        ['key' => 'sensible',  'top' => 'Sensible 180-199',     'bottom' => 'Durofel 65-69.9'],
        ['key' => 'blando',    'top' => 'Blando 0.1 -179',      'bottom' => 'Durofel <65.4'],
    ];

    // Etiquetas multilínea para Chart.js (cada label es un array de 2 líneas)
    $labels = array_map(fn ($c) => [$c['top'], $c['bottom']], $categories);

    $colors = ['Light', 'Dark', 'Black'];

    // Acumuladores indexados por la clave string
    $acc = [];
    foreach ($categories as $c) {
        $acc[$c['key']] = ['Light' => [], 'Dark' => [], 'Black' => []];
    }

    // Recorre recepciones y agrupa valores por categoría y color
    foreach ($receptions as $reception) {
        if ($reception->calidad) {
            $details = $reception->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->values();

            for ($i = 0; $i < $details->count(); $i++) {
                $categoryIndex = intdiv($i, 3); // cada 3 items cambia de categoría
                if ($categoryIndex >= count($categories)) {
                    break;
                }

                $key    = $categories[$categoryIndex]['key'];
                $detail = $details[$i];

                // Normaliza LIGHT/DARK/BLACK a Light/Dark/Black
                $color = ucfirst(strtolower($detail->detalle_item ?? ''));
                $value = (float) ($detail->valor_ss ?? 0);

                if (in_array($color, $colors, true)) {
                    $acc[$key][$color][] = $value;
                }
            }
        }
    }

    // Promedios
    $final = [];
    foreach ($acc as $key => $colorData) {
        $final[$key] = [
            'Light' => count($colorData['Light']) ? array_sum($colorData['Light']) / count($colorData['Light']) : 0,
            'Dark'  => count($colorData['Dark'])  ? array_sum($colorData['Dark'])  / count($colorData['Dark'])  : 0,
            'Black' => count($colorData['Black']) ? array_sum($colorData['Black']) / count($colorData['Black']) : 0,
        ];
    }

    // Series en el mismo orden de $categories
    $series = [
        ['name' => 'Light', 'data' => []],
        ['name' => 'Dark',  'data' => []],
        ['name' => 'Black', 'data' => []],
    ];

    foreach ($categories as $c) {
        $key = $c['key'];
        $series[0]['data'][] = round($final[$key]['Light'] ?? 0, 2);
        $series[1]['data'][] = round($final[$key]['Dark']  ?? 0, 2);
        $series[2]['data'][] = round($final[$key]['Black'] ?? 0, 2);
    }

    // Devuelve labels como arrays (dos líneas) para Chart.js
    return ['categories' => $labels, 'series' => $series];
}

    public static function getDistribucionFirmezasData(Collection $receptions): array
    {
        $chartData = [];
        $firmness = [];
        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'FIRMEZAS') {
                        $name = $detail->detalle_item ?? 'N/A';
                        $firmness[$name] = $detail->valor_ss ?? 0;
                    }
                }
            }
        }
        foreach ($firmness as $name => $data) {
            $chartData[] = ['reading_name' => $name, 'avg_firmness' => $data];
        }

        return array_values($chartData);
    }

    public static function getSolidosSolublesData(Collection $receptions): array
    {
        $chartData = [];
        $brix = [];
        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if (in_array($detail->detalle_item, ['LIGHT', 'DARK', 'BLACK']) && $detail->tipo_item === 'SOLIDOS SOLUBLES') {
                        $size = $detail->detalle_item ?? 'N/A';
                        $brix[$size] = $detail->valor_ss ?? 0;
                    }
                }
            }
        }
        foreach ($brix as $size => $data) {
            $chartData[] = ['size' => $size, 'avg_brix' => $data];
        }

        return array_values($chartData);
    }

    public static function getColorCubrimientoData(Collection $receptions): array
    {
        $first = $receptions->first();
        if ($first && ($first->n_especie === 'Cherries')) {
            $reception_numbers = $receptions->pluck('numero_g_recepcion')->filter()->unique()->map(fn ($n) => (string) $n)->values()->all();
            if (empty($reception_numbers)) {
                return ['categories' => [], 'series' => [], 'countsSeries' => []];
            }
           $conexion = DB::connection('firmpro');

// COLORES
$colors = ['Rojo','Rojo Caoba','Santina','Caoba Oscuro','Negro'];
$coloresQ = $conexion->query()
    ->selectRaw("'Rojo' AS nombre_color")
    ->unionAll($conexion->query()->selectRaw("'Rojo Caoba' AS nombre_color"))
    ->unionAll($conexion->query()->selectRaw("'Santina' AS nombre_color"))
    ->unionAll($conexion->query()->selectRaw("'Caoba Oscuro' AS nombre_color"))
    ->unionAll($conexion->query()->selectRaw("'Negro' AS nombre_color"));

// --- CALIBRES con alias en cada UNION ---
$calibresAllQ = $conexion->query()
    ->selectRaw("'L'  AS categoria_calibres")
    ->unionAll($conexion->query()->selectRaw("'XL' AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'J'  AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'2J' AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'3J' AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'4J' AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'5J' AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'6J' AS categoria_calibres"))
    ->unionAll($conexion->query()->selectRaw("'7J' AS categoria_calibres"));

$caseCategoria = "
    CASE
        WHEN calibre < 22 THEN 'L'
        WHEN calibre BETWEEN 22 AND 23.99 THEN 'L'
        WHEN calibre BETWEEN 24 AND 25.99 THEN 'XL'
        WHEN calibre BETWEEN 26 AND 27.99 THEN 'J'
        WHEN calibre BETWEEN 28 AND 29.99 THEN '2J'
        WHEN calibre BETWEEN 30 AND 31.99 THEN '3J'
        WHEN calibre BETWEEN 32 AND 33.99 THEN '4J'
        WHEN calibre BETWEEN 34 AND 35.99 THEN '5J'
        WHEN calibre BETWEEN 35 AND 36.99 THEN '6J'
        WHEN calibre >= 37 THEN '7J'
    END
";

$datosSub = $conexion->table('fruitcloud.dbo.fpdatos AS fpd')
    ->selectRaw("fpd.nombre_color, {$caseCategoria} AS categoria_calibres, COUNT(*) AS cantidad")
    ->where('fpd.numero_recepcion', $reception_numbers) // o ->whereIn('fpd.numero_recepcion', $reception_numbers)
    ->groupBy('fpd.nombre_color', DB::raw($caseCategoria));

$hay6y7Sub = $conexion->query()
    ->fromSub($datosSub, 'd')
    ->selectRaw("
        CASE
          WHEN COALESCE(SUM(CASE WHEN d.categoria_calibres IN ('6J','7J') THEN d.cantidad END),0) > 0
          THEN 1 ELSE 0
        END AS hay
    ");

$calibresFiltrados = $conexion->query()
    ->fromSub($calibresAllQ, 'f')
    ->joinSub($hay6y7Sub, 'h', function($j){ $j->whereRaw('1=1'); })
    ->where(function ($q) {
        $q->whereNotIn('f.categoria_calibres', ['6J','7J'])
          ->orWhere('h.hay', 1);
    })
    ->select('f.categoria_calibres');

$resultado = $conexion->query()
    ->fromSub($coloresQ, 'c')
    ->joinSub($calibresFiltrados, 'f', function($j){ $j->whereRaw('1=1'); })
    ->leftJoinSub($datosSub, 'd', function ($join) {
        $join->on('d.nombre_color', '=', 'c.nombre_color')
             ->on('d.categoria_calibres', '=', 'f.categoria_calibres');
    })
    ->selectRaw("c.nombre_color, f.categoria_calibres, COALESCE(d.cantidad, 0) AS cantidad")
    ->orderBy('c.nombre_color')
    ->orderBy('f.categoria_calibres')
    ->get();

    $hay6y7 = (int) $conexion->query()->fromSub($hay6y7Sub, 'x')->value('hay');
$grades = $hay6y7
    ? ['L','XL','J','2J','3J','4J','5J','6J','7J']
    : ['L','XL','J','2J','3J','4J','5J'];
            $counts = [];
            $totalsByColor = [];
            foreach ($resultado as $row) {
                $counts[$row->nombre_color][$row->categoria_calibres] = ($counts[$row->nombre_color][$row->categoria_calibres] ?? 0) + (int) $row->cantidad;
                $totalsByColor[$row->nombre_color] = ($totalsByColor[$row->nombre_color] ?? 0) + (int) $row->cantidad;
            }
            $series = [];
            $countsSeries = [];
            foreach ($grades as $g) {
                $data = [];
                $countRow = [];
                foreach ($colors as $c) {
                    $val = $counts[$c][$g] ?? 0;
                    $total = $totalsByColor[$c] ?? 0;
                    $data[] = $total > 0 ? round(($val / $total) * 100, 2) : 0.0;
                    $countRow[] = $val;
                } $series[] = ['name' => $g, 'data' => $data];
                $countsSeries[] = ['name' => $g, 'data' => $countRow];
            }

            return ['categories' => $colors, 'series' => $series, 'countsSeries' => $countsSeries];
        }
        $chartData = [];
        $coverage = [];
        foreach ($receptions as $reception) {
            if ($reception->calidad) {
                foreach ($reception->calidad->detalles as $detail) {
                    if ($detail->tipo_item === 'COLOR DE CUBRIMIENTO') {
                        $color = $detail->detalle_item ?? 'N/A';
                        $pct = $detail->valor_ss ?? 0;
                        $coverage[$color] = ($coverage[$color] ?? 0) + $pct;
                    }
                }
            }
        }
        foreach ($coverage as $color => $sum) {
            $chartData[] = ['color' => $color, 'percentage' => $sum];
        }

        return array_values($chartData);
    }
}
