<!DOCTYPE html>



<html lang="es">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Informe de Recepción de {{ $recepcion->n_especie }}</title>



    <style>



        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');



        .page-break { page-break-before: always; }
        .photo-page { padding: 20px 10px; }
        .photo-title { font-size: 16px; font-weight: 700; text-transform: uppercase; margin-bottom: 16px; color: #2c3e50; }
        .photo-grid { display: flex; flex-wrap: wrap; gap: 16px; }
        .photo-card { width: calc(50% - 8px); border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #ffffff; box-shadow: 0 2px 4px rgba(149, 157, 165, 0.2); }
        .photo-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .photo-info { padding: 10px; font-size: 10px; color: #374151; }
        .photo-info strong { display: block; margin-bottom: 4px; font-size: 11px; }
        .photo-info p { margin: 0; }
        .photo-info span { display: block; margin-top: 4px; font-size: 9px; color: #6b7280; }

        body {



            font-family: 'Roboto', sans-serif;



            margin: 20px;



            font-size: 10px;



            position: relative;



            color: #333;



            line-height: 1;



        }



        .column {



            flex: 1;



            padding: 10px;



        }



        .title {



            top: 30px;



            text-align: center;



            margin-bottom: 30px;



            font-size: 20px;



            font-weight: 700;



            color: #2c3e50;



            text-transform: uppercase;



            letter-spacing: 1px;



        }



        .main-header {



            display: flex;



            justify-content: flex-start;



            /* To push chart left and info columns right */



            align-items: center;



            /* Vertically align items */



            margin-bottom: -5px;



            padding-left: 10px;



            /* To make space for the absolute logo */



        }



        .main-header-info-columns {



            /* New wrapper for info columns */



            display: flex;



            gap: 30px;



            /* Original gap for info columns */



            /* margin-left: px; */



        }



        .header-chart-left {



            /* New style for chart in header, left-aligned */



            width: 25%;



            /* Smaller width for this chart */



            /* Push info columns to the right */



        }



        .header-logo {



            /* New style for logo, absolute positioning */



            position: absolute;



            top: 10px;



            /* Adjust as needed */



            left: 15px;



            /* Adjust as needed */



            width: 150px;



            /* Adjust as needed */



            height: auto;



            z-index: 1000;



            /* Ensure it's on top */



        }



        .header-separator {



            width: 95%;



            height: 2px;



            background-color: #f7922e;



            margin: 5px auto;



        }



        .summary-section {



            display: flex;



            flex-wrap: wrap;



            justify-content: center;



            gap: 30px;



            margin-bottom: -4px;



            padding: 6px;



            background-color: #f9f9f9;



            border-radius: 8px;



            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);



        }



        .section-title {



            width: 100%;



            text-align: center;



            font-size: 14mm;



            font-weight: 700;



            margin-bottom: 20px;



            padding-bottom: 10px;



            border-bottom: 2px solid #4CAF50;



            color: #4CAF50;



        }



        .chart-wrapper-resumen {



            display: flex;



            flex-direction: column;



            /* align-items: center; */



            width: calc(47.3%);



            /* Two columns with gap */



            background-color: #fff;



            padding: 5px;



            border-radius: 8px;



            border-bottom: 2px solid #4CAF50;



            border-right: 2px solid #4CAF50;



            box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.1);



        }



        .chart-wrapper {



            display: flex;



            flex-direction: column;



            /* align-items: center; */



            width: calc(45%);



            /* Two columns with gap */



            background-color: #fff;



            padding-right: 10px;



            border-radius: 8px;



            border-bottom: 2px solid #4CAF50;



            border-right: 2px solid #4CAF50;



            /* box-shadow: 2px 2px 8px rgba(0, 0, 0, 1.1); */



        }



        .full-width-chart {



            width: 100%;



        }



        .chart-container {



            display: flex;



            flex-direction: row;



            align-items: center;



            gap: 10px;



        }



        /* Column layout variant for charts that need footer under canvas */



        .chart-container--column {



            flex-direction: column;



            align-items: flex-start;



        }



        .chart-legend {



            display: flex;



            flex-direction: column;



            gap: 8px;



            font-size: 8px;



        }



        .legend-item {



            display: flex;



            align-items: center;



            gap: 5px;



            font-size: 8px;



        }



        .legend-color-box {



            width: 16px;



            height: 16px;



            border: 1px solid #eee;



            border-radius: 3px;



        }



        /* Horizontal values row for calibre totals */



        .calibre-values {



            display: flex;



            flex-wrap: nowrap;



            gap: 20px;



            align-items: center;



            justify-content: flex-start;



            padding-top: 6px;



            padding-left: 62px;



            font-size: 9px;



        }



        .calibre-value-item {



            min-width: 28px;



            text-align: center;



            color: #333;



            background: #f3f3f3;



            border: 1px solid #e0e0e0;



            border-radius: 4px;



            padding: 2px 4px;



        }



        .stamp-image {



            position: absolute;



            top: 50px;



            left: 80%;



            width: 150px;



            height: auto;



            opacity: 0.3;



            transform: rotate({{ rand(0, 180) }}deg);



            z-index: 1000;



        }



        .new-section-container {



            display: flex;



            justify-content: space-around;



            /* margin-top: 20px;



            margin-bottom: 20px;



            padding: 15px; */



            background-color: #f9f9f9;



            border-radius: 8px;



            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);



        }



        .section-column {



            flex: 1;



            padding: 10px;



            border-right: 1px solid #4CAF50;



            /* Separator between columns */



        }



        .section-column:last-child {



            border-right: none;



            /* No border on the last column */



        }



        .section-column h3 {



            font-size: 12px;



            color: #4CAF50;



            /* Green color for titles */



            margin-bottom: 10px;



            text-align: center;



            border-bottom: 1px solid #4CAF50;



            padding-bottom: 5px;



        }



        .section-column ul {



            list-style: none;



            padding: 0;



            margin: 0;



            line-height: 0.8;



        }



        .section-column ul li {



            font-size: 11px;



            margin-bottom: 5px;



            color: #555;



        }



        .observations-section {



            /* margin-top: 20px;



            margin-bottom: 20px;



            padding: 15px; */



            background-color: #f9f9f9;



            border-radius: 8px;



            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);



        }



        .observations-section h3 {



            font-size: 12px;



            color: #2c3e50;



            border-bottom: 1px solid #eee;



        }



        .observations-section p {



            font-size: 10px;



            color: #555;



            line-height: 0.8;



        }





        .color-matrix-wrapper {

            width: 100%;

            overflow-x: auto;

            padding: 4px 0;

        }



        .color-calibre-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            background-color: #fff;

        }



        .color-calibre-matrix th,

        .color-calibre-matrix td {

            border: 1px solid #dcdcdc;

            padding: 2px 2px;

            text-align: center;

            min-width: 24px;

            white-space: nowrap;

            vertical-align: middle;

        }



        .color-calibre-matrix thead th {

            background-color: #f0f4f8;

            font-weight: 700;

        }



        .color-calibre-matrix tfoot th {

            background-color: #f9f9f9;

        }



        .color-group-cell {

            background-color: #eef3f8;

            font-weight: 600;

            text-transform: uppercase;

            writing-mode: vertical-rl;

            transform: rotate(180deg);

            white-space: nowrap;

            padding: 6px 4px;

            min-width: 18px;

            text-align: center;

        }



        .color-code-label {

            display: flex;

            flex-direction: column;

            align-items: center;

            gap: 2px;

        }



        .color-code-label span {

            font-size: 8px;

            color: #666;

        }



        .color-matrix-summary {

            margin-top: 6px;

            font-size: 9px;

            display: flex;

            gap: 12px;

            justify-content: flex-end;

        }



        .color-matrix-empty {

            font-size: 9px;

            font-style: italic;

            color: #666;

            margin: 8px 0;

        }





    </style>


