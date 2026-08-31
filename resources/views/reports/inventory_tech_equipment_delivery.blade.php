<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta de Entrega {{ $act->codigo }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        .page { width: 100%; }
        .header {
            align-items: center;
            border-bottom: 2px solid #1f2937;
            display: flex;
            justify-content: space-between;
            padding-bottom: 14px;
        }
        .logo { height: 52px; }
        .folio { text-align: right; }
        .folio-label {
            color: #6b7280;
            font-size: 10px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .folio-value { font-size: 18px; font-weight: 700; }
        h1 {
            font-size: 18px;
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
        table { border-collapse: collapse; width: 100%; }
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
            <img class="logo" src="{{ asset('img/logo_garate.png') }}" alt="Gárate Hermanos">
            <div class="folio">
                <div class="folio-label">Acta de entrega</div>
                <div class="folio-value">{{ $act->codigo }}</div>
                <div>{{ optional($act->delivered_at)->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        <h1>Acta de Entrega de Equipos Tecnológicos</h1>

        <p class="intro">
            Por medio de la presente se deja constancia de la entrega de los equipos tecnológicos
            indicados en este documento al receptor que se detalla a continuación, quienes los reciben
            bajo su responsabilidad.
        </p>

        <div class="section-title">Datos del receptor y entrega</div>
        <table>
            <tr>
                <th>Nombre</th>
                <td>{{ $act->person_name }}</td>
                <th>RUT</th>
                <td>{{ $act->person_rut }}</td>
            </tr>
            <tr>
                <th>Departamento</th>
                <td>{{ $act->departamento ?: '-' }}</td>
                <th>Cargo</th>
                <td>{{ $act->cargo ?: '-' }}</td>
            </tr>
            <tr>
                <th>Condición del equipo</th>
                <td>{{ ucfirst($act->condicion) }}</td>
                <th>Entregado por</th>
                <td>{{ $act->creator?->name }}</td>
            </tr>
            @if ($act->returned_at)
            <tr>
                <th>Devolución</th>
                <td>{{ $act->returned_at->format('d/m/Y H:i') }}</td>
                <th></th>
                <td></td>
            </tr>
            @endif
        </table>

        <div class="section-title">Equipos entregados</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 16%">Marca</th>
                    <th style="width: 14%">N° de serie</th>
                    <th style="width: 12%">Fecha</th>
                    <th>Características</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($act->items as $item)
                    <tr>
                        <td>{{ $item->equipment?->marca }}</td>
                        <td>{{ $item->equipment?->numero_serie }}</td>
                        <td>{{ optional($item->equipment?->fecha)->format('d/m/Y') ?: '-' }}</td>
                        <td>{{ $item->equipment?->descripcion ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Observación</div>
        <div class="notes">{{ $act->observations ?: 'Sin observaciones.' }}</div>

        @if ($act->returned_at && $act->return_observations)
            <div class="section-title">Observaciones de devolución</div>
            <div class="notes">{{ $act->return_observations }}</div>
        @endif

        <section class="signature-grid">
            <div class="signature-box">
                @if ($act->signature_data_url)
                    <img src="{{ $act->signature_data_url }}" alt="Firma receptor">
                @endif
                <div class="signature-line">
                    <strong>{{ $act->person_name }}</strong><br>
                    Receptor
                </div>
            </div>

            <div class="signature-box">
                <div style="height: 86px;"></div>
                <div class="signature-line">
                    <strong>{{ $act->creator?->name }}</strong><br>
                    Responsable de entrega
                </div>
            </div>
        </section>

        @if ($act->returned_at)
            <section class="signature-grid" style="margin-top: 22px;">
                <div class="signature-box">
                    @if ($act->return_signature_data_url)
                        <img src="{{ $act->return_signature_data_url }}" alt="Firma devolución">
                    @endif
                    <div class="signature-line">
                        <strong>{{ $act->person_name }}</strong><br>
                        Devolución de equipos
                    </div>
                </div>
                <div class="signature-box">
                    <div style="height: 86px;"></div>
                    <div class="signature-line">
                        <strong>{{ $act->creator?->name }}</strong><br>
                        Responsable de recepción
                    </div>
                </div>
            </section>
        @endif

        <footer class="footer">
            Documento generado automáticamente por el sistema de inventario.
        </footer>
    </main>
</body>
</html>
