<!DOCTYPE html>



<html lang="es">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Informe de Recepción de {{ $recepcion->n_especie }}</title>



    @php
        // Defaults to avoid undefined variables when optional tables are absent
        $html_tabla_distribucion_calibre = $html_tabla_distribucion_calibre ?? '';
        $html_tabla_color = $html_tabla_color ?? '';
        $html_tabla_firmeza_grande = $html_tabla_firmeza_grande ?? '';
        $html_tabla_firmeza_mediana = $html_tabla_firmeza_mediana ?? '';
        $html_tabla_firmeza_pequena = $html_tabla_firmeza_pequena ?? '';
        $html_tabla_color_fondo = $html_tabla_color_fondo ?? '';
        $html_tabla_calibrix = $html_tabla_calibrix ?? '';
        $html_tabla_porc_firmeza = $html_tabla_porc_firmeza ?? '';
        $html_tabla_porcentaje_firmeza = $html_tabla_porcentaje_firmeza ?? '';
    @endphp

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');



        .page-break {
            page-break-before: always;
        }

        .photo-page {
            padding: 20px 10px;
        }

        .photo-title {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 16px;
            color: #2c3e50;
        }

        .photo-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .photo-card {
            width: calc(50% - 8px);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 2px 4px rgba(149, 157, 165, 0.2);
        }

        .photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-info {
            padding: 10px;
            font-size: 10px;
            color: #374151;
        }

        .photo-info strong {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .photo-info p {
            margin: 0;
        }

        .photo-info span {
            display: block;
            margin-top: 4px;
            font-size: 9px;
            color: #6b7280;
        }

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



            /* gap: 8px; */



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
        // Datos precomputados desde el controlador para evitar consultas en la vista.
        $ch_calibre_categories = $ch_calibre_categories ?? [];
        $ch_calibre_series = $ch_calibre_series ?? [];
        $ch_calibre_counts_series = $ch_calibre_counts_series ?? [];
        $ch_color_categories = $ch_color_categories ?? [];
        $ch_color_series = $ch_color_series ?? [];
        $ch_color_counts_series = $ch_color_counts_series ?? [];
    @endphp



    @php
        $normalizedSpecies = strtolower($recepcion->n_especie);
        if (strtolower($recepcion->n_variedad ?? '') === 'dagen') {
            $normalizedSpecies = 'plum';
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

    @if($recepcion->n_especie=='Cherries')
        @if($recepcion->nota_calidad<4)
            <img src="{{ asset('img/sellCC.png') }}" class="stamp-image">
        @elseif($recepcion->nota_calidad==4)
            <img src="{{ asset('img/sellObjetado.png') }}" class="stamp-image">
        @else
            <img src="{{ asset('img/sellRechazado.png') }}" class="stamp-image">
        @endif
    @endif
      @if($recepcion->n_especie!='Cherries')
        @if($recepcion->nota_calidad<3)
            <img src="{{ asset('img/sellCC.png') }}" class="stamp-image">
        @elseif($recepcion->nota_calidad==3)
            <img src="{{ asset('img/sellObjetado.png') }}" class="stamp-image">
        @else
            <img src="{{ asset('img/sellRechazado.png') }}" class="stamp-image">
        @endif
    @endif
    <div class="title">Informe de Recepción de {{ $recepcion->n_especie }}</div>



    <script>
        // Defaults to avoid undefined when controller doesn't pass datasets



        @php

            $sizeDistribution = $sizeDistribution ?? [];

            $coverageColor = $coverageColor ?? [];

            $averageFirmness = $averageFirmness ?? [];

            $firmnessDistribution = $firmnessDistribution ?? [];

            $solubleSolids = $solubleSolids ?? [];
            $hideLegacyFirmnessCharts = ($averageFirmness['mode'] ?? null) === 'lb_brix';

            $precalibrePercentage =
                (float) (optional(
                    optional(optional($recepcion->calidad)->detalles)
                        ->where('tipo_item', 'DEFECTOS DE CALIDAD')
                        ->where('detalle_item', 'PRECALIBRE')
                        ->first(),
                )->porcentaje_muestra ?? 0);

            //dd($coverageColor, $averageFirmness, $firmnessDistribution, $coverageColor,$sizeDistribution);

        @endphp

        const currentSpecies = "{{ $normalizedSpecies }}";

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



                case 'plums':

                    return {

                        exportable: '#b39cff',

                            defectosCalidad: '#8d74e6',

                            defectosCondicion: '#715cc0',

                            danosPlaga: '#5a4799',

                            precalibre: '#d7ccff',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'plum':

                    return {

                        exportable: '#b39cff',

                            defectosCalidad: '#8d74e6',

                            defectosCondicion: '#715cc0',

                            danosPlaga: '#5a4799',

                            precalibre: '#d7ccff',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'apples':

                    return {

                        exportable: '#7bd66a',

                            defectosCalidad: '#58b64c',

                            defectosCondicion: '#3e8d36',

                            danosPlaga: '#2f6c29',

                            precalibre: '#c4f2b8',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'apple':

                    return {

                        exportable: '#7bd66a',

                            defectosCalidad: '#58b64c',

                            defectosCondicion: '#3e8d36',

                            danosPlaga: '#2f6c29',

                            precalibre: '#c4f2b8',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'peaches':

                    return {

                        exportable: '#ffb980',

                            defectosCalidad: '#f59b56',

                            defectosCondicion: '#e07a2e',

                            danosPlaga: '#b85e1f',

                            precalibre: '#ffe0c2',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'peach':

                    return {

                        exportable: '#ffb980',

                            defectosCalidad: '#f59b56',

                            defectosCondicion: '#e07a2e',

                            danosPlaga: '#b85e1f',

                            precalibre: '#ffe0c2',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'nectarines':

                    return {

                        exportable: '#ff9b73',

                            defectosCalidad: '#f07a4c',

                            defectosCondicion: '#d3552b',

                            danosPlaga: '#a43a1c',

                            precalibre: '#ffd0bc',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'nectarine':

                    return {

                        exportable: '#ff9b73',

                            defectosCalidad: '#f07a4c',

                            defectosCondicion: '#d3552b',

                            danosPlaga: '#a43a1c',

                            precalibre: '#ffd0bc',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'pears':

                    return {

                        exportable: '#a7e16c',

                            defectosCalidad: '#86c452',

                            defectosCondicion: '#659a3a',

                            danosPlaga: '#4d792c',

                            precalibre: '#d7f5b6',

                            borderColor: 'rgba(255, 255, 255, 1)'

                    };

                case 'pear':

                    return {

                        exportable: '#a7e16c',

                            defectosCalidad: '#86c452',

                            defectosCondicion: '#659a3a',

                            danosPlaga: '#4d792c',

                            precalibre: '#d7f5b6',

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
            const paletteBySpecies = {
                'cherries': { LIGHT: '#dc0c15', DARK: '#400000', BLACK: '#000000', DEFAULT: '#0ea5e9' },
                'plums': { LIGHT: '#c7b5ff', DARK: '#8d74e6', BLACK: '#5a4799', DEFAULT: '#8d74e6' },
                'plum': { LIGHT: '#c7b5ff', DARK: '#8d74e6', BLACK: '#5a4799', DEFAULT: '#8d74e6' },
                'apples': { LIGHT: '#a2e7a1', DARK: '#4f9f4a', BLACK: '#2f6c29', DEFAULT: '#58b64c' },
                'apple': { LIGHT: '#a2e7a1', DARK: '#4f9f4a', BLACK: '#2f6c29', DEFAULT: '#58b64c' },
                'peaches': { LIGHT: '#ffd9b8', DARK: '#f59b56', BLACK: '#b85e1f', DEFAULT: '#f59b56' },
                'peach': { LIGHT: '#ffd9b8', DARK: '#f59b56', BLACK: '#b85e1f', DEFAULT: '#f59b56' },
                'nectarines': { LIGHT: '#ffc9b3', DARK: '#f07a4c', BLACK: '#a43a1c', DEFAULT: '#f07a4c' },
                'nectarine': { LIGHT: '#ffc9b3', DARK: '#f07a4c', BLACK: '#a43a1c', DEFAULT: '#f07a4c' },
                'pears': { LIGHT: '#d7f5b6', DARK: '#86c452', BLACK: '#4d792c', DEFAULT: '#86c452' },
                'pear': { LIGHT: '#d7f5b6', DARK: '#86c452', BLACK: '#4d792c', DEFAULT: '#86c452' },
                'default': { LIGHT: 'rgba(54, 162, 235, 0.6)', DARK: 'rgba(75, 85, 99, 0.6)', BLACK: '#111827', DEFAULT: 'rgba(54, 162, 235, 0.6)' },
            };

            // Tonos por especie para tamaños Grande/Mediano/Chico (variaciones del mismo color base)
            const firmnessShadesBySpecies = {
                'cherries': ['#fca5a5', '#ef4444', '#991b1b'],
                'plums': ['#e9d5ff', '#c4b5fd', '#7c3aed'],
                'plum': ['#e9d5ff', '#c4b5fd', '#7c3aed'],
                'apples': ['#c8f7c5', '#7bd47f', '#2f855a'],
                'apple': ['#c8f7c5', '#7bd47f', '#2f855a'],
                'peaches': ['#ffe0b2', '#ffb74d', '#f57c00'],
                'peach': ['#ffe0b2', '#ffb74d', '#f57c00'],
                'nectarines': ['#ffd4bf', '#ff9f68', '#d35425'],
                'nectarine': ['#ffd4bf', '#ff9f68', '#d35425'],
                'pears': ['#e8f5d9', '#b7e08a', '#6f9f3f'],
                'pear': ['#e8f5d9', '#b7e08a', '#6f9f3f'],
                'default': ['#bfdbfe', '#60a5fa', '#2563eb'],
            };

            const palette = paletteBySpecies[currentSpecies] || paletteBySpecies.default;
            const upper = (label || '').toUpperCase();

            if (['GRANDE', 'MEDIANO', 'CHICO'].includes(upper)) {
                const shades = firmnessShadesBySpecies[currentSpecies] || firmnessShadesBySpecies.default;
                const order = ['GRANDE', 'MEDIANO', 'CHICO'];
                return shades[order.indexOf(upper)] ?? palette.DEFAULT;
            }

            return palette[upper] || palette.DEFAULT;
        }
        function getColorFondoPalette(species, count) {
            const key = (species || '').toLowerCase();
            let base = ['#e5e7eb', '#cbd5e1', '#e2e8f0', '#f1f5f9'];

            if (key.includes('plum')) {
                base = ['#f1e9ff', '#e0d4ff', '#cebdf9', '#b9a3f0', '#a68ce6', '#9275dd'];
            } else if (key.includes('pear')) {
                base = ['#e8f5e9', '#d6f0d8', '#c4e9c7', '#b3e3b6', '#a2dba5', '#8ed594'];
            } else if (key.includes('peach') || key.includes('nectarin')) {
                base = ['#ffe9dc', '#ffd9c2', '#ffc8a7', '#ffb88d', '#f7a775', '#e5965e'];
            } else if (key.includes('apple')) {
                base = ['#e8f5e9', '#d0ecd6', '#b9e3c3', '#a2daaf', '#8ad19c', '#73c888'];
            }

            if (base.length < count) {
                const last = base[base.length - 1];
                while (base.length < count) {
                    base.push(last);
                }
            }
            return base.slice(0, count);
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



                const formattedValue = chart.config.type === 'bar' ? value.toFixed(0) : value.toFixed(0) + '%';



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



                        <span>${dataset.label}: ${total.toFixed(0)}%</span>



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



                        <span>${ds.label}: ${avg.toFixed(0)}${postfix}</span>



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

            const percentFormatter = (typeof Intl !== 'undefined') ?
                new Intl.NumberFormat('es-CL', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1
                }) :
                null;
            const total = safeCounts.reduce((acc, value) => acc + (Number(value) || 0), 0);
            let html = '';

            safeLabels.forEach((label, index) => {
                const rawValue = Number(safeCounts[index]) || 0;
                const percentValue = total > 0 ? (rawValue / total) * 100 : 0;
                const formattedPercent = percentFormatter ?
                    percentFormatter.format(percentValue) :
                    percentValue.toFixed(percentValue % 1 === 0 ? 0 : 1);

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



                        <span>${it.label}: ${pct.toFixed(0)}% (${it.total})</span>



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
                const species = "{{ $normalizedSpecies }}";
                const precalibre = Number(@json($precalibrePercentage));
                const exportableAdjusted = Math.max(exportable, 0);
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



                                text: `${exportableAdjusted.toFixed(0)}%`,



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



                const species = "{{ $normalizedSpecies }}";



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



                                        const pct = Number(val).toFixed(0);



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
                                            family: 'Sans-Serif',



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
                                            family: 'Sans-Serif',



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



                        return `${cat}`;



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
                                    @if($recepcion->n_especie === 'Cherries')
                                    text:'Distribución de Calibres por Color',
                                    @else
                                    text: 'Distribución de Calibres',
                                    @endif
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
                                            family: 'Sans-Serif',



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
                                            family: 'Sans-Serif',



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



            const speciesForColorChart = "{{ $normalizedSpecies }}";



            if (speciesForColorChart !== 'Cherries') {
                const ctxColor = document.getElementById('color-pie-chart-canvas');

                if (ctxColor) {
                    const coverageColor = @json($coverageColor);
                    const distribucionColor = (coverageColor || []);
                    const labels = distribucionColor.map(item => item.color || 'N/A');
                    const data = distribucionColor.map(item => Number(item.percentage) || 0);
                    console.log('Color Distribution Data:', { labels, data });
                    // Derivar paleta a partir del color de especie (misma lógica que otros gráficos)
                    const speciesPalette = @json($colors ?? []);
                    const baseColors = ['#FF9999', '#FF0000', '#D60000', '#960000', '#640000', '#000000', '#4B5563'];
                    const normalizeHex = (c) => {
                        if (typeof c !== 'string') return null;
                        const v = c.trim();
                        return v.startsWith('#') ? v : null;
                    };
                    const toHsl = (hex) => {
                        const r = parseInt(hex.substr(1,2),16)/255;
                        const g = parseInt(hex.substr(3,2),16)/255;
                        const b = parseInt(hex.substr(5,2),16)/255;
                        const max = Math.max(r,g,b), min = Math.min(r,g,b);
                        let h, s, l = (max + min) / 2;
                        if(max === min){ h = s = 0; } else {
                            const d = max - min;
                            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                            switch(max){
                                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                                case g: h = (b - r) / d + 2; break;
                                case b: h = (r - g) / d + 4; break;
                            }
                            h /= 6;
                        }
                        return {h, s, l};
                    };
                    const fromHsl = ({h,s,l}) => {
                        let r, g, b;
                        if(s === 0){
                            r = g = b = l; // achromatic
                        } else {
                            const hue2rgb = (p, q, t) => {
                                if(t < 0) t += 1;
                                if(t > 1) t -= 1;
                                if(t < 1/6) return p + (q - p) * 6 * t;
                                if(t < 1/2) return q;
                                if(t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                                return p;
                            };
                            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                            const p = 2 * l - q;
                            r = hue2rgb(p, q, h + 1/3);
                            g = hue2rgb(p, q, h);
                            b = hue2rgb(p, q, h - 1/3);
                        }
                        const toHex = (x) => {
                            const v = Math.round(x * 255).toString(16).padStart(2, '0');
                            return v;
                        };
                        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
                    };
                    const buildPalette = () => {
                        const hexColors = Array.isArray(speciesPalette) ? speciesPalette.map(normalizeHex).filter(Boolean) : [];
                        if (hexColors.length === 0) return baseColors;
                        const base = hexColors[0];
                        const hsl = toHsl(base);
                        const shifts = [-0.15, -0.05, 0, 0.05, 0.1, 0.15, 0.2];
                        return shifts.map(delta => {
                            const l = Math.max(0, Math.min(1, hsl.l + delta));
                            return fromHsl({h: hsl.h, s: hsl.s, l});
                        });
                    };
                    //const palette = buildPalette();
                    const palette = getColorFondoPalette(@json($normalizedSpecies), labels.length);
                    const backgroundColors = labels.map((_, idx) => palette[idx % palette.length]);
                    const colorChart = new Chart(ctxColor, {
                        type: 'pie',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: '% de Color',
                                data: data,
                                backgroundColor: palette
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
                                    color: '#111827',
                                    formatter: (value, context) => {
                                        const label = context.chart?.data?.labels?.[context.dataIndex] || '';
                                        return `(${Number(value).toFixed(0)}%)`;
                                    },
                                    font: {
                                        size: 8,
                                        weight: 'bold'
                                    }
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

                    generateHtmlLegend(colorChart, 'color-legend');

                }

            }

            // Promedio de Firmezas Bar Chart

            const hideLegacyFirmnessCharts = @json($hideLegacyFirmnessCharts);


            const ctxFirmezas = document.getElementById('firmezas-bar-chart-canvas');



            if (ctxFirmezas) {



                const avgFirm = @json($averageFirmness);
                const isLbBrixMode = avgFirm && avgFirm.mode === 'lb_brix';
                const labels = (avgFirm && avgFirm.categories) ? avgFirm.categories : [];
                const datasets = (avgFirm && avgFirm.series) ? avgFirm.series.map(s => ({
                    label: s.name,
                    data: s.data,
                    backgroundColor: getFirmezaBrixColors(s.name)
                })) : [];
                const chartTitle = isLbBrixMode ? 'Firmezas (lb) y BRIX' : '% Distribución de Firmezas por Segregación de Color';
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
                                display: true
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
                                formatter: (val) => Number(val).toFixed(0)
                            },
                            title: {
                                display: true,
                                text: chartTitle,
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
                                    text: isLbBrixMode ? 'Lectura (lb / BRIX)' : 'Promedio',
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
                                        family: 'Sans-Serif',
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
                                        family: 'Sans-Serif',
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



            if (!hideLegacyFirmnessCharts && ctxBrix) {



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



                                formatter: (val) => Number(val).toFixed(0)



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
                                        family: 'Sans-Serif',



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
                                        family: 'Sans-Serif',



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



            // Color de Fondo Chart

            const ctxColorFondo = document.getElementById('color-fondo-chart-canvas');
            const colorFondoData = @json($colorFondo);
            let ColorFondoChart;

            if (ctxColorFondo && Array.isArray(colorFondoData) && colorFondoData.length) {
                const labels = colorFondoData.map(item => item.color || 'N/A');
                const data = colorFondoData.map(item => Number(item.percentage) || 0);
                const palette = getColorFondoPalette(@json($normalizedSpecies), labels.length);

                ColorFondoChart = new Chart(ctxColorFondo, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Color de Fondo',
                            data: data,
                            backgroundColor: palette,
                            borderColor: 'transparent',
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                display: false,
                                position: 'right',
                                labels: {
                                    boxWidth: 10,
                                    font: { size: 8 }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Distribución de Color de Fondo',
                                font: { size: 10, weight: 'bold', family: 'Sans-Serif' },
                            },
                            datalabels: {
                                display: true,
                                color: '#111827',
                                formatter: (val, ctx) => {
                                    const label = ctx.chart?.data?.labels?.[ctx.dataIndex] || '';
                                    return `(${val.toFixed(0)}%)`;
                                },
                                font: { size: 8, weight: 'bold' },
                            },
                        },
                        scales: {},
                    },
                });
            }
            if (ColorFondoChart) {
                generateHtmlLegend(ColorFondoChart, 'color-fondo-legend');
            }

                        // Distribución de Firmezas (simple)



            const ctxFirmDist = document.getElementById('firmeza-distribucion-chart-canvas');



            if (!hideLegacyFirmnessCharts && ctxFirmDist) {



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



                                formatter: (val) => Number(val).toFixed(0)



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
                                        family: 'Sans-Serif',



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
                                        family: 'Sans-Serif',




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




                <p><strong>Exportadora:</strong> {{ $recepcion->exportadora }}</p>




                <p><strong>Productor:</strong> {{ $recepcion->n_productor_rotulado }}</p>



                <p><strong>Cuartel:</strong> GE001</p>



                <p><strong>CSG:</strong> {{ $recepcion->csg_productor_rotulado }}</p>





                <p><strong>Variedad:</strong> {{ $recepcion->n_variedad }}</p>



                <p><strong>Fecha Recepción:</strong>



                    {{ \Carbon\Carbon::parse($recepcion->fecha_g_recepcion)->format('d/m/Y') }}</p>



            </div>



            <div class="column">



                <p><strong>N° Lote:</strong> {{ $recepcion->numero_g_recepcion }}</p>

                <p><strong>Guía:</strong> {{ $recepcion->numero_documento_recepcion }}</p>

                <p><strong>T° Pulpa(°C):</strong> {{ $temperatura_pulpa ?? 'N/A' }}</p>



                <p><strong>Kilos Recibidos:</strong> {{ number_format($recepcion->peso_neto, 2, ',', '.') }}</p>



                <p><strong>N° Envases:</strong> {{ number_format($recepcion->cantidad, 0, ',', '.') }}</p>



                <p><strong>Seteo Camión:</strong> {{ $seteo_termo ?? 'N/A' }}</p>



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

                            $mapping = $colorGroupMap[$upper] ?? [
                                'code' => $colorName,
                                'label' => $colorName,
                                'group' => 'Otros',
                            ];

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

                            $matrixRows[$idx]['total_percentage'] =
                                $overallTotal > 0 ? ($row['total'] / $overallTotal) * 100 : 0;
                        }

                        $groupedRows = [];

                        foreach ($matrixRows as $row) {
                            $groupedRows[$row['group']][] = $row;
                        }

                        $groupTotals = [];

                        $groupTotalsPercent = [];

                        foreach ($groupedRows as $group => $rows) {
                            $groupTotal = array_sum(
                                array_map(static function ($row) {
                                    return $row['total'];
                                }, $rows),
                            );

                            $groupTotals[$group] = $groupTotal;

                            $groupTotalsPercent[$group] = $overallTotal > 0 ? ($groupTotal / $overallTotal) * 100 : 0;
                        }

                        $overallTotalPercent = $overallTotal > 0 ? 100 : 0;

                    @endphp

                    <div class="color-matrix-wrapper">

                        <p style="text-align: center;font-weight: 700;font-family:Sans-Serif;color:#666;font-size:10px">
                            Distribuci&oacute;n de Colores por Calibre</p>

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
                                                        <td class="color-group-cell"
                                                            rowspan="{{ count($groupedRows[$orderGroup]) }}">
                                                            {{ $orderGroup }}</td>
                                                    @endif

                                                    <td>
                                                        <strong>{{ $row['code'] }}</strong>
                                                        {{-- <span>{{ $row['label'] }}</span> --}}

                                                    </td>


                                                    @foreach ($calibres as $calibre)
                                                        <td>{{ number_format($row['percentages'][$calibre] ?? 0, 1, ',', '.') }}%
                                                        </td>
                                                    @endforeach

                                                    <td>{{ number_format($row['total_percentage'], 1, ',', '.') }}%
                                                    </td>

                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach

                                </tbody>

                                <tfoot>

                                    <tr>

                                        <th colspan="2">Total (%)</th>

                                        @foreach ($calibres as $calibre)
                                            <th>{{ number_format($columnPercentages[$calibre] ?? 0, 1, ',', '.') }}%
                                            </th>
                                        @endforeach

                                        <th>{{ number_format($overallTotalPercent, 1, ',', '.') }}%</th>

                                    </tr>

                                </tfoot>

                            </table>

                            <div class="color-matrix-summary">

                                @foreach ($groupOrder as $orderGroup)
                                    @if (!empty($groupTotals[$orderGroup]))
                                        <span><strong>{{ $orderGroup }}:</strong>
                                            {{ number_format($groupTotalsPercent[$orderGroup] ?? 0, 1, ',', '.') }}%</span>
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



        @if (!empty($colorFondo) || !$hideLegacyFirmnessCharts)

            @if (!empty($colorFondo))
            <div class="chart-wrapper">
                <div class="chart-container">
                    <div style="position: relative; height:150px; width:80%;">
                        <canvas id="color-fondo-chart-canvas"></canvas>
                    </div>
                    <div id="color-fondo-legend" class="chart-legend"></div>
                </div>
            </div>
            @endif
{{--
            @if (!$hideLegacyFirmnessCharts)
            <div class="chart-wrapper" style="flex:1 1 320px;">
                <div class="chart-container">
                    <div style="position: relative; height:150px; width:100%;">
                        <canvas id="firmeza-distribucion-chart-canvas"></canvas>
                    </div>
                    <div id="firmeza-distribucion-legend" class="chart-legend"></div>
                </div>
            </div>

            <div class="chart-wrapper" style="flex:1 1 320px;">
                <div class="chart-container">
                    <div style="position: relative; height:150px; width:100%;">
                        <canvas id="brix-bar-chart-canvas"></canvas>
                    </div>
                    <div id="brix-legend" class="chart-legend"></div>
                </div>
            </div>
            @endif --}}

        @endif
         @if ($recepcion->n_especie === 'Cherries')
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
         @endif





         @if ($recepcion->n_especie === 'Cherries')
        <div class="chart-wrapper full-width-chart">
            <div class="chart-container">
                <div style="position: relative; height:150px; width:95%;">
                    <canvas id="firmezas-bar-chart-canvas"></canvas>
                </div>
                <div id="firmezas-legend" class="chart-legend"></div>
            </div>
        </div>
        @else
        <div class="chart-wrapper">
            <div class="chart-container">
                <div style="position: relative; height:150px; width:95%;">
                    <canvas id="firmezas-bar-chart-canvas"></canvas>
                </div>
                <div id="firmezas-legend" class="chart-legend"></div>
            </div>
        </div>
        @endif



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
                                @if ($detalle->detalle_item != 'PRECALIBRE')
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
                                $detalleLower = function_exists('mb_strtolower')
                                    ? mb_strtolower($detalleItem, 'UTF-8')
                                    : strtolower($detalleItem);
                                $tienePudricion =
                                    str_contains($detalleLower, 'pudrición') ||
                                    str_contains($detalleLower, 'pudricion');
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

                        if($recepcion->n_especie=='Cherries'){
                        $calidad_fields = [
                            'materia_vegetal' => 'Materia Vegetal',

                            'piedras' => 'Piedras',

                            'barro' => 'Barro',

                            'pedicelo_largo' => 'Pedicelo Largo',

                            'racimo' => 'Racimo',

                            'esponjas' => 'Esponjas',

                            'llenado_tottes' => 'Llenado Tottes',
                        ];
                        }
                        else{
                             $calidad_fields = [
                            'materia_vegetal' => 'Materia Vegetal',

                            'piedras' => 'Piedras',

                            'barro' => 'Barro',

                            'llenado_tottes' => 'Llenado Tottes',
                        ];
                        }
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



            <span>TOTAL
                DEFECTOS:{{ $danos_plaga_sumfinal + $defectos_calidad_sum + $defectos_condicion_sum + $precalibrePercentage }}</span>



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



        {{-- <div class="section-column">



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



        </div> --}}



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
            <h2 class="photo-title">Registro Fotográfico DE DEFECTOS</h2>
            <div class="photo-grid">

                @foreach ($photos as $photo)
                    <div class="photo-card">
                        <img src="{{ $photo->inline_url ?? $photo->url }}"
                            alt="{{ $photo->photoType->name ?? 'Fotografia' }}">
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
         @if(in_array($recepcion->id_emisor,[
                                            8557,
                                            8558,
                                            8559,
                                            8563,
                                            8564,
                                            8657,
                                            8561,
                                            8666,
                                            8881,
                                            8895,
                                            8897,
                                            8898,
                                            8899,
                                            8900,
                                            8901
                                            ]))
            <div class="page-break"></div>

                                    <div style="text-align: center; font-size: 12px; padding-left: 3px; padding-right: 3px;">
                                        <h3>Distribución de Calibres</h3>
                                        @php
                                        // if($html_tabla_distribucion_calibre != null){
                                        //      echo $html_tabla_distribucion_calibre;
                                        // }

                                        @endphp
                                        <h3>Distribución de Color</h3>
                                        @php
                                            echo $html_tabla_color;
                                        @endphp
                                         @if ($recepcion->n_especie != 'Cherries')
                                            <h3>Distribución de Firmeza Grande</h3>
                                            @php
                                                echo $html_tabla_firmeza_grande;
                                            @endphp
                                            <h3>Distribución de Firmeza Mediana</h3>
                                            @php
                                                echo $html_tabla_firmeza_mediana;
                                            @endphp
                                            <h3>Distribución de Firmeza Pequeña</h3>
                                            @php
                                                echo $html_tabla_firmeza_pequena;
                                            @endphp
                                            <h3>Distribución de Color Fondo</h3>
                                            @php
                                                echo $html_tabla_color_fondo;
                                            @endphp
                                        @endif
                                        <h3>Distribución de Calibrix</h3>
                                        @php
                                            echo $html_tabla_calibrix;
                                        @endphp
                                        <h3>Promedio de Firmeza</h3>
                                        @php
                                            echo $html_tabla_porc_firmeza;
                                        @endphp
                                        <h3>Distribución de Porcentaje de Firmeza</h3>
                                        @php
                                            echo $html_tabla_porcentaje_firmeza;
                                        @endphp

                                    </div>
                                </div>
                                @endif
</body>



</html>
