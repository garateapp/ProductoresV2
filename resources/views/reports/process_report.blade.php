<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Proceso {{ $proceso->n_proceso }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');

        @page {
            size: Letter;
            margin: 12mm;
        }

        :root {
            --green: #0f766e;
            --orange: #f97316;
            --gray-900: #111827;
            --gray-700: #374151;
            --gray-500: #6b7280;
            --gray-200: #e5e7eb;
            --gray-100: #f3f4f6;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 14px;
            color: var(--gray-900);
            font-size: 11px;
            line-height: 1.35;
            background: #e5e7eb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .pdf-page {
            position: relative;
            width: 215.9mm;
            min-height: 279.4mm;
            margin: 0 auto;
            padding: 12mm;
            background: var(--white);
            box-shadow: 0 6px 22px rgba(17, 24, 39, 0.18);
        }

        @media print {
            body {
                background: var(--white);
                padding: 0;
            }

            .pdf-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }

        .header-logo {
            position: absolute;
            top: 10px;
            left: 12px;
            width: 145px;
            height: auto;
        }

        .title {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 4px;
            margin-bottom: 8px;
            color: var(--gray-900);
            letter-spacing: .5px;
        }

        .subtitle {
            text-align: center;
            color: var(--gray-700);
            margin-bottom: 10px;
        }

        .separator {
            width: 100%;
            height: 2px;
            background: var(--orange);
            margin-bottom: 12px;
        }

        .cards {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .card {
            flex: 1 1 calc(25% - 10px);
            min-width: 170px;
            border: 1px solid var(--gray-200);
            border-bottom: 2px solid var(--green);
            background: var(--gray-100);
            border-radius: 8px;
            padding: 8px;
        }

        .card-label {
            font-size: 10px;
            color: var(--gray-500);
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .card-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .section {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 12px;
            background: var(--white);
            page-break-inside: auto;
            break-inside: auto;
        }

        .section--no-break {
            page-break-inside: avoid;
            break-inside: avoid-page;
        }

        .section-title {
            background: var(--green);
            color: var(--white);
            font-weight: 700;
            font-size: 12px;
            padding: 8px 10px;
            text-transform: uppercase;
        }

        .section-body {
            padding: 10px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }

        .summary-box {
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 8px;
            background: var(--gray-100);
        }

        .summary-box strong {
            display: block;
            font-size: 13px;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-footer-group;
        }

        th, td {
            border: 1px solid var(--gray-200);
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }

        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        th {
            background: #e7f5f3;
            color: #0b4f49;
            font-weight: 700;
        }

        tfoot td {
            font-weight: 700;
            background: #f0faf8;
        }

        .text-right {
            text-align: right;
        }

        .muted {
            color: var(--gray-500);
        }

        .error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            padding: 8px;
            border-radius: 6px;
            margin-top: 8px;
        }

        .chart-grid {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .chart-card {
            flex: 1 1 calc(50% - 10px);
            min-width: 260px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            background: var(--white);
            padding: 8px;
        }

        .chart-title {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--gray-700);
            text-transform: uppercase;
        }

        .chart-canvas-wrap {
            height: 230px;
            position: relative;
        }

        .chart-meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 10px;
        }

        .chart-chip {
            border: 1px solid var(--gray-200);
            background: var(--gray-100);
            border-radius: 12px;
            padding: 2px 8px;
        }

        .chart-empty {
            margin-top: 8px;
            font-size: 10px;
        }

        .subsection-title {
            margin: 0 0 6px 0;
            padding: 6px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #0b4f49;
            background: #ecfdf5;
            border: 1px solid #d1fae5;
            page-break-after: avoid;
            break-after: avoid-page;
        }

        .subsection {
            margin-top: 10px;
            page-break-inside: auto;
            break-inside: auto;
        }

        .section-title {
            page-break-after: avoid;
            break-after: avoid-page;
        }


    </style>
</head>
<body>
    <div class="pdf-page">
    <img src="{{ asset('img/logo_garate.png') }}" class="header-logo" alt="Gárate Hermanos">
    <div class="title">Informe de Proceso  N° <strong>{{ $proceso->n_proceso }}</strong></div>
    <div class="subtitle">
        Generado: {{ $generatedAt }}
    </div>
    <div class="separator"></div>

    <div class="cards">
        <div class="card">
            <div class="card-label">Productor</div>
            <div class="card-value">{{ $proceso->agricola ?: '-' }}</div>
        </div>
        <div class="card">
            <div class="card-label">Especie / Variedad</div>
            <div class="card-value">{{ $proceso->especie ?: '-' }} / {{ $proceso->variedad ?: '-' }}</div>
        </div>
        <div class="card">
            <div class="card-label">Fecha Proceso</div>
            <div class="card-value">{{ $proceso->fecha ?: '-' }}</div>
        </div>
        <div class="card">
            <div class="card-label">Lote Recepción</div>
            <div class="card-value">{{ $proceso->lote_recepcion ?: '-' }}</div>
        </div>
    </div>

    @if($queryError)
        <div class="error">
            No fue posible consultar SQLSRV para este proceso: {{ $queryError }}
        </div>
    @endif

    <div class="section section--no-break">
        <div class="section-title">Resumen</div>
        <div class="section-body">
            <div class="summary-grid">
                <div class="summary-box">
                    Cantidad ({{ $ingresoPackagingName ?? 'Ingreso a Proceso' }})
                    <strong>{{ number_format($totalIngresosCantidad, 0, ',', '.') }}</strong>
                </div>
                <div class="summary-box">
                    Ingreso Peso Neto
                    <strong>{{ number_format($totalIngresosPeso, 2, ',', '.') }}</strong>
                </div>
                <div class="summary-box">
                    Kilos Procesados
                    <strong>{{ number_format($totalSalidasPeso, 2, ',', '.') }}</strong>
                </div>
                <div class="summary-box">
                    Kilos Merma
                     <strong>{{ number_format($diferenciaPeso, 2, ',', '.') }}</strong>
                </div>
                 <div class="summary-box">
                    % Exportación
                    <strong>{{ number_format((float) ($destinoPercentages['exportable'] ?? 0), 2, ',', '.') }}%</strong>
                </div>
                <div class="summary-box">
                    % Mercado Interno / Sobrecalibre
                    <strong>
                        {{ number_format((float) ($destinoPercentages['mercado_interno'] ?? 0), 2, ',', '.') }}%
                        /
                        {{ number_format((float) ($destinoPercentages['sobrecalibre'] ?? 0), 2, ',', '.') }}%
                    </strong>
                </div>
                <div class="summary-box">
                    % Desecho
                    <strong>{{ number_format((float) ($destinoPercentages['desecho'] ?? 0), 2, ',', '.') }}%</strong>
                </div>
                <div class="summary-box">
                    % Mermas
                    <strong>{{ number_format((float) ($mermasPercentage ?? 0), 2, ',', '.') }}%</strong>
                </div>
            </div>
            {{-- <div style="margin-top: 8px;">
                Diferencia de Peso (Ingreso - Salida):
                <strong>{{ number_format($diferenciaPeso, 2, ',', '.') }}</strong>
            </div> --}}
        </div>
    </div>

    <div class="section section--no-break">
        <div class="section-title">Gráficos</div>
        <div class="section-body">
            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-title">% Destino de Salidas</div>
                    <div class="chart-canvas-wrap">
                        <canvas id="destino-doughnut-chart"></canvas>
                    </div>
                    <p id="destino-empty" class="muted chart-empty" style="display: none;">Sin datos suficientes para el gráfico.</p>
                    <div class="chart-meta">
                        <span class="chart-chip">Exportable (Cat 1 / Cat I): {{ number_format((float) ($destinoPercentages['exportable'] ?? 0), 2, ',', '.') }}%</span>
                        <span class="chart-chip">Mercado Interno: {{ number_format((float) ($destinoPercentages['mercado_interno'] ?? 0), 2, ',', '.') }}%</span>
                        <span class="chart-chip">Sobrecalibre: {{ number_format((float) ($destinoPercentages['sobrecalibre'] ?? 0), 2, ',', '.') }}%</span>
                        <span class="chart-chip">Desecho: {{ number_format((float) ($destinoPercentages['desecho'] ?? 0), 2, ',', '.') }}%</span>
                        <span class="chart-chip">Mermas: {{ number_format((float) ($mermasPercentage ?? 0), 2, ',', '.') }}%</span>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">Curva de Calibre por Cantidad y %</div>
                    <div class="chart-canvas-wrap">
                        <canvas id="calibre-curve-chart"></canvas>
                    </div>
                    <p id="calibre-empty" class="muted chart-empty" style="display: none;">Sin datos suficientes para el gráfico.</p>
                    <div class="chart-meta">
                        <span class="chart-chip">Total Cantidad Curva: {{ number_format(array_sum($calibreCurveCantidad ?? []), 0, ',', '.') }}</span>
                        <span class="chart-chip">Total % Curva: {{ number_format(array_sum($calibreCurvePorcentaje ?? []), 2, ',', '.') }}%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section section--no-break">
        <div class="section-title">Información del Proceso</div>
        <div class="section-body">
            @if($cabecera)
                <table>
                    <tbody>
                        <tr>
                            <th>Productor</th>
                            <td>{{ $cabecera['n_productor'] ?? '-' }}</td>
                            <th>Especie</th>
                            <td>{{ $cabecera['n_especie'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Variedad</th>
                            <td>{{ $cabecera['n_variedad'] ?? '-' }}</td>
                            <th>Línea Proceso</th>
                            <td>{{ $cabecera['n_linea_proceso'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Centro Costo</th>
                            <td>{{ $cabecera['n_centrocosto'] ?? '-' }}</td>
                            <th>N° Producción</th>
                            <td>{{ $cabecera['numero_g_produccion'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Fecha Producción</th>
                            <td>{{ $cabecera['fecha_g_produccion'] ?? '-' }}</td>
                            <th>Turno</th>
                            <td>{{ $cabecera['n_turno'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Tipo Proceso</th>
                            <td>{{ $cabecera['n_tipo_proceso'] ?? '-' }}</td>
                            <th>Categoría</th>
                            <td>{{ $cabecera['t_categoria'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>C. Embalaje</th>
                            <td>{{ $cabecera['c_embalaje'] ?? '-' }}</td>
                            <th>Calibre / Etiqueta</th>
                            <td>{{ $cabecera['n_calibre'] ?? '-' }} / {{ $cabecera['n_etiqueta'] ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <p class="muted">Sin datos de cabecera.</p>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Ingresos a Proceso</div>
        <div class="section-body">
            <table>
                <thead>
                    <tr>
                        <th>Productor</th>
                        <th>N° Guía/Lote</th>
                        <th>Especie</th>
                        <th>Variedad</th>
                        <th>Embalaje</th>
                        <th>Categoría</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Peso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ingresos as $row)
                        <tr>
                            <td>{{ $row['n_productor'] ?? '-' }}</td>
                            <td>{{ $row['guia_lote'] ?? '-' }}</td>
                            <td>{{ $row['n_especie'] ?? '-' }}</td>
                            <td>{{ $row['n_variedad'] ?? '-' }}</td>
                            <td>{{ $row['n_embalaje'] ?? ($row['c_embalaje'] ?? '-') }}</td>
                            <td>{{ $row['t_categoria'] ?? '-' }}</td>
                            <td class="text-right">{{ number_format((float)($row['cantidad'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float)($row['peso'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Sin registros de ingresos.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">Totales Ingresos</td>
                        <td class="text-right">{{ number_format((float) $totalIngresosCantidad, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) $totalIngresosPeso, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Salidas de Proceso</div>
        <div class="section-body">
            @php
                $salidasSecciones = [
                    ['titulo' => 'Exportación', 'rows' => $salidasExportacion ?? collect()],
                    ['titulo' => 'Mercado Interno', 'rows' => $salidasMercadoInterno ?? collect()],
                    ['titulo' => 'Sobrecalibre', 'rows' => $salidasSobrecalibre ?? collect()],
                    ['titulo' => 'Desecho', 'rows' => $salidasDesecho ?? collect()],
                ];

                if (($salidasSinClasificacion ?? collect())->isNotEmpty()) {
                    $salidasSecciones[] = ['titulo' => 'Sin Clasificación', 'rows' => $salidasSinClasificacion];
                }
            @endphp

            @foreach($salidasSecciones as $bloque)
                @php
                    $rows = $bloque['rows'];
                    $totalCantidadBloque = (float) $rows->sum('cantidad');
                    $totalPesoBloque = (float) $rows->sum('peso_neto');
                @endphp
                <div class="subsection">
                    <div class="subsection-title">{{ $bloque['titulo'] }}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Productor</th>
                                <th>Especie</th>
                                <th>Variedad</th>
                                <th>Embalaje</th>
                                <th>Categoría</th>
                                <th>Etiqueta</th>
                                <th>Calibre</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Peso Neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row['n_productor'] ?? '-' }}</td>
                                    <td>{{ $row['n_especie'] ?? '-' }}</td>
                                    <td>{{ $row['n_variedad'] ?? '-' }}</td>
                                    <td>{{ $row['c_embalaje'] ?? ($row['c_embalaje'] ?? '-') }}</td>
                                    <td>{{ $row['n_categoria'] ?? '-' }}</td>
                                    <td>{{ $row['n_etiqueta'] ?? '-' }}</td>
                                    <td>{{ $row['n_calibre'] ?? '-' }}</td>
                                    <td class="text-right">{{ number_format((float)($row['cantidad'] ?? 0), 0, ',', '.') }}</td>
                                    <td class="text-right">{{ number_format((float)($row['peso_neto'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="muted">Sin registros de {{ strtolower($bloque['titulo']) }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7">Total {{ $bloque['titulo'] }}</td>
                                <td class="text-right">{{ number_format($totalCantidadBloque, 0, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($totalPesoBloque, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach

            <div style="margin-top: 8px;">
                Total General Salidas:
                <strong>Cantidad {{ number_format((float) $totalSalidasCantidad, 0, ',', '.') }}</strong>
                ·
                <strong>Peso Neto {{ number_format((float) $totalSalidasPeso, 2, ',', '.') }}</strong>
            </div>
        </div>
    </div>

    </div>



    <script>
        {!! @file_get_contents(public_path('vendor/chart.js/chart.umd.min.js')) !!}
    </script>

    <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            function normalizeSpeciesName(value) {
                return String(value || '').trim().toLowerCase();
            }

            function getChartColors(species) {
                switch (normalizeSpeciesName(species)) {
                    case 'cherries':
                    case 'cherry':
                    case 'cereza':
                    case 'cerezas':
                        return {
                            exportable: '#dc0c15',
                            defectosCalidad: '#a5030d',
                            defectosCondicion: '#7f1313',
                            danosPlaga: '#4a1006',
                            precalibre: '#fbbf24'
                        };
                    case 'plums':
                    case 'plum':
                    case 'ciruela':
                    case 'ciruelas':
                        return {
                            exportable: '#b39cff',
                            defectosCalidad: '#8d74e6',
                            defectosCondicion: '#715cc0',
                            danosPlaga: '#5a4799',
                            precalibre: '#d7ccff'
                        };
                    case 'apples':
                    case 'apple':
                    case 'manzana':
                    case 'manzanas':
                        return {
                            exportable: '#7bd66a',
                            defectosCalidad: '#58b64c',
                            defectosCondicion: '#3e8d36',
                            danosPlaga: '#2f6c29',
                            precalibre: '#c4f2b8'
                        };
                    case 'peaches':
                    case 'peach':
                    case 'durazno':
                    case 'duraznos':
                        return {
                            exportable: '#ffb980',
                            defectosCalidad: '#f59b56',
                            defectosCondicion: '#e07a2e',
                            danosPlaga: '#b85e1f',
                            precalibre: '#ffe0c2'
                        };
                    case 'nectarines':
                    case 'nectarine':
                    case 'nectarina':
                    case 'nectarinas':
                        return {
                            exportable: '#ff9b73',
                            defectosCalidad: '#f07a4c',
                            defectosCondicion: '#d3552b',
                            danosPlaga: '#a43a1c',
                            precalibre: '#ffd0bc'
                        };
                    case 'pears':
                    case 'pear':
                    case 'pera':
                    case 'peras':
                        return {
                            exportable: '#a7e16c',
                            defectosCalidad: '#86c452',
                            defectosCondicion: '#659a3a',
                            danosPlaga: '#4d792c',
                            precalibre: '#d7f5b6'
                        };
                    default:
                        return {
                            exportable: '#0ea5e9',
                            defectosCalidad: '#0284c7',
                            defectosCondicion: '#0369a1',
                            danosPlaga: '#1e3a8a',
                            precalibre: '#7dd3fc'
                        };
                }
            }

            const species = @json($speciesForCharts ?? '');
            const palette = getChartColors(species);
            const numberFormatter = new Intl.NumberFormat('es-CL');

            const destinoPercentages = @json($destinoPercentages ?? []);
            const destinoTotalsPeso = @json($destinoTotalsPeso ?? []);
            const totalIngresosPeso = Number(@json((float) ($totalIngresosPeso ?? 0)));
            const mermasPercentage = Number(@json((float) ($mermasPercentage ?? 0)));
            const mermasPeso = Number(@json((float) ($mermasPeso ?? 0)));
            const destinoLabels = [
                'Exportable (Cat 1 / Cat I)',
                'Mercado Interno',
                'Sobrecalibre',
                'Desecho',
                'Mermas'
            ];
            const destinoValues = [
                Number(destinoPercentages.exportable || 0),
                Number(destinoPercentages.mercado_interno || 0),
                Number(destinoPercentages.sobrecalibre || 0),
                Number(destinoPercentages.desecho || 0),
                mermasPercentage
            ];
            const destinoAbsolute = [
                Number(destinoTotalsPeso.exportable || 0),
                Number(destinoTotalsPeso.mercado_interno || 0),
                Number(destinoTotalsPeso.sobrecalibre || 0),
                Number(destinoTotalsPeso.desecho || 0),
                mermasPeso
            ];

            const destinoCanvas = document.getElementById('destino-doughnut-chart');
            const destinoEmpty = document.getElementById('destino-empty');

            if (destinoCanvas) {
                if (totalIngresosPeso > 0) {
                    new Chart(destinoCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: destinoLabels,
                            datasets: [{
                                data: destinoValues,
                                backgroundColor: [palette.exportable, palette.precalibre, palette.defectosCondicion, palette.danosPlaga, '#9ca3af'],
                                borderColor: '#ffffff',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const percent = Number(context.parsed || 0).toFixed(2);
                                            const absolute = destinoAbsolute[context.dataIndex] || 0;
                                            return context.label + ': ' + percent + '% (' + numberFormatter.format(absolute) + ' PN)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    destinoCanvas.style.display = 'none';
                    if (destinoEmpty) {
                        destinoEmpty.style.display = 'block';
                    }
                }
            }

            const calibreLabels = @json($calibreCurveLabels ?? []);
            const calibreCantidad = @json($calibreCurveCantidad ?? []);
            const calibrePorcentaje = @json($calibreCurvePorcentaje ?? []);
            const curveCanvas = document.getElementById('calibre-curve-chart');
            const calibreEmpty = document.getElementById('calibre-empty');
            const hasCurveData = Array.isArray(calibreCantidad)
                && calibreCantidad.some(function (value) {
                    return Number(value) > 0;
                });

            if (curveCanvas) {
                if (Array.isArray(calibreLabels) && calibreLabels.length > 0 && hasCurveData) {
                    new Chart(curveCanvas, {
                        type: 'bar',
                        data: {
                            labels: calibreLabels,
                            datasets: [
                                {
                                    label: 'Cantidad',
                                    data: calibreCantidad,
                                    backgroundColor: palette.exportable,
                                    borderColor: palette.defectosCalidad,
                                    borderWidth: 1,
                                    yAxisID: 'y',
                                    stack: 'curva-calibre'
                                },
                                {
                                    label: '%',
                                    data: calibrePorcentaje,
                                    backgroundColor: palette.precalibre,
                                    borderColor: palette.defectosCondicion,
                                    borderWidth: 1,
                                    yAxisID: 'y1',
                                    stack: 'curva-calibre-porcentaje'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: {
                                            size: 10
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            const rawValue = Number(context.parsed.y || 0);
                                            if (context.dataset.yAxisID === 'y1') {
                                                return '%: ' + rawValue.toFixed(2) + '%';
                                            }

                                            return 'Cantidad: ' + numberFormatter.format(rawValue);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    ticks: {
                                        font: {
                                            size: 9
                                        },
                                        maxRotation: 45,
                                        minRotation: 0
                                    },
                                    title: {
                                        display: true,
                                        text: 'Calibre'
                                    }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function (value) {
                                            return numberFormatter.format(Number(value || 0));
                                        },
                                        font: {
                                            size: 9
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Cantidad'
                                    }
                                },
                                y1: {
                                    position: 'right',
                                    beginAtZero: true,
                                    grid: {
                                        drawOnChartArea: false
                                    },
                                    ticks: {
                                        callback: function (value) {
                                            return Number(value || 0).toFixed(2) + '%';
                                        },
                                        font: {
                                            size: 9
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: '%'
                                    }
                                }
                            }
                        }
                    });
                } else {
                    curveCanvas.style.display = 'none';
                    if (calibreEmpty) {
                        calibreEmpty.style.display = 'block';
                    }
                }
            }
        })();
    </script>
</body>
</html>
