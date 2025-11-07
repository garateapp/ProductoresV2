<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consolidado Producto Terminado - Proceso #{{ $proceso->n_proceso }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2933; margin: 24px; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 8px 0; color: #111827; }
        h2 { font-size: 16px; margin: 16px 0 8px 0; color: #1f2933; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #059669; padding-bottom: 10px; margin-bottom: 18px; }
        .header-left { display: flex; align-items: center; gap: 12px; }
        .header-logo { height: 48px; width: auto; }
        .chip { display: inline-block; padding: 4px 10px; border-radius: 9999px; background: #ecfdf5; color: #047857; font-weight: 600; border: 1px solid #a7f3d0; }
        .meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px 16px; }
        .meta div { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .defect-col { width: 28%; }
        .box-cell { text-align: center; }
        .muted { color: #6b7280; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <img src="{{ asset('img/logogreenex.png') }}" alt="Greenex" class="header-logo" />
            <div>
                <h1>Consolidado Producto Terminado</h1>
                <div class="muted">Detalle comparativo por caja evaluada</div>
            </div>
        </div>
        <div class="chip">Proceso #{{ $proceso->n_proceso }}</div>
    </div>

    <div class="meta">
        <div><strong>Agrícola:</strong> {{ $proceso->agricola ?? 'N/A' }}</div>
        <div><strong>Especie:</strong> {{ $proceso->especie ?? 'N/A' }}</div>
        <div><strong>Variedad:</strong> {{ $proceso->variedad ?? 'N/A' }}</div>
        <div><strong>Lote Recepción:</strong> {{ $proceso->lote_recepcion ?? $proceso->LPP_recepcion ?? 'N/A' }}</div>
        <div><strong>Kilos Netos:</strong> {{ number_format((float)($proceso->kilos_netos ?? 0), 0, ',', '.') }} kg</div>
        <div><strong>Fecha Proceso:</strong> {{ $proceso->fecha ? \Carbon\Carbon::parse($proceso->fecha)->format('d/m/Y') : 'N/A' }}</div>
        <div><strong>Total Cajas:</strong> {{ count($boxLabels) }}</div>
    </div>

    @foreach ($tables as $groupName => $table)
        <h3 style="margin-top:24px;">{{ $groupName }}</h3>
        <table>
            <thead>
                <tr>
                    <th class="defect-col">Defecto</th>
                    @foreach ($boxLabels as $label)
                        <th class="box-cell">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($table['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        @foreach ($boxLabels as $label)
                            @php $value = $row['values'][$label] ?? null; @endphp
                            <td class="box-cell">
                                @if ($value)
                                    <div>{{ $value['cantidad'] ?? '-' }}</div>
                                    <div class="muted">{{ isset($value['porcentaje']) ? number_format((float)$value['porcentaje'], 2, ',', '.') . ' %' : '-' }}</div>
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Totales</strong></td>
                    @foreach ($boxLabels as $label)
                        @php $total = $table['totals'][$label] ?? ['cantidad' => 0, 'porcentaje' => 0]; @endphp
                        <td class="box-cell">
                            <div><strong>{{ number_format((float)$total['cantidad'], 0, ',', '.') }}</strong></div>
                            <div class="muted"><strong>{{ number_format((float)$total['porcentaje'], 2, ',', '.') }} %</strong></div>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endforeach

    <p class="muted" style="margin-top: 12px;">Notas: Los valores corresponden a la cantidad y porcentaje de muestra reportado por cada caja evaluada.</p>
</body>
</html>
