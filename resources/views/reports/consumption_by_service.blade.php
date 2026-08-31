<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Consumo por Servicio</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Roboto', 'Segoe UI', Arial, sans-serif;
            margin: 24px;
            font-size: 11px;
            color: #1f2937;
        }
        h1 { font-size: 18px; margin: 0 0 4px; color: #111827; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #374151; text-transform: uppercase; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 16px; }
        .filters { color: #374151; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { padding: 5px 8px; border: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        td.num, th.num { text-align: right; }
        .summary { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .summary .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 12px; min-width: 120px; }
        .summary .box .label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .summary .box .value { font-size: 16px; font-weight: 700; margin-top: 2px; }
        .muted { color: #9ca3af; }
        .page-footer { margin-top: 24px; color: #9ca3af; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <h1>Consumo de materiales por servicio</h1>
    <div class="meta">Generado: {{ $generatedAt }}</div>
    <div class="filters">
        Rango: <b>{{ $filters['date_from'] ?: 'todo' }}</b> — <b>{{ $filters['date_to'] ?: 'todo' }}</b>
        @if ($filters['service_id'])
            · Servicio: <b>{{ $filters['service_id'] }}</b>
        @endif
        @if ($filters['material_id'])
            · Material id: <b>{{ $filters['material_id'] }}</b>
        @endif
        @if ($filters['tipo_folio'])
            · Tipo: <b>{{ $filters['tipo_folio'] }}</b>
        @endif
        @if (!$filters['incluir_mermas'])
            · Sin mermas
        @endif
    </div>

    <div class="summary">
        <div class="box"><div class="label">Consumo normal</div><div class="value">{{ number_format($totals['consumo_normal'], 2) }}</div></div>
        <div class="box"><div class="label">Consumo temporal</div><div class="value">{{ number_format($totals['consumo_temporal'], 2) }}</div></div>
        <div class="box"><div class="label">Total consumo</div><div class="value">{{ number_format($totals['consumo_total'], 2) }}</div></div>
        <div class="box"><div class="label">Mermas</div><div class="value">{{ number_format($totals['merma'], 2) }}</div></div>
        <div class="box"><div class="label">Gran total</div><div class="value">{{ number_format($totals['gran_total'], 2) }}</div></div>
    </div>

    <h2>Por servicio</h2>
    <table>
        <thead>
            <tr>
                <th>Servicio</th>
                <th class="num">Materiales</th>
                <th class="num">Consumo normal</th>
                <th class="num">Consumo temporal</th>
                <th class="num">Total consumo</th>
                <th class="num">Mermas</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byService as $row)
                <tr>
                    <td>{{ $row['service_name'] }}</td>
                    <td class="num">{{ $row['materiales'] }}</td>
                    <td class="num">{{ number_format($row['normal'], 2) }}</td>
                    <td class="num">{{ number_format($row['temporal'], 2) }}</td>
                    <td class="num">{{ number_format($row['consumo_total'], 2) }}</td>
                    <td class="num">{{ number_format($row['merma'], 2) }}</td>
                    <td class="num">{{ number_format($row['gran_total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin datos para el rango seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Por material</h2>
    <table>
        <thead>
            <tr>
                <th>Servicio</th>
                <th>Material</th>
                <th>Código</th>
                <th class="num">Consumo normal</th>
                <th class="num">Consumo temporal</th>
                <th class="num">Total consumo</th>
                <th class="num">Mermas</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byMaterial as $row)
                <tr>
                    <td>{{ $row['service_name'] }}</td>
                    <td>{{ $row['material_nombre'] }}</td>
                    <td>{{ $row['material_codigo'] }}</td>
                    <td class="num">{{ number_format($row['normal'], 2) }}</td>
                    <td class="num">{{ number_format($row['temporal'], 2) }}</td>
                    <td class="num">{{ number_format($row['consumo_total'], 2) }}</td>
                    <td class="num">{{ number_format($row['merma'], 2) }}</td>
                    <td class="num">{{ number_format($row['gran_total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Sin datos para el rango seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-footer">Greenex · Reporte de consumo por servicio</div>
</body>
</html>