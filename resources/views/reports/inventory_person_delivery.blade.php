<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta de Entrega {{ $delivery->codigo }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        .page {
            width: 100%;
        }
        .header {
            align-items: center;
            border-bottom: 2px solid #1f2937;
            display: flex;
            justify-content: space-between;
            padding-bottom: 14px;
        }
        .logo {
            height: 52px;
        }
        .folio {
            text-align: right;
        }
        .folio-label {
            color: #6b7280;
            font-size: 10px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .folio-value {
            font-size: 18px;
            font-weight: 700;
        }
        h1 {
            font-size: 20px;
            letter-spacing: .04em;
            margin: 22px 0 14px;
            text-align: center;
            text-transform: uppercase;
        }
        .intro {
            margin: 0 0 18px;
            text-align: justify;
        }
        .section-title {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            margin: 18px 0 0;
            padding: 7px 9px;
            text-transform: uppercase;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 8px 9px;
            vertical-align: top;
        }
        th {
            background: #f9fafb;
            color: #374151;
            font-size: 10px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
        }
        .number {
            text-align: right;
            white-space: nowrap;
        }
        .notes {
            border: 1px solid #d1d5db;
            min-height: 54px;
            padding: 10px;
            white-space: pre-wrap;
        }
        .signature-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: 1fr 1fr;
            margin-top: 28px;
        }
        .signature-box {
            border: 1px solid #d1d5db;
            min-height: 150px;
            padding: 12px;
            text-align: center;
        }
        .signature-box img {
            height: 86px;
            max-width: 100%;
            object-fit: contain;
        }
        .signature-line {
            border-top: 1px solid #111827;
            margin-top: 12px;
            padding-top: 6px;
        }
        .trace {
            color: #4b5563;
            font-size: 10px;
            margin-top: 18px;
            word-break: break-all;
        }
        .footer {
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 10px;
            margin-top: 24px;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            <img class="logo" src="{{ asset('img/logogreenex.png') }}" alt="Greenex">
            <div class="folio">
                <div class="folio-label">Acta de entrega</div>
                <div class="folio-value">{{ $delivery->codigo }}</div>
                <div>{{ optional($delivery->delivered_at)->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        <h1>Acta de Entrega de Materiales a Persona</h1>

        <p class="intro">
            Por medio de la presente se deja constancia de la entrega de los materiales indicados en este documento,
            los cuales fueron descontados del stock de la ubicación de origen registrada.
        </p>

        <div class="section-title">Datos generales</div>
        <table>
            <tr>
                <th>Persona que recibe</th>
                <td>{{ $delivery->person_name }}</td>
                <th>Cargo</th>
                <td>{{ $delivery->person_position }}</td>
            </tr>
            <tr>
                <th>Ubicación origen</th>
                <td>{{ $delivery->originLocation?->nombre }}</td>
                <th>Entregado por</th>
                <td>{{ $delivery->creator?->name }}</td>
            </tr>
            <tr>
                <th>Movimiento inventario</th>
                <td>{{ $delivery->movement?->folio ?? '-' }}</td>
                <th>Estado movimiento</th>
                <td>{{ $delivery->movement?->estado ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-title">Materiales entregados</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 18%">Código</th>
                    <th>Material</th>
                    <th style="width: 14%">Unidad</th>
                    <th style="width: 18%">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($delivery->items as $item)
                    <tr>
                        <td>{{ $item->material?->codigo }}</td>
                        <td>{{ $item->material?->nombre }}</td>
                        <td>{{ $item->material?->unit?->codigo ?? '-' }}</td>
                        <td class="number">{{ number_format((float) $item->cantidad, 4, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Observación</div>
        <div class="notes">{{ $delivery->notes ?: 'Sin observaciones.' }}</div>

        <section class="signature-grid">
            <div class="signature-box">
                @if ($delivery->signature_data_url)
                    <img src="{{ $delivery->signature_data_url }}" alt="Firma receptor">
                @endif
                <div class="signature-line">
                    <strong>{{ $delivery->person_name }}</strong><br>
                    Receptor
                </div>
            </div>

            <div class="signature-box">
                <div style="height: 86px;"></div>
                <div class="signature-line">
                    <strong>{{ $delivery->creator?->name }}</strong><br>
                    Responsable de entrega
                </div>
            </div>
        </section>

        <div class="trace">
            Hash ledger: {{ $delivery->movement?->ledger_hash ?? '-' }}
        </div>

        <footer class="footer">
            Documento generado automáticamente por el sistema de inventario.
        </footer>
    </main>
</body>
</html>