{{--
                                    @if ($photo->created_at)
                                <span>{{ optional($photo->created_at)->format('d-m-Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif --}}

<script>



        {!! @file_get_contents(public_path('vendor/chart.js/chart.umd.min.js')) !!}



    </script>



    <script>



        {!! @file_get_contents(public_path('vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js')) !!}



    </script>



    @php



        // Para Cherries: precomputar matrices desde FirmPro (color x calibre)



        if ($recepcion->n_especie === 'Cherries') {



            try {



                $colors = ['Rojo', 'Rojo Caoba', 'Santina', 'Caoba Oscuro', 'Negro'];



                $grades = ['L', 'XL', 'J', '2J', '3J', '4J', '5J', '6J', '7J'];



                // 1) Tablas inline (UNION ALL) para colores y calibres - usar conexión firmpro (SQL Server)



                $conexion = DB::connection('firmpro');



                $coloresQuery = $conexion



                    ->query()



                    ->selectRaw("'Rojo' AS nombre_color")



                    ->unionAll($conexion->query()->selectRaw("'Rojo Caoba' AS nombre_color"))



                    ->unionAll($conexion->query()->selectRaw("'Santina' AS nombre_color"))



                    ->unionAll($conexion->query()->selectRaw("'Caoba Oscuro' AS nombre_color"))



                    ->unionAll($conexion->query()->selectRaw("'Negro' AS nombre_color"));



                $calibresQuery = $conexion



                    ->query()



                    ->selectRaw("'L' AS categoria_calibres")



                    ->unionAll($conexion->query()->selectRaw("'XL' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'J' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'2J' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'3J' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'4J' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'5J' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'6J' AS categoria_calibres"))



                    ->unionAll($conexion->query()->selectRaw("'7J' AS categoria_calibres"));



                // 2) CASE reutilizable (mismas reglas que tu SQL)



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



                // 3) Subconsulta "datos" (agregada por color + categoría)



                $datosSub = $conexion



                    ->table('fruitcloud.dbo.fpdatos AS fpd')



                    ->selectRaw(



                        "



                        fpd.nombre_color,



                        {$caseCategoria} AS categoria_calibres,



                        COUNT(*) AS cantidad



                    ",



                    )



                    ->where('fpd.numero_recepcion', (string) ($recepcion->numero_g_recepcion ?? ''))



                    ->groupBy('fpd.nombre_color', DB::raw($caseCategoria));



                // 4) Subconsulta "hay_6y7": 1 si existe algo en 6J/7J, 0 en caso contrario



                $hay6y7Sub = $conexion->query()->fromSub($datosSub, 'd')->selectRaw("



                        CASE



                        WHEN COALESCE(SUM(CASE WHEN d.categoria_calibres IN ('6J','7J') THEN d.cantidad END),0) > 0



                        THEN 1 ELSE 0



                        END AS hay



                    ");



                $hay6y7 = (int) $conexion->query()->fromSub($hay6y7Sub, 'x')->value('hay');



                $grades = $hay6y7



                    ? ['L', 'XL', 'J', '2J', '3J', '4J', '5J', '6J', '7J']



                    : ['L', 'XL', 'J', '2J', '3J', '4J', '5J'];



                // 5) Subconsulta "calibres_filtrados":



                //    - De la lista completa de calibres



                //    - JOIN al flag hay_6y7



                //    - Filtro: siempre incluir no-6J/7J; incluir 6J/7J SOLO si hay=1



                $calibresFiltrados = $conexion



                    ->query()



                    ->fromSub($calibresQuery, 'f')



                    ->joinSub($hay6y7Sub, 'h', function ($join) {



                        $join->whereRaw('1=1');



                    }) // CROSS JOIN (1=1)



                    ->where(function ($q) {



                        $q->whereNotIn('f.categoria_calibres', ['6J', '7J'])->orWhere('h.hay', 1);



                    })



                    ->select('f.categoria_calibres');



                // 6) Query final:



                //    - "colores" como tabla base



                //    - CROSS JOIN a "calibres_filtrados" (joinSub con 1=1)



                //    - LEFT JOIN a "datos"



                $resultado = $conexion



                    ->query()



                    ->fromSub($coloresQuery, 'c') // c



                    ->joinSub($calibresFiltrados, 'f', function ($join) {



                        $join->whereRaw('1=1');



                    }) // CROSS JOIN



                    ->leftJoinSub($datosSub, 'd', function ($join) {



                        $join



                            ->on('d.nombre_color', '=', 'c.nombre_color')



                            ->on('d.categoria_calibres', '=', 'f.categoria_calibres');



                    })



                    ->selectRaw(



                        "



                        c.nombre_color,



                        f.categoria_calibres,



                        COALESCE(d.cantidad, 0) AS cantidad



                    ",



                    )



                    ->orderBy('c.nombre_color')



                    ->orderBy('f.categoria_calibres')



                    ->get();



                $countsByGradeColor = [];



                $totalsByGrade = [];



                $countsByColorGrade = [];



                $totalsByColor = [];



                foreach ($resultado as $r) {



                    $countsByGradeColor[$r->categoria_calibres][$r->nombre_color] =



                        ($countsByGradeColor[$r->categoria_calibres][$r->nombre_color] ?? 0) + (int) $r->cantidad;



                    $totalsByGrade[$r->categoria_calibres] =



                        ($totalsByGrade[$r->categoria_calibres] ?? 0) + (int) $r->cantidad;



                    $countsByColorGrade[$r->nombre_color][$r->categoria_calibres] =



                        ($countsByColorGrade[$r->nombre_color][$r->categoria_calibres] ?? 0) + (int) $r->cantidad;



                    $totalsByColor[$r->nombre_color] = ($totalsByColor[$r->nombre_color] ?? 0) + (int) $r->cantidad;



                }



                // Calibres: categories=grades; series por color (porcentaje y conteos)



                $ch_calibre_categories = $grades;



                $ch_calibre_series = [];



                $ch_calibre_counts_series = [];



                foreach ($colors as $c) {



                    $pctData = [];



                    $absData = [];



                    foreach ($grades as $g) {



                        $val = $countsByGradeColor[$g][$c] ?? 0;



                        $tot = $totalsByGrade[$g] ?? 0;



                        $pctData[] = $tot > 0 ? round(($val / $tot) * 100, 2) : 0.0;



                        $absData[] = $val;



                    }



                    $ch_calibre_series[] = ['name' => $c, 'data' => $pctData];



                    $ch_calibre_counts_series[] = ['name' => $c, 'data' => $absData];



                }



                // Colores: categories=colors; series por calibre (porcentaje y conteos)



                $ch_color_categories = $colors;



                $ch_color_series = [];



                $ch_color_counts_series = [];



                foreach ($grades as $g) {



                    $pctData = [];



                    $absData = [];



                    foreach ($colors as $c) {



                        $val = $countsByColorGrade[$c][$g] ?? 0;



                        $tot = $totalsByColor[$c] ?? 0;



                        $pctData[] = $tot > 0 ? round(($val / $tot) * 100, 2) : 0.0;



                        $absData[] = $val;



                    }



                    $ch_color_series[] = ['name' => $g, 'data' => $pctData];



                    $ch_color_counts_series[] = ['name' => $g, 'data' => $absData];



                }



            } catch (\Throwable $e) {



                dd($e);



                $ch_calibre_categories = [];



                $ch_calibre_series = [];



                $ch_calibre_counts_series = [];



                $ch_color_categories = [];



                $ch_color_series = [];



                $ch_color_counts_series = [];



            }



        }



    @endphp



</head>



@php



    if ($recepcion->calidad->detalles->where('tipo_item', 'COLOR DE CUBRIMIENTO')) {



        $col = 0;



        foreach ($recepcion->calidad->detalles->where('tipo_item', 'COLOR DE CUBRIMIENTO') as $item) {



            if ($recepcion->n_especie == 'Apples') {



                if ($recepcion->n_variedad == 'Pink Lady' || $recepcion->n_variedad == 'Rossy Glo') {



                    if ($item->detalle_item == '<40') {



                        $col += $item->porcentaje_muestra;



                    }



                }



                if ($item->detalle_item == '<50') {



                    $col += $item->porcentaje_muestra;



                }



            }



            if ($recepcion->n_especie == 'Mandarinas') {



                if ($item->detalle_item == '<30') {



                    $col += $item->porcentaje_muestra;



                }



            }



            if ($recepcion->n_especie == 'Membrillos') {



                if ($item->detalle_item == '<7' || $item->detalle_item == '>9') {



                    $col += $item->porcentaje_muestra;



                }



            }



            if ($recepcion->n_especie == 'Orange') {



                if ($item->detalle_item == '<30') {



                    $col += $item->porcentaje_muestra;



                }



            }



            if ($recepcion->n_especie == 'Cherries') {



                if ($item->detalle_item == 'Fuera de Color') {



                    $col += $item->valor_ss;



                }



            }



            if ($recepcion->n_especie == 'Pears') {



                if ($item->detalle_item == '<40') {



                    $col += $item->porcentaje_muestra;



                }



            }



        }



    }



@endphp



<body>



    <img src="{{ asset('img/logogreenex.png') }}" class="header-logo">



    <img src="{{ asset('img/sellCC.png') }}" class="stamp-image">



    <div class="title">Informe de Recepción de {{ $recepcion->n_especie }}</div>



    <script>



        // Defaults to avoid undefined when controller doesn't pass datasets



        @php



            $sizeDistribution = $sizeDistribution ?? [];



            $coverageColor = $coverageColor ?? [];



            $averageFirmness = $averageFirmness ?? [];



            $firmnessDistribution = $firmnessDistribution ?? [];



            $solubleSolids = $solubleSolids ?? [];



            $precalibrePercentage =



                (float) (optional(



                    optional(optional($recepcion->calidad)->detalles)



                        ->where('tipo_item', 'DEFECTOS DE CALIDAD')



                        ->where('detalle_item', 'PRECALIBRE')



                        ->first(),



                )->porcentaje_muestra ?? 0);



            //dd($coverageColor, $averageFirmness, $firmnessDistribution, $coverageColor,$sizeDistribution);



        @endphp



        function getChartColors(species) {



            switch (species.toLowerCase()) {



                case 'cherries':



                    return {



                        exportable: 'rgba(255, 99, 132, 0.6)', // Red tone



                            defectosCalidad: 'rgba(200, 0, 0, 0.6)', // Darker red



                            defectosCondicion: 'rgba(150, 0, 0, 0.6)', // Even darker red



                            danosPlaga: 'rgba(100, 0, 0, 0.6)', // Darkest red



                            precalibre: '#FBBF24',



                            borderColor: 'rgba(255, 255, 255, 1)'



                    };



                case 'apples':



                    return {



                        exportable: 'rgba(75, 192, 192, 0.6)', // Green tone



                            defectosCalidad: 'rgba(0, 150, 0, 0.6)', // Darker green



                            defectosCondicion: 'rgba(0, 100, 0, 0.6)', // Even darker green



                            danosPlaga: 'rgba(0, 50, 0, 0.6)', // Darkest green



                            precalibre: '#FBBF24',



                            borderColor: 'rgba(255, 255, 255, 1)'



                    };



                case 'nectarines':



                    return {



                        exportable: 'rgba(255, 159, 64, 0.6)', // Orange tone



                            defectosCalidad: 'rgba(200, 100, 0, 0.6)', // Darker orange



                            defectosCondicion: 'rgba(150, 50, 0, 0.6)', // Even darker orange



                            danosPlaga: 'rgba(100, 25, 0, 0.6)', // Darkest orange



                            precalibre: '#FBBF24',



                            borderColor: 'rgba(255, 255, 255, 1)'



                    };



                default: // Default colors if species not matched



                    return {



                        exportable: 'rgba(54, 162, 235, 0.6)', // Blue



                            defectosCalidad: 'rgba(255, 206, 86, 0.6)', // Yellow



                            defectosCondicion: 'rgba(153, 102, 255, 0.6)', // Purple



                            danosPlaga: 'rgba(255, 99, 132, 0.6)', // Red



                            precalibre: '#FBBF24',



                            borderColor: 'rgba(255, 255, 255, 1)'



                    };



            }



        }



        // Color map for cherries series (used in stacked charts)



        const cherryCoverageColorsMap = {



            'ROJO': '#dc0c15',



            'ROJO CAOBA': '#7f1313ff',



            'SANTINA': '#DE3163',



            'CAOBA OSCURO': '#4a1006ff',



            'NEGRO': '#000000',



            'FUERA DE COLOR': '#808080'



        };



        // Gradiente por calibre (L más claro → 7J más oscuro) para Cherries



        const cherryCalibreGradientMap = {



            'L': '#FFE5EA',



            'XL': '#FFC9D3',



            'J': '#FFADBD',



            '2J': '#FF91A7',



            '3J': '#FF7591',



            '4J': '#F35A7C',



            '5J': '#D14466',



            '6J': '#A8324F',



            '7J': '#7F1F38'



        };



        function getFirmezaBrixColors(label) {



            switch (label.toUpperCase()) {



                case 'LIGHT':



                    return '#dc0c15';



                case 'DARK':



                    return '#400000';



                case 'BLACK':



                    return '#000000';



                default:



                    return 'rgba(54, 162, 235, 0.6)'; // Default blue



            }



        }



        // Colors for Distribución de Firmezas (ctxFirmDist) by label



        function getFirmDistBarColor(label) {



            const key = String(label || '').toUpperCase();



            if (key === 'BLACK') return '#000000';



            if (key === 'DARK') return '#71160e';



            if (key === 'LIGHT') return '#dc0c15';



            if (key === 'FRUTA BLANDA') return 'rgba(255, 99, 132, 0.6)';



            return '#666666';



        }



        function generateHtmlLegend(chart, legendContainerId, options = {}) {



            const legendContainer = document.getElementById(legendContainerId);



            if (!legendContainer) return;



            const labels = chart?.data?.labels || [];



            const datasets = chart?.data?.datasets || [];



            if (!labels.length || !datasets.length) {



                legendContainer.innerHTML = '';



                return;



            }



            const skipSet = new Set((options.skipLabels || []).map(label => String(label).toLowerCase()));



            let html = '';



            labels.forEach((label, index) => {



                if (skipSet.has(String(label).toLowerCase())) return;



                const dataset = datasets[0];



                const background = Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[index] : dataset



                    .backgroundColor;



                const rawValue = Array.isArray(dataset.data) ? dataset.data[index] : dataset.data;



                const value = Number(rawValue) || 0;



                const formattedValue = chart.config.type === 'bar' ? value.toFixed(1) : value.toFixed(1) + '%';



                html += `



                    <div class="legend-item">



                        <div class="legend-color-box" style="background-color:${background}"></div>



                        <span>${label}: ${formattedValue}</span>



                    </div>



                `;



            });



            legendContainer.innerHTML = html;



        }



        function generateDatasetLegend(chart, legendContainerId) {



            const legendContainer = document.getElementById(legendContainerId);



            if (!legendContainer) return;



            const datasets = chart.data.datasets;



            let html = '';



            datasets.forEach((dataset, index) => {



                const color = dataset.backgroundColor;



                const total = dataset.data.reduce((acc, val) => acc + val, 0);



                html += `



                    <div class="legend-item">



                        <div class="legend-color-box" style="background-color:${color}"></div>



                        <span>${dataset.label}: ${total.toFixed(1)}%</span>



                    </div>



                `;



            });



            legendContainer.innerHTML = html;



        }



        // Legend per dataset: shows dataset color and an aggregate value (average of data)



        function generateSeriesLegend(chart, legendContainerId, postfix = '') {



            const el = document.getElementById(legendContainerId);



            if (!el) return;



            const datasets = chart?.data?.datasets || [];



            let html = '';



            datasets.forEach(ds => {



                const vals = (ds.data || []).map(v => Number(v) || 0);



                const avg = vals.length ? (vals.reduce((a, b) => a + b, 0) / vals.length) : 0;



                const color = Array.isArray(ds.backgroundColor) ? ds.backgroundColor[0] : ds.backgroundColor;



                html += `



                    <div class="legend-item">



                        <div class="legend-color-box" style="background-color:${color}"></div>



                        <span>${ds.label}: ${avg.toFixed(1)}${postfix}</span>



                    </div>



                `;



            });



            el.innerHTML = html;



        }



        function generateCalibreLegend(labels, counts, legendContainerId) {

            const legendContainer = document.getElementById(legendContainerId);
            if (!legendContainer) return;

            const safeLabels = Array.isArray(labels) ? labels : [];
            const safeCounts = Array.isArray(counts) ? counts : [];

            if (!safeLabels.length) {
                legendContainer.innerHTML = '';
                return;
            }

            const percentFormatter = (typeof Intl !== 'undefined')
                ? new Intl.NumberFormat('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 1 })
                : null;
            const total = safeCounts.reduce((acc, value) => acc + (Number(value) || 0), 0);
            let html = '';

            safeLabels.forEach((label, index) => {
                const rawValue = Number(safeCounts[index]) || 0;
                const percentValue = total > 0 ? (rawValue / total) * 100 : 0;
                const formattedPercent = percentFormatter
                    ? percentFormatter.format(percentValue)
                    : percentValue.toFixed(percentValue % 1 === 0 ? 0 : 1);

                html += `
                    <div class="legend-item">
                        <span>${label}: ${formattedPercent}%</span>
                    </div>
                `;
            });

            legendContainer.innerHTML = html;
        }



        // Legend for stacked charts (compute share from absolute counts)



        function generateStackedLegendFromCounts(chart, legendContainerId) {



            const legendContainer = document.getElementById(legendContainerId);



            if (!legendContainer) return;



            const datasets = chart.data.datasets || [];



            const countsSeries = (chart.options && chart.options.countsSeries) ? chart.options.countsSeries : [];



            const sums = datasets.map((ds, i) => {



                const arr = (countsSeries[i] && countsSeries[i].data) ? countsSeries[i].data : [];



                const total = arr.reduce((acc, v) => acc + (Number(v) || 0), 0);



                const color = Array.isArray(ds.backgroundColor) ? ds.backgroundColor[0] : ds.backgroundColor;



                console.log(color);



                return {



                    label: ds.label || `Serie ${i+1}`,



                    total,



                    color



                };



            });



            const grand = sums.reduce((acc, it) => acc + it.total, 0) || 0;



            let html = '';



            sums.forEach(it => {



                const pct = grand > 0 ? (it.total / grand) * 100 : 0;



                html += `



                    <div class="legend-item">



                        <div class="legend-color-box" style="background-color:${it.color}"></div>



                        <span>${it.label}: ${pct.toFixed(1)}% (${it.total})</span>



                    </div>



                `;



            });



            legendContainer.innerHTML = html;



        }



        document.addEventListener('DOMContentLoaded', function() {



            const doughnutCenterTextPlugin = {



                id: 'doughnutCenterText',



                afterDraw(chart) {



                    const centerText = chart?.config?.options?.plugins?.centerText;



                    if (!centerText?.text) return;



                    const {



                        chartArea,



                        ctx



                    } = chart;



                    if (!chartArea) return;



                    const x = (chartArea.left + chartArea.right) / 2;



                    const y = (chartArea.top + chartArea.bottom) / 2 + (centerText.offsetY || 0);



                    ctx.save();



                    ctx.font = centerText.font || 'bold 16px sans-serif';



                    ctx.fillStyle = centerText.color || '#111827';



                    ctx.textAlign = 'center';



                    ctx.textBaseline = 'middle';



                    ctx.fillText(centerText.text, x, y);



                    ctx.restore();



                }



            };



            Chart.register(ChartDataLabels, doughnutCenterTextPlugin);



            // Exportable Pie Chart



            const ctx = document.getElementById('exportable-pie-chart-canvas');



            if (ctx) {



                const exportable = {{ $porcentaje_exportable }};



                const defectosCalidad = {{ $defectos_calidad_sum }};



                const defectosCondicion = {{ $defectos_condicion_sum }};



                const danosPlaga = {{ $danos_plaga_sum }};



                const species = "{{ $recepcion->n_especie }}";



                const precalibre = Number(@json($precalibrePercentage));



                const exportableAdjusted = Math.max(exportable - precalibre, 0);



                const colors = getChartColors(species);



                const doughnutData = [



                    exportableAdjusted,



                    defectosCalidad,



                    defectosCondicion,



                    danosPlaga,



                    precalibre



                ];



                const exportableChart = new Chart(ctx, {



                    type: 'doughnut',



                    data: {



                        labels: ['Exportable', 'Defectos de Calidad', 'Defectos de Condición',



                            'Daños de Plaga', 'Precalibre'



                        ],



                        datasets: [{



                            data: doughnutData,



                            backgroundColor: [



                                colors.exportable,



                                colors.defectosCalidad,



                                colors.defectosCondicion,



                                colors.danosPlaga,



                                colors.precalibre



                            ],



                            borderColor: colors.borderColor



                        }]



                    },



                    options: {



                        responsive: true,



                        maintainAspectRatio: false,



                        animation: false,



                        plugins: {



                            legend: {



                                display: false



                            },



                            datalabels: {



                                display: false



                            },



                            title: {



                                display: true,



                                text: 'Resumen Recepción',



                                font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                     family: 'Sans-Serif',

                                },



                            },



                            centerText: {



                                text: `${exportableAdjusted.toFixed(1)}%`,



                                color: '#111827',



                                font: 'bold 14px "Roboto", sans-serif'



                            }



                        }



                    }



                });



                generateHtmlLegend(exportableChart, 'exportable-legend', {



                    skipLabels: ['Exportable']



                });



            }



            // Calibre Distribution Chart



            const ctxCalibre = document.getElementById('calibre-bar-chart-canvas');



            if (ctxCalibre) {



                const species = "{{ $recepcion->n_especie }}";



                const colors = (species == "Cherries") ? cherryCoverageColorsMap : getChartColors(species);



                @if ($recepcion->n_especie === 'Cherries')



                    const calibreCategories = @json($ch_calibre_categories ?? []);



                    const calibreSeries = @json($ch_calibre_series ?? []);



                    const calibreCountsSeries = @json($ch_calibre_counts_series ?? []);



                    // Totales por calibre para incluir en etiquetas del eje X



                    const totalsByCalibre = (calibreCountsSeries || []).reduce((acc, serie) => {



                        (serie.data || []).forEach((v, i) => {



                            acc[i] = (acc[i] || 0) + (Number(v) || 0);



                        });



                        return acc;



                    }, []);



                    const labelsWithTotals = (calibreCategories || []).map((cat, i) => {



                        const num = totalsByCalibre[i] ?? 0;



                        const formatted = (typeof Intl !== 'undefined') ? new Intl.NumberFormat('es-CL')



                            .format(num) : String(num);



                        return `${cat}`;



                    });



                    // Series por color: mantener colores por nombre de color (original correcto)



                    const calibreDatasets = (calibreCountsSeries || []).map((serie) => ({



                        label: serie.name,



                        data: serie.data,



                        backgroundColor: cherryCoverageColorsMap[(serie.name || '').toUpperCase()],



                    }));



                    const calibreChart = new Chart(ctxCalibre, {



                        type: 'bar',



                        data: {



                            labels: labelsWithTotals,



                            datasets: calibreDatasets



                        },



                        plotOptions: {



                            bar: {



                                horizontal: false,



                                columnWidth: '35%',



                                endingShape: 'rounded'



                            }



                        },



                        options: {



                            responsive: true,



                            maintainAspectRatio: false,



                            animation: false,



                            stacked: false,



                            plugins: {



                                legend: {



                                    display: false



                                },



                                datalabels: {



                                    display: false,



                                    color: '#FFF',



                                    font: {



                                        size: 7,



                                        weight: 'bold'



                                    },



                                    align: 'end',



                                    anchor: 'end',



                                    formatter: (val, ctx) => {



                                        const si = ctx.datasetIndex;



                                        const di = ctx.dataIndex;



                                        const abs = (calibreSeries && calibreSeries[si] &&



                                                calibreSeries[si].data) ?



                                            calibreCountsSeries[si].data[di] : null;



                                        const pct = Number(val).toFixed(1);



                                        return abs !== null ? `${pct}% ` : `${pct}%`;



                                    }



                                },



                                title: {



                                    display: true,



                                    text: '% de Distribución de Calibre',
                                    font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                     family: 'Sans-Serif',

                                },



                                }



                            },



                            scales: {



                                y: {



                                    beginAtZero: true,



                                    stacked: true,



                                    title: {



                                        display: true,



                                        text: 'Porcentaje (%)',
                                        font: {

                                        size: 10,

                                        weight: 'bold',

                                        color: '#333',

                                         family: 'Sans-Serif',
                                        }








                                    },



                                    grid: {



                                        display: false,



                                        drawBorder: false



                                    },



                                    ticks: {



                                        font: {



                                            size: 8,
                                            family:'Sans-Serif',



                                        },



                                        maxRotation: 0,



                                        minRotation: 0,



                                        autoSkip: false



                                    }



                                },



                                x: {



                                    stacked: true,



                                    grid: {



                                        display: false,



                                        drawBorder: false



                                    },



                                    ticks: {



                                        font: {



                                            size: 8,
                                            family:'Sans-Serif',



                                        },



                                        maxRotation: 0,



                                        minRotation: 0,



                                        autoSkip: false



                                    },



                                    title: {



                                        display: true,



                                        text: 'Calibre',



                                       font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Roboto',

                                },



                                    }



                                }



                            }



                        }



                    });



                    // Use absolute counts to compute overall legend percentages by series



                    generateCalibreLegend(calibreCategories, totalsByCalibre, 'calibre-legend');



                    // Oculta fila de totales bajo el gráfico si existiera



                    (function hideCalibreFooter() {



                        const el = document.getElementById('calibre-values');



                        if (el) el.style.display = 'none';



                    })();



                @else



                    const sizeDistributionSimple = @json($sizeDistribution);



                    const labels = (sizeDistributionSimple || []).map(item => item.calibre);



                    const data = (sizeDistributionSimple || []).map(item => item.count);



                    // Incluir totales en las etiquetas del eje X



                    const labelsWithTotals = (labels || []).map((cat, i) => {



                        const num = data[i] ?? 0;



                        const formatted = (typeof Intl !== 'undefined') ? new Intl.NumberFormat('es-CL')



                            .format(num) : String(num);



                        return `${cat} (${formatted})`;



                    });



                    const calibreChart = new Chart(ctxCalibre, {



                        type: 'bar',



                        data: {



                            labels: labelsWithTotals,



                            datasets: [{



                                label: '% de Calibres',



                                data: data,



                                backgroundColor: colors.exportable



                            }]



                        },



                        options: {



                            responsive: true,



                            maintainAspectRatio: false,



                            animation: false,



                            plugins: {



                                legend: {



                                    display: false



                                },



                                datalabels: {



                                    display: false



                                },



                                title: {



                                    display: true,



                                    text: 'Distribución de Calibres por Color',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Roboto',

                                },



                                }



                            },



                            scales: {



                                y: {



                                    beginAtZero: true,



                                    title: {



                                        display: true,



                                        text: 'Cantidad',
                                         font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                    },



                                    grid: {



                                        display: false,



                                        drawBorder: false



                                    },



                                    ticks: {



                                        font: {



                                            size: 8,
                                            family:'Sans-Serif',



                                        },



                                        maxRotation: 0,



                                        minRotation: 0,



                                        autoSkip: false



                                    }



                                },



                                x: {



                                    grid: {



                                        display: false,



                                        drawBorder: false



                                    },



                                    ticks: {



                                        font: {



                                            size: 8,
                                            family:'Sans-Serif',



                                        },



                                        maxRotation: 45,



                                        minRotation: 0,



                                        autoSkip: false



                                    },



                                    title: {



                                        display: true,



                                        text: 'Calibre',
                                         font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                    }



                                }



                            }



                        }



                    });



                    generateCalibreLegend(labels, data, 'calibre-legend');



                    // Oculta fila de totales bajo el gráfico si existiera



                    (function hideCalibreFooter() {



                        const el = document.getElementById('calibre-values');



                        if (el) el.style.display = 'none';



                    })();



                @endif



            }



            // Color Distribution Chart



            const speciesForColorChart = "{{ $recepcion->n_especie }}";



            if (speciesForColorChart !== 'Cherries') {

                const ctxColor = document.getElementById('color-pie-chart-canvas');



                if (ctxColor) {

                    const coverageColor = @json($coverageColor);

                    const distribucionColor = (coverageColor || []);

                    const labels = distribucionColor.map(item => item.color);

                    const data = distribucionColor.map(item => item.percentage);

                    const labelsWithValues = (labels || []).map((name, i) =>

                        `${name} (${Number(data[i] || 0).toFixed(0)}%)`

                    );

                    const backgroundColors = ['#FF9999', '#FF0000', '#D60000', '#960000', '#640000', '#000000'];

                    const colorChart = new Chart(ctxColor, {

                        type: 'pie',

                        data: {

                            labels: labelsWithValues,

                            datasets: [{

                                label: '% de Color',

                                data: data,

                                backgroundColor: backgroundColors

                            }]

                        },

                        options: {

                            responsive: true,

                            maintainAspectRatio: false,

                            animation: false,

                            plugins: {

                                legend: {

                                    display: true,

                                    position: 'bottom',

                                    labels: {

                                        font: {

                                            size: 8

                                        }

                                    }

                                },

                                datalabels: {

                                    display: false

                                },

                                title: {

                                    display: true,

                                    text: '% de Distribución de Color',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },

                                }

                            }

                        }

                    });

                    generateDatasetLegend(colorChart, 'color-legend');

                }

            }

            // Promedio de Firmezas Bar Chart



            const ctxFirmezas = document.getElementById('firmezas-bar-chart-canvas');



            if (ctxFirmezas) {



                const avgFirm = @json($averageFirmness);



                const labels = (avgFirm && avgFirm.categories) ? avgFirm.categories : [];



                const datasets = (avgFirm && avgFirm.series) ? avgFirm.series.map(s => ({



                    label: s.name,



                    data: s.data,



                    backgroundColor: getFirmezaBrixColors(s.name)



                })) : [];



                const firmezasChart = new Chart(ctxFirmezas, {



                    type: 'bar',



                    data: {



                        labels: labels,



                        datasets: datasets



                    },



                    options: {



                        responsive: true,



                        maintainAspectRatio: false,



                        animation: false,



                        plugins: {



                            legend: {



                                display: false



                            },



                            datalabels: {



                                display: true,



                                color: '#000000',



                                align: 'end',



                                anchor: 'end',



                                offset: -4,



                                font: {



                                    weight: 'bold',
                                    size: 8



                                },



                                formatter: (val) => Number(val).toFixed(1)



                            },



                            title: {



                                display: true,



                                text: '% Distribución de Firmezas por Segregación de Color',
                                  font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                            }



                        },



                        scales: {



                            y: {



                                beginAtZero: true,



                                title: {



                                    display: true,



                                    text: 'Promedio',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                },



                                grid: {



                                    display: false,



                                    drawBorder: false



                                },



                                ticks: {



                                    font: {



                                        size: 8,
                                        family:'Sans-Serif',



                                    },



                                    maxRotation: 0,



                                    minRotation: 0,



                                    autoSkip: false



                                }



                            },



                            x: {



                                title: {



                                    display: true,



                                    text: 'Color',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                },



                                grid: {



                                    display: false,



                                    drawBorder: false



                                },



                                ticks: {



                                    font: {



                                        size: 8,
                                        family:'Sans-Serif',



                                    },



                                    maxRotation: 0,



                                    minRotation: 0,



                                    autoSkip: false



                                }



                            }



                        }



                    }



                });



                // Show legend by dataset (Light/Dark/Black) with average values



                // generateSeriesLegend(firmezasChart, 'firmezas-legend', '');



            }



            // Promedio de Brix Bar Chart



            const ctxBrix = document.getElementById('brix-bar-chart-canvas');



            if (ctxBrix) {



                const solubleSolids = @json($solubleSolids);



                const labels = (solubleSolids || []).map(item => item.size);



                const data = (solubleSolids || []).map(item => item.avg_brix);



                const backgroundColors = labels.map(label => getFirmezaBrixColors(label));



                const brixChart = new Chart(ctxBrix, {



                    type: 'bar',



                    data: {



                        labels: labels,



                        datasets: [{



                            label: 'Promedio de Brix',



                            data: data,



                            backgroundColor: backgroundColors,



                        }]



                    },



                    options: {



                        responsive: true,



                        maintainAspectRatio: false,



                        animation: false,



                        plugins: {



                            legend: {



                                display: false



                            },



                            datalabels: {



                                display: true,



                                color: '#000000',



                                align: 'end',



                                anchor: 'end',



                                offset: -4,



                                font: {



                                    weight: 'bold',
                                    size: 8



                                },



                                formatter: (val) => Number(val).toFixed(1)



                            },



                            title: {



                                display: true,



                                text: 'Promedio de Brix',
                                 font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                            }



                        },



                        scales: {



                            y: {



                                beginAtZero: true,



                                title: {



                                    display: true,



                                    text: 'Promedio',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                },



                                grid: {



                                    display: false,



                                    drawBorder: false



                                },



                                ticks: {



                                    font: {



                                        size: 8,
                                        family:'Sans-Serif',



                                    },



                                    maxRotation: 0,



                                    minRotation: 0,



                                    autoSkip: false



                                }



                            },



                            x: {



                                title: {



                                    display: true,



                                    text: 'Color',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                },



                                grid: {



                                    display: false,



                                    drawBorder: false



                                },



                                ticks: {



                                    font: {



                                        size: 8,
                                        family:'Sans-Serif',



                                    },



                                    maxRotation: 0,



                                    minRotation: 0,



                                    autoSkip: false



                                }



                            }



                        }



                    }



                });



                generateHtmlLegend(brixChart, 'brix-legend');



            }



            @php



                $categories = [];



                $series = [];



            @endphp



            @if ($recepcion->calidad->detalles)



                @if ($recepcion->n_variedad == 'Dagen')



                    @foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA') as $detalle)



                        @php



                            $categories[] = $detalle->detalle_item;



                            $series[] = $detalle->porcentaje_muestra;



                        @endphp



                    @endforeach



                @else



                    @foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->where('detalle_item', 'LIGHT') as $detalle)



                        @php



                            $l[] = $detalle->valor_ss;



                        @endphp



                    @endforeach



                    @foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->where('detalle_item', 'DARK') as $detalle)



                        @php



                            $d[] = $detalle->valor_ss;



                        @endphp



                    @endforeach



                    @foreach ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE FIRMEZA')->where('detalle_item', 'BLACK') as $detalle)



                        @php



                            $b[] = $detalle->valor_ss;



                        @endphp



                    @endforeach



                @endif



            @endif



            @if ($recepcion->n_especie == 'Cherries')



                @php



                    $colors = ['#2b1d16', '#71160e', '#dc0c15'];



                @endphp



            @elseif ($recepcion->n_especie == 'Apples')



                @php



                    $colors = ['#831816'];



                @endphp



            @elseif ($recepcion->n_especie == 'Pears')



                @php



                    $colors = ['#788527'];



                @endphp



            @elseif ($recepcion->n_variedad == 'Dagen')



                @php



                    $colors = ['#9817BB'];



                @endphp



            @else



                @php



                    $colors = ['#24a745'];



                @endphp



            @endif



            // Distribución de Firmezas (simple)



            const ctxFirmDist = document.getElementById('firmeza-distribucion-chart-canvas');



            if (ctxFirmDist) {



                const firmDist = @json($firmnessDistribution);



                const firmDistFiltered = (firmDist || []).filter(x => String(x.reading_name || '').toUpperCase() !==



                    'FRUTA BLANDA');



                const labels = firmDistFiltered.map(x => x.reading_name);



                const data = firmDistFiltered.map(x => x.avg_firmness);



                const barColors = labels.map(l => getFirmDistBarColor(l));



                const firmezaDistChart = new Chart(ctxFirmDist, {



                    type: 'bar',



                    data: {



                        labels: labels,



                        datasets: [{



                            label: 'Firmeza',



                            data: data,



                            backgroundColor: barColors



                        }]



                    },



                    options: {



                        responsive: true,



                        maintainAspectRatio: false,



                        animation: false,



                        plugins: {



                            legend: {



                                display: false



                            },



                            title: {



                                display: true,



                                text: 'Promedio de Firmezas',
                                 font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                            },



                            datalabels: {



                                display: true,



                                color: '#000000',



                                align: 'end',



                                anchor: 'end',



                                offset: -4,



                                font: {



                                    weight: 'bold',
                                    size: 8



                                },



                                formatter: (val) => Number(val).toFixed(1)



                            }



                        },



                        scales: {



                            y: {



                                beginAtZero: true,



                                title: {



                                    display: true,



                                    text: 'Valor',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                },



                                grid: {



                                    display: false,



                                    drawBorder: false



                                },



                                ticks: {



                                    font: {



                                        size: 8,
                                        family:'Sans-Serif',



                                    },



                                    maxRotation: 0,



                                    minRotation: 0,



                                    autoSkip: false



                                }



                            },



                            x: {



                                title: {



                                    display: true,



                                    text: 'Color',
                                     font: {

                                    size: 10,

                                    weight: 'bold',

                                    color: '#333',

                                    family: 'Sans-Serif',

                                },



                                },



                                grid: {



                                    display: false,



                                    drawBorder: false



                                },



                                ticks: {



                                    font: {



                                        size: 8,
                                        family:'Sans-Serif',




                                    },



                                    maxRotation: 0,



                                    minRotation: 0,



                                    autoSkip: false



                                }



                            }



                        }



                    }



                });



                generateHtmlLegend(firmezaDistChart, 'firmeza-distribucion-legend');



            }



        });



    </script>



    <div class="main-header">



        <div class="chart-wrapper-resumen header-chart-left">



            <div class="chart-container">



                <div style="position: relative; height:130px; width:230px;">



                    <canvas id="exportable-pie-chart-canvas"></canvas>



                </div>



                <div id="exportable-legend" class="chart-legend"></div>



            </div>



        </div>



        <div class="main-header-info-columns" style="padding-left: 15px;">



            <div class="column">



                <p><strong>Exportadora:</strong> Greenex Spa</p>



                <p><strong>Productor:</strong> {{ $recepcion->n_emisor }}</p>



                <p><strong>Cuartel:</strong> GE001</p>



                <p><strong>CSG:</strong> {{ $recepcion->Codigo_Sag_emisor }}</p>



                <p><strong>Variedad:</strong> {{ $recepcion->n_variedad }}</p>



                <p><strong>Fecha/Hora recepción:</strong>



                    {{ \Carbon\Carbon::parse($recepcion->fecha_g_recepcion)->format('d/m/Y H:i') }}</p>



            </div>



            <div class="column">



                <p><strong>N° Lote:</strong> {{ $recepcion->numero_g_recepcion }}</p>



                <p><strong>T° Pulpa(°C):</strong> {{ $temperatura_pulpa ?? 'N/A' }}</p>



                <p><strong>Kilos Recibidos:</strong> {{ number_format($recepcion->peso_neto, 2, ',', '.') }}</p>



                <p><strong>N° Envases:</strong> {{ number_format($recepcion->cantidad, 0, ',', '.') }}</p>



                <p><strong>Seteo Camión:</strong> {{ $recepcion->seteo_camion ?? 'N/A' }}</p>



                <p><strong>Nota de Calidad:</strong> {{ $recepcion->nota_calidad ?? 'S/N' }}</p>



            </div>



        </div>



    </div>



    <div class="header-separator"></div>



    <div class="summary-section">



        <div class="chart-wrapper">



            <div class="chart-container">



                <div style="position: relative; height:150px; width:75%;">



                    <canvas id="calibre-bar-chart-canvas"></canvas>



                </div>



                <div id="calibre-legend" class="chart-legend"></div>



            </div>



        </div>



        <div class="chart-wrapper">



            <div class="chart-container">



                @if ($recepcion->n_especie === 'Cherries')

                    @php

                        $colorMatrixData = $coverageColor ?? [];

                        $colorCategories = $colorMatrixData['categories'] ?? [];

                        $countsSeries = $colorMatrixData['countsSeries'] ?? [];

                        $calibres = [];



                        foreach ($countsSeries as $serie) {

                            $name = $serie['name'] ?? '';

                            if ($name !== '' && !in_array($name, $calibres, true)) {

                                $calibres[] = $name;

                            }

                        }



                        $colorGroupMap = [

                            'ROJO' => ['code' => '2', 'label' => 'Rojo', 'group' => 'Light'],

                            'ROJO CAOBA' => ['code' => '3', 'label' => 'Rojo Caoba', 'group' => 'Dark'],

                            'SANTINA' => ['code' => '3.5', 'label' => 'Santina', 'group' => 'Dark'],

                            'CAOBA OSCURO' => ['code' => '4', 'label' => 'Caoba Oscuro', 'group' => 'Black'],

                            'NEGRO' => ['code' => '5', 'label' => 'Negro', 'group' => 'Black'],

                        ];



                        $matrixRows = [];

                        foreach ($colorCategories as $index => $colorName) {

                            $upper = mb_strtoupper($colorName ?? '', 'UTF-8');

                            $mapping = $colorGroupMap[$upper] ?? ['code' => $colorName, 'label' => $colorName, 'group' => 'Otros'];

                            $rowCounts = [];

                            foreach ($countsSeries as $serie) {

                                $calName = $serie['name'] ?? '';

                                if ($calName === '') {

                                    continue;

                                }

                                $serieData = $serie['data'] ?? [];

                                $rowCounts[$calName] = isset($serieData[$index]) ? (int) $serieData[$index] : 0;

                            }

                            $matrixRows[] = [

                                'group' => $mapping['group'],

                                'code' => $mapping['code'],

                                'label' => $mapping['label'],

                                'counts' => $rowCounts,

                                'total' => array_sum($rowCounts),

                            ];

                        }

                        $groupOrder = ['Light', 'Dark', 'Black', 'Otros'];



                        usort($matrixRows, function ($a, $b) use ($groupOrder) {

                            $groupPosA = array_search($a['group'], $groupOrder, true);

                            $groupPosB = array_search($b['group'], $groupOrder, true);



                            $groupPosA = $groupPosA === false ? PHP_INT_MAX : $groupPosA;

                            $groupPosB = $groupPosB === false ? PHP_INT_MAX : $groupPosB;



                            if ($groupPosA === $groupPosB) {

                                return strcmp((string) $a['code'], (string) $b['code']);

                            }



                            return $groupPosA <=> $groupPosB;

                        });



                        $columnTotals = [];



                        foreach ($matrixRows as $row) {

                            foreach ($row['counts'] as $calibre => $value) {

                                $columnTotals[$calibre] = ($columnTotals[$calibre] ?? 0) + $value;

                            }

                        }



                        $overallTotal = array_sum($columnTotals);



                        $columnPercentages = [];

                        if ($overallTotal > 0) {

                            foreach ($columnTotals as $calibre => $value) {

                                $columnPercentages[$calibre] = ($value / $overallTotal) * 100;

                            }

                        }

                        foreach ($matrixRows as $idx => $row) {

                            $percentages = [];

                            foreach ($calibres as $calibre) {

                                $countValue = $row['counts'][$calibre] ?? 0;

                                $percentages[$calibre] = $overallTotal > 0 ? ($countValue / $overallTotal) * 100 : 0;

                            }

                            $matrixRows[$idx]['percentages'] = $percentages;

                            $matrixRows[$idx]['total_percentage'] = $overallTotal > 0 ? ($row['total'] / $overallTotal) * 100 : 0;

                        }





                        $groupedRows = [];

                        foreach ($matrixRows as $row) {

                            $groupedRows[$row['group']][] = $row;

                        }

                        $groupTotals = [];

                        $groupTotalsPercent = [];



                        foreach ($groupedRows as $group => $rows) {

                            $groupTotal = array_sum(array_map(static function ($row) {

                                return $row['total'];

                            }, $rows));

                            $groupTotals[$group] = $groupTotal;

                            $groupTotalsPercent[$group] = $overallTotal > 0 ? ($groupTotal / $overallTotal) * 100 : 0;

                        }



                        $overallTotalPercent = $overallTotal > 0 ? 100 : 0;

                    @endphp



                    <div class="color-matrix-wrapper">

                        <p style="text-align: center;font-weight: 700;font-family:Sans-Serif;color:#666;font-size:10px">Distribuci&oacute;n de Colores por Calibre</p>

                        @if (!empty($calibres) && !empty($matrixRows))

                            <table class="color-calibre-matrix">

                                <thead>

                                    <tr>
                                        <th>Grupo</th>
                                        <th>Color</th>
                                        @foreach ($calibres as $calibre)
                                            <th style="padding: 1px;">{{ $calibre }}</th>
                                        @endforeach
                                        <th>Total (%)</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    @foreach ($groupOrder as $orderGroup)

                                        @if (!empty($groupedRows[$orderGroup]))

                                            @foreach ($groupedRows[$orderGroup] as $index => $row)

                                                <tr>

                                                    @if ($index === 0)

                                                        <td class="color-group-cell" rowspan="{{ count($groupedRows[$orderGroup]) }}">{{ $orderGroup }}</td>

                                                    @endif

                                                    <td>
                                                        <strong>{{ $row['code'] }}</strong>
                                                        {{-- <span>{{ $row['label'] }}</span> --}}

                                                    </td>


                                                    @foreach ($calibres as $calibre)

                                                        <td>{{ number_format($row['percentages'][$calibre] ?? 0, 1, ',', '.') }}%</td>

                                                    @endforeach

                                                    <td>{{ number_format($row['total_percentage'], 1, ',', '.') }}%</td>

                                                </tr>

                                            @endforeach

                                        @endif

                                    @endforeach

                                </tbody>

                                <tfoot>

                                    <tr>

                                        <th colspan="2">Total (%)</th>

                                        @foreach ($calibres as $calibre)

                                            <th>{{ number_format($columnPercentages[$calibre] ?? 0, 1, ',', '.') }}%</th>

                                        @endforeach

                                        <th>{{ number_format($overallTotalPercent, 1, ',', '.') }}%</th>

                                    </tr>

                                </tfoot>

                            </table>

                            <div class="color-matrix-summary">

                                @foreach ($groupOrder as $orderGroup)

                                    @if (!empty($groupTotals[$orderGroup]))

                                        <span><strong>{{ $orderGroup }}:</strong> {{ number_format($groupTotalsPercent[$orderGroup] ?? 0, 1, ',', '.') }}%</span>

                                    @endif

                                @endforeach

                            </div>

                        @else

                            <p class="color-matrix-empty">Sin datos de color disponibles.</p>

                        @endif

                    </div>

                @else

                    <div style="position: relative; height:150px; width:75%;">

                        <canvas id="color-pie-chart-canvas"></canvas>

                    </div>



                    <div id="color-legend" class="chart-legend"></div>

                @endif



            </div>



        </div>



        <div class="chart-wrapper">



            <div class="chart-container">



                <div style="position: relative; height:150px; width:75%;">



                    <canvas id="firmeza-distribucion-chart-canvas"></canvas>



                </div>



                <div id="firmeza-distribucion-legend" class="chart-legend"></div>



            </div>



        </div>



        <div class="chart-wrapper">



            <div class="chart-container">



                <div style="position: relative; height:150px; width:75%;">



                    <canvas id="brix-bar-chart-canvas"></canvas>



                </div>



                <div id="brix-legend" class="chart-legend"></div>



            </div>



        </div>



        <div class="chart-wrapper full-width-chart">



            <div class="chart-container">



                <div style="position: relative; height:140px; width:90%;">



                    <canvas id="firmezas-bar-chart-canvas"></canvas>



                </div>



                <div id="firmezas-legend" class="chart-legend"></div>



            </div>



        </div>



    </div>



    <div class="header-separator"></div>



    <div class="new-section-container">



        <div class="section-column">



            <h3>DEFECTOS DE CALIDAD</h3>



            <ul>



                @if (isset($recepcion->calidad->detalles))



                    @foreach ($recepcion->calidad->detalles as $detalle)



                        @if (



                            $detalle->tipo_item == 'DEFECTOS DE CALIDAD' &&



                                isset($detalle->porcentaje_muestra) &&



                                $detalle->porcentaje_muestra > 0)


                            @if($detalle->detalle_item!='PRECALIBRE')
                                <li>{{ $detalle->detalle_item }}: {{ $detalle->porcentaje_muestra }} %</li>
                            @endif



                        @endif



                    @endforeach



                @endif



            </ul>



            <b>TOTAL:{{ $defectos_calidad_sum }} %</b>



        </div>



        <div class="section-column">



            <h3>DEFECTOS DE CONDICION</h3>



            <ul>



                @if (isset($recepcion->calidad->detalles))



                    @foreach ($recepcion->calidad->detalles as $detalle)



                        @if ($detalle->tipo_item == 'DEFECTOS DE CONDICIÓN' && isset($detalle->detalle_item) && $detalle->detalle_item > 0)



                            @php
                                $detalleItem = $detalle->detalle_item ?? '';
                                $porcentajeMuestra = (float) ($detalle->porcentaje_muestra ?? 0);
                                $detalleLower = function_exists('mb_strtolower') ? mb_strtolower($detalleItem, 'UTF-8') : strtolower($detalleItem);
                                $tienePudricion = str_contains($detalleLower, 'pudrición') || str_contains($detalleLower, 'pudricion');
                            @endphp



                            <li>
                                @if ($tienePudricion && $porcentajeMuestra >= 2)
                                    <strong>{{ $detalleItem }}: {{ $detalle->porcentaje_muestra }} %</strong>
                                @else
                                    {{ $detalleItem }}: {{ $detalle->porcentaje_muestra }} %
                                @endif
                            </li>



                        @endif



                    @endforeach



                @endif



            </ul>



            <b>TOTAL:{{ $defectos_condicion_sum }} %</b>



        </div>



        <div class="section-column">



            <h3>DAÑOS DE PLAGA</h3>



            <ul>



                @php



                    $danos_plaga_sumfinal = 0;



                @endphp



                @if (isset($recepcion->calidad->detalles))



                    @foreach ($recepcion->calidad->detalles as $detalle)



                        @if ($detalle->tipo_item == 'DAÑO DE PLAGA' && isset($detalle->porcentaje_muestra) && $detalle->porcentaje_muestra > 0)



                            <li>{{ $detalle->detalle_item }}: {{ $detalle->porcentaje_muestra }} %</li>



                            @php $danos_plaga_sumfinal += $detalle->porcentaje_muestra; @endphp



                        @endif



                    @endforeach



                @endif



            </ul>



            <b>TOTAL:{{ $danos_plaga_sumfinal }} %</b>



        </div>



        <div class="section-column">



            <h3>CALIDAD de LLEGADA</h3>



            <ul>



                @if (isset($recepcion->calidad))



                    @php



                        $calidad_fields = [



                            'materia_vegetal' => 'Materia Vegetal',



                            'piedras' => 'Piedras',



                            'barro' => 'Barro',



                            'pedicelo_largo' => 'Pedicelo Largo',



                            'racimo' => 'Racimo',



                            'esponjas' => 'Esponjas',



                            'llenado_tottes' => 'Llenado Tottes',



                        ];



                    @endphp



                    @foreach ($calidad_fields as $field_key => $field_name)



                        @if (isset($recepcion->calidad->$field_key) && $recepcion->calidad->$field_key > 0)



                            <li>{{ $field_name }}: {{ $recepcion->calidad->$field_key }}</li>



                        @endif



                    @endforeach



                @endif



            </ul>



        </div>



    </div>



    <div class="new-section-container" style="margin-top: -18px;">



        <div class="section-column">



            <span>TOTAL DEFECTOS:{{ $danos_plaga_sumfinal + $defectos_calidad_sum + $defectos_condicion_sum }}</span>



        </div>



        {{-- <div class="section-column">



            <span>PRECALIBRE:</span>



            @if ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'PRECALIBRE')->first())



                {{ $recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'PRECALIBRE')->first()->porcentaje_muestra }}



                %



            @else



                -



            @endif



        </div> --}}



        {{-- <div class="section-column">



            <span>SOBRECALIBRE:</span>



            @if ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'SOBRECALIBRE')->first())



                {{ $recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'SOBRECALIBRE')->first()->porcentaje_muestra }}



                %



            @else



                -



            @endif



        </div> --}}



        {{--<div class="section-column">



             <span>FUERA DE COLOR:</span>



            @php



                $col = 0;



                $detallesColor =



                    optional(optional($recepcion->calidad)->detalles)->where('tipo_item', 'COLOR DE CUBRIMIENTO') ??



                    collect();



                if ($detallesColor->count() > 0) {



                    if ($recepcion->n_especie == 'Cherries') {



                        $col =



                            (float) optional($detallesColor->firstWhere('detalle_item', 'Fuera de Color'))->valor_ss ??



                            0;



                    } elseif ($recepcion->n_especie == 'Apples') {



                        // Pink Lady / Rossy Glo usan <40 además de <50



                        if (in_array($recepcion->n_variedad, ['Pink Lady', 'Rossy Glo'])) {



                            $col +=



                                (float) optional($detallesColor->firstWhere('detalle_item', '<40'))



                                    ->porcentaje_muestra ?? 0;



                        }



                        $col +=



                            (float) optional($detallesColor->firstWhere('detalle_item', '<50'))->porcentaje_muestra ??



                            0;



                    } elseif ($recepcion->n_especie == 'Mandarinas') {



                        $col =



                            (float) optional($detallesColor->firstWhere('detalle_item', '<30'))->porcentaje_muestra ??



                            0;



                    } elseif ($recepcion->n_especie == 'Membrillos') {



                        $col =



                            ((float) optional($detallesColor->firstWhere('detalle_item', '<7'))->porcentaje_muestra ??



                                0) +



                            ((float) optional($detallesColor->firstWhere('detalle_item', '>9'))->porcentaje_muestra ??



                                0);



                    } elseif ($recepcion->n_especie == 'Orange') {



                        $col =



                            (float) optional($detallesColor->firstWhere('detalle_item', '<30'))->porcentaje_muestra ??



                            0;



                    } elseif ($recepcion->n_especie == 'Pears') {



                        $col =



                            (float) optional($detallesColor->firstWhere('detalle_item', '<40'))->porcentaje_muestra ??



                            0;



                    }



                }



            @endphp



            @if ($col > 0)



                {{ $col }}%



            @else



                -



            @endif



        </div>--}}



    </div>



    <div class="observations-section">



        @if (isset($recepcion->calidad->obs_ext) && !empty($recepcion->calidad->obs_ext))



            <p> <b>OBSERVACIONES: </b> {{ $recepcion->calidad->obs_ext }}</p>



        @else



            <p>No hay observaciones adicionales.</p>



        @endif



    </div>



    @php
        $photos = collect($recepcion->calidad?->photos ?? []);
    @endphp

    @if ($photos->count())
        <div class="page-break"></div>
        <div class="photo-page">
            <h2 class="photo-title">Registro Fotográfico</h2>
            <div class="photo-grid">

                @foreach ($photos as $photo)

                    <div class="photo-card">
                        <img src="{{ $photo->inline_url ?? $photo->url }}" alt="{{ $photo->photoType->name ?? 'Fotografia' }}">
                        <div class="photo-info">
                            <strong>{{ $photo->photoType->name ?? 'Sin clasificacion' }}</strong>
                            @if (!empty($photo->observations))
                                <p>{{ $photo->observations }}</p>
                            @endif
                            <span>{{ $photo->url ?? 'url' }}</span>
                            <span>{{ $photo->inline_url ?? 'inlineurl' }}</span>
                            @if ($photo->created_at)
                                <span>{{ optional($photo->created_at)->format('d-m-Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</body>



</html>
