<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Descarte Comercial Cerezas</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2933;
            margin: 0;
            padding: 24px;
        }
        h1 {
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 20px;
            margin-bottom: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 13px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            text-transform: uppercase;
            font-size: 12px;
            color: #374151;
        }
        .section-title {
            font-weight: bold;
            margin: 20px 0 8px;
            text-transform: uppercase;
            font-size: 14px;
        }
        .signature {
            margin-top: 32px;
            text-align: center;
        }
        .signature img {
            width: 280px;
            height: 120px;
            border: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    <div class="header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
        <img src="{{ asset('img/logo_garate.png') }}" alt="Gárate Hermanos" style="height:60px;">
        <div style="text-align:right;">
            <div style="font-size:12px; text-transform:uppercase; color:#6b7280;">N° Descarte</div>
            <div style="font-size:20px; font-weight:600; color:#111827;">#{{ $record->id }}</div>
        </div>
    </div>
    <h1>Descarte Comercial Cerezas</h1>

    <div class="section-title">Información General</div>
    <table>
        <tr>
            <th>Fecha</th>
            <td>{{ optional($record->fecha)->format('d/m/Y H:i') }}</td>
            <th>N° Línea</th>
            <td>{{ $record->linea }}</td>
        </tr>
        <tr>
            <th>Turno</th>
            <td>{{ $record->turno }}</td>
            <th>N° de Frutos</th>
            <td>{{ number_format($record->frutos) }}</td>
        </tr>
    </table>

    <div class="section-title">Identificación</div>
    <table>
        <tr>
            <th>Productor</th>
            <td>{{ $record->productor }}</td>
            <th>Especie</th>
            <td>{{ $record->especie }}</td>
        </tr>
        <tr>
            <th>Variedad</th>
            <td>{{ $record->variedad }}</td>
            <th>N° de Lote</th>
            <td>{{ $record->lote }}</td>
        </tr>
        <tr>
            <th>N° de Proceso</th>
            <td colspan="3">{{ $record->proceso }}</td>
        </tr>
    </table>

    <div class="section-title">Defectos registrados</div>
    <table>
        <thead>
            <tr>
                <th style="width: 35%;">Tipo</th>
                <th style="width: 35%;">Valor</th>
                <th style="width: 15%;">Comercial</th>
                <th style="width: 15%;">Desecho</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($record->details as $detail)
                <tr>
                    <td>{{ $detail->parametro->name ?? '-' }}</td>
                    <td>{{ $detail->valor->name ?? '-' }}</td>
                    <td>{{ number_format($detail->comercial) }}</td>
                    <td>{{ number_format($detail->desecho) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;">Sin defectos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Observaciones</div>
    <table>
        <tr>
            <td>{{ $record->observaciones ?? 'Sin observaciones' }}</td>
        </tr>
    </table>

    <div class="signature">
        <p>Firma Responsable</p>
        @if ($signatureDataUrl)
            <img src="{{ $signatureDataUrl }}" alt="Firma" />
        @else
            <div style="border:1px solid #d1d5db; width:280px; height:120px; margin:0 auto;"></div>
        @endif
    </div>
</body>
</html>
