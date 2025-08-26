<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Recepción de {{ $recepcion->n_especie }}</title>
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');

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
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-header {
            display: flex;
            justify-content:flex-start; /* To push chart left and info columns right */
            align-items: center; /* Vertically align items */
            margin-bottom: 15px;
            padding-left: 10px; /* To make space for the absolute logo */
        }
        .main-header-info-columns { /* New wrapper for info columns */
            display: flex;
            gap: 30px; /* Original gap for info columns */
            /* margin-left: px; */
        }
        .header-chart-left { /* New style for chart in header, left-aligned */
            width: 25%; /* Smaller width for this chart */
             /* Push info columns to the right */
        }
        .header-logo { /* New style for logo, absolute positioning */
            position: absolute;
            top: 20px; /* Adjust as needed */
            left: 20px; /* Adjust as needed */
            width: 150px; /* Adjust as needed */
            height: auto;
            z-index: 1000; /* Ensure it's on top */
        }

        .header-separator {
            width: 95%;
            height: 2px;
            background-color: #f7922e;
            margin: 20px auto;
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
            font-size: 18px;
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
            width: calc(50%); /* Two columns with gap */
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
            width: calc(40%); /* Two columns with gap */
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            border-bottom: 2px solid #4CAF50;
            border-right: 2px solid #4CAF50;
            box-shadow: 2px 2px 8px rgba(0, 0, 0, 1.1);
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
        .chart-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 10px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .legend-color-box {
            width: 16px;
            height: 16px;
            border: 1px solid #eee;
            border-radius: 3px;
        }
        .stamp-image {
            position: absolute;
            top: 200px;
            left:45%;
            width: 150px;
            height: auto;
            opacity: 0.5;
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
            border-right: 1px solid #4CAF50; /* Separator between columns */
        }

        .section-column:last-child {
            border-right: none; /* No border on the last column */
        }

        .section-column h3 {
            font-size: 12px;
            color: #4CAF50; /* Green color for titles */
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
            font-size: 16px;
            color: #2c3e50;

            border-bottom: 1px solid #eee;

        }

        .observations-section p {
            font-size: 11px;
            color: #555;
            line-height: 0.8;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
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
        function getChartColors(species) {
            switch (species.toLowerCase()) {
                case 'cherries':
                    return {
                        exportable: 'rgba(255, 99, 132, 0.6)', // Red tone
                        defectosCalidad: 'rgba(200, 0, 0, 0.6)', // Darker red
                        defectosCondicion: 'rgba(150, 0, 0, 0.6)', // Even darker red
                        danosPlaga: 'rgba(100, 0, 0, 0.6)', // Darkest red
                        borderColor: 'rgba(255, 255, 255, 1)'
                    };
                case 'apples':
                    return {
                        exportable: 'rgba(75, 192, 192, 0.6)', // Green tone
                        defectosCalidad: 'rgba(0, 150, 0, 0.6)', // Darker green
                        defectosCondicion: 'rgba(0, 100, 0, 0.6)', // Even darker green
                        danosPlaga: 'rgba(0, 50, 0, 0.6)', // Darkest green
                        borderColor: 'rgba(255, 255, 255, 1)'
                    };
                case 'nectarines':
                    return {
                        exportable: 'rgba(255, 159, 64, 0.6)', // Orange tone
                        defectosCalidad: 'rgba(200, 100, 0, 0.6)', // Darker orange
                        defectosCondicion: 'rgba(150, 50, 0, 0.6)', // Even darker orange
                        danosPlaga: 'rgba(100, 25, 0, 0.6)', // Darkest orange
                        borderColor: 'rgba(255, 255, 255, 1)'
                    };
                default: // Default colors if species not matched
                    return {
                        exportable: 'rgba(54, 162, 235, 0.6)', // Blue
                        defectosCalidad: 'rgba(255, 206, 86, 0.6)', // Yellow
                        defectosCondicion: 'rgba(153, 102, 255, 0.6)', // Purple
                        danosPlaga: 'rgba(255, 99, 132, 0.6)', // Red
                        borderColor: 'rgba(255, 255, 255, 1)'
                    };
            }
        }

        function getFirmezaBrixColors(label) {
            switch (label.toUpperCase()) {
                case 'LIGHT':
                    return '#800000';
                case 'DARK':
                    return '#400000';
                case 'BLACK':
                    return '#000000';
                default:
                    return 'rgba(54, 162, 235, 0.6)'; // Default blue
            }
        }

        function generateHtmlLegend(chart, legendContainerId) {
            const legendContainer = document.getElementById(legendContainerId);
            if (!legendContainer) return;

            const { labels } = chart.data;
            const datasets = chart.data.datasets;

            let html = '';
            labels.forEach((label, index) => {
                const color = Array.isArray(datasets[0].backgroundColor) ? datasets[0].backgroundColor[index] : datasets[0].backgroundColor;
                const value = datasets[0].data[index];
                const formattedValue = chart.config.type === 'bar' ? value.toFixed(2) : value.toFixed(2) + '%';
                html += `
                    <div class="legend-item">
                        <div class="legend-color-box" style="background-color:${color}"></div>
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
                        <span>${dataset.label}: ${total.toFixed(2)}%</span>
                    </div>
                `;
            });

            legendContainer.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', function() {
            Chart.register(ChartDataLabels);

            // Exportable Pie Chart
            const ctx = document.getElementById('exportable-pie-chart-canvas');
            if (ctx) {
                const exportable = {{ $porcentaje_exportable }};
                const defectosCalidad = {{ $defectos_calidad_sum }};
                const defectosCondicion = {{ $defectos_condicion_sum }};
                const danosPlaga = {{ $danos_plaga_sum }};
                const species = "{{ $recepcion->n_especie }}";
                const colors = getChartColors(species);

                const exportableChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Exportable', 'Defectos de Calidad', 'Defectos de Condición', 'Daños de Plaga'],
                        datasets: [{
                            data: [exportable, defectosCalidad, defectosCondicion, danosPlaga],
                            backgroundColor: [colors.exportable, colors.defectosCalidad, colors.defectosCondicion, colors.danosPlaga],
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: { display: false },
                            title: { display: true, text: 'Resumen Recepción' }
                        }
                    }
                });
                generateHtmlLegend(exportableChart, 'exportable-legend');
            }

            // Calibre Distribution Bar Chart
            const ctxCalibre = document.getElementById('calibre-bar-chart-canvas');
            if (ctxCalibre) {
                const distribucionCalibres = @json($distribucion_calibres);
                const labels = distribucionCalibres.map(item => item.detalle_item);
                const data = distribucionCalibres.map(item => item.porcentaje_muestra);
                const species = "{{ $recepcion->n_especie }}";
                const colors = getChartColors(species);

                const calibreChart = new Chart(ctxCalibre, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '% de Calibres',
                            data: data,
                            backgroundColor: colors.exportable,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: { display: false },
                            title: { display: true, text: '% de Distribución de Calibres' }
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Porcentaje (%)' } },
                            x: { title: { display: true, text: 'Calibre' } }
                        }
                    }
                });
                generateHtmlLegend(calibreChart, 'calibre-legend');
            }

            // Color Distribution Pie Chart
            const ctxColor = document.getElementById('color-pie-chart-canvas');
            if (ctxColor) {
                const distribucionColor = @json($distribucion_color);
                const labels = distribucionColor.map(item => item.detalle_item);
                const data = distribucionColor.map(item => item.valor_ss);
                const backgroundColors = [
                    '#FF9999',
                    '#FF0000',
                    '#D60000',
                    '#960000',
                    '#640000',
                    '#000000'
                ];

                const colorChart = new Chart(ctxColor, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '% de Color',
                            data: data,
                            backgroundColor: backgroundColors,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: true, position: 'bottom' },
                            datalabels: { display: false },
                            title: { display: true, text: '% de Distribución de Color' }
                        }
                    }
                });
            }

            // Promedio de Firmezas Bar Chart
            const ctxFirmezas = document.getElementById('firmezas-bar-chart-canvas');
            if (ctxFirmezas) {
                const promedioFirmezas = @json($promedio_firmezas);
                const labels = promedioFirmezas.map(item => item.detalle_item);
                const data = promedioFirmezas.map(item => item.valor_ss);
                const backgroundColors = labels.map(label => getFirmezaBrixColors(label));

                const firmezasChart = new Chart(ctxFirmezas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Promedio de Firmeza',
                            data: data,
                            backgroundColor: backgroundColors,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: { display: false },
                            title: { display: true, text: 'Promedio de Firmezas' }
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Promedio' } },
                            x: { title: { display: true, text: 'Color' } }
                        }
                    }
                });
                generateHtmlLegend(firmezasChart, 'firmezas-legend');
            }

            // Promedio de Brix Bar Chart
            const ctxBrix = document.getElementById('brix-bar-chart-canvas');
            if (ctxBrix) {
                const promedioBrix = @json($promedio_brix);
                const labels = promedioBrix.map(item => item.detalle_item);
                const data = promedioBrix.map(item => item.valor_ss);
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
                            legend: { display: false },
                            datalabels: { display: false },
                            title: { display: true, text: 'Promedio de Brix' }
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Promedio' } },
                            x: { title: { display: true, text: 'Color' } }
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
            $colors = ['#dc0c15', '#71160e', '#2b1d16'];
        @endphp
    @elseif($recepcion->n_especie == 'Apples')
        @php
            $colors = ['#831816'];
        @endphp
    @elseif($recepcion->n_especie == 'Pears')
        @php
            $colors = ['#788527'];
        @endphp
    @elseif($recepcion->n_variedad == 'Dagen')
        @php
            $colors = ['#9817BB'];
        @endphp
    @else
        @php
            $colors = ['#24a745'];
        @endphp
    @endif


            // Distribucion de Firmezas por Color
            const ctxFirmDist = document.getElementById('firmeza-distribucion-chart-canvas');
            if (ctxFirmDist) {
                const distribucionFirmezaColor = @json($distribucion_firmeza_color);
                 var l = @json($l);
                var d = @json($d);
                var b = @json($b);
                var col = @json($colors);
                 var categories = [
                    ['Muy Firme >280 - 1000', 'Durofel >75'],
                    ['Firme 200 - 279', 'Durofel 72 - 74.9'],
                    ['Sensible 180 - 199', 'Durofel 65 - 69.9'],
                    ['Blando 0,1 - 179', 'Durofel <65,4']
                ];

                const firmezaDistChart = new Chart(ctxFirmDist, {
                    type: 'bar',
                    data: {
                        labels: categories,
                        datasets: [
                            {
                                label: 'LIGHT',
                                data: distribucionFirmezaColor.light,
                                backgroundColor: getFirmezaBrixColors('LIGHT'),
                            },
                            {
                                label: 'DARK',
                                data: distribucionFirmezaColor.dark,
                                backgroundColor: getFirmezaBrixColors('DARK'),
                            },
                            {
                                label: 'BLACK',
                                data: distribucionFirmezaColor.black,
                                backgroundColor: getFirmezaBrixColors('BLACK'),
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: { display: false },
                            title: { display: true, text: '% Distribución de Firmezas por Segregación de Color' },
                            datalabels: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, stacked: true, title: { display: true, text: '%' } },
                            x: { stacked: true, title: { display: true, text: 'Categoría Firmeza' } }
                        }
                    }
                });
                generateDatasetLegend(firmezaDistChart, 'firmeza-distribucion-legend');
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
        <div class="main-header-info-columns">
            <div class="column">
                <p><strong>Exportadora:</strong> Greenex Spa</p>
                <p><strong>Productor:</strong> {{ $recepcion->n_emisor }}</p>
                <p><strong>Cuartel:</strong> GE001</p>
                <p><strong>CSG:</strong> {{ $recepcion->Codigo_Sag_emisor }}</p>
                <p><strong>Variedad:</strong> {{ $recepcion->n_variedad }}</p>
                <p><strong>Fecha/Hora recepción:</strong> {{ \Carbon\Carbon::parse($recepcion->fecha_g_recepcion)->format('d/m/Y H:i') }}</p>
            </div>
            <div class="column">
                <p><strong>N° Lote:</strong> {{ $recepcion->numero_g_recepcion }}</p>
                <p><strong>T° Pulpa(°C):</strong> {{ $temperatura_pulpa ?? 'N/A' }}</p>
                <p><strong>Kilos Recibidos:</strong> {{ number_format($recepcion->peso_neto, 2, ',', '.') }}</p>
                <p><strong>N° Envases:</strong> {{ number_format($recepcion->cantidad, 0, ',', '.') }}</p>
                <p><strong>Seteo Camión:</strong></p>
                <p><strong>Nota de Calidad:</strong> {{ $recepcion->nota_calidad ?? 'S/N' }}</p>
            </div>
        </div>
    </div>

    <div class="header-separator"></div>

    <div class="summary-section">
        <div class="chart-wrapper">
            <div class="chart-container">
                <div style="position: relative; height:150px; width:210px;">
                    <canvas id="calibre-bar-chart-canvas"></canvas>
                </div>
                <div id="calibre-legend" class="chart-legend"></div>
            </div>
        </div>
        <div class="chart-wrapper">
            <div class="chart-container">
                <div style="position: relative; height:150px; width:210px;">
                    <canvas id="color-pie-chart-canvas"></canvas>
                </div>
                <div id="color-legend" class="chart-legend"></div>
            </div>
        </div>
        <div class="chart-wrapper">
            <div class="chart-container">
                <div style="position: relative; height:180px; width:250px;">
                    <canvas id="firmezas-bar-chart-canvas"></canvas>
                </div>
                <div id="firmezas-legend" class="chart-legend"></div>
            </div>
        </div>
        <div class="chart-wrapper">
            <div class="chart-container">
                <div style="position: relative; height:180px; width:250px;">
                    <canvas id="brix-bar-chart-canvas"></canvas>
                </div>
                <div id="brix-legend" class="chart-legend"></div>
            </div>
        </div>
        <div class="chart-wrapper full-width-chart">
            <div class="chart-container">
                <div style="position: relative; height:230px; width:90%;">
                    <canvas id="firmeza-distribucion-chart-canvas"></canvas>
                </div>
                <div id="firmeza-distribucion-legend" class="chart-legend"></div>
            </div>
        </div>
    </div>
    <div class="header-separator"></div>
    <div class="new-section-container">
        <div class="section-column">
            <h3>DEFECTOS DE CALIDAD</h3>
            <ul>
                @if(isset($recepcion->calidad->detalles))
                    @foreach($recepcion->calidad->detalles as $detalle)
                        @if($detalle->tipo_item == 'DEFECTOS DE CALIDAD' && isset($detalle->porcentaje_muestra) && $detalle->porcentaje_muestra > 0)
                            <li>{{ $detalle->detalle_item }}: {{ $detalle->porcentaje_muestra }} %</li>
                        @endif
                    @endforeach
                @endif
            </ul>
            <b>TOTAL:{{ $defectos_calidad_sum }} %</b>
        </div>
        <div class="section-column">
            <h3>DEFECTOS DE CONDICION</h3>
            <ul>
                @if(isset($recepcion->calidad->detalles))
                    @foreach($recepcion->calidad->detalles as $detalle)
                        @if($detalle->tipo_item == 'DEFECTOS DE CONDICIÓN' && isset($detalle->detalle_item) && $detalle->detalle_item > 0)
                            <li>{{ $detalle->detalle_item }}: {{ $detalle->porcentaje_muestra }} %</li>
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
                    $danos_plaga_sumfinal=0;
                @endphp
                @if(isset($recepcion->calidad->detalles))

                    @foreach($recepcion->calidad->detalles as $detalle)
                        @if($detalle->tipo_item == 'DAÑO DE PLAGA' && isset($detalle->porcentaje_muestra) && $detalle->porcentaje_muestra > 0)
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
                @if(isset($recepcion->calidad))
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
                    @foreach($calidad_fields as $field_key => $field_name)
                        @if(isset($recepcion->calidad->$field_key) && $recepcion->calidad->$field_key > 0)
                            <li>{{ $field_name }}: {{ $recepcion->calidad->$field_key }}</li>
                        @endif
                    @endforeach
                @endif
            </ul>
        </div>
    </div>
      <div class="new-section-container" style="margin-top: -18px;">
        <div class="section-column">
            <span>TOTAL DEFECTOS:{{ $danos_plaga_sumfinal+$defectos_calidad_sum+$defectos_condicion_sum}}</span>
        </div>
        <div class="section-column">
            <span>PRECALIBRE:</span>
             @if ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'PRECALIBRE')->first())
                 {{ $recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'PRECALIBRE')->first()->porcentaje_muestra }}
                                                   %
                                                @else
                                                    -
                                                @endif
        </div>
        <div class="section-column">
            <span>SOBRECALIBRE:</span>
             @if ($recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'SOBRECALIBRE')->first())
                                                    {{ $recepcion->calidad->detalles->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->where('detalle_item', 'SOBRECALIBRE')->first()->porcentaje_muestra }}
                                                    %
                                                @else
                                                    -
                                                @endif

        </div>
        <div class="section-column">
            <span>FUERA DE COLOR:</span>
             @if ($col > 0)
                                                    {{ $col }}%
                                                @else
                                                    -
                                                @endif
        </div>
      </div>
    <div class="observations-section">

        @if(isset($recepcion->calidad->obs_ext) && !empty($recepcion->calidad->obs_ext))
            <p> <b>OBSERVACIONES: </b> {{ $recepcion->calidad->obs_ext }}</p>
        @else
            <p>No hay observaciones adicionales.</p>
        @endif
    </div>

</body>
</html>
